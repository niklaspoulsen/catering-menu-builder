<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cmbwc_get_service_option_by_key( $service_key ) {
	$all = function_exists( 'cmbwc_get_service_options' ) ? cmbwc_get_service_options() : array();

	if ( empty( $all[ $service_key ] ) || ! is_array( $all[ $service_key ] ) ) {
		return null;
	}

	return $all[ $service_key ];
}

function cmbwc_get_included_names_for_product( $product_id ) {
	$included_products = get_post_meta( $product_id, '_cmbwc_included_products', true );

	if ( ! is_array( $included_products ) ) {
		return array();
	}

	$included_names = array();

	foreach ( $included_products as $included_id ) {
		$included_id      = absint( $included_id );
		$included_product = wc_get_product( $included_id );

		if ( ! $included_product || 'publish' !== get_post_status( $included_id ) ) {
			continue;
		}

		$included_names[] = $included_product->get_name();
	}

	return $included_names;
}

function cmbwc_parse_selected_addons_from_request() {
	if ( empty( $_POST['cmbwc_selected_addons'] ) ) {
		return array();
	}

	$raw  = wp_unslash( $_POST['cmbwc_selected_addons'] );
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) ) {
		return array();
	}

	$parsed = array();

	foreach ( $data as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$id     = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$qty    = isset( $row['qty'] ) ? absint( $row['qty'] ) : 0;
		$follow = isset( $row['follow_covers'] ) && 'yes' === $row['follow_covers'] ? 'yes' : 'no';

		if ( ! $id || $qty < 1 ) {
			continue;
		}

		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== get_post_status( $id ) ) {
			continue;
		}

		$parsed[] = array(
			'id'            => $id,
			'name'          => $product->get_name(),
			'price'         => (float) $product->get_price(),
			'qty'           => $qty,
			'follow_covers' => $follow,
		);
	}

	return $parsed;
}

add_filter( 'woocommerce_add_to_cart_validation', 'cmbwc_validate_add_to_cart', 10, 3 );

function cmbwc_validate_add_to_cart( $passed, $product_id, $quantity ) {
	if ( 'yes' !== get_post_meta( $product_id, '_cmbwc_is_menu', true ) ) {
		return $passed;
	}

	$covers         = isset( $_POST['cmbwc_covers'] ) ? absint( wp_unslash( $_POST['cmbwc_covers'] ) ) : 0;
	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$cover_step     = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}
	if ( $cover_step < 1 ) {
		$cover_step = 1;
	}

	if ( $covers < $minimum_covers ) {
		wc_add_notice( sprintf( 'Minimum antal kuverter er %d.', $minimum_covers ), 'error' );
		return false;
	}

	if ( $cover_step > 1 ) {
		$diff = $covers - $minimum_covers;
		if ( $diff % $cover_step !== 0 ) {
			wc_add_notice( sprintf( 'Kuvertantal skal følge interval på %d.', $cover_step ), 'error' );
			return false;
		}
	}

	return $passed;
}

add_filter( 'woocommerce_add_cart_item_data', 'cmbwc_add_cart_item_data', 10, 3 );

function cmbwc_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
	if ( 'yes' !== get_post_meta( $product_id, '_cmbwc_is_menu', true ) ) {
		return $cart_item_data;
	}

	$covers         = isset( $_POST['cmbwc_covers'] ) ? absint( wp_unslash( $_POST['cmbwc_covers'] ) ) : 1;
	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	if ( $covers < $minimum_covers ) {
		$covers = $minimum_covers;
	}

	$allowed_addons_meta = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
	if ( ! is_array( $allowed_addons_meta ) ) {
		$allowed_addons_meta = array();
	}

	$selected_addons = cmbwc_parse_selected_addons_from_request();
	$filtered_addons = array();

	foreach ( $selected_addons as $addon ) {
		$addon_id = $addon['id'];

		if ( empty( $allowed_addons_meta[ $addon_id ] ) ) {
			continue;
		}

		$follow_covers = isset( $allowed_addons_meta[ $addon_id ]['follow_covers'] ) && 'yes' === $allowed_addons_meta[ $addon_id ]['follow_covers'] ? 'yes' : 'no';
		$addon['follow_covers'] = $follow_covers;

		if ( 'yes' === $follow_covers ) {
			$addon['qty'] = $covers;
		}

		$filtered_addons[] = $addon;
	}

	$selected_service = isset( $_POST['cmbwc_selected_service'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_selected_service'] ) ) : '';
	$allowed_services = get_post_meta( $product_id, '_cmbwc_service_allowed', true );

	if ( ! is_array( $allowed_services ) ) {
		$allowed_services = array();
	}

	if ( ! in_array( $selected_service, $allowed_services, true ) ) {
		$selected_service = '';
	}

	$service_data   = $selected_service ? cmbwc_get_service_option_by_key( $selected_service ) : null;
	$included_names = cmbwc_get_included_names_for_product( $product_id );

	$cart_item_data['cmbwc_data'] = array(
		'is_menu'          => 'yes',
		'covers'           => $covers,
		'selected_service' => $selected_service,
		'service_data'     => $service_data,
		'selected_addons'  => $filtered_addons,
		'included_names'   => $included_names,
	);

	$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data['cmbwc_data'] ) . microtime() );

	return $cart_item_data;
}

add_filter( 'woocommerce_get_cart_item_from_session', 'cmbwc_get_cart_item_from_session', 10, 3 );

function cmbwc_get_cart_item_from_session( $cart_item, $values, $key ) {
	if ( ! empty( $values['cmbwc_data'] ) ) {
		$cart_item['cmbwc_data'] = $values['cmbwc_data'];
	}

	return $cart_item;
}

add_filter( 'woocommerce_get_item_data', 'cmbwc_display_cart_item_data', 10, 2 );

function cmbwc_display_cart_item_data( $item_data, $cart_item ) {
	if ( empty( $cart_item['cmbwc_data'] ) || ! is_array( $cart_item['cmbwc_data'] ) ) {
		return $item_data;
	}

	$data = $cart_item['cmbwc_data'];

	if ( ! empty( $data['covers'] ) ) {
		$item_data[] = array(
			'key'   => 'Kuverter',
			'value' => (string) absint( $data['covers'] ),
		);
	}

	if ( ! empty( $data['included_names'] ) && is_array( $data['included_names'] ) ) {
		$item_data[] = array(
			'key'   => 'Indhold',
			'value' => implode( ', ', $data['included_names'] ),
		);
	}

	if ( ! empty( $data['selected_addons'] ) && is_array( $data['selected_addons'] ) ) {
		$addon_lines = array();

		foreach ( $data['selected_addons'] as $addon ) {
			$addon_lines[] = $addon['name'] . ' × ' . absint( $addon['qty'] );
		}

		$item_data[] = array(
			'key'   => 'Tilvalg',
			'value' => implode( ', ', $addon_lines ),
		);
	}

	if ( ! empty( $data['service_data'] ) && is_array( $data['service_data'] ) && ! empty( $data['service_data']['label'] ) ) {
		$item_data[] = array(
			'key'   => 'Service',
			'value' => $data['service_data']['label'],
		);
	}

	return $item_data;
}

add_action( 'woocommerce_before_calculate_totals', 'cmbwc_before_calculate_totals', 100 );

function cmbwc_before_calculate_totals( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( empty( $cart_item['cmbwc_data'] ) || ! is_array( $cart_item['cmbwc_data'] ) ) {
			continue;
		}

		if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
			continue;
		}

		$data    = $cart_item['cmbwc_data'];
		$product = $cart_item['data'];

		$base_price = (float) $product->get_price();
		$covers     = ! empty( $data['covers'] ) ? absint( $data['covers'] ) : 1;

		$addons_total = 0;
		if ( ! empty( $data['selected_addons'] ) && is_array( $data['selected_addons'] ) ) {
			foreach ( $data['selected_addons'] as $addon ) {
				$addon_price = isset( $addon['price'] ) ? (float) $addon['price'] : 0;
				$addon_qty   = isset( $addon['qty'] ) ? absint( $addon['qty'] ) : 0;
				$addons_total += $addon_price * $addon_qty;
			}
		}

		$service_total = 0;
		if ( ! empty( $data['service_data'] ) && is_array( $data['service_data'] ) ) {
			$service_price = isset( $data['service_data']['price'] ) ? (float) $data['service_data']['price'] : 0;
			$service_type  = isset( $data['service_data']['price_type'] ) ? $data['service_data']['price_type'] : 'fixed';

			if ( 'per_cover' === $service_type ) {
				$service_total = $service_price * $covers;
			} else {
				$service_total = $service_price;
			}
		}

		$total_price = ( $base_price * $covers ) + $addons_total + $service_total;

		$cart->cart_contents[ $cart_item_key ]['quantity'] = 1;
		$product->set_price( $total_price );
	}
}

add_action( 'woocommerce_checkout_create_order_line_item', 'cmbwc_add_order_item_meta', 10, 4 );

function cmbwc_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['cmbwc_data'] ) || ! is_array( $values['cmbwc_data'] ) ) {
		return;
	}

	$data = $values['cmbwc_data'];

	if ( ! empty( $data['covers'] ) ) {
		$item->add_meta_data( 'Kuverter', absint( $data['covers'] ) );
	}

	if ( ! empty( $data['included_names'] ) && is_array( $data['included_names'] ) ) {
		$item->add_meta_data( 'Indhold', implode( ', ', $data['included_names'] ) );
	}

	if ( ! empty( $data['selected_addons'] ) && is_array( $data['selected_addons'] ) ) {
		$addon_lines = array();

		foreach ( $data['selected_addons'] as $addon ) {
			$addon_lines[] = $addon['name'] . ' × ' . absint( $addon['qty'] );
		}

		$item->add_meta_data( 'Tilvalg', implode( ', ', $addon_lines ) );
	}

	if ( ! empty( $data['service_data'] ) && is_array( $data['service_data'] ) && ! empty( $data['service_data']['label'] ) ) {
		$item->add_meta_data( 'Service', $data['service_data']['label'] );
	}
}
