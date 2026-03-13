<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

	$items = [];

	foreach ( $order->get_items() as $item_id => $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			continue;
		}

		$included = (string) $item->get_meta( 'Indhold', true );
		$addons   = (string) $item->get_meta( 'Tilvalg', true );

		$included_lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $included ) ) );
		$addons_lines   = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $addons ) ) );

		$items[] = [
			'name'     => $item->get_name(),
			'covers'   => $item->get_meta( 'Kuverter', true ),
			'included' => $included_lines,
			'addons'   => $addons_lines,
			'service'  => $item->get_meta( 'Service', true ),
		];
	}

	$customer = trim( $order->get_formatted_billing_full_name() );
	if ( '' === $customer ) {
		$customer = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
	}

	return [
		'order_number'    => $order->get_order_number(),
		'created'         => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
		'delivery_date'   => (string) $order->get_meta( '_delivery_date' ),
		'delivery_time'   => (string) $order->get_meta( '_delivery_time' ),
		'customer'        => $customer,
		'company'         => (string) $order->get_billing_company(),
		'phone'           => (string) $order->get_billing_phone(),
		'shipping_method' => (string) $order->get_shipping_method(),
		'order_note'      => (string) $order->get_customer_note(),
		'items'           => $items,
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
 * Tilføj ordre-actions på enkelt ordre.
 */
add_filter( 'woocommerce_order_actions', 'cmbwc_add_order_actions' );

function cmbwc_add_order_actions( $actions ) {
	$actions['cmbwc_preview_bon'] = 'Vis BON';
	$actions['cmbwc_print_bon']   = 'Print BON';

	return $actions;
}

/**
 * Håndter "Vis BON" action.
 */
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

/**
 * Håndter "Print BON" action.
 */
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
 * Preview BON via URL.
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
 * Ekstra action-knapper i ordrelisten.
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
