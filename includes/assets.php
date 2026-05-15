<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'cmbwc_enqueue_assets' );

function cmbwc_enqueue_assets() {
	$css_file       = CMBWC_PATH . 'assets/css/frontend.css';
	$js_file        = CMBWC_PATH . 'assets/js/frontend.js';
	$blocks_js_file = CMBWC_PATH . 'assets/js/blocks-integration.js';

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

	if ( file_exists( $blocks_js_file ) ) {
		$blocks_js_ver = filemtime( $blocks_js_file );
		$dependencies  = array();

		/*
		 * WooCommerce Blocks exposes window.wc.blocksCheckout through this script.
		 * It should normally be registered on pages using Cart/Checkout Blocks.
		 */
		if ( wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			$dependencies[] = 'wc-blocks-checkout';
		}

		if ( wp_script_is( 'wp-element', 'registered' ) ) {
			$dependencies[] = 'wp-element';
		}

		if ( wp_script_is( 'wp-html-entities', 'registered' ) ) {
			$dependencies[] = 'wp-html-entities';
		}

		wp_enqueue_script(
			'cmbwc-blocks-integration',
			CMBWC_URL . 'assets/js/blocks-integration.js',
			array_unique( $dependencies ),
			$blocks_js_ver,
			true
		);
	}
}
