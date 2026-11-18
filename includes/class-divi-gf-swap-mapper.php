<?php
/**
 * Map WP Tools Gravity Forms Divi 5 attrs to native divi/gravity-forms attrs.
 *
 * @package Divi_GF_Swap
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) && ! defined( 'DIVI_GF_SWAP_TEST_BOOTSTRAP' ) ) {
	exit;
}

/**
 * Attribute mapper for WP Tools → native Divi 5 Gravity Forms modules.
 *
 * Pure PHP: safe to load from CLI fixture tests without WordPress.
 */
class Divi_GF_Swap_Mapper {

	/**
	 * WP Tools attr roots with no native counterpart.
	 *
	 * @var array<int, string>
	 */
	const SKIP_ROOTS = array(
		'gridRowGap',
		'gridColumnGap',
		'fieldWrapper',
		'inputWrapper',
		'footer',
		'radioCheckboxOptionContainer',
		'sectionField',
		'consentCheckboxContainer',
		'checkboxField',
		'radioField',
		'activeCheckboxAndRadioTextOption',
		'dateDropdownDateField',
		'amPmDropdown',
		'hourMinField',
		'cssReadyGfLeftHalf',
		'cssReadyGfLeftThirdGfMiddleThird',
		'productTitle',
		'productDescription',
		'productPriceLabel',
		'productCalculatedPrice',
		'listFieldColumn',
		'sendLinkButton',
		'consentCheckboxTitle',
		'text',
	);

	/**
	 * Custom CSS subName renames: WP Tools → native.
	 *
	 * @var array<string, string>
	 */
	const CSS_RENAMES = array(
		'label'                   => 'fieldLabel',
		'inputGeneral'            => 'field',
		'sectionTitle'            => 'sectionHeading',
		'submitButton'            => 'button',
		'saveAndContinueButton'   => 'saveButton',
		'fieldValidationMessage'  => 'validationFieldMessage',
		'confirmationMessage'     => 'formConfirmation',
		'asterisk'                => 'requiredMarker',
		'progressBarTitle'        => 'progressBar',
		'validationErrorHeading'  => 'validationSummary',
		'validationErrorContainer'=> 'validationSummary',
	);

	/**
	 * Native custom CSS slots that accept a 1:1 copy.
	 *
	 * @var array<int, string>
	 */
	const CSS_KEEP = array(
		'title',
		'subLabel',
		'description',
		'before',
		'mainElement',
		'after',
		'nextButton',
		'previousButton',
		'fileUploadButton',
	);

	/**
	 * Map WP Tools block attributes to native Divi Gravity Forms attributes.
	 *
	 * @param array<string, mixed> $source Decoded WP Tools attrs.
	 * @return array{
	 *   attrs: array<string, mixed>,
	 *   mapped: array<int, string>,
	 *   skipped: array<int, string>,
	 *   warnings: array<int, string>
	 * }
	 */
	public static function map( array $source ): array {
		$mapped   = array();
		$skipped  = array();
		$warnings = array();
		$native   = array();

		self::map_content( $source, $native, $mapped, $skipped, $warnings );
		self::map_module_shell( $source, $native, $mapped );
		self::map_same_root( $source, $native, $mapped, array( 'title', 'description', 'subLabel' ) );
		self::map_renamed_roots( $source, $native, $mapped );
		self::map_field_group( $source, $native, $mapped, $skipped, $warnings );
		self::map_choice_fonts( $source, $native, $mapped );
		self::map_required_marker( $source, $native, $mapped );
		self::map_validation_summary( $source, $native, $mapped );
		self::map_confirmation( $source, $native, $mapped );
		self::map_progress_bar( $source, $native, $mapped );
		self::map_folded_descriptions( $source, $native, $mapped, $warnings );
		self::map_custom_css( $source, $native, $mapped, $skipped );
		self::map_skipped_roots( $source, $skipped );
		self::map_unknown_roots( $source, $skipped );

		$native = self::normalize_backgrounds( $native );

		return array(
			'attrs'    => $native,
			'mapped'   => array_values( array_unique( $mapped ) ),
			'skipped'  => array_values( array_unique( $skipped ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Form ID and Ajax.
	 *
	 * @param array<string, mixed> $source   Source attrs.
	 * @param array<string, mixed> $native   Native attrs (by ref).
	 * @param array<int, string>   $mapped   Mapped keys (by ref).
	 * @param array<int, string>   $skipped  Skipped keys (by ref).
	 * @param array<int, string>   $warnings Warnings (by ref).
	 */
	private static function map_content( array $source, array &$native, array &$mapped, array &$skipped, array &$warnings ): void {
		$form_id = self::extract_form_id( $source );
		$use_ajax = 'off';

		$gf = $source['gravityform']['advanced']['desktop']['value'] ?? null;
		if ( is_array( $gf ) ) {
			if ( isset( $gf['useAjax'] ) && in_array( (string) $gf['useAjax'], array( 'on', 'off' ), true ) ) {
				$use_ajax = (string) $gf['useAjax'];
			}

			if ( isset( $gf['showTitle'] ) && 'off' === (string) $gf['showTitle'] ) {
				$warnings[] = 'showTitle:off';
			}
			if ( isset( $gf['showDescription'] ) && 'off' === (string) $gf['showDescription'] ) {
				$warnings[] = 'showDescription:off';
			}

			if ( isset( $gf['tabIndex'] ) && '' !== (string) $gf['tabIndex'] && '0' !== (string) $gf['tabIndex'] ) {
				$skipped[] = 'tabIndex';
			}
			if ( isset( $gf['fieldValues'] ) && '' !== (string) $gf['fieldValues'] ) {
				$skipped[] = 'fieldValues';
			}

			$mapped[] = 'gravityform';
		}

		$native['gravityForm'] = array(
			'advanced'     => array(
				'useAjax' => array(
					'desktop' => array(
						'value' => $use_ajax,
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
		);

		$mapped[] = 'formId';
		$mapped[] = 'useAjax';
	}

	/**
	 * Module shell decoration and advanced.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_module_shell( array $source, array &$native, array &$mapped ): void {
		if ( ! isset( $source['module'] ) || ! is_array( $source['module'] ) ) {
			return;
		}

		$module = array();

		if ( isset( $source['module']['decoration'] ) && is_array( $source['module']['decoration'] ) ) {
			$module['decoration'] = self::deep_copy( $source['module']['decoration'] );
			$mapped[]             = 'module.decoration';
		}

		if ( isset( $source['module']['advanced'] ) && is_array( $source['module']['advanced'] ) ) {
			$advanced = array();
			foreach ( array( 'htmlAttributes', 'link', 'text' ) as $key ) {
				if ( isset( $source['module']['advanced'][ $key ] ) && is_array( $source['module']['advanced'][ $key ] ) ) {
					$advanced[ $key ] = self::deep_copy( $source['module']['advanced'][ $key ] );
					$mapped[]         = 'module.advanced.' . $key;
				}
			}
			if ( ! empty( $advanced ) ) {
				$module['advanced'] = $advanced;
			}
		}

		if ( isset( $source['module']['meta']['adminLabel'] ) && is_array( $source['module']['meta']['adminLabel'] ) ) {
			$module['meta'] = array(
				'adminLabel' => self::deep_copy( $source['module']['meta']['adminLabel'] ),
			);
			$mapped[]       = 'module.meta.adminLabel';
		}

		if ( ! empty( $module ) ) {
			$native['module'] = $module;
		}
	}

	/**
	 * Copy roots that share the same name and decoration shape.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 * @param array<int, string>   $roots  Root names to copy.
	 */
	private static function map_same_root( array $source, array &$native, array &$mapped, array $roots ): void {
		foreach ( $roots as $root ) {
			if ( ! self::has_data( $source, $root ) ) {
				continue;
			}
			$native[ $root ] = self::copy_decoration_subtree( $source[ $root ] );
			$mapped[]        = $root;
		}
	}

	/**
	 * Rename roots with the same decoration shape.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_renamed_roots( array $source, array &$native, array &$mapped ): void {
		$renames = array(
			'submitButton'            => 'button',
			'nextButton'              => 'nextButton',
			'previousButton'          => 'previousButton',
			'saveAndContinueButton'   => 'saveButton',
			'fileUploadButton'        => 'fileUploadButton',
			'sectionTitle'            => 'sectionHeading',
			'fieldValidationMessage'  => 'validationFieldMessage',
		);

		foreach ( $renames as $from => $to ) {
			if ( ! self::has_data( $source, $from ) ) {
				continue;
			}
			$copied = self::copy_decoration_subtree( $source[ $from ] );
			if ( 'saveButton' === $to && isset( $copied['decoration'] ) && is_array( $copied['decoration'] ) ) {
				unset( $copied['decoration']['iconSize'], $copied['decoration']['iconColor'] );
			}
			$native[ $to ] = isset( $native[ $to ] ) && is_array( $native[ $to ] )
				? self::deep_merge( $native[ $to ], $copied )
				: $copied;
			$mapped[]      = $from . '→' . $to;
		}
	}

	/**
	 * Merge label / input trees into native field.
	 *
	 * @param array<string, mixed> $source   Source attrs.
	 * @param array<string, mixed> $native   Native attrs (by ref).
	 * @param array<int, string>   $mapped   Mapped keys (by ref).
	 * @param array<int, string>   $skipped  Skipped keys (by ref).
	 * @param array<int, string>   $warnings Warnings (by ref).
	 */
	private static function map_field_group( array $source, array &$native, array &$mapped, array &$skipped, array &$warnings ): void {
		$field = array( 'decoration' => array() );

		if ( self::has_data( $source, 'label' ) ) {
			$label = $source['label'];
			if ( isset( $label['decoration']['font'] ) && is_array( $label['decoration']['font'] ) ) {
				$field['decoration']['labelFont'] = self::deep_copy( $label['decoration']['font'] );
				$mapped[]                         = 'label.font→field.labelFont';
			}
			if ( isset( $label['decoration']['spacing'] ) && is_array( $label['decoration']['spacing'] ) ) {
				$input_spacing_empty = ! isset( $source['inputGeneral']['decoration']['spacing'] )
					|| ! self::tree_has_values( $source['inputGeneral']['decoration']['spacing'] );
				if ( $input_spacing_empty ) {
					$field['decoration']['spacing'] = self::deep_copy( $label['decoration']['spacing'] );
					$mapped[]                       = 'label.spacing→field.spacing';
				} else {
					$warnings[] = 'label.spacing:partial';
				}
			}
		}

		if ( self::has_data( $source, 'inputGeneral' ) ) {
			$ig = $source['inputGeneral']['decoration'] ?? array();
			foreach ( array( 'font', 'background', 'border', 'spacing' ) as $key ) {
				if ( isset( $ig[ $key ] ) && is_array( $ig[ $key ] ) ) {
					$field['decoration'][ $key ] = self::deep_merge(
						$field['decoration'][ $key ] ?? array(),
						self::deep_copy( $ig[ $key ] )
					);
					$mapped[] = 'inputGeneral.' . $key;
				}
			}
		}

		foreach ( array( 'textAndTextarea', 'select' ) as $overlay ) {
			if ( ! self::has_data( $source, $overlay ) ) {
				continue;
			}
			$dec = $source[ $overlay ]['decoration'] ?? array();
			foreach ( array( 'boxShadow', 'spacing' ) as $key ) {
				if ( ! isset( $dec[ $key ] ) || ! is_array( $dec[ $key ] ) ) {
					continue;
				}
				if ( ! isset( $field['decoration'][ $key ] ) || ! self::tree_has_values( $field['decoration'][ $key ] ) ) {
					$field['decoration'][ $key ] = self::deep_copy( $dec[ $key ] );
					$mapped[]                    = $overlay . '.' . $key;
				}
			}
		}

		if ( self::has_data( $source, 'inputPlaceholder' ) ) {
			$ph = $source['inputPlaceholder']['decoration']['font'] ?? null;
			if ( is_array( $ph ) ) {
				$field['decoration']['placeholderFont'] = self::deep_copy( $ph );
				$mapped[]                               = 'inputPlaceholder→field.placeholderFont';
			}
		}

		if ( self::has_data( $source, 'inputGeneralFocus' ) ) {
			$focus = $source['inputGeneralFocus']['decoration'] ?? array();
			$moved = false;
			foreach ( array( 'border', 'background' ) as $key ) {
				if ( ! isset( $focus[ $key ] ) || ! is_array( $focus[ $key ] ) ) {
					continue;
				}
				if ( self::has_state_branch( $focus[ $key ] ) ) {
					$field['decoration'][ $key ] = self::deep_merge(
						$field['decoration'][ $key ] ?? array(),
						self::deep_copy( $focus[ $key ] )
					);
					$moved = true;
				}
			}
			if ( $moved ) {
				$mapped[] = 'inputGeneralFocus';
			} else {
				$warnings[] = 'inputGeneralFocus:partial';
			}
		}

		if ( ! empty( $field['decoration'] ) ) {
			$native['field'] = $field;
		}
	}

	/**
	 * Checkbox / radio choice label fonts.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_choice_fonts( array $source, array &$native, array &$mapped ): void {
		if ( ! self::has_data( $source, 'checkboxAndRadioTextOption' ) ) {
			return;
		}
		$font = $source['checkboxAndRadioTextOption']['decoration']['font'] ?? null;
		if ( ! is_array( $font ) ) {
			return;
		}
		$font_copy = self::deep_copy( $font );
		foreach ( array( 'checkbox', 'radioButton' ) as $root ) {
			$native[ $root ] = self::deep_merge(
				$native[ $root ] ?? array(),
				array( 'decoration' => array( 'font' => $font_copy ) )
			);
		}
		$mapped[] = 'checkboxAndRadioTextOption.font';
	}

	/**
	 * Required marker from asterisk styles.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_required_marker( array $source, array &$native, array &$mapped ): void {
		$font = null;
		foreach ( array( 'asterisk', 'asteriskLegend' ) as $root ) {
			if ( ! self::has_data( $source, $root ) ) {
				continue;
			}
			$candidate = $source[ $root ]['decoration']['font'] ?? null;
			if ( is_array( $candidate ) ) {
				$font     = is_array( $font ) ? self::deep_merge( $font, self::deep_copy( $candidate ) ) : self::deep_copy( $candidate );
				$mapped[] = $root . '→requiredMarker';
			}
		}
		if ( is_array( $font ) ) {
			$native['requiredMarker'] = array(
				'decoration' => array(
					'font' => $font,
				),
			);
		}
	}

	/**
	 * Validation summary: heading font → bodyFont, container chrome.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_validation_summary( array $source, array &$native, array &$mapped ): void {
		$summary = array( 'decoration' => array() );

		if ( self::has_data( $source, 'validationErrorHeading' ) ) {
			$font = $source['validationErrorHeading']['decoration']['font'] ?? null;
			if ( is_array( $font ) ) {
				$summary['decoration']['bodyFont'] = array(
					'body' => array(
						'font' => self::deep_copy( $font ),
					),
				);
				$mapped[] = 'validationErrorHeading→validationSummary';
			}
			foreach ( array( 'spacing', 'border', 'background' ) as $key ) {
				if ( isset( $source['validationErrorHeading']['decoration'][ $key ] ) ) {
					$summary['decoration'][ $key ] = self::deep_copy( $source['validationErrorHeading']['decoration'][ $key ] );
				}
			}
		}

		if ( self::has_data( $source, 'validationErrorContainer' ) ) {
			foreach ( array( 'background', 'border', 'spacing' ) as $key ) {
				if ( isset( $source['validationErrorContainer']['decoration'][ $key ] ) && is_array( $source['validationErrorContainer']['decoration'][ $key ] ) ) {
					$summary['decoration'][ $key ] = self::deep_merge(
						$summary['decoration'][ $key ] ?? array(),
						self::deep_copy( $source['validationErrorContainer']['decoration'][ $key ] )
					);
					$mapped[] = 'validationErrorContainer.' . $key;
				}
			}
		}

		if ( ! empty( $summary['decoration'] ) ) {
			$native['validationSummary'] = $summary;
		}
	}

	/**
	 * Confirmation message + wrapper background.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_confirmation( array $source, array &$native, array &$mapped ): void {
		$conf = array( 'decoration' => array() );

		if ( self::has_data( $source, 'confirmationMessage' ) ) {
			$font = $source['confirmationMessage']['decoration']['font'] ?? null;
			if ( is_array( $font ) ) {
				$conf['decoration']['bodyFont'] = array(
					'body' => array(
						'font' => self::deep_copy( $font ),
					),
				);
				$mapped[] = 'confirmationMessage.font';
			}
			if ( isset( $source['confirmationMessage']['decoration']['spacing'] ) ) {
				$conf['decoration']['spacing'] = self::deep_copy( $source['confirmationMessage']['decoration']['spacing'] );
				$mapped[]                     = 'confirmationMessage.spacing';
			}
		}

		if ( self::has_data( $source, 'confirmationMessageWrapper' ) ) {
			$bg = $source['confirmationMessageWrapper']['decoration']['background'] ?? null;
			if ( is_array( $bg ) ) {
				$conf['decoration']['background'] = self::deep_copy( $bg );
				$mapped[]                        = 'confirmationMessageWrapper.background';
			}
		}

		if ( ! empty( $conf['decoration'] ) ) {
			$native['formConfirmation'] = $conf;
		}
	}

	/**
	 * Progress bar title + percentage into one root.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param array<string, mixed> $native Native attrs (by ref).
	 * @param array<int, string>   $mapped Mapped keys (by ref).
	 */
	private static function map_progress_bar( array $source, array &$native, array &$mapped ): void {
		$bar = array( 'decoration' => array() );

		if ( self::has_data( $source, 'progressBarTitle' ) ) {
			$font = $source['progressBarTitle']['decoration']['font'] ?? null;
			if ( is_array( $font ) ) {
				$bar['decoration']['font'] = self::deep_copy( $font );
				$mapped[]                  = 'progressBarTitle→progressBar';
			}
		}

		if ( self::has_data( $source, 'progressBarPercentage' ) ) {
			$dec = $source['progressBarPercentage']['decoration'] ?? array();
			if ( isset( $dec['background'] ) && is_array( $dec['background'] ) ) {
				$bar['decoration']['background'] = self::deep_copy( $dec['background'] );
				$mapped[]                        = 'progressBarPercentage.background';
			}
			if ( isset( $dec['textColor'] ) && is_array( $dec['textColor'] ) ) {
				// Fold text color into font color when possible.
				$bar['decoration']['font'] = self::deep_merge(
					$bar['decoration']['font'] ?? array(),
					self::text_color_as_font_color( $dec['textColor'] )
				);
				$mapped[] = 'progressBarPercentage.textColor';
			}
		}

		if ( ! empty( $bar['decoration'] ) ) {
			$native['progressBar'] = $bar;
		}
	}

	/**
	 * Fold extra description fonts into native description.
	 *
	 * @param array<string, mixed> $source   Source attrs.
	 * @param array<string, mixed> $native   Native attrs (by ref).
	 * @param array<int, string>   $mapped   Mapped keys (by ref).
	 * @param array<int, string>   $warnings Warnings (by ref).
	 */
	private static function map_folded_descriptions( array $source, array &$native, array &$mapped, array &$warnings ): void {
		foreach ( array( 'fieldDescription', 'sectionDescription', 'consentCheckboxDescription' ) as $root ) {
			if ( ! self::has_data( $source, $root ) ) {
				continue;
			}
			$font = $source[ $root ]['decoration']['font'] ?? null;
			if ( ! is_array( $font ) ) {
				continue;
			}
			$existing = $native['description']['decoration']['font'] ?? null;
			if ( ! is_array( $existing ) || ! self::tree_has_values( $existing ) ) {
				$native['description'] = self::deep_merge(
					$native['description'] ?? array(),
					array( 'decoration' => array( 'font' => self::deep_copy( $font ) ) )
				);
				$mapped[]   = $root . '→description';
				$warnings[] = $root . ':folded';
			} else {
				$warnings[] = $root . ':folded-skipped-existing';
			}
		}
	}

	/**
	 * Custom CSS object.
	 *
	 * @param array<string, mixed> $source  Source attrs.
	 * @param array<string, mixed> $native  Native attrs (by ref).
	 * @param array<int, string>   $mapped  Mapped keys (by ref).
	 * @param array<int, string>   $skipped Skipped keys (by ref).
	 */
	private static function map_custom_css( array $source, array &$native, array &$mapped, array &$skipped ): void {
		if ( ! isset( $source['css'] ) || ! is_array( $source['css'] ) ) {
			return;
		}

		$css = array();

		foreach ( $source['css'] as $breakpoint => $slots ) {
			if ( ! is_array( $slots ) ) {
				continue;
			}
			foreach ( $slots as $slot => $value ) {
				if ( ! is_string( $value ) || '' === $value ) {
					continue;
				}

				if ( in_array( $slot, self::CSS_KEEP, true ) ) {
					$css[ $breakpoint ][ $slot ] = $value;
					$mapped[]                    = 'css.' . $slot;
					continue;
				}

				if ( isset( self::CSS_RENAMES[ $slot ] ) ) {
					$target = self::CSS_RENAMES[ $slot ];
					if ( 'validationSummary' === $target && isset( $css[ $breakpoint ][ $target ] ) && 'validationErrorContainer' === $slot ) {
						// Heading already won.
						continue;
					}
					$css[ $breakpoint ][ $target ] = $value;
					$mapped[]                      = 'css.' . $slot . '→' . $target;
					continue;
				}

				if ( 'checkboxAndRadioTextOption' === $slot ) {
					foreach ( array( 'checkbox', 'radioButton' ) as $target ) {
						if ( ! isset( $css[ $breakpoint ][ $target ] ) ) {
							$css[ $breakpoint ][ $target ] = $value;
						}
					}
					$mapped[] = 'css.checkboxAndRadioTextOption';
					continue;
				}

				$skipped[] = 'css.' . $slot;
			}
		}

		if ( ! empty( $css ) ) {
			$native['css'] = $css;
		}
	}

	/**
	 * Record skip roots that have stored data.
	 *
	 * @param array<string, mixed> $source  Source attrs.
	 * @param array<int, string>   $skipped Skipped keys (by ref).
	 */
	private static function map_skipped_roots( array $source, array &$skipped ): void {
		foreach ( self::SKIP_ROOTS as $root ) {
			if ( self::has_data( $source, $root ) ) {
				$skipped[] = $root;
			}
		}
	}

	/**
	 * Unknown top-level keys that still have data.
	 *
	 * @param array<string, mixed> $source  Source attrs.
	 * @param array<int, string>   $skipped Skipped keys (by ref).
	 */
	private static function map_unknown_roots( array $source, array &$skipped ): void {
		$known = array_merge(
			self::SKIP_ROOTS,
			array(
				'module',
				'title',
				'description',
				'subLabel',
				'gravityform',
				'label',
				'inputGeneral',
				'inputGeneralFocus',
				'textAndTextarea',
				'select',
				'inputPlaceholder',
				'submitButton',
				'nextButton',
				'previousButton',
				'saveAndContinueButton',
				'fileUploadButton',
				'sectionTitle',
				'fieldValidationMessage',
				'checkboxAndRadioTextOption',
				'asterisk',
				'asteriskLegend',
				'validationErrorHeading',
				'validationErrorContainer',
				'confirmationMessage',
				'confirmationMessageWrapper',
				'progressBarTitle',
				'progressBarPercentage',
				'fieldDescription',
				'sectionDescription',
				'consentCheckboxDescription',
				'css',
				'modulePreset',
				'builderVersion',
				'locked',
			)
		);

		foreach ( array_keys( $source ) as $key ) {
			if ( in_array( $key, $known, true ) ) {
				continue;
			}
			if ( self::has_data( $source, $key ) ) {
				$skipped[] = $key;
			}
		}
	}

	/**
	 * Extract numeric form ID from WP Tools gravityform attrs.
	 *
	 * @param array<string, mixed> $attrs Source attrs.
	 * @return string
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
	 * Whether a root exists and has nested values beyond empty arrays.
	 *
	 * @param array<string, mixed> $source Source attrs.
	 * @param string               $root   Root key.
	 * @return bool
	 */
	private static function has_data( array $source, string $root ): bool {
		return isset( $source[ $root ] ) && is_array( $source[ $root ] ) && self::tree_has_values( $source[ $root ] );
	}

	/**
	 * Whether a tree contains any scalar / non-empty nested value.
	 *
	 * @param mixed $node Tree node.
	 * @return bool
	 */
	private static function tree_has_values( $node ): bool {
		if ( ! is_array( $node ) ) {
			return null !== $node && '' !== $node;
		}
		foreach ( $node as $child ) {
			if ( self::tree_has_values( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a decoration tree already uses hover/sticky/focus state keys.
	 *
	 * @param array<string, mixed> $tree Decoration subtree.
	 * @return bool
	 */
	private static function has_state_branch( array $tree ): bool {
		foreach ( array_keys( $tree ) as $key ) {
			if ( in_array( $key, array( 'hover', 'sticky', 'focus' ), true ) ) {
				return true;
			}
			if ( is_array( $tree[ $key ] ) && self::has_state_branch( $tree[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Copy decoration (+ advanced when present) from a root.
	 *
	 * @param array<string, mixed> $root Source root.
	 * @return array<string, mixed>
	 */
	private static function copy_decoration_subtree( array $root ): array {
		$out = array();
		if ( isset( $root['decoration'] ) && is_array( $root['decoration'] ) ) {
			$out['decoration'] = self::deep_copy( $root['decoration'] );
		}
		if ( isset( $root['advanced'] ) && is_array( $root['advanced'] ) ) {
			$out['advanced'] = self::deep_copy( $root['advanced'] );
		}
		return $out;
	}

	/**
	 * Convert WP Tools textColor decoration into a font color overlay.
	 *
	 * @param array<string, mixed> $text_color Text color tree.
	 * @return array<string, mixed>
	 */
	private static function text_color_as_font_color( array $text_color ): array {
		$out = array();
		foreach ( $text_color as $bp => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['value'] ) && is_string( $node['value'] ) ) {
				$out['font'][ $bp ]['value']['color'] = $node['value'];
			} elseif ( isset( $node['value']['color'] ) ) {
				$out['font'][ $bp ]['value']['color'] = $node['value']['color'];
			}
		}
		return $out;
	}

	/**
	 * Normalize background.desktop.color.value → background.desktop.value.color.
	 *
	 * @param array<string, mixed> $tree Attr tree.
	 * @return array<string, mixed>
	 */
	private static function normalize_backgrounds( array $tree ): array {
		foreach ( $tree as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( 'background' === $key ) {
				$tree[ $key ] = self::normalize_background_node( $value );
			} else {
				$tree[ $key ] = self::normalize_backgrounds( $value );
			}
		}
		return $tree;
	}

	/**
	 * Fix one background decoration node.
	 *
	 * @param array<string, mixed> $bg Background tree keyed by breakpoint.
	 * @return array<string, mixed>
	 */
	private static function normalize_background_node( array $bg ): array {
		foreach ( $bg as $bp => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			// Shape: desktop.color.value → desktop.value.color
			if ( isset( $node['color']['value'] ) && ! isset( $node['value']['color'] ) ) {
				$color = $node['color']['value'];
				unset( $bg[ $bp ]['color'] );
				$bg[ $bp ]['value']['color'] = $color;
			}
			// Nested recurse for unexpected nesting.
			$bg[ $bp ] = self::normalize_backgrounds( $bg[ $bp ] );
		}
		return $bg;
	}

	/**
	 * Deep array copy.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function deep_copy( $value ) {
		return json_decode( (string) json_encode( $value ), true );
	}

	/**
	 * Deep merge; later values overwrite scalars, arrays recurse.
	 *
	 * @param array<string, mixed> $base Base.
	 * @param array<string, mixed> $over Overlay.
	 * @return array<string, mixed>
	 */
	private static function deep_merge( array $base, array $over ): array {
		foreach ( $over as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
