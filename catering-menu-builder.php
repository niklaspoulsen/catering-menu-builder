<?php
/**
 * Plugin Name: Catering Menu Builder
 * Description: Custom catering menu builder for WooCommerce.
 * Version: 1.1.0
 * Author: Niklas Poulsen
 * Text Domain: catering-menu-builder
 * Domain Path: /languages
 *
 * Requires at least: 6.8
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 *
 * WC requires at least: 8.2
 * WC tested up to: 10.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMBWC_FILE', __FILE__ );
define( 'CMBWC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMBWC_URL', plugin_dir_url( __FILE__ ) );
define( 'CMBWC_VERSION', '1.1.0' );

/**
 * Declare WooCommerce feature compatibility.
 *
 * This declaration tells WooCommerce that the plugin is compatible with
 * High-Performance Order Storage (HPOS / custom order tables).
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Load plugin textdomain.
 */
add_action(
	'init',
	function() {
		load_plugin_textdomain(
			'catering-menu-builder',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
);

/**
 * Plugin activation.
 */
function cmbwc_activate_plugin() {
	if ( file_exists( CMBWC_PATH . 'includes/frontend-production-app.php' ) ) {
		require_once CMBWC_PATH . 'includes/frontend-production-app.php';
	}

	if ( function_exists( 'cmbwc_register_production_app_rewrite' ) ) {
		cmbwc_register_production_app_rewrite();
	}

	flush_rewrite_rules();
}

/**
 * Plugin deactivation.
 */
function cmbwc_deactivate_plugin() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'cmbwc_activate_plugin' );
register_deactivation_hook( __FILE__, 'cmbwc_deactivate_plugin' );

require_once CMBWC_PATH . 'includes/bootstrap.php';
