<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'cmbwc_bootstrap' );

function cmbwc_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once CMBWC_PATH . 'includes/product-admin.php';
	require_once CMBWC_PATH . 'includes/assets.php';
	require_once CMBWC_PATH . 'includes/shortcodes.php';
}
