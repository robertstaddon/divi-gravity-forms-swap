<?php
/**
 * Plugin Name:       Divi Gravity Forms Swap
 * Plugin URI:        https://github.com/robertstaddon/divi-gravity-forms-swap
 * Description:       Scan posts, pages, and Divi layouts for WP Tools Gravity Forms Divi 5 modules and replace them with the native Divi 5 Gravity Forms module.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  gravityforms
 * Author:            Abundant Designs
 * Author URI:        https://abundantdesigns.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       divi-gravity-forms-swap
 *
 * @package Divi_GF_Swap
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIVI_GF_SWAP_VERSION', '1.0.3' );
define( 'DIVI_GF_SWAP_FILE', __FILE__ );
define( 'DIVI_GF_SWAP_PATH', plugin_dir_path( __FILE__ ) );

require_once DIVI_GF_SWAP_PATH . 'includes/class-divi-gf-swap-content.php';
require_once DIVI_GF_SWAP_PATH . 'includes/class-divi-gf-swap-admin.php';

/**
 * Whether Divi theme or the Divi Builder plugin is active.
 */
function divi_gf_swap_has_divi(): bool {
	if ( function_exists( 'get_template' ) && 'Divi' === get_template() ) {
		return true;
	}

	return defined( 'ET_BUILDER_PLUGIN_ACTIVE' );
}

/**
 * Whether Gravity Forms is active.
 */
function divi_gf_swap_has_gravity_forms(): bool {
	return class_exists( 'GFForms' );
}

/**
 * Whether all runtime dependencies are present.
 */
function divi_gf_swap_dependencies_met(): bool {
	return divi_gf_swap_has_divi() && divi_gf_swap_has_gravity_forms();
}

/**
 * Human-readable names of missing dependencies.
 *
 * @return array<int, string>
 */
function divi_gf_swap_missing_dependency_labels(): array {
	$missing = array();

	if ( ! divi_gf_swap_has_divi() ) {
		$missing[] = __( 'Divi (theme or Divi Builder plugin)', 'divi-gravity-forms-swap' );
	}

	if ( ! divi_gf_swap_has_gravity_forms() ) {
		$missing[] = __( 'Gravity Forms', 'divi-gravity-forms-swap' );
	}

	return $missing;
}

/**
 * Admin notice when Divi or Gravity Forms is missing.
 */
function divi_gf_swap_missing_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$missing = divi_gf_swap_missing_dependency_labels();
	if ( empty( $missing ) ) {
		return;
	}

	$list = implode( ', ', $missing );

	echo '<div class="notice notice-warning"><p>';
	echo esc_html(
		sprintf(
			/* translators: %s: comma-separated list of missing products */
			__( 'Gravity Forms Swap requires Divi (theme or Divi Builder plugin) and Gravity Forms. Missing: %s.', 'divi-gravity-forms-swap' ),
			$list
		)
	);
	echo '</p></div>';
}

/**
 * Bootstrap the plugin.
 */
function divi_gf_swap_init(): void {
	load_plugin_textdomain(
		'divi-gravity-forms-swap',
		false,
		dirname( plugin_basename( DIVI_GF_SWAP_FILE ) ) . '/languages'
	);

	if ( ! divi_gf_swap_dependencies_met() ) {
		add_action( 'admin_notices', 'divi_gf_swap_missing_notice' );
		return;
	}

	if ( is_admin() ) {
		Divi_GF_Swap_Admin::init();
	}
}
add_action( 'plugins_loaded', 'divi_gf_swap_init' );
