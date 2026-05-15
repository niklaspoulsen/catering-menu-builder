<?php
/**
 * WooCommerce Store API integration for Catering Menu Builder.
 *
 * Exposes Catering Menu Builder cart item metadata to WooCommerce Cart/Checkout Blocks.
 *
 * Classic WooCommerce cart/checkout still uses the existing PHP hooks in cart-order.php.
 * This file only adds Store API data for Block Cart / Block Checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_blocks_loaded', 'cmbwc_register_store_api_cart_item_extension' );

if ( ! function_exists( 'cmbwc_register_store_api_cart_item_extension' ) ) {
	function cmbwc_register_store_api_cart_item_extension() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'catering-menu-builder',
				'data_callback'   => 'cmbwc_store_api_cart_item_data',
				'schema_callback' => 'cmbwc_store_api_cart_item_schema',
				'schema_type'     => ARRAY_A,
			)
		);
	}
}

if ( ! function_exists( 'cmbwc_store_api_cart_item_data' ) ) {
	function cmbwc_store_api_cart_item_data( $cart_item ) {
		$data = cmbwc_store_api_default_cart_item_data();

		if ( ! is_array( $cart_item ) ) {
			return $data;
		}

		/*
		 * Parent menu line.
		 */
		if ( ! empty( $cart_item['cmbwc_data'] ) && is_array( $cart_item['cmbwc_data'] ) ) {
			$cmbwc_data = $cart_item['cmbwc_data'];
			$group_id   = ! empty( $cmbwc_data['group_id'] ) ? sanitize_text_field( $cmbwc_data['group_id'] ) : '';

			$data['is_menu']  = true;
			$data['group_id'] = $group_id;
			$data['covers']   = ! empty( $cmbwc_data['covers'] ) ? absint( $cmbwc_data['covers'] ) : 0;

			if ( ! empty( $cmbwc_data['included_names'] ) && is_array( $cmbwc_data['included_names'] ) ) {
				$data['included'] = cmbwc_store_api_clean_string_list( $cmbwc_data['included_names'] );
			}

			if ( $group_id && function_exists( 'cmbwc_get_cart_children_for_group' ) ) {
				$children = cmbwc_get_cart_children_for_group( $group_id );

				if ( ! empty( $children['addons'] ) && is_array( $children['addons'] ) ) {
					foreach ( $children['addons'] as $addon ) {
						if ( empty( $addon['name'] ) ) {
							continue;
						}

						$data['addons'][] = array(
							'name' => sanitize_text_field( $addon['name'] ),
							'qty'  => ! empty( $addon['qty'] ) ? absint( $addon['qty'] ) : 1,
						);
					}
				}

				if ( ! empty( $children['services'] ) && is_array( $children['services'] ) ) {
					foreach ( $children['services'] as $service ) {
						if ( empty( $service['name'] ) ) {
							continue;
						}

						$data['services'][] = array(
							'name' => sanitize_text_field( $service['name'] ),
							'qty'  => ! empty( $service['qty'] ) ? absint( $service['qty'] ) : 1,
						);
					}
				}
			}

			return $data;
		}

		/*
		 * Child line: add-on or service.
		 */
		if ( ! empty( $cart_item['cmbwc_child_item'] ) && is_array( $cart_item['cmbwc_child_item'] ) ) {
			$child = $cart_item['cmbwc_child_item'];

			$child_type = ! empty( $child['child_type'] ) ? sanitize_text_field( $child['child_type'] ) : '';
			$group_id   = ! empty( $child['group_id'] ) ? sanitize_text_field( $child['group_id'] ) : '';

			$data['is_child']           = true;
			$data['child_type']         = $child_type;
			$data['group_id']           = $group_id;
			$data['parent_product_id']  = ! empty( $child['parent_product_id'] ) ? absint( $child['parent_product_id'] ) : 0;
			$data['parent_name']        = ! empty( $child['parent_name'] ) ? sanitize_text_field( $child['parent_name'] ) : '';
			$data['display_type_label'] = ! empty( $child['display_type_label'] ) ? sanitize_text_field( $child['display_type_label'] ) : '';

			if ( 'service' === $child_type ) {
				$data['is_child_service'] = true;
				$data['is_locked']        = true;
				$data['is_deposit']       = ! empty( $child['is_deposit'] ) && 'yes' === $child['is_deposit'];

				if ( function_exists( 'cmbwc_target_service_qty' ) ) {
					$data['locked_qty'] = absint( cmbwc_target_service_qty( $cart_item ) );
				} elseif ( ! empty( $child['locked_qty'] ) ) {
					$data['locked_qty'] = absint( $child['locked_qty'] );
				}

				if ( $data['locked_qty'] < 1 ) {
					$data['locked_qty'] = 1;
				}
			}

			return $data;
		}

		return $data;
	}
}

if ( ! function_exists( 'cmbwc_store_api_default_cart_item_data' ) ) {
	function cmbwc_store_api_default_cart_item_data() {
		return array(
			'is_menu'            => false,
			'is_child'           => false,
			'is_child_service'   => false,
			'is_locked'          => false,
			'is_deposit'         => false,
			'group_id'           => '',
			'child_type'         => '',
			'parent_product_id'  => 0,
			'parent_name'        => '',
			'display_type_label' => '',
			'covers'             => 0,
			'locked_qty'         => 0,
			'included'           => array(),
			'addons'             => array(),
			'services'           => array(),
		);
	}
}

if ( ! function_exists( 'cmbwc_store_api_clean_string_list' ) ) {
	function cmbwc_store_api_clean_string_list( $items ) {
		$clean = array();

		if ( ! is_array( $items ) ) {
			return $clean;
		}

		foreach ( $items as $item ) {
			$item = sanitize_text_field( $item );

			if ( '' !== $item ) {
				$clean[] = $item;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}

if ( ! function_exists( 'cmbwc_store_api_cart_item_schema' ) ) {
	function cmbwc_store_api_cart_item_schema() {
		return array(
			'is_menu'            => array(
				'description' => 'Whether this cart item is a Catering Menu Builder parent menu line.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_child'           => array(
				'description' => 'Whether this cart item is a Catering Menu Builder child line.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_child_service'   => array(
				'description' => 'Whether this cart item is a locked service child line.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_locked'          => array(
				'description' => 'Whether this cart item should be treated as locked in Block Cart.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_deposit'         => array(
				'description' => 'Whether this service line is a deposit line.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'group_id'           => array(
				'description' => 'Internal Catering Menu Builder group ID.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'child_type'         => array(
				'description' => 'Child line type, for example addon or service.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'parent_product_id'  => array(
				'description' => 'Parent product ID.',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'parent_name'        => array(
				'description' => 'Parent product name.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'display_type_label' => array(
				'description' => 'Display label for the child line type.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'covers'             => array(
				'description' => 'Number of covers.',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'locked_qty'         => array(
				'description' => 'Locked quantity for service lines.',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'included'           => array(
				'description' => 'Included menu item names.',
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type' => 'string',
				),
			),
			'addons'             => array(
				'description' => 'Selected add-ons.',
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'string',
						),
						'qty'  => array(
							'type' => 'integer',
						),
					),
				),
			),
			'services'           => array(
				'description' => 'Selected services.',
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'string',
						),
						'qty'  => array(
							'type' => 'integer',
						),
					),
				),
			),
		);
	}
}
