<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'cmbwc_enqueue_assets' );

function cmbwc_enqueue_assets() {
	wp_enqueue_style(
		'cmbwc-frontend',
		CMBWC_URL . 'assets/css/frontend.css',
		array(),
		CMBWC_VERSION
	);

	wp_enqueue_script(
		'cmbwc-frontend',
		CMBWC_URL . 'assets/js/frontend.js',
		array( 'jquery' ),
		CMBWC_VERSION,
		true
	);
}
