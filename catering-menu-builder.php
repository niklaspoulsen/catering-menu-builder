<?php
/**
 * Plugin Name: Catering Menu Builder
 * Description: Custom catering menu builder for WooCommerce.
 * Version: 1.0.0
 * Author: Niklas Poulsen
 * Text Domain: catering-menu-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMBWC_FILE', __FILE__ );
define( 'CMBWC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMBWC_URL', plugin_dir_url( __FILE__ ) );
define( 'CMBWC_VERSION', '1.0.0' );

function cmbwc_activate_plugin() {
	if ( file_exists( CMBWC_PATH . 'includes/frontend-production-app.php' ) ) {
		require_once CMBWC_PATH . 'includes/frontend-production-app.php';
	}

	if ( function_exists( 'cmbwc_register_production_app_rewrite' ) ) {
		cmbwc_register_production_app_rewrite();
	}

	flush_rewrite_rules();
}

function cmbwc_deactivate_plugin() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'cmbwc_activate_plugin' );
register_deactivation_hook( __FILE__, 'cmbwc_deactivate_plugin' );

require_once CMBWC_PATH . 'includes/bootstrap.php';
