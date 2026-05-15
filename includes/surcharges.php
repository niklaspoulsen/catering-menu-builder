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

		if ( preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $m ) ) {
			if ( checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
				return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
			}
		}

		$timestamp = strtotime( $date );

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( 'Y-m-d', $timestamp );
	}
}

if ( ! function_exists( 'cmbwc_get_selected_delivery_date_ymd' ) ) {
	function cmbwc_get_selected_delivery_date_ymd() {
		$session_keys = array(
			'wcr_delivery_date',
			'delivery_date',
			'_delivery_date',
		);

		foreach ( $session_keys as $key ) {
			$date = '';

			if ( class_exists( 'WCR_Session' ) && method_exists( 'WCR_Session', 'get_session' ) ) {
				$date = WCR_Session::get_session( $key, '' );
			}

			if ( '' === trim( (string) $date ) && function_exists( 'WC' ) && WC()->session ) {
				$date = WC()->session->get( $key );
			}

			$date = cmbwc_surcharge_parse_date_to_ymd( $date );

			if ( '' !== $date ) {
				return $date;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cmbwc_get_selected_shipping_method_key' ) ) {
	function cmbwc_get_selected_shipping_method_key() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
			return '';
		}

		$method_key = (string) reset( $chosen_methods );

		return sanitize_text_field( $method_key );
	}
}

if ( ! function_exists( 'cmbwc_normalize_shipping_method_key' ) ) {
	function cmbwc_normalize_shipping_method_key( $method_key ) {
		$method_key = sanitize_text_field( (string) $method_key );

		if ( '' === $method_key ) {
			return '';
		}

		/*
		 * WooCommerce chosen shipping methods usually look like:
		 * - flat_rate:12
		 * - local_pickup:8
		 * - free_shipping:4
		 *
		 * Keep the full key as primary value.
		 */
		return $method_key;
	}
}

if ( ! function_exists( 'cmbwc_shipping_method_key_matches' ) ) {
	function cmbwc_shipping_method_key_matches( $selected_method, $configured_method ) {
		$selected_method   = cmbwc_normalize_shipping_method_key( $selected_method );
		$configured_method = cmbwc_normalize_shipping_method_key( $configured_method );

		if ( '' === $selected_method || '' === $configured_method ) {
			return false;
		}

		if ( $selected_method === $configured_method ) {
			return true;
		}

		/*
		 * Fallback:
		 * If one side is saved without instance ID, allow matching by method ID.
		 * Example:
		 * selected: local_pickup:8
		 * configured: local_pickup
		 */
		$selected_method_id   = strtok( $selected_method, ':' );
		$configured_method_id = strtok( $configured_method, ':' );

		return $selected_method_id && $configured_method_id && $selected_method_id === $configured_method_id;
	}
}

if ( ! function_exists( 'cmbwc_get_shipping_kind_from_method' ) ) {
	function cmbwc_get_shipping_kind_from_method( $method_key ) {
		$settings = function_exists( 'cmbwc_get_surcharge_settings' ) ? cmbwc_get_surcharge_settings() : array();

		$pickup_methods   = ! empty( $settings['pickup_methods'] ) && is_array( $settings['pickup_methods'] ) ? $settings['pickup_methods'] : array();
		$delivery_methods = ! empty( $settings['delivery_methods'] ) && is_array( $settings['delivery_methods'] ) ? $settings['delivery_methods'] : array();

		foreach ( $pickup_methods as $pickup_method ) {
			if ( cmbwc_shipping_method_key_matches( $method_key, $pickup_method ) ) {
				return 'pickup';
			}
		}

		foreach ( $delivery_methods as $delivery_method ) {
			if ( cmbwc_shipping_method_key_matches( $method_key, $delivery_method ) ) {
				return 'delivery';
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cmbwc_get_weekday_surcharge_amount' ) ) {
	function cmbwc_get_weekday_surcharge_amount( $shipping_kind, $delivery_date_ymd ) {
		$delivery_date_ymd = cmbwc_surcharge_parse_date_to_ymd( $delivery_date_ymd );

		if ( '' === $delivery_date_ymd ) {
			return 0;
		}

		$settings = function_exists( 'cmbwc_get_surcharge_settings' ) ? cmbwc_get_surcharge_settings() : array();
		$weekday  = (string) wp_date( 'w', strtotime( $delivery_date_ymd ) );

		if ( 'pickup' === $shipping_kind ) {
			return isset( $settings['pickup_weekdays'][ $weekday ] ) ? max( 0, (float) $settings['pickup_weekdays'][ $weekday ] ) : 0;
		}

		if ( 'delivery' === $shipping_kind ) {
			return isset( $settings['delivery_weekdays'][ $weekday ] ) ? max( 0, (float) $settings['delivery_weekdays'][ $weekday ] ) : 0;
		}

		return 0;
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

			$amount = 0;

			if ( 'pickup' === $shipping_kind ) {
				$amount = isset( $row['pickup_fee'] ) ? (float) $row['pickup_fee'] : 0;
			} elseif ( 'delivery' === $shipping_kind ) {
				$amount = isset( $row['delivery_fee'] ) ? (float) $row['delivery_fee'] : 0;
			}

			$amount = max( 0, $amount );

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

if ( ! function_exists( 'cmbwc_get_surcharge_weekday_label' ) ) {
	function cmbwc_get_surcharge_weekday_label( $shipping_kind ) {
		if ( 'pickup' === $shipping_kind ) {
			return 'Tillæg (Afhent selv)';
		}

		if ( 'delivery' === $shipping_kind ) {
			return 'Tillæg (Levering)';
		}

		return 'Tillæg';
	}
}

if ( ! function_exists( 'cmbwc_add_date_based_surcharges' ) ) {
	add_action( 'woocommerce_cart_calculate_fees', 'cmbwc_add_date_based_surcharges', 30 );

	function cmbwc_add_date_based_surcharges( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) || $cart->is_empty() ) {
			return;
		}

		$delivery_date_ymd = cmbwc_get_selected_delivery_date_ymd();

		if ( '' === $delivery_date_ymd ) {
			return;
		}

		$method_key = cmbwc_get_selected_shipping_method_key();

		if ( '' === $method_key ) {
			return;
		}

		$shipping_kind = cmbwc_get_shipping_kind_from_method( $method_key );

		if ( '' === $shipping_kind ) {
			return;
		}

		$weekday_amount = cmbwc_get_weekday_surcharge_amount( $shipping_kind, $delivery_date_ymd );

		if ( $weekday_amount > 0 ) {
			$cart->add_fee(
				cmbwc_get_surcharge_weekday_label( $shipping_kind ),
				$weekday_amount,
				false
			);
		}

		$special_rows = cmbwc_get_special_date_surcharge_rows( $shipping_kind, $delivery_date_ymd );

		foreach ( $special_rows as $row ) {
			$label = ! empty( $row['title'] )
				? sprintf( 'Datogebyr (%s)', $row['title'] )
				: 'Datogebyr';

			$cart->add_fee(
				$label,
				(float) $row['amount'],
				false
			);
		}
	}
}
