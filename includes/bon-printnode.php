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

	// Først split på linjeskift.
	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$clean = [];

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		// Hvis linjen er meget lang og kommasepareret, så split yderligere.
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

		$included = cmbwc_bon_split_lines( $item->get_meta( 'Indhold', true ) );
		$addons   = cmbwc_bon_split_lines( $item->get_meta( 'Tilvalg', true ) );

		$items[] = [
			'name'       => $item->get_name(),
			'qty'        => (int) $item->get_quantity(),
			'covers'     => $item->get_meta( 'Kuverter', true ),
			'included'   => $included,
			'addons'     => $addons,
			'service'    => $item->get_meta( 'Service', true ),
			'line_total' => (float) $item->get_total() + (float) $item->get_total_tax(),
		];
	}

	$customer = trim( $order->get_formatted_billing_full_name() );
	if ( '' === $customer ) {
		$customer = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
	}

	return [
		'order_id'         => $order->get_id(),
		'order_number'     => $order->get_order_number(),
		'created'          => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
		'delivery_date'    => (string) $order->get_meta( '_delivery_date' ),
		'delivery_time'    => (string) $order->get_meta( '_delivery_time' ),
		'customer'         => $customer,
		'company'          => (string) $order->get_billing_company(),
		'phone'            => (string) $order->get_billing_phone(),
		'shipping_method'  => (string) $order->get_shipping_method(),
		'payment_method'   => (string) $order->get_payment_method_title(),
		'order_note'       => (string) $order->get_customer_note(),
		'subtotal'         => (float) $order->get_subtotal(),
		'shipping_total'   => (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
		'fees_total'       => (float) $order->get_total_fees(),
		'total_tax'        => (float) $order->get_total_tax(),
		'discount_total'   => (float) $order->get_discount_total(),
		'grand_total'      => (float) $order->get_total(),
		'items'            => $items,
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
 * Preview åbner i ny fane.
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
 * Ekstra "Vis BON" knap på ordresiden, som åbner i ny fane.
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'cmbwc_add_preview_button_on_order_edit' );

function cmbwc_add_preview_button_on_order_edit( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
		'cmbwc_preview_bon_' . $order->get_id()
	);

	echo '<a href="' . esc_url( $url ) . '" target="_blank" class="button" style="margin-left:8px;">Vis BON</a>';
}
