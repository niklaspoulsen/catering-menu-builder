<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cmbwc_truthy( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	$value = is_string( $value ) ? strtolower( trim( $value ) ) : $value;

	return in_array( $value, array( '1', 1, true, 'true', 'yes', 'ja', 'on' ), true );
}

function cmbwc_is_menu_request( $product_id ) {
	$is_menu_product  = 'yes' === get_post_meta( $product_id, '_cmbwc_is_menu', true );
	$has_menu_request = isset( $_POST['cmbwc_covers'] ) || isset( $_POST['cmbwc_selected_addons'] ) || isset( $_POST['cmbwc_selected_service'] );

	return $is_menu_product || $has_menu_request;
}

function cmbwc_service_is_deposit( $service_data ) {
	if ( ! is_array( $service_data ) ) {
		return false;
	}

	if ( array_key_exists( 'is_deposit', $service_data ) ) {
		return cmbwc_truthy( $service_data['is_deposit'] );
	}

	if ( array_key_exists( 'deposit', $service_data ) ) {
		return cmbwc_truthy( $service_data['deposit'] );
	}

	return false;
}

function cmbwc_service_price_mode( $service_data ) {
	if ( ! is_array( $service_data ) ) {
		return 'fixed';
	}

	if ( ! empty( $service_data['price_type'] ) ) {
		return (string) $service_data['price_type'];
	}

	if ( ! empty( $service_data['price_model'] ) ) {
		return (string) $service_data['price_model'];
	}

	return 'fixed';
}

function cmbwc_service_should_follow_covers( $service_data ) {
	if ( cmbwc_service_is_deposit( $service_data ) ) {
		return false;
	}

	$mode = strtolower( cmbwc_service_price_mode( $service_data ) );

	return in_array( $mode, array( 'per_cover', 'per-cover', 'per cover', 'pr. kuvert', 'pr_kuvert' ), true );
}

function cmbwc_service_locked_qty( $service_data, $covers ) {
	return cmbwc_service_should_follow_covers( $service_data ) ? max( 1, absint( $covers ) ) : 1;
}

function cmbwc_is_child_cart_item( $cart_item ) {
	return ! empty( $cart_item['cmbwc_child_item'] ) && is_array( $cart_item['cmbwc_child_item'] );
}

function cmbwc_is_child_service_item( $cart_item ) {
	return ! empty( $cart_item['cmbwc_child_item']['child_type'] ) && 'service' === $cart_item['cmbwc_child_item']['child_type'];
}

function cmbwc_child_service_follow_covers( $cart_item ) {
	if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
		return false;
	}

	if ( array_key_exists( 'locked_follow_covers', $cart_item['cmbwc_child_item'] ) ) {
		return cmbwc_truthy( $cart_item['cmbwc_child_item']['locked_follow_covers'] );
	}

	return ! empty( $cart_item['cmbwc_child_item']['follow_covers'] ) && 'yes' === $cart_item['cmbwc_child_item']['follow_covers'];
}

function cmbwc_child_service_locked_qty( $cart_item, $parent_qty ) {
	if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
		return 1;
	}

	// Hvis vi eksplicit har låst qty ved oprettelse, så brug ALTID den.
	if ( isset( $cart_item['cmbwc_child_item']['locked_qty'] ) ) {
		$locked_qty = absint( $cart_item['cmbwc_child_item']['locked_qty'] );

		if ( $locked_qty > 0 ) {
			return $locked_qty;
		}
	}

	return cmbwc_child_service_follow_covers( $cart_item ) ? max( 1, absint( $parent_qty ) ) : 1;
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

function cmbwc_generate_group_id() {
	return 'cmbwc_' . wp_generate_password( 12, false, false );
}

function cmbwc_get_cart_children_for_group( $group_id ) {
	$result = array(
		'addons'   => array(),
		'services' => array(),
	);

	if ( empty( $group_id ) || empty( WC()->cart ) ) {
		return $result;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['cmbwc_child_item']['group_id'] ) ) {
			continue;
		}

		if ( $group_id !== $cart_item['cmbwc_child_item']['group_id'] ) {
			continue;
		}

		$child_type = ! empty( $cart_item['cmbwc_child_item']['child_type'] ) ? $cart_item['cmbwc_child_item']['child_type'] : 'addon';
		$name       = ! empty( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ? $cart_item['data']->get_name() : '';
		$qty        = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

		if ( 'service' === $child_type ) {
			$result['services'][] = array(
				'name' => $name,
				'qty'  => $qty,
			);
		} else {
			$result['addons'][] = array(
				'name' => $name,
				'qty'  => $qty,
			);
		}
	}

	return $result;
}

function cmbwc_get_parent_qty_from_group_id( $group_id ) {
	if ( empty( $group_id ) || empty( WC()->cart ) ) {
		return 1;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['cmbwc_data']['group_id'] ) ) {
			continue;
		}

		if ( $group_id !== $cart_item['cmbwc_data']['group_id'] ) {
			continue;
		}

		return isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
	}

	return 1;
}

function cmbwc_group_parent_exists_in_cart( $group_id ) {
	if ( empty( $group_id ) || empty( WC()->cart ) ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['cmbwc_data']['group_id'] ) ) {
			continue;
		}

		if ( $group_id === $cart_item['cmbwc_data']['group_id'] ) {
			return true;
		}
	}

	return false;
}

function cmbwc_sync_child_lines_for_group( $group_id, $parent_qty ) {
	if ( empty( $group_id ) || empty( WC()->cart ) ) {
		return;
	}

	$parent_qty = max( 1, absint( $parent_qty ) );

	foreach ( WC()->cart->get_cart() as $child_key => $child_item ) {
		if ( empty( $child_item['cmbwc_child_item']['group_id'] ) || $group_id !== $child_item['cmbwc_child_item']['group_id'] ) {
			continue;
		}

		$child_type = ! empty( $child_item['cmbwc_child_item']['child_type'] ) ? $child_item['cmbwc_child_item']['child_type'] : 'addon';
		$target_qty = isset( $child_item['quantity'] ) ? absint( $child_item['quantity'] ) : 1;

		if ( 'service' === $child_type ) {
			$target_qty = cmbwc_child_service_follow_covers( $child_item ) ? $parent_qty : 1;
		} else {
			$follow_covers = ! empty( $child_item['cmbwc_child_item']['follow_covers'] ) && 'yes' === $child_item['cmbwc_child_item']['follow_covers'];

			if ( $follow_covers ) {
				$target_qty = $parent_qty;
			}
		}

		$target_qty = max( 1, absint( $target_qty ) );

		if ( (int) $child_item['quantity'] !== $target_qty ) {
			WC()->cart->set_quantity( $child_key, $target_qty, false );
		}
	}
}

add_filter( 'woocommerce_add_to_cart_validation', 'cmbwc_validate_add_to_cart', 10, 3 );

function cmbwc_validate_add_to_cart( $passed, $product_id, $quantity ) {
	if ( ! cmbwc_is_menu_request( $product_id ) ) {
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
	if ( ! empty( $_POST['cmbwc_parent_group_id'] ) ) {
		$cart_item_data['cmbwc_child_item'] = array(
			'group_id'             => sanitize_text_field( wp_unslash( $_POST['cmbwc_parent_group_id'] ) ),
			'parent_product_id'    => isset( $_POST['cmbwc_parent_product_id'] ) ? absint( wp_unslash( $_POST['cmbwc_parent_product_id'] ) ) : 0,
			'parent_name'          => isset( $_POST['cmbwc_parent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_parent_name'] ) ) : '',
			'child_type'           => isset( $_POST['cmbwc_child_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_child_type'] ) ) : '',
			'source_key'           => isset( $_POST['cmbwc_source_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_source_key'] ) ) : '',
			'follow_covers'        => isset( $_POST['cmbwc_follow_covers'] ) && 'yes' === wp_unslash( $_POST['cmbwc_follow_covers'] ) ? 'yes' : 'no',
			'display_type_label'   => isset( $_POST['cmbwc_display_type_label'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_display_type_label'] ) ) : '',
			'is_deposit'           => isset( $_POST['cmbwc_is_deposit'] ) && 'yes' === wp_unslash( $_POST['cmbwc_is_deposit'] ) ? 'yes' : 'no',
			'locked_follow_covers' => isset( $_POST['cmbwc_locked_follow_covers'] ) && 'yes' === wp_unslash( $_POST['cmbwc_locked_follow_covers'] ) ? 'yes' : 'no',
			'locked_qty'           => isset( $_POST['cmbwc_locked_qty'] ) ? absint( wp_unslash( $_POST['cmbwc_locked_qty'] ) ) : 1,
		);

		$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data['cmbwc_child_item'] ) . microtime() );

		return $cart_item_data;
	}

	if ( ! cmbwc_is_menu_request( $product_id ) ) {
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

		$follow_covers          = isset( $allowed_addons_meta[ $addon_id ]['follow_covers'] ) && 'yes' === $allowed_addons_meta[ $addon_id ]['follow_covers'] ? 'yes' : 'no';
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

	$service_data = $selected_service && function_exists( 'cmbwc_get_service_option_by_key' )
		? cmbwc_get_service_option_by_key( $selected_service )
		: null;

	$included_names = cmbwc_get_included_names_for_product( $product_id );
	$group_id       = cmbwc_generate_group_id();

	$cart_item_data['cmbwc_data'] = array(
		'is_menu'          => 'yes',
		'covers'           => $covers,
		'group_id'         => $group_id,
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

	if ( ! empty( $values['cmbwc_child_item'] ) ) {
		$cart_item['cmbwc_child_item'] = $values['cmbwc_child_item'];
	}

	if ( isset( $values['cmbwc_service_price_override'] ) ) {
		$cart_item['cmbwc_service_price_override'] = $values['cmbwc_service_price_override'];
	}

	return $cart_item;
}

add_filter( 'woocommerce_get_item_data', 'cmbwc_render_item_data', 10, 2 );

function cmbwc_render_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return array();
	}

	if ( cmbwc_is_child_cart_item( $cart_item ) ) {
		return array();
	}

	return $item_data;
}

function cmbwc_get_cart_meta_html( $data ) {
	$covers         = ! empty( $data['covers'] ) ? absint( $data['covers'] ) : 0;
	$included_names = ! empty( $data['included_names'] ) && is_array( $data['included_names'] ) ? $data['included_names'] : array();
	$addons         = array();
	$services       = array();

	if ( ! empty( $data['group_id'] ) ) {
		$children = cmbwc_get_cart_children_for_group( $data['group_id'] );
		$addons   = ! empty( $children['addons'] ) ? $children['addons'] : array();
		$services = ! empty( $children['services'] ) ? $children['services'] : array();
	}

	ob_start();
	?>
	<div class="cmbwc-cart-meta">
		<?php if ( $covers > 0 ) : ?>
			<div class="cmbwc-cart-row">
				<span class="cmbwc-cart-label">Kuverter:</span>
				<span class="cmbwc-cart-value"><?php echo esc_html( $covers ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $included_names ) ) : ?>
			<div class="cmbwc-cart-row">
				<span class="cmbwc-cart-label">Indhold:</span>
				<span class="cmbwc-cart-value">
					<ul class="cmbwc-cart-list">
						<?php foreach ( $included_names as $name ) : ?>
							<li><?php echo esc_html( $name ); ?></li>
						<?php endforeach; ?>
					</ul>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $addons ) ) : ?>
			<div class="cmbwc-cart-row">
				<span class="cmbwc-cart-label">Valgte tilvalg:</span>
				<span class="cmbwc-cart-value">
					<ul class="cmbwc-cart-list">
						<?php foreach ( $addons as $addon ) : ?>
							<li><?php echo esc_html( absint( $addon['qty'] ) . ' x ' . $addon['name'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $services ) ) : ?>
			<div class="cmbwc-cart-row">
				<span class="cmbwc-cart-label">Valgt service:</span>
				<span class="cmbwc-cart-value">
					<ul class="cmbwc-cart-list">
						<?php foreach ( $services as $service ) : ?>
							<li><?php echo esc_html( $service['qty'] > 1 ? absint( $service['qty'] ) . ' x ' . $service['name'] : $service['name'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_filter( 'woocommerce_cart_item_name', 'cmbwc_render_cart_item_name_block', 20, 3 );

function cmbwc_render_cart_item_name_block( $product_name, $cart_item, $cart_item_key ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return $product_name . cmbwc_get_cart_meta_html( $cart_item['cmbwc_data'] );
	}

	if ( cmbwc_is_child_cart_item( $cart_item ) ) {
		return $product_name;
	}

	return $product_name;
}

add_filter( 'widget_cart_item_name', 'cmbwc_render_widget_cart_item_name_block', 20, 3 );

function cmbwc_render_widget_cart_item_name_block( $product_name, $cart_item, $cart_item_key ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return $product_name . cmbwc_get_cart_meta_html( $cart_item['cmbwc_data'] );
	}

	if ( cmbwc_is_child_cart_item( $cart_item ) ) {
		return $product_name;
	}

	return $product_name;
}

add_filter( 'woocommerce_widget_cart_item_quantity', 'cmbwc_widget_cart_item_quantity', 20, 3 );

function cmbwc_widget_cart_item_quantity( $html, $cart_item, $cart_item_key ) {
	if ( empty( $cart_item['cmbwc_data'] ) || ! is_array( $cart_item['cmbwc_data'] ) ) {
		return $html;
	}

	$line_total = 0;

	if ( isset( $cart_item['line_total'] ) ) {
		$line_total = (float) $cart_item['line_total'];

		if ( ! empty( $cart_item['line_tax'] ) ) {
			$line_total += (float) $cart_item['line_tax'];
		}
	}

	return '<span class="quantity cmbwc-mini-cart-quantity">Samlet: ' . wc_price( $line_total ) . '</span>';
}

add_action( 'woocommerce_add_to_cart', 'cmbwc_expand_menu_to_cart_lines', 20, 6 );

function cmbwc_expand_menu_to_cart_lines( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	if ( empty( $cart_item_data['cmbwc_data'] ) || ! is_array( $cart_item_data['cmbwc_data'] ) ) {
		return;
	}

	if ( empty( WC()->cart ) ) {
		return;
	}

	$data      = $cart_item_data['cmbwc_data'];
	$covers    = ! empty( $data['covers'] ) ? absint( $data['covers'] ) : 1;
	$group_id  = ! empty( $data['group_id'] ) ? $data['group_id'] : cmbwc_generate_group_id();
	$menu_name = get_the_title( $product_id );
	$menu_item = WC()->cart->get_cart_item( $cart_item_key );

	if ( $menu_item ) {
		WC()->cart->cart_contents[ $cart_item_key ]['cmbwc_data']['group_id'] = $group_id;
		WC()->cart->set_quantity( $cart_item_key, $covers, false );
	}

	if ( ! empty( $data['selected_addons'] ) && is_array( $data['selected_addons'] ) ) {
		foreach ( $data['selected_addons'] as $addon ) {
			$addon_product_id = isset( $addon['id'] ) ? absint( $addon['id'] ) : 0;
			$addon_qty        = isset( $addon['qty'] ) ? absint( $addon['qty'] ) : 0;

			if ( ! $addon_product_id || $addon_qty < 1 ) {
				continue;
			}

			WC()->cart->add_to_cart(
				$addon_product_id,
				$addon_qty,
				0,
				array(),
				array(
					'cmbwc_child_item' => array(
						'group_id'             => $group_id,
						'parent_product_id'    => $product_id,
						'parent_name'          => $menu_name,
						'child_type'           => 'addon',
						'source_key'           => (string) $addon_product_id,
						'follow_covers'        => ! empty( $addon['follow_covers'] ) && 'yes' === $addon['follow_covers'] ? 'yes' : 'no',
						'display_type_label'   => 'Tilvalg',
						'is_deposit'           => 'no',
						'locked_follow_covers' => ! empty( $addon['follow_covers'] ) && 'yes' === $addon['follow_covers'] ? 'yes' : 'no',
						'locked_qty'           => ! empty( $addon['follow_covers'] ) && 'yes' === $addon['follow_covers'] ? $covers : $addon_qty,
					),
					'unique_key' => md5( $group_id . '_addon_' . $addon_product_id . '_' . microtime() ),
				)
			);
		}
	}

	if ( ! empty( $data['service_data'] ) && is_array( $data['service_data'] ) ) {
		$service_data      = $data['service_data'];
		$linked_product_id = ! empty( $service_data['linked_product_id'] ) ? absint( $service_data['linked_product_id'] ) : 0;

		if ( $linked_product_id ) {
			$is_deposit         = cmbwc_service_is_deposit( $service_data ) ? 'yes' : 'no';
			$locked_follow      = cmbwc_service_should_follow_covers( $service_data ) ? 'yes' : 'no';
			$locked_qty         = cmbwc_service_locked_qty( $service_data, $covers );
			$service_qty        = $locked_qty;

			WC()->cart->add_to_cart(
				$linked_product_id,
				$service_qty,
				0,
				array(),
				array(
					'cmbwc_child_item' => array(
						'group_id'             => $group_id,
						'parent_product_id'    => $product_id,
						'parent_name'          => $menu_name,
						'child_type'           => 'service',
						'source_key'           => ! empty( $data['selected_service'] ) ? $data['selected_service'] : '',
						'follow_covers'        => $locked_follow,
						'display_type_label'   => 'Service',
						'is_deposit'           => $is_deposit,
						'locked_follow_covers' => $locked_follow,
						'locked_qty'           => $locked_qty,
					),
					'cmbwc_service_price_override' => isset( $service_data['price'] ) ? (float) $service_data['price'] : null,
					'unique_key' => md5( $group_id . '_service_' . $linked_product_id . '_' . microtime() ),
				)
			);
		}
	}
}

add_action( 'woocommerce_before_calculate_totals', 'cmbwc_before_calculate_totals', 9999 );
add_action( 'woocommerce_cart_loaded_from_session', 'cmbwc_force_locked_service_quantities', 9999 );
add_action( 'woocommerce_before_cart', 'cmbwc_force_locked_service_quantities', 9999 );
add_action( 'woocommerce_before_mini_cart', 'cmbwc_force_locked_service_quantities', 9999 );

function cmbwc_before_calculate_totals( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
			continue;
		}

		if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
			$covers = ! empty( $cart_item['cmbwc_data']['covers'] ) ? absint( $cart_item['cmbwc_data']['covers'] ) : 1;

			if ( (int) $cart_item['quantity'] !== $covers ) {
				$cart->cart_contents[ $cart_item_key ]['quantity'] = $covers;
			}
		}

		if ( cmbwc_is_child_service_item( $cart_item ) ) {
			$target_qty = cmbwc_child_service_locked_qty( $cart_item, cmbwc_get_parent_qty_from_group_id( $cart_item['cmbwc_child_item']['group_id'] ) );

			if ( (int) $cart_item['quantity'] !== $target_qty ) {
				$cart->cart_contents[ $cart_item_key ]['quantity'] = $target_qty;
			}
		}

		if ( isset( $cart_item['cmbwc_service_price_override'] ) && null !== $cart_item['cmbwc_service_price_override'] ) {
			$cart->cart_contents[ $cart_item_key ]['data']->set_price( (float) $cart_item['cmbwc_service_price_override'] );
		}
	}
}

add_action( 'woocommerce_after_cart_item_quantity_update', 'cmbwc_after_cart_item_quantity_update', 10, 4 );

function cmbwc_after_cart_item_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
	if ( empty( $cart->cart_contents[ $cart_item_key ] ) ) {
		return;
	}

	$item = $cart->cart_contents[ $cart_item_key ];

	if ( ! empty( $item['cmbwc_data'] ) && is_array( $item['cmbwc_data'] ) ) {
		$quantity = max( 1, absint( $quantity ) );
		$cart->cart_contents[ $cart_item_key ]['cmbwc_data']['covers'] = $quantity;

		if ( ! empty( $item['cmbwc_data']['group_id'] ) ) {
			cmbwc_sync_child_lines_for_group( $item['cmbwc_data']['group_id'], $quantity );
		}
		return;
	}

	if ( cmbwc_is_child_cart_item( $item ) ) {
		$group_id   = ! empty( $item['cmbwc_child_item']['group_id'] ) ? $item['cmbwc_child_item']['group_id'] : '';
		$child_type = ! empty( $item['cmbwc_child_item']['child_type'] ) ? $item['cmbwc_child_item']['child_type'] : 'addon';
		$target_qty = max( 1, absint( $quantity ) );

		if ( 'service' === $child_type ) {
			$target_qty = cmbwc_child_service_follow_covers( $item )
				? cmbwc_get_parent_qty_from_group_id( $group_id )
				: 1;
		} else {
			$follow = ! empty( $item['cmbwc_child_item']['follow_covers'] ) && 'yes' === $item['cmbwc_child_item']['follow_covers'];

			if ( $follow ) {
				$target_qty = cmbwc_get_parent_qty_from_group_id( $group_id );
			}
		}

		if ( $target_qty !== (int) $quantity ) {
			$cart->set_quantity( $cart_item_key, $target_qty, false );
		}
	}
}

add_filter( 'woocommerce_cart_item_quantity', 'cmbwc_lock_service_cart_quantity', 20, 3 );

function cmbwc_lock_service_cart_quantity( $product_quantity, $cart_item_key, $cart_item ) {
	if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
		return $product_quantity;
	}

	$qty = cmbwc_child_service_follow_covers( $cart_item )
		? cmbwc_get_parent_qty_from_group_id( $cart_item['cmbwc_child_item']['group_id'] )
		: 1;

	return '<span class="cmbwc-locked-service-qty">' . esc_html( $qty ) . '</span>';
}

add_filter( 'woocommerce_cart_item_remove_link', 'cmbwc_disable_service_remove_link', 20, 2 );

function cmbwc_disable_service_remove_link( $link, $cart_item_key ) {
	if ( empty( WC()->cart ) ) {
		return $link;
	}

	$cart_item = WC()->cart->get_cart_item( $cart_item_key );

	if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
		return $link;
	}

	return '';
}

add_filter( 'woocommerce_widget_cart_item_quantity', 'cmbwc_disable_service_remove_link_in_widget_markup', 30, 3 );

function cmbwc_disable_service_remove_link_in_widget_markup( $html, $cart_item, $cart_item_key ) {
	if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
		return $html;
	}

	return $html;
}

add_filter( 'woocommerce_cart_item_class', 'cmbwc_add_service_item_class', 20, 3 );

function cmbwc_add_service_item_class( $class, $cart_item, $cart_item_key ) {
	if ( cmbwc_is_child_service_item( $cart_item ) ) {
		$class .= ' cmbwc-service-line';
	}

	return $class;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'cmbwc_strip_service_remove_links_from_minicart', 20, 1 );

function cmbwc_strip_service_remove_links_from_minicart( $fragments ) {
	if ( empty( WC()->cart ) ) {
		return $fragments;
	}

	ob_start();
	woocommerce_mini_cart();
	$fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . ob_get_clean() . '</div>';

	return $fragments;
}

add_filter( 'woocommerce_cart_item_permalink', 'cmbwc_service_cart_item_permalink', 20, 3 );

function cmbwc_service_cart_item_permalink( $permalink, $cart_item, $cart_item_key ) {
	return $permalink;
}

add_action( 'woocommerce_remove_cart_item', 'cmbwc_restore_service_if_removed_directly', 5, 2 );

function cmbwc_restore_service_if_removed_directly( $cart_item_key, $cart ) {
	$removed = isset( $cart->removed_cart_contents[ $cart_item_key ] ) ? $cart->removed_cart_contents[ $cart_item_key ] : null;

	if ( ! cmbwc_is_child_service_item( $removed ) ) {
		return;
	}

	$group_id = ! empty( $removed['cmbwc_child_item']['group_id'] ) ? $removed['cmbwc_child_item']['group_id'] : '';

	if ( ! empty( $GLOBALS['cmbwc_skip_service_restore_groups'] ) && in_array( $group_id, (array) $GLOBALS['cmbwc_skip_service_restore_groups'], true ) ) {
		return;
	}

	if ( ! cmbwc_group_parent_exists_in_cart( $group_id ) ) {
		return;
	}

	$product_id = isset( $removed['product_id'] ) ? absint( $removed['product_id'] ) : 0;
	$qty        = isset( $removed['quantity'] ) ? absint( $removed['quantity'] ) : 1;

	if ( ! $product_id ) {
		return;
	}

	$cart->add_to_cart(
		$product_id,
		max( 1, $qty ),
		0,
		array(),
		array(
			'cmbwc_child_item' => $removed['cmbwc_child_item'],
			'cmbwc_service_price_override' => isset( $removed['cmbwc_service_price_override'] ) ? $removed['cmbwc_service_price_override'] : null,
			'unique_key' => md5( 'restore_service_' . $cart_item_key . '_' . microtime() ),
		)
	);
}

add_action( 'woocommerce_remove_cart_item', 'cmbwc_remove_child_lines_with_parent', 10, 2 );

function cmbwc_remove_child_lines_with_parent( $cart_item_key, $cart ) {
	$removed = isset( $cart->removed_cart_contents[ $cart_item_key ] ) ? $cart->removed_cart_contents[ $cart_item_key ] : null;

	if ( empty( $removed['cmbwc_data']['group_id'] ) ) {
		return;
	}

	$group_id = $removed['cmbwc_data']['group_id'];

	if ( empty( $GLOBALS['cmbwc_skip_service_restore_groups'] ) || ! is_array( $GLOBALS['cmbwc_skip_service_restore_groups'] ) ) {
		$GLOBALS['cmbwc_skip_service_restore_groups'] = array();
	}

	if ( ! in_array( $group_id, $GLOBALS['cmbwc_skip_service_restore_groups'], true ) ) {
		$GLOBALS['cmbwc_skip_service_restore_groups'][] = $group_id;
	}

	foreach ( $cart->get_cart() as $child_key => $child_item ) {
		if ( empty( $child_item['cmbwc_child_item']['group_id'] ) ) {
			continue;
		}

		if ( $group_id !== $child_item['cmbwc_child_item']['group_id'] ) {
			continue;
		}

		$cart->remove_cart_item( $child_key );
	}

	$GLOBALS['cmbwc_skip_service_restore_groups'] = array_values(
		array_diff( $GLOBALS['cmbwc_skip_service_restore_groups'], array( $group_id ) )
	);
}

add_action( 'woocommerce_before_cart', 'cmbwc_cleanup_orphan_service_lines' );
add_action( 'woocommerce_before_mini_cart', 'cmbwc_cleanup_orphan_service_lines' );

function cmbwc_cleanup_orphan_service_lines() {
	if ( empty( WC()->cart ) ) {
		return;
	}

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
			continue;
		}

		$group_id = ! empty( $cart_item['cmbwc_child_item']['group_id'] ) ? $cart_item['cmbwc_child_item']['group_id'] : '';

		if ( ! cmbwc_group_parent_exists_in_cart( $group_id ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}
}

add_filter( 'woocommerce_checkout_cart_item_quantity', 'cmbwc_clean_checkout_quantity_display', 20, 3 );

function cmbwc_clean_checkout_quantity_display( $quantity_html, $cart_item, $cart_item_key ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return '';
	}

	if ( ! empty( $cart_item['cmbwc_child_item'] ) && is_array( $cart_item['cmbwc_child_item'] ) ) {
		return '';
	}

	return $quantity_html;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'cmbwc_add_order_item_meta', 10, 4 );

function cmbwc_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
	if ( ! empty( $values['cmbwc_data'] ) && is_array( $values['cmbwc_data'] ) ) {
		$data = $values['cmbwc_data'];

		if ( ! empty( $data['covers'] ) ) {
			$item->add_meta_data( 'Kuverter', absint( $data['covers'] ) );
		}

		if ( ! empty( $data['included_names'] ) && is_array( $data['included_names'] ) ) {
			$item->add_meta_data( 'Indhold', implode( "\n", $data['included_names'] ) );
		}

		if ( ! empty( $data['group_id'] ) ) {
			$children = cmbwc_get_cart_children_for_group( $data['group_id'] );

			if ( ! empty( $children['addons'] ) ) {
				$addon_lines = array();

				foreach ( $children['addons'] as $addon ) {
					$addon_lines[] = absint( $addon['qty'] ) . ' x ' . $addon['name'];
				}

				$item->add_meta_data( 'Valgte tilvalg', implode( "\n", $addon_lines ) );
			}

			if ( ! empty( $children['services'] ) ) {
				$service_lines = array();

				foreach ( $children['services'] as $service ) {
					$service_lines[] = $service['qty'] > 1 ? absint( $service['qty'] ) . ' x ' . $service['name'] : $service['name'];
				}

				$item->add_meta_data( 'Valgt service', implode( "\n", $service_lines ) );
			}

			$item->add_meta_data( '_cmbwc_group_id', sanitize_text_field( $data['group_id'] ) );
		}

		return;
	}

	if ( cmbwc_is_child_cart_item( $values ) ) {
		$child = $values['cmbwc_child_item'];

		if ( ! empty( $child['child_type'] ) ) {
			$item->add_meta_data( '_cmbwc_child_type', sanitize_text_field( $child['child_type'] ) );
		}

		if ( ! empty( $child['group_id'] ) ) {
			$item->add_meta_data( '_cmbwc_group_id', sanitize_text_field( $child['group_id'] ) );
		}
	}
}

add_filter( 'woocommerce_order_item_display_meta_value', 'cmbwc_format_order_item_meta_value', 20, 3 );

function cmbwc_format_order_item_meta_value( $display_value, $meta, $item ) {
	$key = isset( $meta->key ) ? $meta->key : '';

	if ( in_array( $key, array( 'Indhold', 'Valgte tilvalg', 'Valgt service' ), true ) ) {
		$value = isset( $meta->value ) ? (string) $meta->value : '';
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) );

		if ( empty( $lines ) ) {
			return $display_value;
		}

		$html = '<ul class="cmbwc-order-meta-list">';

		foreach ( $lines as $line ) {
			$html .= '<li>' . esc_html( $line ) . '</li>';
		}

		$html .= '</ul>';

		return wp_kses(
			$html,
			array(
				'ul' => array( 'class' => true ),
				'li' => array(),
			)
		);
	}

	return $display_value;
}

function cmbwc_force_locked_service_quantities( $cart = null ) {
	if ( ! $cart && function_exists( 'WC' ) && WC()->cart ) {
		$cart = WC()->cart;
	}

	if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( ! cmbwc_is_child_service_item( $cart_item ) ) {
			continue;
		}

		$group_id   = ! empty( $cart_item['cmbwc_child_item']['group_id'] ) ? $cart_item['cmbwc_child_item']['group_id'] : '';
		$parent_qty = cmbwc_get_parent_qty_from_group_id( $group_id );
		$target_qty = cmbwc_child_service_locked_qty( $cart_item, $parent_qty );

		if ( (int) $cart_item['quantity'] !== $target_qty ) {
			$cart->set_quantity( $cart_item_key, $target_qty, false );
		}
	}
}
