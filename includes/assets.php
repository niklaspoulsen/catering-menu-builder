<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'cmbwc_enqueue_assets' );

function cmbwc_enqueue_assets() {
	$css_file = CMBWC_PATH . 'assets/css/frontend.css';
	$js_file  = CMBWC_PATH . 'assets/js/frontend.js';

	$css_ver = file_exists( $css_file ) ? filemtime( $css_file ) : CMBWC_VERSION;
	$js_ver  = file_exists( $js_file ) ? filemtime( $js_file ) : CMBWC_VERSION;

	wp_enqueue_style(
		'cmbwc-frontend',
		CMBWC_URL . 'assets/css/frontend.css',
		array(),
		$css_ver
	);

	wp_enqueue_script(
		'cmbwc-frontend',
		CMBWC_URL . 'assets/js/frontend.js',
		array( 'jquery' ),
		$js_ver,
		true
	);
}
