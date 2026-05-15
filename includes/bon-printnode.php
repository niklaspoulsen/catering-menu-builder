<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Splitter tekst til BON-linjer.
 *
 * Vi splitter kun på linjeskift.
 */
function cmbwc_bon_split_lines( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return array();
	}

	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$clean = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		$clean[] = cmbwc_bon_normalize_qty_prefix( $line );
	}

	return array_values( array_filter( $clean ) );
}

/**
 * Sørger for at antal altid står som "2 x Varenavn".
 */
function cmbwc_bon_normalize_qty_prefix( $text ) {
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	if ( preg_match( '/^\d+\s*x\s+/i', $text ) ) {
		return $text;
	}

	if ( preg_match( '/^(.*?)\s*x\s*(\d+)$/i', $text, $matches ) ) {
		$name = trim( $matches[1] );
		$qty  = absint( $matches[2] );

		if ( $qty > 0 && '' !== $name ) {
			return $qty . ' x ' . $name;
		}
	}

	return $text;
}

/**
 * Formatter Woo-pris.
 */
function cmbwc_bon_price( $amount, $order = null ) {
	$amount = (float) $amount;

	if ( $order && is_a( $order, 'WC_Order' ) ) {
		return wc_price(
			$amount,
			array(
				'currency' => $order->get_currency(),
			)
		);
	}

	return wc_price( $amount );
}

/**
 * Formater leveringdato pænt og tydeligt.
 * Eksempel: MANDAG D. 16/03 2026
 */
function cmbwc_bon_format_delivery_date( $date_string ) {
	$date_string = trim( (string) $date_string );

	if ( '' === $date_string ) {
		return '';
	}

	$timestamp = false;

	$formats = array(
		'd/m/Y',
		'd-m-Y',
		'Y-m-d',
		'Y/m/d',
		'd.m.Y',
	);

	foreach ( $formats as $format ) {
		$date = DateTime::createFromFormat( $format, $date_string );
		if ( $date instanceof DateTime ) {
			$timestamp = $date->getTimestamp();
			break;
		}
	}

	if ( ! $timestamp ) {
		$timestamp = strtotime( $date_string );
	}

	if ( ! $timestamp ) {
		return $date_string;
	}

	$day_name = wp_date( 'l', $timestamp );

	$translations = array(
		'Monday'    => 'MANDAG',
		'Tuesday'   => 'TIRSDAG',
		'Wednesday' => 'ONSDAG',
		'Thursday'  => 'TORSDAG',
		'Friday'    => 'FREDAG',
		'Saturday'  => 'LØRDAG',
		'Sunday'    => 'SØNDAG',
	);

	if ( isset( $translations[ $day_name ] ) ) {
		$day_name = $translations[ $day_name ];
	} else {
		$day_name = strtoupper( $day_name );
	}

	return $day_name . ' D. ' . wp_date( 'd/m Y', $timestamp );
}

/**
 * Leveringstype.
 */
if ( ! function_exists( 'cmbwc_get_order_delivery_type_label' ) ) {
	function cmbwc_get_order_delivery_type_label( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return '-';
		}

		$method_title = trim( (string) $order->get_shipping_method() );

		if ( '' === $method_title ) {
			return 'Afhent selv';
		}

		$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $method_title ) : strtolower( $method_title );

		$pickup_keywords = array(
			'local pickup',
			'pickup',
			'afhent',
			'afhentning',
		);

		foreach ( $pickup_keywords as $keyword ) {
			if ( false !== strpos( $normalized, $keyword ) ) {
				return 'Afhent selv';
			}
		}

		return 'Levering';
	}
}

/**
 * Udregner evt. service/depositum-linje fra en ordrelinje.
 */
function cmbwc_bon_get_deposit_line_from_item( $item ) {
	if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return null;
	}

	$is_deposit = $item->get_meta( '_cmbwc_service_is_deposit', true );
	if ( ! in_array( $is_deposit, array( 'yes', '1', 1, true, 'true' ), true ) ) {
		return null;
	}

	$service_label = trim( (string) $item->get_meta( 'Service', true ) );
	$service_price = (float) $item->get_meta( '_cmbwc_service_price', true );
	$price_type    = trim( (string) $item->get_meta( '_cmbwc_service_price_type', true ) );
	$covers        = absint( $item->get_meta( 'Kuverter', true ) );

	if ( '' === $service_label ) {
		$service_label = 'Depositum';
	}

	if ( $service_price <= 0 ) {
		return null;
	}

	$amount = $service_price;

	if ( 'per_cover' === $price_type ) {
		$amount = $service_price * max( 1, $covers );
	}

	return array(
		'name'         => $service_label,
		'display_name' => $service_label . ' (Depositum)',
		'amount'       => (float) $amount,
	);
}

/**
 * Print-status helpers.
 */
function cmbwc_is_order_bon_printed( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	return 'yes' === $order->get_meta( '_cmbwc_bon_printed', true );
}

function cmbwc_mark_order_bon_printed( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$order->update_meta_data( '_cmbwc_bon_printed', 'yes' );
	$order->update_meta_data( '_cmbwc_bon_printed_at', current_time( 'mysql' ) );
	$order->save();

	return true;
}

/**
 * Midlertidig print-lås, så samme ordre ikke sendes dobbelt lige efter hinanden.
 */
function cmbwc_get_printnode_lock_key( $order_id ) {
	return 'cmbwc_printnode_lock_' . absint( $order_id );
}

function cmbwc_is_printnode_locked( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id ) {
		return false;
	}

	$locked_until = (int) get_transient( cmbwc_get_printnode_lock_key( $order_id ) );

	return $locked_until > time();
}

function cmbwc_acquire_printnode_lock( $order_id, $ttl = 15 ) {
	$order_id = absint( $order_id );
	$ttl      = max( 5, absint( $ttl ) );

	if ( ! $order_id ) {
		return false;
	}

	if ( cmbwc_is_printnode_locked( $order_id ) ) {
		return false;
	}

	$locked_until = time() + $ttl;
	set_transient( cmbwc_get_printnode_lock_key( $order_id ), $locked_until, $ttl );

	if ( function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( $order_id );

		if ( $order && is_a( $order, 'WC_Order' ) ) {
			$order->update_meta_data( '_cmbwc_printnode_last_lock', $locked_until );
			$order->save();
		}
	}

	return true;
}

function cmbwc_release_printnode_lock( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id ) {
		return;
	}

	delete_transient( cmbwc_get_printnode_lock_key( $order_id ) );
}

function cmbwc_unmark_order_bon_printed( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$order->delete_meta_data( '_cmbwc_bon_printed' );
	$order->delete_meta_data( '_cmbwc_bon_printed_at' );
	$order->save();

	return true;
}

function cmbwc_get_order_bon_printed_at( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return '';
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return '';
	}

	return (string) $order->get_meta( '_cmbwc_bon_printed_at', true );
}

/**
 * Tjek om PrintNode-plugin er tilgængeligt.
 */
function cmbwc_can_use_printnode() {
	global $woocommerce_simba_printorders_printnode;

	return is_object( $woocommerce_simba_printorders_printnode );
}

/**
 * Finder printer-ID for "Simple order summary" fra Simba/PrintNode settings.
 *
 * Den relevante setting ligger i:
 * woocommerce_printnode_options['copies']['internal']
 *
 * Eksempel:
 * [
 *   'copies' => [
 *     'internal' => [
 *       ['copies' => 1, 'printer_id' => '12345']
 *     ]
 *   ]
 * ]
 */
function cmbwc_get_printnode_target_printer_id() {
	$options = get_option( 'woocommerce_printnode_options', array() );

	if ( ! is_array( $options ) ) {
		return 0;
	}

	if ( empty( $options['copies'] ) || ! is_array( $options['copies'] ) ) {
		return 0;
	}

	if ( empty( $options['copies']['internal'] ) || ! is_array( $options['copies']['internal'] ) ) {
		return 0;
	}

	foreach ( $options['copies']['internal'] as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}

		if ( empty( $rule['printer_id'] ) ) {
			continue;
		}

		$printer_id = (string) $rule['printer_id'];

		// "__all" betyder alle enabled printere - det vil vi IKKE bruge her.
		if ( '__all' === $printer_id ) {
			continue;
		}

		return absint( $printer_id );
	}

	return 0;
}

/**
 * Finder den præcise PrintNode action key for target printer + internal source.
 */
function cmbwc_get_printnode_internal_action_key() {
	$actions = apply_filters( 'woocommerce_order_actions', array() );

	if ( ! is_array( $actions ) || empty( $actions ) ) {
		return '';
	}

	$target_printer_id = cmbwc_get_printnode_target_printer_id();

	if ( $target_printer_id > 0 ) {
		$wanted = 'print-orders-printnode-' . $target_printer_id . '___internal';

		if ( isset( $actions[ $wanted ] ) ) {
			return $wanted;
		}
	}

	// Ingen gyldig printer valgt i Simba settings.
	return '';
}

/**
 * Samler alle data til BON.
 */
function cmbwc_get_order_bon_data( $order ) {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return array();
	}

	$items         = array();
	$deposit_items = array();
	$coupon_lines  = array();

	foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
		if ( ! is_a( $coupon_item, 'WC_Order_Item_Coupon' ) ) {
			continue;
		}

		$code     = method_exists( $coupon_item, 'get_code' ) ? (string) $coupon_item->get_code() : '';
		$discount = (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax();

		$coupon_lines[] = array(
			'code'     => $code,
			'discount' => $discount,
		);
	}

	foreach ( $order->get_items() as $item_id => $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			continue;
		}

		$item_data = array(
			'name'       => $item->get_name(),
			'qty'        => (int) $item->get_quantity(),
			'covers'     => $item->get_meta( 'Kuverter', true ),
			'included'   => cmbwc_bon_split_lines( $item->get_meta( 'Indhold', true ) ),
			'addons'     => cmbwc_bon_split_lines( $item->get_meta( 'Tilvalg', true ) ),
			'service'    => $item->get_meta( 'Service', true ),
			'line_total' => (float) $item->get_total() + (float) $item->get_total_tax(),
		);

		$items[] = $item_data;

		$deposit_line = cmbwc_bon_get_deposit_line_from_item( $item );
		if ( ! empty( $deposit_line ) ) {
			$deposit_items[] = $deposit_line;
		}
	}

	$customer = trim( $order->get_formatted_billing_full_name() );
	if ( '' === $customer ) {
		$customer = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
	}

	$shipping_address = $order->get_address( 'shipping' );
	if ( empty( array_filter( $shipping_address ) ) ) {
		$shipping_address = $order->get_address( 'billing' );
	}

	$address_lines = array();

	if ( ! empty( $shipping_address['address_1'] ) ) {
		$address_lines[] = $shipping_address['address_1'];
	}
	if ( ! empty( $shipping_address['address_2'] ) ) {
		$address_lines[] = $shipping_address['address_2'];
	}

	$city_line = trim(
		( ! empty( $shipping_address['postcode'] ) ? $shipping_address['postcode'] : '' ) .
		' ' .
		( ! empty( $shipping_address['city'] ) ? $shipping_address['city'] : '' )
	);

	if ( '' !== trim( $city_line ) ) {
		$address_lines[] = $city_line;
	}

	$shipping_address = implode( "\n", $address_lines );

	$delivery_date_raw = (string) $order->get_meta( '_delivery_date' );
	$delivery_type     = cmbwc_get_order_delivery_type_label( $order );
	$settings          = function_exists( 'cmbwc_get_print_settings' ) ? cmbwc_get_print_settings() : array();

	return array(
		'order_id'                => $order->get_id(),
		'order_number'            => $order->get_order_number(),
		'created'                 => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
		'delivery_type'           => $delivery_type,
		'delivery_date'           => $delivery_date_raw,
		'delivery_date_formatted' => cmbwc_bon_format_delivery_date( $delivery_date_raw ),
		'delivery_time'           => (string) $order->get_meta( '_delivery_time' ),
		'customer'                => $customer,
		'company'                 => (string) $order->get_billing_company(),
		'phone'                   => (string) $order->get_billing_phone(),
		'shipping_method'         => (string) $order->get_shipping_method(),
		'shipping_address'        => $shipping_address,
		'payment_method'          => (string) $order->get_payment_method_title(),
		'order_note'              => (string) $order->get_customer_note(),
		'subtotal'                => (float) $order->get_subtotal(),
		'shipping_total'          => (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
		'fees_total'              => (float) $order->get_total_fees(),
		'total_tax'               => (float) $order->get_total_tax(),
		'discount_total'          => (float) $order->get_discount_total(),
		'grand_total'             => (float) $order->get_total(),
		'coupon_lines'            => $coupon_lines,
		'items'                   => $items,
		'deposit_items'           => $deposit_items,
		'has_deposit'             => ! empty( $deposit_items ),
		'printed'                 => cmbwc_is_order_bon_printed( $order->get_id() ),
		'printed_at'              => cmbwc_get_order_bon_printed_at( $order->get_id() ),
		'settings'                => $settings,
	);
}

/**
 * Skjul tekniske meta-felter på ordrelinjer i Woo-admin.
 */
add_filter( 'woocommerce_hidden_order_itemmeta', 'cmbwc_hide_internal_order_item_meta' );

function cmbwc_hide_internal_order_item_meta( $hidden ) {
	$hidden[] = '_cmbwc_service_key';
	$hidden[] = '_cmbwc_service_price';
	$hidden[] = '_cmbwc_service_price_type';
	$hidden[] = '_cmbwc_service_is_deposit';
	$hidden[] = '_cmbwc_group_id';
	$hidden[] = '_cmbwc_child_type';
	$hidden[] = '_line_discount';

	return array_unique( $hidden );
}

/**
 * PrintNode template override.
 */
add_filter( 'woocommerce_printorders_printnode_print_template', 'cmbwc_printnode_template_override', 10, 2 );

function cmbwc_printnode_template_override( $template, $order = null ) {
	if ( 'yes' !== cmbwc_get_print_setting( 'enabled', 'yes' ) ) {
		return $template;
	}

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

	cmbwc_send_order_to_printnode( $order->get_id() );
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

	$actions['cmbwc_preview_bon'] = array(
		'url'    => $preview_url,
		'name'   => 'Vis BON',
		'action' => 'view cmbwc-preview-bon',
	);

	$actions['cmbwc_print_bon'] = array(
		'url'    => $print_url,
		'name'   => 'Print BON',
		'action' => 'processing cmbwc-print-bon',
	);

	return $actions;
}

/**
 * Tving "Vis BON" i ordreoversigten til at åbne i ny fane.
 */
add_action( 'admin_footer', 'cmbwc_force_order_list_preview_blank_target' );

function cmbwc_force_order_list_preview_blank_target() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen ) {
		return;
	}

	$allowed_ids = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );

	if ( ! in_array( $screen->id, $allowed_ids, true ) ) {
		return;
	}
	?>
	<script>
	jQuery(function($){
		$('a.cmbwc-preview-bon, a.button.action.cmbwc-preview-bon, a.view.cmbwc-preview-bon').attr('target', '_blank').attr('rel', 'noopener');
		$(document).on('mouseenter', 'a.cmbwc-preview-bon, a.button.action.cmbwc-preview-bon, a.view.cmbwc-preview-bon', function(){
			$(this).attr('target', '_blank').attr('rel', 'noopener');
		});
	});
	</script>
	<?php
}

/**
 * Send ordre til PrintNode ved at kalde den præcise order-action,
 * som matcher Simple order summary + valgt printer.
 */
function cmbwc_send_order_to_printnode( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id ) {
		return false;
	}

	if ( cmbwc_is_printnode_locked( $order_id ) ) {
		return false;
	}

	if ( ! cmbwc_acquire_printnode_lock( $order_id, 15 ) ) {
		return false;
	}

	if ( ! cmbwc_can_use_printnode() ) {
		cmbwc_release_printnode_lock( $order_id );
		return false;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		cmbwc_release_printnode_lock( $order_id );
		return false;
	}

	$action_key = cmbwc_get_printnode_internal_action_key();

	if ( '' === $action_key ) {
		cmbwc_release_printnode_lock( $order_id );
		return false;
	}

	do_action( 'woocommerce_order_action_' . $action_key, $order );

	if ( 'yes' === cmbwc_get_print_setting( 'auto_mark_printed', 'yes' ) ) {
		cmbwc_mark_order_bon_printed( $order_id );
	}

	return true;
}

/**
 * Manuel print fra ordrelisten / produktionsoverblik.
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

	$ok = cmbwc_send_order_to_printnode( $order_id );

	$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' );
	$redirect = add_query_arg( 'cmbwc_printnode_status', $ok ? 'sent' : 'missing', $redirect );

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Rigtig preview-knap på ordresiden.
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

/**
 * Metabox med printstatus.
 */
add_action( 'add_meta_boxes', 'cmbwc_register_print_status_metabox' );

function cmbwc_register_print_status_metabox() {
	$screens = array( 'shop_order' );

	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}

	foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
		add_meta_box(
			'cmbwc-print-status',
			'BON / Printstatus',
			'cmbwc_render_print_status_metabox',
			$screen,
			'side',
			'default'
		);
	}
}

function cmbwc_render_print_status_metabox( $object ) {
	$order = null;

	if ( $object && is_a( $object, 'WC_Order' ) ) {
		$order = $object;
	} elseif ( isset( $object->ID ) ) {
		$order = wc_get_order( $object->ID );
	}

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		echo '<p>Ordre ikke fundet.</p>';
		return;
	}

	$is_printed = cmbwc_is_order_bon_printed( $order->get_id() );
	$printed_at = cmbwc_get_order_bon_printed_at( $order->get_id() );

	echo '<p><strong>Status:</strong> ' . ( $is_printed ? '<span style="color:#137333;">Printet</span>' : '<span style="color:#b32d2e;">Ikke printet</span>' ) . '</p>';

	if ( $printed_at ) {
		echo '<p><strong>Sidst printet:</strong><br>' . esc_html( $printed_at ) . '</p>';
	}

	if ( cmbwc_can_use_printnode() ) {
		$print_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=cmbwc_manual_print_bon&order_id=' . $order->get_id() ),
			'cmbwc_manual_print_bon_' . $order->get_id()
		);

		echo '<p><a class="button button-primary" href="' . esc_url( $print_url ) . '">Print BON nu</a></p>';
	} else {
		echo '<p style="color:#b32d2e;">PrintNode-plugin eller metode blev ikke fundet.</p>';
	}

	$preview_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
		'cmbwc_preview_bon_' . $order->get_id()
	);

	echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $preview_url ) . '">Vis BON</a></p>';
}

/**
 * Admin-notices efter manuelt print.
 */
add_action( 'admin_notices', 'cmbwc_printnode_admin_notices' );

function cmbwc_printnode_admin_notices() {
	if ( ! is_admin() || ! isset( $_GET['cmbwc_printnode_status'] ) ) {
		return;
	}

	$status = sanitize_text_field( wp_unslash( $_GET['cmbwc_printnode_status'] ) );

	if ( 'sent' === $status ) {
		echo '<div class="notice notice-success is-dismissible"><p>Printjob sendt til PrintNode.</p></div>';
	} elseif ( 'missing' === $status ) {
		echo '<div class="notice notice-error is-dismissible"><p>PrintNode kunne ikke bruges. Tjek at PrintNode-pluginet er aktivt, at printeren er enabled, og at Simple order summary er valgt.</p></div>';
	}
}
