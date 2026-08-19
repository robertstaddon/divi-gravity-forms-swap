<?php
/**
 * Plugin Name:       Divi Gravity Forms Swap
 * Plugin URI:        https://github.com/robertstaddon/divi-gravity-forms-swap
 * Description:       Scan posts, pages, and Divi layouts for WP Tools Gravity Forms Divi 5 modules and replace them with the native Divi 5 Gravity Forms module.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
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

define( 'DIVI_GF_SWAP_VERSION', '1.0.0' );
define( 'DIVI_GF_SWAP_FILE', __FILE__ );
define( 'DIVI_GF_SWAP_PATH', plugin_dir_path( __FILE__ ) );

require_once DIVI_GF_SWAP_PATH . 'includes/class-divi-gf-swap-content.php';
require_once DIVI_GF_SWAP_PATH . 'includes/class-divi-gf-swap-admin.php';

/**
 * Bootstrap the plugin.
 */
function divi_gf_swap_init(): void {
	load_plugin_textdomain(
		'divi-gravity-forms-swap',
		false,
		dirname( plugin_basename( DIVI_GF_SWAP_FILE ) ) . '/languages'
	);

	if ( is_admin() ) {
		Divi_GF_Swap_Admin::init();
	}
}
add_action( 'plugins_loaded', 'divi_gf_swap_init' );
