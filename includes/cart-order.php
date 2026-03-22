<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

function cmbwc_is_child_cart_item( $cart_item ) {
	return ! empty( $cart_item['cmbwc_child_item'] ) && is_array( $cart_item['cmbwc_child_item'] );
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
	if ( ! empty( $_POST['cmbwc_parent_group_id'] ) ) {
		$cart_item_data['cmbwc_child_item'] = array(
			'group_id'         => sanitize_text_field( wp_unslash( $_POST['cmbwc_parent_group_id'] ) ),
			'parent_product_id' => isset( $_POST['cmbwc_parent_product_id'] ) ? absint( wp_unslash( $_POST['cmbwc_parent_product_id'] ) ) : 0,
			'parent_name'      => isset( $_POST['cmbwc_parent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_parent_name'] ) ) : '',
			'child_type'       => isset( $_POST['cmbwc_child_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_child_type'] ) ) : '',
			'source_key'       => isset( $_POST['cmbwc_source_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cmbwc_source_key'] ) ) : '',
			'follow_covers'    => isset( $_POST['cmbwc_follow_covers'] ) && 'yes' === wp_unslash( $_POST['cmbwc_follow_covers'] ) ? 'yes' : 'no',
		);

		$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data['cmbwc_child_item'] ) . microtime() );
		return $cart_item_data;
	}

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

		$follow_covers         = isset( $allowed_addons_meta[ $addon_id ]['follow_covers'] ) && 'yes' === $allowed_addons_meta[ $addon_id ]['follow_covers'] ? 'yes' : 'no';
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

	return $cart_item;
}

add_filter( 'woocommerce_get_item_data', 'cmbwc_hide_default_item_data', 10, 2 );

function cmbwc_hide_default_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return array();
	}

	if ( cmbwc_is_child_cart_item( $cart_item ) ) {
		return array();
	}

	return $item_data;
}

function cmbwc_get_cart_meta_html( $data ) {
	ob_start();
	?>
	<div class="cmbwc-cart-meta">
		<?php if ( ! empty( $data['covers'] ) ) : ?>
			<div class="cmbwc-cart-row">
				<span class="cmbwc-cart-label">Kuverter:</span>
				<span class="cmbwc-cart-value"><?php echo esc_html( absint( $data['covers'] ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $data['included_names'] ) && is_array( $data['included_names'] ) ) : ?>
			<div class="cmbwc-cart-group">
				<div class="cmbwc-cart-group-title">Indhold</div>
				<ul class="cmbwc-cart-list">
					<?php foreach ( $data['included_names'] as $name ) : ?>
						<li><?php echo esc_html( $name ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_get_child_cart_meta_html( $child_data ) {
	$parent_name = ! empty( $child_data['parent_name'] ) ? $child_data['parent_name'] : '';

	if ( '' === $parent_name ) {
		return '';
	}

	ob_start();
	?>
	<div class="cmbwc-cart-meta">
		<div class="cmbwc-cart-row">
			<span class="cmbwc-cart-label">Hører til:</span>
			<span class="cmbwc-cart-value"><?php echo esc_html( $parent_name ); ?></span>
		</div>
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
		return $product_name . cmbwc_get_child_cart_meta_html( $cart_item['cmbwc_child_item'] );
	}

	return $product_name;
}

add_filter( 'widget_cart_item_name', 'cmbwc_render_widget_cart_item_name_block', 20, 3 );

function cmbwc_render_widget_cart_item_name_block( $product_name, $cart_item, $cart_item_key ) {
	if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
		return $product_name . cmbwc_get_cart_meta_html( $cart_item['cmbwc_data'] );
	}

	if ( cmbwc_is_child_cart_item( $cart_item ) ) {
		return $product_name . cmbwc_get_child_cart_meta_html( $cart_item['cmbwc_child_item'] );
	}

	return $product_name;
}

add_action( 'woocommerce_add_to_cart', 'cmbwc_expand_menu_to_cart_lines', 20, 6 );

function cmbwc_expand_menu_to_cart_lines( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	if ( empty( $cart_item_data['cmbwc_data'] ) || ! is_array( $cart_item_data['cmbwc_data'] ) ) {
		return;
	}

	if ( empty( WC()->cart ) ) {
		return;
	}

	$data       = $cart_item_data['cmbwc_data'];
	$covers     = ! empty( $data['covers'] ) ? absint( $data['covers'] ) : 1;
	$group_id   = ! empty( $data['group_id'] ) ? $data['group_id'] : cmbwc_generate_group_id();
	$menu_name  = get_the_title( $product_id );
	$menu_item  = WC()->cart->get_cart_item( $cart_item_key );

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
						'group_id'          => $group_id,
						'parent_product_id' => $product_id,
						'parent_name'       => $menu_name,
						'child_type'        => 'addon',
						'source_key'        => (string) $addon_product_id,
						'follow_covers'     => isset( $addon['follow_covers'] ) && 'yes' === $addon['follow_covers'] ? 'yes' : 'no',
					),
					'unique_key' => md5( $group_id . '_addon_' . $addon_product_id . '_' . microtime() ),
				)
			);
		}
	}

	if ( ! empty( $data['service_data'] ) && is_array( $data['service_data'] ) ) {
		$service_data       = $data['service_data'];
		$linked_product_id  = ! empty( $service_data['linked_product_id'] ) ? absint( $service_data['linked_product_id'] ) : 0;
		$service_price_type = ! empty( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed';

		if ( $linked_product_id ) {
			$service_qty = 'per_cover' === $service_price_type ? $covers : 1;

			WC()->cart->add_to_cart(
				$linked_product_id,
				$service_qty,
				0,
				array(),
				array(
					'cmbwc_child_item' => array(
						'group_id'          => $group_id,
						'parent_product_id' => $product_id,
						'parent_name'       => $menu_name,
						'child_type'        => 'service',
						'source_key'        => ! empty( $data['selected_service'] ) ? $data['selected_service'] : '',
						'follow_covers'     => 'per_cover' === $service_price_type ? 'yes' : 'no',
					),
					'cmbwc_service_price_override' => isset( $service_data['price'] ) ? (float) $service_data['price'] : null,
					'unique_key' => md5( $group_id . '_service_' . $linked_product_id . '_' . microtime() ),
				)
			);
		}
	}
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
		if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
			continue;
		}

		if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
			$covers = ! empty( $cart_item['cmbwc_data']['covers'] ) ? absint( $cart_item['cmbwc_data']['covers'] ) : 1;

			if ( $cart_item['quantity'] !== $covers ) {
				$cart->cart_contents[ $cart_item_key ]['quantity'] = $covers;
			}
		}

		if ( isset( $cart_item['cmbwc_service_price_override'] ) && null !== $cart_item['cmbwc_service_price_override'] ) {
			$cart_item['data']->set_price( (float) $cart_item['cmbwc_service_price_override'] );
		}
	}
}

add_action( 'woocommerce_remove_cart_item', 'cmbwc_remove_child_lines_with_parent', 10, 2 );

function cmbwc_remove_child_lines_with_parent( $cart_item_key, $cart ) {
	$removed = isset( $cart->removed_cart_contents[ $cart_item_key ] ) ? $cart->removed_cart_contents[ $cart_item_key ] : null;

	if ( empty( $removed['cmbwc_data']['group_id'] ) ) {
		return;
	}

	$group_id = $removed['cmbwc_data']['group_id'];

	foreach ( $cart->get_cart() as $child_key => $child_item ) {
		if ( empty( $child_item['cmbwc_child_item']['group_id'] ) ) {
			continue;
		}

		if ( $group_id !== $child_item['cmbwc_child_item']['group_id'] ) {
			continue;
		}

		$cart->remove_cart_item( $child_key );
	}
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
			$item->add_meta_data( '_cmbwc_group_id', sanitize_text_field( $data['group_id'] ) );
		}

		return;
	}

	if ( cmbwc_is_child_cart_item( $values ) ) {
		$child = $values['cmbwc_child_item'];

		if ( ! empty( $child['parent_name'] ) ) {
			$item->add_meta_data( 'Hører til', sanitize_text_field( $child['parent_name'] ) );
		}

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

	if ( 'Indhold' === $key ) {
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
