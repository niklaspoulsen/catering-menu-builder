<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cmbwc_surcharge_parse_date_to_ymd' ) ) {
	function cmbwc_surcharge_parse_date_to_ymd( $date ) {
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return '';
		}

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			if ( checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
			}
		}

		if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m ) ) {
			if ( checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
				return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cmbwc_get_selected_delivery_date_ymd' ) ) {
	function cmbwc_get_selected_delivery_date_ymd() {
		if ( class_exists( 'WCR_Session' ) && method_exists( 'WCR_Session', 'get_session' ) ) {
			$date = WCR_Session::get_session( 'wcr_delivery_date', '' );

			if ( ! empty( $date ) ) {
				return cmbwc_surcharge_parse_date_to_ymd( $date );
			}
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$date = WC()->session->get( 'wcr_delivery_date' );
			if ( ! empty( $date ) ) {
				return cmbwc_surcharge_parse_date_to_ymd( $date );
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cmbwc_get_selected_shipping_kind' ) ) {
	function cmbwc_get_selected_shipping_kind() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return 'delivery';
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
			return 'delivery';
		}

		$method = strtolower( (string) reset( $chosen_methods ) );

		$pickup_keywords = array(
			'local_pickup',
			'local pickup',
			'pickup',
			'afhent',
			'afhentning',
		);

		foreach ( $pickup_keywords as $keyword ) {
			if ( false !== strpos( $method, strtolower( $keyword ) ) ) {
				return 'pickup';
			}
		}

		return 'delivery';
	}
}

if ( ! function_exists( 'cmbwc_get_weekday_surcharge_amount' ) ) {
	function cmbwc_get_weekday_surcharge_amount( $shipping_kind, $delivery_date_ymd ) {
		$delivery_date_ymd = cmbwc_surcharge_parse_date_to_ymd( $delivery_date_ymd );

		if ( '' === $delivery_date_ymd ) {
			return 0;
		}

		$settings = function_exists( 'cmbwc_get_surcharge_settings' ) ? cmbwc_get_surcharge_settings() : array();
		$weekday  = (string) date( 'w', strtotime( $delivery_date_ymd ) );

		if ( 'pickup' === $shipping_kind ) {
			return isset( $settings['pickup_weekdays'][ $weekday ] ) ? (float) $settings['pickup_weekdays'][ $weekday ] : 0;
		}

		return isset( $settings['delivery_weekdays'][ $weekday ] ) ? (float) $settings['delivery_weekdays'][ $weekday ] : 0;
	}
}

if ( ! function_exists( 'cmbwc_get_special_date_surcharge_rows' ) ) {
	function cmbwc_get_special_date_surcharge_rows( $shipping_kind, $delivery_date_ymd ) {
		$delivery_date_ymd = cmbwc_surcharge_parse_date_to_ymd( $delivery_date_ymd );

		if ( '' === $delivery_date_ymd ) {
			return array();
		}

		$settings = function_exists( 'cmbwc_get_surcharge_settings' ) ? cmbwc_get_surcharge_settings() : array();
		$rows     = ! empty( $settings['special_dates'] ) && is_array( $settings['special_dates'] ) ? $settings['special_dates'] : array();
		$matches  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$date = isset( $row['date'] ) ? cmbwc_surcharge_parse_date_to_ymd( $row['date'] ) : '';
			if ( '' === $date || $date !== $delivery_date_ymd ) {
				continue;
			}

			if ( empty( $row['enabled'] ) || 'yes' !== $row['enabled'] ) {
				continue;
			}

			$amount = 'pickup' === $shipping_kind
				? ( isset( $row['pickup_fee'] ) ? (float) $row['pickup_fee'] : 0 )
				: ( isset( $row['delivery_fee'] ) ? (float) $row['delivery_fee'] : 0 );

			if ( $amount <= 0 ) {
				continue;
			}

			$matches[] = array(
				'title'  => ! empty( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
				'amount' => $amount,
				'date'   => $date,
			);
		}

		return $matches;
	}
}

add_action( 'woocommerce_cart_calculate_fees', 'cmbwc_add_date_based_surcharges', 30 );

function cmbwc_add_date_based_surcharges( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
		return;
	}

	if ( $cart->is_empty() ) {
		return;
	}

	$delivery_date_ymd = cmbwc_get_selected_delivery_date_ymd();

	if ( '' === $delivery_date_ymd ) {
		return;
	}

	$shipping_kind = cmbwc_get_selected_shipping_kind();

	$weekday_amount = cmbwc_get_weekday_surcharge_amount( $shipping_kind, $delivery_date_ymd );

	if ( $weekday_amount > 0 ) {
		$label = 'pickup' === $shipping_kind ? 'Weekendtillæg (Afhent selv)' : 'Weekendtillæg (Levering)';
		$cart->add_fee( $label, $weekday_amount, false );
	}

	$special_rows = cmbwc_get_special_date_surcharge_rows( $shipping_kind, $delivery_date_ymd );

	foreach ( $special_rows as $row ) {
		$label = ! empty( $row['title'] )
			? sprintf( 'Datogebyr (%s)', $row['title'] )
			: 'Datogebyr';

		$cart->add_fee( $label, (float) $row['amount'], false );
	}
}
