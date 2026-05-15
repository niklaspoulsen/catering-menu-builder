<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'cmbwc_enqueue_assets' );

function cmbwc_get_frontend_delivery_value( $keys ) {
	foreach ( $keys as $key ) {
		$value = '';

		if ( class_exists( 'WCR_Session' ) && method_exists( 'WCR_Session', 'get_session' ) ) {
			$value = WCR_Session::get_session( $key, '' );
		}

		if ( '' === trim( (string) $value ) && function_exists( 'WC' ) && WC()->session ) {
			$value = WC()->session->get( $key );
		}

		$value = trim( (string) $value );

		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

function cmbwc_get_frontend_delivery_state() {
	$date = cmbwc_get_frontend_delivery_value(
		array(
			'wcr_delivery_date',
			'delivery_date',
			'_delivery_date',
		)
	);

	$time = cmbwc_get_frontend_delivery_value(
		array(
			'wcr_delivery_time',
			'delivery_time',
			'_delivery_time',
		)
	);

	return array(
		'deliveryDate'      => $date,
		'deliveryTime'      => $time,
		'hasDeliveryChoice' => ( '' !== $date && '' !== $time ),
		'deliveryPopupSelectors' => array(
			'#wcr-open-modal',
			'.wcr-floating-button',
			'.wcr-open-modal',
			'[data-wcr-open-modal]',
			'[data-open-wcr-modal]',
		),
		'messages' => array(
			'chooseDeliveryFirst' => 'Vælg venligst dato og tidspunkt først.',
		),
	);
}

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

	wp_localize_script(
		'cmbwc-frontend',
		'cmbwcFrontend',
		cmbwc_get_frontend_delivery_state()
	);

	if ( file_exists( $blocks_js_file ) ) {
		$blocks_js_ver = filemtime( $blocks_js_file );
		$dependencies  = array();

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
