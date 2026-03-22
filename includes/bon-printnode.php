<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Splitter tekst til BON-linjer.
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
 * Sørger for korrekt qty-format
 */
function cmbwc_bon_normalize_qty_prefix( $text ) {
	$text = trim( (string) $text );

	if ( preg_match( '/^\d+\s*x\s+/i', $text ) ) {
		return $text;
	}

	if ( preg_match( '/^(.*?)\s*x\s*(\d+)$/i', $text, $matches ) ) {
		return absint( $matches[2] ) . ' x ' . trim( $matches[1] );
	}

	return $text;
}

/**
 * Pris formattering
 */
function cmbwc_bon_price( $amount, $order = null ) {
	return wc_price( (float) $amount );
}

/**
 * Dato formattering
 */
function cmbwc_bon_format_delivery_date( $date_string ) {
	$date_string = trim( (string) $date_string );

	if ( '' === $date_string ) {
		return '';
	}

	$timestamp = false;

	$formats = array( 'd/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd.m.Y' );

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

	$day = wp_date( 'l', $timestamp );

	$map = array(
		'Monday'=>'MANDAG','Tuesday'=>'TIRSDAG','Wednesday'=>'ONSDAG',
		'Thursday'=>'TORSDAG','Friday'=>'FREDAG','Saturday'=>'LØRDAG','Sunday'=>'SØNDAG'
	);

	$day = isset($map[$day]) ? $map[$day] : strtoupper($day);

	return $day . ' D. ' . wp_date( 'd/m Y', $timestamp );
}

/**
 * Leveringstype
 */
function cmbwc_get_order_delivery_type_label( $order ) {
	$method = strtolower( $order->get_shipping_method() );

	if ( strpos( $method, 'afhent' ) !== false || strpos( $method, 'pickup' ) !== false ) {
		return 'Afhent selv';
	}

	return $method ? 'Levering' : 'Afhent selv';
}

/**
 * PRINTNODE FIX
 */
function cmbwc_send_order_to_printnode( $order_id ) {

	if ( ! function_exists('get_option') ) {
		return false;
	}

	global $woocommerce_simba_printorders_printnode;

	if ( ! is_object( $woocommerce_simba_printorders_printnode ) ) {
		return false;
	}

	// 🔥 Hent printere fra plugin
	$printers = get_option( 'woocommerce_printnode_printers', array() );

	if ( empty( $printers ) ) {
		return false;
	}

	$printer_id = null;

	foreach ( $printers as $printer ) {
		if ( ! empty( $printer['enabled'] ) ) {
			$printer_id = $printer['id'];
			break;
		}
	}

	if ( ! $printer_id ) {
		return false;
	}

	// 🔥 SEND DIREKTE MED PRINTER-ID
	$woocommerce_simba_printorders_printnode->woocommerce_print_order_go( $order_id, $printer_id );

	update_post_meta( $order_id, '_cmbwc_bon_printed', 'yes' );
	update_post_meta( $order_id, '_cmbwc_bon_printed_at', current_time( 'mysql' ) );

	return true;
}

/**
 * ORDER ACTIONS
 */
add_filter( 'woocommerce_order_actions', function($actions){
	$actions['cmbwc_print_bon'] = 'Print BON';
	$actions['cmbwc_preview_bon'] = 'Vis BON';
	return $actions;
});

add_action( 'woocommerce_order_action_cmbwc_print_bon', function($order){
	cmbwc_send_order_to_printnode( $order->get_id() );
});

add_action( 'woocommerce_order_action_cmbwc_preview_bon', function($order){
	wp_safe_redirect( admin_url('admin-post.php?action=cmbwc_preview_bon&order_id='.$order->get_id()) );
	exit;
});

/**
 * PREVIEW
 */
add_action( 'admin_post_cmbwc_preview_bon', function(){

	$order = wc_get_order( $_GET['order_id'] );

	echo '<html><body>';
	include CMBWC_PATH . 'templates/printnode-bon.php';
	echo '</body></html>';
	exit;

});

/**
 * LIST BUTTONS
 */
add_filter( 'woocommerce_admin_order_actions', function($actions,$order){

	$actions['print'] = array(
		'url' => admin_url('admin-post.php?action=cmbwc_manual_print_bon&order_id='.$order->get_id()),
		'name' => 'Print BON'
	);

	return $actions;

},10,2);

add_action( 'admin_post_cmbwc_manual_print_bon', function(){

	cmbwc_send_order_to_printnode( $_GET['order_id'] );

	wp_safe_redirect( wp_get_referer() );
	exit;

});
