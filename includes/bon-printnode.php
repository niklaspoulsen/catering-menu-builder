<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Samler alle data til BON
 */
function cmbwc_get_order_bon_data( $order ) {

	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return [];
	}

	$items = [];

	foreach ( $order->get_items() as $item_id => $item ) {

		$included = $item->get_meta('Indhold');
		$addons   = $item->get_meta('Tilvalg');

		$included_lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $included)));
		$addons_lines   = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $addons)));

		$items[] = [

			'name' => $item->get_name(),

			'covers' => $item->get_meta('Kuverter'),

			'included' => $included_lines,

			'addons' => $addons_lines,

			'service' => $item->get_meta('Service'),

		];
	}

	return [

		'order_number' => $order->get_order_number(),

		'created' => $order->get_date_created()->date('d/m/Y H:i'),

		'delivery_date' => $order->get_meta('_delivery_date'),

		'delivery_time' => $order->get_meta('_delivery_time'),

		'customer' => $order->get_formatted_billing_full_name(),

		'company' => $order->get_billing_company(),

		'phone' => $order->get_billing_phone(),

		'shipping_method' => $order->get_shipping_method(),

		'order_note' => $order->get_customer_note(),

		'items' => $items
	];
}


/**
 * PrintNode template override
 */

add_filter(
	'woocommerce_printorders_printnode_print_template',
	function( $template ){

		$custom = CMBWC_PATH . 'templates/printnode-bon.php';

		if ( file_exists($custom) ) {
			return $custom;
		}

		return $template;
	}
);


/**
 * Preview BON
 */

add_action('add_meta_boxes', function(){

	add_meta_box(
		'cmbwc_bon_preview',
		'Catering BON',
		'cmbwc_render_bon_metabox',
		'shop_order',
		'side',
		'high'
	);

});


function cmbwc_render_bon_metabox($post){

	$order_id = $post->ID;

	$preview = admin_url(
		"admin-post.php?action=cmbwc_preview_bon&order_id=$order_id"
	);

	$print = admin_url(
		"admin-post.php?action=cmbwc_print_bon&order_id=$order_id"
	);

	echo '<p>';

	echo '<a target="_blank" class="button button-primary" href="'.$preview.'">
	Preview BON
	</a>';

	echo '</p>';

	echo '<p>';

	echo '<a class="button" href="'.$print.'">
	Print BON nu
	</a>';

	echo '</p>';

}


/**
 * BON preview page
 */

add_action('admin_post_cmbwc_preview_bon', function(){

	$order_id = intval($_GET['order_id']);

	$order = wc_get_order($order_id);

	if(!$order){
		wp_die('Order not found');
	}

	include CMBWC_PATH . 'templates/printnode-bon.php';

	exit;

});


/**
 * Manuel print via PrintNode
 */

add_action('admin_post_cmbwc_print_bon', function(){

	$order_id = intval($_GET['order_id']);

	global $woocommerce_simba_printorders_printnode;

	if( isset($woocommerce_simba_printorders_printnode) ){

		$woocommerce_simba_printorders_printnode
			->woocommerce_print_order_go($order_id);

	}

	wp_redirect(wp_get_referer());

	exit;

});
