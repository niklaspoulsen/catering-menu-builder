<?php
/**
 * Plugin Name: Catering Menu Builder
 * Description: Custom catering menu builder for WooCommerce.
 * Version: 0.1.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMBWC_FILE', __FILE__ );
define( 'CMBWC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMBWC_URL', plugin_dir_url( __FILE__ ) );
define( 'CMBWC_VERSION', '0.1.0' );

require_once CMBWC_PATH . 'includes/bootstrap.php';
