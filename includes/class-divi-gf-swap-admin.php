<?php
/**
 * Tools admin page and replacement request handlers.
 *
 * @package Divi_GF_Swap
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for scanning and confirming Gravity Forms module swaps.
 */
class Divi_GF_Swap_Admin {

	const PAGE_SLUG     = 'divi-gravity-forms-swap';
	const POST_ACTION   = 'divi_gf_swap_replace';
	const NONCE_ACTION  = 'divi_gf_swap_replace';
	const REPORT_PREFIX = 'divi_gf_swap_report_';

	/**
	 * Hook admin screens and POST handlers.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( __CLASS__, 'handle_replace' ) );
	}

	/**
	 * Add Tools > Divi Gravity Forms Swap.
	 */
	public static function register_menu(): void {
		add_management_page(
			__( 'Divi Gravity Forms Swap', 'divi-gravity-forms-swap' ),
			__( 'Divi Gravity Forms Swap', 'divi-gravity-forms-swap' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Tools page URL.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function page_url( array $args = array() ): string {
		$args['page'] = self::PAGE_SLUG;
		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}

	/**
	 * Render scan or confirm screen.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'divi-gravity-forms-swap' ) );
		}

		$action  = isset( $_GET['swap_action'] ) ? sanitize_key( wp_unslash( $_GET['swap_action'] ) ) : '';
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Divi Gravity Forms Swap', 'divi-gravity-forms-swap' ) . '</h1>';

		self::render_report_notice();

		if ( 'confirm' === $action ) {
			self::render_confirm( $post_id );
		} else {
			self::render_scan();
		}

		echo '</div>';
	}

	/**
	 * Handle nonce-protected replacement POST.
	 */
	public static function handle_replace(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'divi-gravity-forms-swap' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$matches = Divi_GF_Swap_Content::find_matches();
		$targets = $matches;

		if ( $post_id > 0 ) {
			$targets = array_values(
				array_filter(
					$matches,
					static function ( $row ) use ( $post_id ) {
						return (int) $row->ID === $post_id;
					}
				)
			);
		}

		$updated = 0;
		$skipped = 0;
		$failed  = 0;
		$items   = array();

		foreach ( $targets as $row ) {
			$title   = ( isset( $row->post_title ) && '' !== $row->post_title ) ? (string) $row->post_title : __( '(no title)', 'divi-gravity-forms-swap' );
			$result  = Divi_GF_Swap_Content::replace_one( (int) $row->ID, $title );
			$items[] = $result;

			if ( 'updated' === $result['status'] ) {
				++$updated;
			} elseif ( 'failed' === $result['status'] ) {
				++$failed;
			} else {
				++$skipped;
			}
		}

		set_transient(
			self::REPORT_PREFIX . get_current_user_id(),
			array(
				'updated' => $updated,
				'skipped' => $skipped,
				'failed'  => $failed,
				'items'   => $items,
			),
			120
		);

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Show scan results and per-item / bulk actions.
	 */
	private static function render_scan(): void {
		$results = Divi_GF_Swap_Content::find_matches();

		echo '<p>' . esc_html__( 'Scan posts, pages, and Divi Theme Builder layouts for WP Tools Gravity Forms Divi 5 modules, then replace them with the native Divi 5 Gravity Forms module. Only the form ID is copied; styles and other settings are not.', 'divi-gravity-forms-swap' ) . '</p>';

		if ( empty( $results ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'No posts, pages, or layouts contain WP Tools Gravity Forms Divi 5 modules.', 'divi-gravity-forms-swap' ) . '</p></div>';
			echo '<p>' . esc_html__( 'You can deactivate and delete this plugin.', 'divi-gravity-forms-swap' ) . '</p>';
			return;
		}

		echo '<p>';
		printf(
			esc_html__( 'Found %d match(es).', 'divi-gravity-forms-swap' ),
			count( $results )
		);
		echo '</p>';

		self::render_match_table( $results );

		echo '<p>';
		printf(
			'<a class="button button-secondary" href="%s">%s</a>',
			esc_url( self::page_url( array( 'swap_action' => 'confirm' ) ) ),
			esc_html__( 'Replace all listed items', 'divi-gravity-forms-swap' )
		);
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'A revision is saved before each item is rewritten. Surrounding Divi structure is kept. Purge any page cache after replacing, then deactivate this plugin when finished.', 'divi-gravity-forms-swap' ) . '</p>';
	}

	/**
	 * Confirm a single item or bulk replacement.
	 *
	 * @param int $post_id Post ID, or 0 for all matches.
	 */
	private static function render_confirm( int $post_id ): void {
		$results = Divi_GF_Swap_Content::find_matches();

		if ( $post_id > 0 ) {
			$row = Divi_GF_Swap_Content::find_match( $post_id );
			if ( ! $row ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'That item was not in the match list (it may already have been replaced).', 'divi-gravity-forms-swap' ) . '</p></div>';
				echo '<p><a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Back to scan', 'divi-gravity-forms-swap' ) . '</a></p>';
				return;
			}

			$title = ( isset( $row->post_title ) && '' !== $row->post_title ) ? (string) $row->post_title : __( '(no title)', 'divi-gravity-forms-swap' );

			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: post title, 2: post ID */
					__( 'Replace WP Tools Gravity Forms modules on "%1$s" (ID %2$d) with the native Divi 5 Gravity Forms module? Only the form ID is copied. Styles and other settings are not. A revision is saved first.', 'divi-gravity-forms-swap' ),
					$title,
					$post_id
				)
			) . '</p>';
		} else {
			if ( empty( $results ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'No posts, pages, or layouts contain WP Tools Gravity Forms Divi 5 modules. Nothing to replace.', 'divi-gravity-forms-swap' ) . '</p></div>';
				echo '<p><a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Back to scan', 'divi-gravity-forms-swap' ) . '</a></p>';
				return;
			}

			echo '<p><strong>' . esc_html__( 'This will rewrite post_content on every item listed below.', 'divi-gravity-forms-swap' ) . '</strong> ' . esc_html__( 'WP Tools Gravity Forms Divi 5 modules are replaced with the native Divi 5 Gravity Forms module. Only the form ID is copied. A revision is saved first.', 'divi-gravity-forms-swap' ) . '</p>';
			self::render_match_table( $results, false );
		}

		$button_label = $post_id > 0
			? __( 'Yes, replace modules on this item only', 'divi-gravity-forms-swap' )
			: __( 'Yes, replace modules on all listed items', 'divi-gravity-forms-swap' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::POST_ACTION ) . '" />';
		echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '" />';
		submit_button( $button_label, 'primary', 'submit', false );
		echo ' <a class="button button-secondary" href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Cancel', 'divi-gravity-forms-swap' ) . '</a>';
		echo '</form>';
	}

	/**
	 * Render the match list table.
	 *
	 * @param array<int, object> $results    Query rows.
	 * @param bool               $show_replace Whether to show per-item replace links.
	 */
	private static function render_match_table( array $results, bool $show_replace = true ): void {
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'divi-gravity-forms-swap' ) . '</th>';
		echo '<th>' . esc_html__( 'ID', 'divi-gravity-forms-swap' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'divi-gravity-forms-swap' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'divi-gravity-forms-swap' ) . '</th>';
		echo '<th>' . esc_html__( 'Modules', 'divi-gravity-forms-swap' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'divi-gravity-forms-swap' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $results as $row ) {
			$post_id = (int) $row->ID;
			$title   = ( isset( $row->post_title ) && '' !== $row->post_title ) ? (string) $row->post_title : __( '(no title)', 'divi-gravity-forms-swap' );
			$edit    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
			$count   = isset( $row->module_count ) ? (int) $row->module_count : 0;
			$replace = self::page_url(
				array(
					'swap_action' => 'confirm',
					'post_id'     => $post_id,
				)
			);

			$actions = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit ),
				esc_html__( 'Edit', 'divi-gravity-forms-swap' )
			);

			if ( self::can_view_post( (string) $row->post_type ) ) {
				$view = get_permalink( $post_id );
				$view = $view ? $view : home_url( '/?p=' . $post_id );
				$actions = sprintf(
					'<a href="%1$s">%2$s</a> | <a href="%3$s" target="_blank" rel="noopener">%4$s</a>',
					esc_url( $edit ),
					esc_html__( 'Edit', 'divi-gravity-forms-swap' ),
					esc_url( $view ),
					esc_html__( 'View', 'divi-gravity-forms-swap' )
				);
			}

			if ( $show_replace ) {
				$actions .= sprintf(
					' | <a href="%1$s">%2$s</a>',
					esc_url( $replace ),
					esc_html__( 'Replace with native Divi 5 Gravity Forms Module', 'divi-gravity-forms-swap' )
				);
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $title ) . '</strong></td>';
			echo '<td>' . esc_html( (string) $post_id ) . '</td>';
			echo '<td>' . esc_html( self::format_post_type( (string) $row->post_type ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row->post_status ) . '</td>';
			echo '<td>' . esc_html( (string) $count ) . '</td>';
			echo '<td>' . wp_kses(
				$actions,
				array(
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Whether a post type has a useful front-end View link.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function can_view_post( string $post_type ): bool {
		return in_array( $post_type, array( 'post', 'page' ), true );
	}

	/**
	 * Human-readable post type label.
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	private static function format_post_type( string $post_type ): string {
		if ( 'et_pb_layout' === $post_type ) {
			return __( 'Divi layout', 'divi-gravity-forms-swap' );
		}

		$object = get_post_type_object( $post_type );
		if ( $object && isset( $object->labels->singular_name ) ) {
			return (string) $object->labels->singular_name;
		}

		return $post_type;
	}

	/**
	 * Show the last replacement report, if any.
	 */
	private static function render_report_notice(): void {
		$key    = self::REPORT_PREFIX . get_current_user_id();
		$report = get_transient( $key );

		if ( ! is_array( $report ) ) {
			return;
		}

		delete_transient( $key );

		$updated = isset( $report['updated'] ) ? (int) $report['updated'] : 0;
		$skipped = isset( $report['skipped'] ) ? (int) $report['skipped'] : 0;
		$failed  = isset( $report['failed'] ) ? (int) $report['failed'] : 0;
		$type    = $failed > 0 ? 'error' : 'success';

		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>';
		printf(
			/* translators: 1: updated count, 2: skipped count, 3: failed count */
			esc_html__( 'Updated %1$d item(s). Skipped %2$d. Failed %3$d.', 'divi-gravity-forms-swap' ),
			$updated,
			$skipped,
			$failed
		);
		echo '</p>';

		if ( ! empty( $report['items'] ) && is_array( $report['items'] ) ) {
			echo '<ul>';
			foreach ( $report['items'] as $item ) {
				if ( is_array( $item ) ) {
					echo '<li>' . esc_html( self::format_item_message( $item ) ) . '</li>';
				}
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Human-readable line for one replacement result.
	 *
	 * @param array<string, mixed> $item Result from Divi_GF_Swap_Content::replace_one().
	 * @return string
	 */
	private static function format_item_message( array $item ): string {
		$title     = isset( $item['title'] ) ? (string) $item['title'] : __( '(no title)', 'divi-gravity-forms-swap' );
		$post_id   = isset( $item['post_id'] ) ? (int) $item['post_id'] : 0;
		$status    = isset( $item['status'] ) ? (string) $item['status'] : 'skipped';
		$replaced  = isset( $item['replaced'] ) ? (int) $item['replaced'] : 0;
		$remaining = isset( $item['remaining'] ) ? (int) $item['remaining'] : 0;
		$skipped   = isset( $item['skipped'] ) ? (int) $item['skipped'] : 0;
		$form_ids  = isset( $item['form_ids'] ) && is_array( $item['form_ids'] ) ? $item['form_ids'] : array();

		$form_label = self::format_form_ids( $form_ids );

		if ( 'updated' === $status ) {
			$message = sprintf(
				/* translators: 1: title, 2: post ID, 3: replaced module count, 4: form ID list */
				__( '%1$s (ID %2$d) — replaced %3$d module(s) with form ID(s) %4$s.', 'divi-gravity-forms-swap' ),
				$title,
				$post_id,
				$replaced,
				$form_label
			);

			if ( $remaining > 0 || $skipped > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: 1: remaining count, 2: skipped invalid count */
					__( '%1$d remaining, %2$d skipped (invalid markup).', 'divi-gravity-forms-swap' ),
					$remaining,
					$skipped
				);
			}

			return $message;
		}

		if ( 'failed' === $status ) {
			return sprintf(
				/* translators: 1: title, 2: post ID */
				__( '%1$s (ID %2$d) — database update failed.', 'divi-gravity-forms-swap' ),
				$title,
				$post_id
			);
		}

		return sprintf(
			/* translators: 1: title, 2: post ID */
			__( '%1$s (ID %2$d) — no matching modules replaced, skipped.', 'divi-gravity-forms-swap' ),
			$title,
			$post_id
		);
	}

	/**
	 * Format replaced form IDs for the report.
	 *
	 * @param array<int, mixed> $form_ids Form ID strings.
	 * @return string
	 */
	private static function format_form_ids( array $form_ids ): string {
		$labels = array();

		foreach ( $form_ids as $form_id ) {
			$form_id = is_scalar( $form_id ) ? (string) $form_id : '';
			$labels[] = '' === $form_id ? __( '(none)', 'divi-gravity-forms-swap' ) : $form_id;
		}

		if ( empty( $labels ) ) {
			return __( '(none)', 'divi-gravity-forms-swap' );
		}

		return implode( ', ', $labels );
	}
}
