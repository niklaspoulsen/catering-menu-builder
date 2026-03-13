<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Splitter tekst til BON-linjer.
 * Understøtter både linjeskift og kommaseparerede værdier.
 */
function cmbwc_bon_split_lines( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return [];
	}

	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$clean = [];

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		if ( strpos( $line, ',' ) !== false ) {
			$parts = array_map( 'trim', explode( ',', $line ) );

			foreach ( $parts as $part ) {
				if ( '' !== $part ) {
					$clean[] = $part;
				}
			}
		} else {
			$clean[] = $line;
		}
	}

	return array_values( array_filter( $clean ) );
}

/**
 * Formatter Woo-pris.
 */
function cmbwc_bon_price( $amount, $order = null ) {
	$amount = (float) $amount;

	if ( $order && is_a( $order, 'WC_Order' ) ) {
		return wc_price( $amount, [ 'currency' => $order->get_currency() ] );
	}

	return wc_price( $amount );
}

/**
 * Finder depositumlinjer.
 * Vi prøver både på produktnavn og meta.
 */
function cmbwc_bon_is_deposit_item( $item ) {
	if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return false;
	}

	$name = mb_strtolower( trim( (string) $item->get_name() ) );

	$keywords = [
		'depositum',
		'pant',
		'service depositum',
		'emballage depositum',
	];

	foreach ( $keywords as $keyword ) {
		if ( false !== strpos( $name, $keyword ) ) {
			return true;
		}
	}

	$meta_checks = [
		'_cmbwc_is_deposit',
		'_is_deposit',
		'depositum',
	];

	foreach ( $meta_checks as $meta_key ) {
		$meta_value = $item->get_meta( $meta_key, true );

		if ( in_array( $meta_value, [ '1', 1, 'yes', 'true', true ], true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Samler alle data til BON.
 */
function cmbwc_get_order_bon_data( $order ) {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return [];
	}

	$items          = [];
	$deposit_items  = [];

	foreach ( $order->get_items() as $item_id => $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			continue;
		}

		$is_deposit = cmbwc_bon_is_deposit_item( $item );

		$item_data = [
			'name'       => $item->get_name(),
			'qty'        => (int) $item->get_quantity(),
			'covers'     => $item->get_meta( 'Kuverter', true ),
			'included'   => cmbwc_bon_split_lines( $item->get_meta( 'Indhold', true ) ),
			'addons'     => cmbwc_bon_split_lines( $item->get_meta( 'Tilvalg', true ) ),
			'service'    => $item->get_meta( 'Service', true ),
			'line_total' => (float) $item->get_total() + (float) $item->get_total_tax(),
		];

		if ( $is_deposit ) {
			$deposit_items[] = $item_data;
		} else {
			$items[] = $item_data;
		}
	}

	$customer = trim( $order->get_formatted_billing_full_name() );
	if ( '' === $customer ) {
		$customer = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
	}

	$shipping_address = $order->get_formatted_shipping_address();
	if ( '' === trim( wp_strip_all_tags( $shipping_address ) ) ) {
		$shipping_address = $order->get_formatted_billing_address();
	}

	return [
		'order_id'          => $order->get_id(),
		'order_number'      => $order->get_order_number(),
		'created'           => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
		'delivery_date'     => (string) $order->get_meta( '_delivery_date' ),
		'delivery_time'     => (string) $order->get_meta( '_delivery_time' ),
		'customer'          => $customer,
		'company'           => (string) $order->get_billing_company(),
		'phone'             => (string) $order->get_billing_phone(),
		'shipping_method'   => (string) $order->get_shipping_method(),
		'shipping_address'  => $shipping_address,
		'payment_method'    => (string) $order->get_payment_method_title(),
		'order_note'        => (string) $order->get_customer_note(),
		'subtotal'          => (float) $order->get_subtotal(),
		'shipping_total'    => (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
		'fees_total'        => (float) $order->get_total_fees(),
		'total_tax'         => (float) $order->get_total_tax(),
		'discount_total'    => (float) $order->get_discount_total(),
		'grand_total'       => (float) $order->get_total(),
		'items'             => $items,
		'deposit_items'     => $deposit_items,
	];
}

/**
 * PrintNode template override.
 */
add_filter( 'woocommerce_printorders_printnode_print_template', 'cmbwc_printnode_template_override', 10, 2 );

function cmbwc_printnode_template_override( $template, $order = null ) {
	$custom = CMBWC_PATH . 'templates/printnode-bon.php';

	if ( file_exists( $custom ) ) {
		return $custom;
	}

	return $template;
}

/**
 * Ordre-actions dropdown.
 */
add_filter( 'woocommerce_order_actions', 'cmbwc_add_order_actions' );

function cmbwc_add_order_actions( $actions ) {
	$actions['cmbwc_preview_bon'] = 'Vis BON';
	$actions['cmbwc_print_bon']   = 'Print BON';

	return $actions;
}

add_action( 'woocommerce_order_action_cmbwc_preview_bon', 'cmbwc_order_action_preview_bon' );

function cmbwc_order_action_preview_bon( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
		'cmbwc_preview_bon_' . $order->get_id()
	);

	wp_safe_redirect( $url );
	exit;
}

add_action( 'woocommerce_order_action_cmbwc_print_bon', 'cmbwc_order_action_print_bon' );

function cmbwc_order_action_print_bon( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	global $woocommerce_simba_printorders_printnode;

	if ( is_object( $woocommerce_simba_printorders_printnode ) && method_exists( $woocommerce_simba_printorders_printnode, 'woocommerce_print_order_go' ) ) {
		$woocommerce_simba_printorders_printnode->woocommerce_print_order_go( $order->get_id() );
	}
}

/**
 * Preview-side.
 */
add_action( 'admin_post_cmbwc_preview_bon', 'cmbwc_preview_bon_page' );

function cmbwc_preview_bon_page() {
	if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Ingen adgang.' );
	}

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	if ( ! $order_id ) {
		wp_die( 'Manglende ordre-id.' );
	}

	check_admin_referer( 'cmbwc_preview_bon_' . $order_id );

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		wp_die( 'Ordre ikke fundet.' );
	}

	echo '<!doctype html><html><head><meta charset="utf-8"><title>BON preview</title></head><body>';
	include CMBWC_PATH . 'templates/printnode-bon.php';
	echo '</body></html>';
	exit;
}

/**
 * Knapper i ordrelisten.
 * Bemærk: target virker ikke altid i WooCommerce's renderer,
 * men vi sætter det på hvor muligt.
 */
add_filter( 'woocommerce_admin_order_actions', 'cmbwc_add_list_table_order_actions', 20, 2 );

function cmbwc_add_list_table_order_actions( $actions, $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return $actions;
	}

	$preview_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
		'cmbwc_preview_bon_' . $order->get_id()
	);

	$print_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_manual_print_bon&order_id=' . $order->get_id() ),
		'cmbwc_manual_print_bon_' . $order->get_id()
	);

	$actions['cmbwc_preview_bon'] = [
		'url'    => $preview_url,
		'name'   => 'Vis BON',
		'action' => 'view cmbwc-preview-bon',
		'target' => '_blank',
	];

	$actions['cmbwc_print_bon'] = [
		'url'    => $print_url,
		'name'   => 'Print BON',
		'action' => 'processing cmbwc-print-bon',
	];

	return $actions;
}

/**
 * Manuel print fra ordrelisten.
 */
add_action( 'admin_post_cmbwc_manual_print_bon', 'cmbwc_manual_print_bon_handler' );

function cmbwc_manual_print_bon_handler() {
	if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Ingen adgang.' );
	}

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	if ( ! $order_id ) {
		wp_die( 'Manglende ordre-id.' );
	}

	check_admin_referer( 'cmbwc_manual_print_bon_' . $order_id );

	global $woocommerce_simba_printorders_printnode;

	if ( is_object( $woocommerce_simba_printorders_printnode ) && method_exists( $woocommerce_simba_printorders_printnode, 'woocommerce_print_order_go' ) ) {
		$woocommerce_simba_printorders_printnode->woocommerce_print_order_go( $order_id );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
	exit;
}

/**
 * Rigtig preview-knap på ordresiden.
 * Denne åbner i ny fane.
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'cmbwc_add_preview_button_on_order_edit' );

function cmbwc_add_preview_button_on_order_edit( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$preview_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
		'cmbwc_preview_bon_' . $order->get_id()
	);

	$print_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_manual_print_bon&order_id=' . $order->get_id() ),
		'cmbwc_manual_print_bon_' . $order->get_id()
	);

	echo '<a href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener" class="button" style="margin-left:8px;">Vis BON</a>';
	echo '<a href="' . esc_url( $print_url ) . '" class="button" style="margin-left:8px;">Print BON</a>';
}
