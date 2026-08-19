<?php
/**
 * Scan and replace WP Tools Gravity Forms Divi 5 blocks.
 *
 * @package Divi_GF_Swap
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content helpers for finding and replacing WP Tools Gravity Forms modules.
 */
class Divi_GF_Swap_Content {

	const OLD_OPENER       = '<!-- wp:d5-gravity-form-module/divi-gravity-form';
	const OLD_CLOSER       = '<!-- /wp:d5-gravity-form-module/divi-gravity-form -->';
	const BUILDER_VERSION  = '5.11.0';
	const MAX_REPLACEMENTS = 20000;

	/**
	 * Count WP Tools Gravity Forms block openers in a string.
	 *
	 * @param string $content Post content.
	 * @return int
	 */
	public static function count_blocks( string $content ): int {
		return substr_count( $content, self::OLD_OPENER );
	}

	/**
	 * Fetch posts whose content contains WP Tools Gravity Forms Divi 5 modules.
	 *
	 * @return array<int, object>
	 */
	public static function find_matches(): array {
		global $wpdb;

		$marker     = self::OLD_OPENER;
		$like       = '%' . $wpdb->esc_like( $marker ) . '%';
		$marker_len = strlen( $marker );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status,
					( LENGTH(post_content) - LENGTH( REPLACE( post_content, %s, '' ) ) ) / %d AS module_count
				FROM {$wpdb->posts}
				WHERE post_type IN ( 'post', 'page', 'et_pb_layout' )
				  AND post_status NOT IN ( 'trash', 'auto-draft' )
				  AND post_content LIKE %s
				ORDER BY post_type ASC, post_title ASC",
				$marker,
				$marker_len,
				$like
			)
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as $row ) {
			$row->module_count = isset( $row->module_count ) ? (int) $row->module_count : 0;
		}

		return $results;
	}

	/**
	 * Find a single match row by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public static function find_match( int $post_id ): ?object {
		foreach ( self::find_matches() as $row ) {
			if ( (int) $row->ID === $post_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Replace WP Tools Gravity Forms blocks in one post and report the result.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Post title.
	 * @return array{status: string, post_id: int, title: string, replaced: int, remaining: int, skipped: int, form_ids: array<int, string>}
	 */
	public static function replace_one( int $post_id, string $title ): array {
		global $wpdb;

		$base = array(
			'status'    => 'skipped',
			'post_id'   => $post_id,
			'title'     => $title,
			'replaced'  => 0,
			'remaining' => 0,
			'skipped'   => 0,
			'form_ids'  => array(),
		);

		$content = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			)
		);

		if ( ! is_string( $content ) || '' === $content ) {
			return $base;
		}

		$before = self::count_blocks( $content );
		$result = self::replace_blocks( $content );

		if ( $result['content'] === $content ) {
			$base['remaining'] = $before;
			$base['skipped']   = $result['skipped'];
			return $base;
		}

		if ( function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( $post_id );
		}

		$saved = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $result['content'] ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $saved ) {
			$base['status']    = 'failed';
			$base['remaining'] = $before;
			$base['skipped']   = $result['skipped'];
			return $base;
		}

		delete_post_meta( $post_id, '_et_pb_truncate_post' );
		delete_post_meta( $post_id, '_et_pb_truncate_post_date' );
		clean_post_cache( $post_id );

		return array(
			'status'    => 'updated',
			'post_id'   => $post_id,
			'title'     => $title,
			'replaced'  => $result['replaced'],
			'remaining' => self::count_blocks( $result['content'] ),
			'skipped'   => $result['skipped'],
			'form_ids'  => $result['form_ids'],
		);
	}

	/**
	 * Replace every valid WP Tools Gravity Forms block in a content string.
	 *
	 * Invalid JSON blocks are left in place and counted as skipped.
	 *
	 * @param string $content Post content.
	 * @return array{content: string, replaced: int, skipped: int, form_ids: array<int, string>}
	 */
	public static function replace_blocks( string $content ): array {
		$result = array(
			'content'  => $content,
			'replaced' => 0,
			'skipped'  => 0,
			'form_ids' => array(),
		);

		$offset = 0;
		$last   = 0;
		$guard  = 0;
		$pieces = array();

		while ( $guard < self::MAX_REPLACEMENTS ) {
			$block = self::find_next_block( $content, $offset );
			if ( null === $block ) {
				break;
			}

			if ( empty( $block['valid'] ) ) {
				++$result['skipped'];
				$offset = $block['end'] > $offset ? $block['end'] : $offset + 1;
				++$guard;
				continue;
			}

			$form_id     = self::extract_form_id( $block['attrs'] );
			$replacement = self::build_native_block( $form_id );

			$pieces[] = substr( $content, $last, $block['start'] - $last );
			$pieces[] = $replacement;

			$last   = $block['end'];
			$offset = $block['end'];

			++$result['replaced'];
			$result['form_ids'][] = $form_id;
			++$guard;
		}

		if ( 0 === $result['replaced'] ) {
			return $result;
		}

		$pieces[]          = substr( $content, $last );
		$result['content'] = implode( '', $pieces );

		return $result;
	}

	/**
	 * Build a native Divi 5 Gravity Forms self-closing block.
	 *
	 * @param string $form_id Numeric form ID, or empty string.
	 * @return string
	 */
	public static function build_native_block( string $form_id ): string {
		$attrs = array(
			'gravityForm'    => array(
				'advanced'    => array(
					'useAjax' => array(
						'desktop' => array(
							'value' => 'on',
						),
					),
				),
				'innerContent' => array(
					'desktop' => array(
						'value' => array(
							'formId' => $form_id,
						),
					),
				),
			),
			'builderVersion' => self::BUILDER_VERSION,
		);

		$json = wp_json_encode( $attrs );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		return '<!-- wp:divi/gravity-forms ' . $json . ' /-->';
	}

	/**
	 * Read the numeric Gravity Forms ID from WP Tools block attributes.
	 *
	 * @param array<string, mixed> $attrs Decoded block attributes.
	 * @return string Numeric ID, or empty string when missing / gf-0.
	 */
	public static function extract_form_id( array $attrs ): string {
		$raw = '';

		if ( isset( $attrs['gravityform']['advanced']['desktop']['value']['id'] ) ) {
			$raw = $attrs['gravityform']['advanced']['desktop']['value']['id'];
		}

		if ( ! is_string( $raw ) ) {
			$raw = is_scalar( $raw ) ? (string) $raw : '';
		}

		$id = (string) preg_replace( '/^gf-/i', '', $raw );

		if ( '' === $id || '0' === $id ) {
			return '';
		}

		return $id;
	}

	/**
	 * Locate the next WP Tools Gravity Forms block comment.
	 *
	 * @param string $content Post content.
	 * @param int    $offset  Search offset.
	 * @return array{start: int, end: int, attrs: array<string, mixed>, valid: bool}|null
	 */
	private static function find_next_block( string $content, int $offset ): ?array {
		$start = strpos( $content, self::OLD_OPENER, $offset );
		if ( false === $start ) {
			return null;
		}

		$after_opener = $start + strlen( self::OLD_OPENER );
		$len          = strlen( $content );
		$i            = $after_opener + strspn( $content, " \t\n\r", $after_opener );
		$attrs        = array();

		if ( $i < $len && '{' === $content[ $i ] ) {
			$json_end = self::find_json_end( $content, $i );
			if ( $json_end < 0 ) {
				return array(
					'start' => $start,
					'end'   => $after_opener,
					'attrs' => array(),
					'valid' => false,
				);
			}

			$json    = substr( $content, $i, $json_end - $i + 1 );
			$decoded = json_decode( $json, true );
			if ( ! is_array( $decoded ) ) {
				return array(
					'start' => $start,
					'end'   => $after_opener,
					'attrs' => array(),
					'valid' => false,
				);
			}

			$attrs = $decoded;
			$i     = $json_end + 1;
			$i    += strspn( $content, " \t\n\r", $i );
		}

		if ( ( $i + 4 ) <= $len && 0 === substr_compare( $content, '/-->', $i, 4 ) ) {
			return array(
				'start' => $start,
				'end'   => $i + 4,
				'attrs' => $attrs,
				'valid' => true,
			);
		}

		if ( ( $i + 3 ) <= $len && 0 === substr_compare( $content, '-->', $i, 3 ) ) {
			$closer_pos = strpos( $content, self::OLD_CLOSER, $i + 3 );
			if ( false === $closer_pos ) {
				return array(
					'start' => $start,
					'end'   => $after_opener,
					'attrs' => array(),
					'valid' => false,
				);
			}

			return array(
				'start' => $start,
				'end'   => $closer_pos + strlen( self::OLD_CLOSER ),
				'attrs' => $attrs,
				'valid' => true,
			);
		}

		return array(
			'start' => $start,
			'end'   => $after_opener,
			'attrs' => array(),
			'valid' => false,
		);
	}

	/**
	 * Find the closing brace of a JSON object, respecting strings and escapes.
	 *
	 * @param string $content Full content string.
	 * @param int    $start   Offset of the opening `{`.
	 * @return int Offset of the matching `}`, or -1 if not found.
	 */
	private static function find_json_end( string $content, int $start ): int {
		$len       = strlen( $content );
		$depth     = 0;
		$in_string = false;
		$escape    = false;

		for ( $i = $start; $i < $len; $i++ ) {
			$ch = $content[ $i ];

			if ( $in_string ) {
				if ( $escape ) {
					$escape = false;
					continue;
				}
				if ( '\\' === $ch ) {
					$escape = true;
					continue;
				}
				if ( '"' === $ch ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $ch ) {
				$in_string = true;
				continue;
			}

			if ( '{' === $ch ) {
				++$depth;
				continue;
			}

			if ( '}' === $ch ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return -1;
	}
}
