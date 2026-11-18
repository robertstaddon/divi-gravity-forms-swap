<?php
/**
 * CLI fixture runner for Divi_GF_Swap_Mapper (no WordPress bootstrap).
 *
 * Usage: php tests/run-mapper.php
 *
 * @package Divi_GF_Swap
 */

declare(strict_types=1);

define( 'DIVI_GF_SWAP_TEST_BOOTSTRAP', true );

require_once dirname( __DIR__ ) . '/includes/class-divi-gf-swap-mapper.php';

$fixtures_dir = __DIR__ . '/fixtures';
$files        = glob( $fixtures_dir . '/*.json' );
if ( ! is_array( $files ) || empty( $files ) ) {
	fwrite( STDERR, "No fixtures found in {$fixtures_dir}\n" );
	exit( 1 );
}

$failed = 0;
$passed = 0;

foreach ( $files as $file ) {
	$raw = file_get_contents( $file );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
	if ( ! is_array( $data ) || ! isset( $data['input'], $data['expect'], $data['name'] ) ) {
		fwrite( STDERR, "Invalid fixture: {$file}\n" );
		++$failed;
		continue;
	}

	$name   = (string) $data['name'];
	$result = Divi_GF_Swap_Mapper::map( $data['input'] );
	$attrs  = $result['attrs'];
	$expect = $data['expect'];
	$errors = array();

	$form_id = $attrs['gravityForm']['innerContent']['desktop']['value']['formId'] ?? null;
	$use_ajax = $attrs['gravityForm']['advanced']['useAjax']['desktop']['value'] ?? null;

	if ( isset( $expect['formId'] ) && $form_id !== $expect['formId'] ) {
		$errors[] = "formId expected {$expect['formId']}, got " . var_export( $form_id, true );
	}
	if ( isset( $expect['useAjax'] ) && $use_ajax !== $expect['useAjax'] ) {
		$errors[] = "useAjax expected {$expect['useAjax']}, got " . var_export( $use_ajax, true );
	}

	if ( ! empty( $expect['mappedContains'] ) && is_array( $expect['mappedContains'] ) ) {
		foreach ( $expect['mappedContains'] as $needle ) {
			if ( ! in_array( $needle, $result['mapped'], true ) ) {
				$errors[] = "mapped missing {$needle}";
			}
		}
	}

	if ( ! empty( $expect['skippedContains'] ) && is_array( $expect['skippedContains'] ) ) {
		foreach ( $expect['skippedContains'] as $needle ) {
			if ( ! in_array( $needle, $result['skipped'], true ) ) {
				$errors[] = "skipped missing {$needle}";
			}
		}
	}

	if ( ! empty( $expect['warningsContains'] ) && is_array( $expect['warningsContains'] ) ) {
		foreach ( $expect['warningsContains'] as $needle ) {
			if ( ! in_array( $needle, $result['warnings'], true ) ) {
				$errors[] = "warnings missing {$needle}";
			}
		}
	}

	if ( ! empty( $expect['warningsEmpty'] ) && ! empty( $result['warnings'] ) ) {
		$errors[] = 'warnings expected empty, got ' . implode( ',', $result['warnings'] );
	}

	if ( isset( $expect['titleSize'] ) ) {
		$size = $attrs['title']['decoration']['font']['font']['desktop']['value']['size'] ?? null;
		if ( $size !== $expect['titleSize'] ) {
			$errors[] = "titleSize expected {$expect['titleSize']}, got " . var_export( $size, true );
		}
	}

	if ( isset( $expect['labelFontSize'] ) ) {
		$size = $attrs['field']['decoration']['labelFont']['font']['desktop']['value']['size'] ?? null;
		if ( $size !== $expect['labelFontSize'] ) {
			$errors[] = "labelFontSize expected {$expect['labelFontSize']}, got " . var_export( $size, true );
		}
	}

	if ( isset( $expect['inputBgNormalized'] ) ) {
		$bg = $attrs['field']['decoration']['background']['desktop']['value']['color'] ?? null;
		if ( $bg !== $expect['inputBgNormalized'] ) {
			$errors[] = 'inputBgNormalized expected ' . $expect['inputBgNormalized'] . ', got ' . var_export( $bg, true );
		}
	}

	if ( isset( $expect['buttonBg'] ) ) {
		$bg = $attrs['button']['decoration']['background']['desktop']['value']['color'] ?? null;
		if ( $bg !== $expect['buttonBg'] ) {
			$errors[] = 'buttonBg expected ' . $expect['buttonBg'] . ', got ' . var_export( $bg, true );
		}
	}

	if ( isset( $expect['cssFieldLabel'] ) ) {
		$css = $attrs['css']['desktop']['fieldLabel'] ?? null;
		if ( $css !== $expect['cssFieldLabel'] ) {
			$errors[] = 'cssFieldLabel mismatch';
		}
	}

	if ( isset( $expect['cssButton'] ) ) {
		$css = $attrs['css']['desktop']['button'] ?? null;
		if ( $css !== $expect['cssButton'] ) {
			$errors[] = 'cssButton mismatch';
		}
	}

	if ( empty( $errors ) ) {
		echo "PASS {$name}\n";
		++$passed;
	} else {
		echo "FAIL {$name}\n";
		foreach ( $errors as $error ) {
			echo "  - {$error}\n";
		}
		++$failed;
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
