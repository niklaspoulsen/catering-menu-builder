<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cmbwc_normalize_delivery_date_to_ymd' ) ) {
	function cmbwc_normalize_delivery_date_to_ymd( $date_string ) {
		$date_string = trim( (string) $date_string );

		if ( '' === $date_string ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
			return $date_string;
		}

		if ( preg_match( '/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $date_string, $matches ) ) {
			$day   = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
			$month = str_pad( $matches[2], 2, '0', STR_PAD_LEFT );
			$year  = $matches[3];

			return $year . '-' . $month . '-' . $day;
		}

		$timestamp = strtotime( $date_string );

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( 'Y-m-d', $timestamp );
	}
}

if ( ! function_exists( 'cmbwc_get_production_date_label' ) ) {
	function cmbwc_get_production_date_label( $delivery_date ) {
		$delivery_date = trim( (string) $delivery_date );

		if ( '' === $delivery_date ) {
			return '';
		}

		if ( function_exists( 'cmbwc_bon_format_delivery_date' ) ) {
			return cmbwc_bon_format_delivery_date( $delivery_date );
		}

		return $delivery_date;
	}
}

if ( ! function_exists( 'cmbwc_get_production_preset_dates' ) ) {
	function cmbwc_get_production_preset_dates( $preset ) {
		$today_ts = current_time( 'timestamp' );
		$today    = wp_date( 'Y-m-d', $today_ts );

		switch ( $preset ) {
			case 'today':
				return array(
					'date_from' => $today,
					'date_to'   => $today,
				);

			case 'week':
				$weekday = (int) wp_date( 'N', $today_ts );
				$start   = strtotime( '-' . ( $weekday - 1 ) . ' days', $today_ts );
				$end     = strtotime( '+6 days', $start );

				return array(
					'date_from' => wp_date( 'Y-m-d', $start ),
					'date_to'   => wp_date( 'Y-m-d', $end ),
				);

			case 'month':
				return array(
					'date_from' => wp_date( 'Y-m-01', $today_ts ),
					'date_to'   => wp_date( 'Y-m-t', $today_ts ),
				);
		}

		return array(
			'date_from' => $today,
			'date_to'   => wp_date( 'Y-m-d', strtotime( '+14 days', $today_ts ) ),
		);
	}
}

if ( ! function_exists( 'cmbwc_get_production_sort_datetime' ) ) {
	function cmbwc_get_production_sort_datetime( $delivery_date, $delivery_time, $order = null ) {
		$delivery_date = trim( (string) $delivery_date );
		$delivery_time = trim( (string) $delivery_time );

		if ( '' === $delivery_date ) {
			return 0;
		}

		$date_part = $delivery_date;

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_part ) ) {
			$date_part = cmbwc_normalize_delivery_date_to_ymd( $date_part );
		}

		if ( '' === $date_part ) {
			return 0;
		}

		$time_part = '23:59';

		if ( preg_match( '/^\d{1,2}:\d{2}$/', $delivery_time ) ) {
			$time_part = $delivery_time;
		}

		$timestamp = strtotime( $date_part . ' ' . $time_part );

		if ( $timestamp ) {
			return $timestamp;
		}

		if ( $order && $order->get_date_created() ) {
			return $order->get_date_created()->getTimestamp();
		}

		return 0;
	}
}

if ( ! function_exists( 'cmbwc_get_production_statuses' ) ) {
	function cmbwc_get_production_statuses() {
		return array(
			'afventer' => array(
				'label' => 'Afventer',
				'color' => '#5b646d',
			),
			'i_gang' => array(
				'label' => 'I gang',
				'color' => '#c77700',
			),
			'klar' => array(
				'label' => 'Klar',
				'color' => '#0a7a35',
			),
			'leveret' => array(
				'label' => 'Afhentet / Leveret',
				'color' => '#135e96',
			),
		);
	}
}

if ( ! function_exists( 'cmbwc_get_default_production_status' ) ) {
	function cmbwc_get_default_production_status() {
		return 'afventer';
	}
}

if ( ! function_exists( 'cmbwc_get_order_production_status' ) ) {
	function cmbwc_get_order_production_status( $order_id ) {
		$order_id = absint( $order_id );
		$statuses = cmbwc_get_production_statuses();
		$status   = (string) get_post_meta( $order_id, '_cmbwc_production_status', true );

		if ( ! isset( $statuses[ $status ] ) ) {
			$status = cmbwc_get_default_production_status();
		}

		return $status;
	}
}

if ( ! function_exists( 'cmbwc_get_production_status_label' ) ) {
	function cmbwc_get_production_status_label( $status ) {
		$statuses = cmbwc_get_production_statuses();

		if ( isset( $statuses[ $status ]['label'] ) ) {
			return $statuses[ $status ]['label'];
		}

		$default = cmbwc_get_default_production_status();
		return isset( $statuses[ $default ]['label'] ) ? $statuses[ $default ]['label'] : 'Afventer';
	}
}

if ( ! function_exists( 'cmbwc_update_order_production_status' ) ) {
	function cmbwc_update_order_production_status( $order_id, $status ) {
		$order_id = absint( $order_id );
		$statuses = cmbwc_get_production_statuses();

		if ( ! $order_id || ! isset( $statuses[ $status ] ) ) {
			return false;
		}

		update_post_meta( $order_id, '_cmbwc_production_status', $status );
		update_post_meta( $order_id, '_cmbwc_production_status_updated_at', current_time( 'mysql' ) );

		$current_user = wp_get_current_user();
		if ( $current_user && ! empty( $current_user->display_name ) ) {
			update_post_meta( $order_id, '_cmbwc_production_status_updated_by', $current_user->display_name );
		}

		return true;
	}
}

if ( ! function_exists( 'cmbwc_get_order_production_status_updated_at' ) ) {
	function cmbwc_get_order_production_status_updated_at( $order_id ) {
		return (string) get_post_meta( absint( $order_id ), '_cmbwc_production_status_updated_at', true );
	}
}

if ( ! function_exists( 'cmbwc_get_order_production_status_updated_by' ) ) {
	function cmbwc_get_order_production_status_updated_by( $order_id ) {
		return (string) get_post_meta( absint( $order_id ), '_cmbwc_production_status_updated_by', true );
	}
}

if ( ! function_exists( 'cmbwc_format_production_status_meta' ) ) {
	function cmbwc_format_production_status_meta( $datetime_string, $user_name ) {
		$datetime_string = trim( (string) $datetime_string );
		$user_name       = trim( (string) $user_name );

		$parts = array();

		if ( '' !== $datetime_string ) {
			$timestamp = strtotime( $datetime_string );

			if ( $timestamp ) {
				$parts[] = wp_date( 'd/m Y - H:i', $timestamp );
			} else {
				$parts[] = $datetime_string;
			}
		}

		if ( '' !== $user_name ) {
			$parts[] = $user_name;
		}

		return implode( ' - ', $parts );
	}
}

if ( ! function_exists( 'cmbwc_sort_production_rows' ) ) {
	function cmbwc_sort_production_rows( $a, $b ) {
		$a_sort = isset( $a['delivery_sort'] ) ? (int) $a['delivery_sort'] : 0;
		$b_sort = isset( $b['delivery_sort'] ) ? (int) $b['delivery_sort'] : 0;

		if ( $a_sort === $b_sort ) {
			$a_created = isset( $a['created_sort'] ) ? (int) $a['created_sort'] : 0;
			$b_created = isset( $b['created_sort'] ) ? (int) $b['created_sort'] : 0;

			if ( $a_created === $b_created ) {
				return 0;
			}

			return ( $a_created < $b_created ) ? -1 : 1;
		}

		return ( $a_sort < $b_sort ) ? -1 : 1;
	}
}

if ( ! function_exists( 'cmbwc_group_production_rows_by_date' ) ) {
	function cmbwc_group_production_rows_by_date( $rows ) {
		$grouped = array();

		foreach ( $rows as $row ) {
			$key = ! empty( $row['delivery_date_ymd'] ) ? $row['delivery_date_ymd'] : 'unknown';

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'label'        => ! empty( $row['delivery_date_label'] ) ? $row['delivery_date_label'] : $row['delivery_date'],
					'rows'         => array(),
					'covers_total' => 0,
				);
			}

			$grouped[ $key ]['rows'][] = $row;
			$grouped[ $key ]['covers_total'] += isset( $row['covers_total'] ) ? (int) $row['covers_total'] : 0;
		}

		return $grouped;
	}
}

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

if ( ! function_exists( 'cmbwc_find_next_unprinted_order_in_rows' ) ) {
	function cmbwc_find_next_unprinted_order_in_rows( $rows ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( empty( $row['is_printed'] ) ) {
				return $row;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'cmbwc_get_production_orders' ) ) {
	function cmbwc_get_production_orders( $args = array() ) {
		$defaults = array(
			'date_from'         => '',
			'date_to'           => '',
			'limit'             => -1,
			'production_status' => '',
			'hide_completed'    => 'no',
		);

		$args = wp_parse_args( $args, $defaults );

		$order_query_args = array(
			'limit'   => $args['limit'],
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
			'status'  => array_keys( wc_get_order_statuses() ),
		);

		$orders = wc_get_orders( $order_query_args );

		if ( empty( $orders ) ) {
			return array();
		}

		$rows = array();

		foreach ( $orders as $order ) {
			if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			if ( 'cancelled' === $order->get_status() ) {
				continue;
			}

			$delivery_date_raw = trim( (string) $order->get_meta( '_delivery_date' ) );
			$delivery_time     = trim( (string) $order->get_meta( '_delivery_time' ) );

			if ( '' === $delivery_date_raw ) {
				continue;
			}

			$delivery_date_ymd = cmbwc_normalize_delivery_date_to_ymd( $delivery_date_raw );

			if ( '' === $delivery_date_ymd ) {
				continue;
			}

			if ( ! empty( $args['date_from'] ) && $delivery_date_ymd < $args['date_from'] ) {
				continue;
			}

			if ( ! empty( $args['date_to'] ) && $delivery_date_ymd > $args['date_to'] ) {
				continue;
			}

			$production_status = cmbwc_get_order_production_status( $order->get_id() );

			if ( ! empty( $args['production_status'] ) && $production_status !== $args['production_status'] ) {
				continue;
			}

			if ( 'yes' === $args['hide_completed'] && 'leveret' === $production_status ) {
				continue;
			}

			$items_output        = array();
			$addons_output       = array();
			$service_output      = array();
			$covers_total        = 0;
			$addon_labels_seen   = array();
			$service_labels_seen = array();

			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}

				$addons  = trim( (string) $item->get_meta( 'Valgte tilvalg', true ) );
				$service = trim( (string) $item->get_meta( 'Valgt service', true ) );

				if ( '' !== $addons ) {
					$addon_lines = preg_split( '/\r\n|\r|\n/', $addons );

					foreach ( $addon_lines as $addon_line ) {
						$addon_line = trim( (string) $addon_line );

						if ( '' !== $addon_line ) {
							$addons_output[] = $addon_line;
							$addon_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $addon_line ) : strtolower( $addon_line );
							$addon_labels_seen[ $addon_key ] = true;
						}
					}
				}

				if ( '' !== $service ) {
					$service_lines = preg_split( '/\r\n|\r|\n/', $service );

					foreach ( $service_lines as $service_line ) {
						$service_line = trim( (string) $service_line );

						if ( '' !== $service_line ) {
							$service_output[] = $service_line;
							$service_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $service_line ) : strtolower( $service_line );
							$service_labels_seen[ $service_key ] = true;
						}
					}
				}
			}

			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}

				$item_name  = trim( (string) $item->get_name() );
				$item_qty   = (int) $item->get_quantity();
				$covers     = absint( $item->get_meta( 'Kuverter', true ) );
				$child_type = trim( (string) $item->get_meta( '_cmbwc_child_type', true ) );

				$base_label = $item_name;
				if ( $item_qty > 1 ) {
					$base_label = $item_qty . ' x ' . $item_name;
				}

				$label_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $base_label ) : strtolower( $base_label );

				if ( 'service' === $child_type ) {
					continue;
				}

				if ( 'addon' === $child_type ) {
					continue;
				}

				if ( isset( $addon_labels_seen[ $label_key ] ) || isset( $service_labels_seen[ $label_key ] ) ) {
					continue;
				}

				if ( $covers > 0 ) {
					$covers_total += $covers;
				}

				$items_output[] = $base_label;
			}

			$items_output   = array_values( array_unique( $items_output ) );
			$addons_output  = array_values( array_unique( $addons_output ) );
			$service_output = array_values( array_unique( $service_output ) );

			$customer = trim( $order->get_formatted_billing_full_name() );

			if ( '' === $customer ) {
				$customer = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
			}

			if ( '' === $customer ) {
				$customer = '-';
			}

			$preview_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cmbwc_preview_bon&order_id=' . $order->get_id() ),
				'cmbwc_preview_bon_' . $order->get_id()
			);

			$print_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cmbwc_manual_print_bon&order_id=' . $order->get_id() ),
				'cmbwc_manual_print_bon_' . $order->get_id()
			);

			$status_update_base_url = admin_url( 'admin-post.php?action=cmbwc_update_production_status&order_id=' . $order->get_id() );
			$status_update_nonce    = wp_create_nonce( 'cmbwc_update_production_status_' . $order->get_id() );

			$is_printed = function_exists( 'cmbwc_is_order_bon_printed' ) ? cmbwc_is_order_bon_printed( $order->get_id() ) : false;

			$production_status_label = cmbwc_get_production_status_label( $production_status );
			$production_statuses     = cmbwc_get_production_statuses();
			$production_color        = isset( $production_statuses[ $production_status ]['color'] ) ? $production_statuses[ $production_status ]['color'] : '#5b646d';
			$status_updated_at       = cmbwc_get_order_production_status_updated_at( $order->get_id() );
			$status_updated_by       = cmbwc_get_order_production_status_updated_by( $order->get_id() );
			$status_meta_label       = cmbwc_format_production_status_meta( $status_updated_at, $status_updated_by );

			$quick_data = array(
				'order_number'      => $order->get_order_number(),
				'customer'          => $customer,
				'delivery_date'     => cmbwc_get_production_date_label( $delivery_date_raw ),
				'delivery_time'     => $delivery_time ? $delivery_time : '-',
				'delivery_type'     => cmbwc_get_order_delivery_type_label( $order ),
				'covers_total'      => $covers_total,
				'service'           => array_values( array_unique( $service_output ) ),
				'status'            => wc_get_order_status_name( $order->get_status() ),
				'production_status' => $production_status_label,
				'items'             => $items_output,
				'addons'            => array_values( array_unique( $addons_output ) ),
				'admin_order_url'   => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			);

			$rows[] = array(
				'order_id'                          => $order->get_id(),
				'order_number'                      => $order->get_order_number(),
				'status'                            => $order->get_status(),
				'status_label'                      => wc_get_order_status_name( $order->get_status() ),
				'customer'                          => $customer,
				'delivery_date'                     => $delivery_date_raw,
				'delivery_date_ymd'                 => $delivery_date_ymd,
				'delivery_date_label'               => cmbwc_get_production_date_label( $delivery_date_raw ),
				'delivery_time'                     => $delivery_time,
				'delivery_type'                     => cmbwc_get_order_delivery_type_label( $order ),
				'covers_total'                      => $covers_total,
				'items'                             => $items_output,
				'addons'                            => array_values( array_unique( $addons_output ) ),
				'service'                           => array_values( array_unique( $service_output ) ),
				'preview_url'                       => $preview_url,
				'print_url'                         => $print_url,
				'admin_order_url'                   => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
				'is_printed'                        => $is_printed,
				'quick_data'                        => $quick_data,
				'delivery_sort'                     => cmbwc_get_production_sort_datetime( $delivery_date_ymd, $delivery_time, $order ),
				'created_sort'                      => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
				'production_status'                 => $production_status,
				'production_status_label'           => $production_status_label,
				'production_status_color'           => $production_color,
				'production_status_update_base_url' => $status_update_base_url,
				'production_status_nonce'           => $status_update_nonce,
				'production_status_updated_at'      => $status_updated_at,
				'production_status_updated_by'      => $status_updated_by,
				'production_status_meta_label'      => $status_meta_label,
			);
		}

		if ( ! empty( $rows ) ) {
			usort( $rows, 'cmbwc_sort_production_rows' );
		}

		return $rows;
	}
}

add_action( 'admin_post_cmbwc_update_production_status', 'cmbwc_handle_update_production_status' );

if ( ! function_exists( 'cmbwc_handle_update_production_status' ) ) {
	function cmbwc_handle_update_production_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang.' );
		}

		$order_id = isset( $_REQUEST['order_id'] ) ? absint( wp_unslash( $_REQUEST['order_id'] ) ) : 0;
		$status   = isset( $_REQUEST['production_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['production_status'] ) ) : '';

		if ( ! $order_id ) {
			wp_die( 'Manglende ordre-id.' );
		}

		check_admin_referer( 'cmbwc_update_production_status_' . $order_id );

		cmbwc_update_order_production_status( $order_id, $status );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=cmbwc-production-overview' );
		}

		$redirect = add_query_arg(
			array(
				'cmbwc_production_status_saved' => 1,
				'cmbwc_order_id'                => $order_id,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}

if ( ! function_exists( 'cmbwc_render_production_overview_page' ) ) {
	function cmbwc_render_production_overview_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$preset            = isset( $_GET['preset'] ) ? sanitize_text_field( wp_unslash( $_GET['preset'] ) ) : 'upcoming';
		$date_from         = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to           = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$production_status = isset( $_GET['production_status'] ) ? sanitize_text_field( wp_unslash( $_GET['production_status'] ) ) : '';
		$hide_completed    = isset( $_GET['hide_completed'] ) ? sanitize_text_field( wp_unslash( $_GET['hide_completed'] ) ) : 'no';
		$all_statuses      = cmbwc_get_production_statuses();

		if ( '' === $date_from && '' === $date_to ) {
			$preset_dates = cmbwc_get_production_preset_dates( $preset );
			$date_from    = $preset_dates['date_from'];
			$date_to      = $preset_dates['date_to'];
		}

		$rows    = cmbwc_get_production_orders(
			array(
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'production_status' => $production_status,
				'hide_completed'    => $hide_completed,
			)
		);
		$grouped = cmbwc_group_production_rows_by_date( $rows );

		?>
		<div class="wrap cmbwc-production-wrap">
			<h1>Produktionsoversigt</h1>

			<?php if ( ! empty( $_GET['cmbwc_production_status_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Produktionsstatus er opdateret.</p></div>
			<?php endif; ?>

			<style>
				.cmbwc-filters {
					display: flex;
					gap: 10px;
					flex-wrap: wrap;
					align-items: center;
					margin: 16px 0 20px;
				}
				.cmbwc-status-cell {
					min-width: 185px;
					position: relative;
				}
				.cmbwc-status-toggle {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 6px 12px;
					border: 0;
					border-radius: 999px;
					color: #fff;
					font-weight: 600;
					font-size: 13px;
					line-height: 1.2;
					cursor: pointer;
					box-shadow: none;
				}
				.cmbwc-status-toggle:hover,
				.cmbwc-status-toggle:focus {
					color: #fff;
					opacity: 0.92;
					outline: none;
					box-shadow: none;
				}
				.cmbwc-status-toggle .dashicons {
					font-size: 14px;
					width: 14px;
					height: 14px;
				}
				.cmbwc-status-menu {
					position: absolute;
					top: calc(100% + 6px);
					left: 0;
					z-index: 50;
					display: none;
					min-width: 210px;
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 8px;
					box-shadow: 0 6px 18px rgba(0,0,0,0.12);
					padding: 6px;
				}
				.cmbwc-status-cell.is-open .cmbwc-status-menu {
					display: block;
				}
				.cmbwc-status-option {
					display: block;
					width: 100%;
					text-align: left;
					background: transparent;
					border: 0;
					padding: 9px 10px;
					border-radius: 6px;
					text-decoration: none;
					color: #1d2327;
					box-sizing: border-box;
				}
				.cmbwc-status-option:hover,
				.cmbwc-status-option:focus {
					background: #f6f7f7;
					color: #1d2327;
					outline: none;
				}
				.cmbwc-status-help {
					display: block;
					font-size: 11px;
					color: #646970;
					margin-top: 3px;
				}
				.cmbwc-status-meta {
					margin-top: 6px;
					font-size: 11px;
					color: #646970;
					line-height: 1.35;
				}
				.cmbwc-day-block {
					margin-top: 26px;
				}
				.cmbwc-day-title {
					margin: 0 0 10px;
					font-size: 16px;
					font-weight: 700;
				}
				.cmbwc-day-cover-count {
					font-weight: 400;
					color: #50575e;
				}
				.cmbwc-next-print {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					margin: 10px 0 0;
					padding: 7px 11px;
					border-radius: 999px;
					background: #eef5ff;
					color: #135e96;
					font-weight: 600;
					text-decoration: none;
				}
				.cmbwc-next-print:hover,
				.cmbwc-next-print:focus {
					color: #0b4b77;
				}
				.cmbwc-summary-list {
					margin: 0;
					padding-left: 16px;
				}
				.cmbwc-summary-list li {
					margin: 0 0 3px;
				}
				.cmbwc-muted {
					color: #646970;
				}
				.cmbwc-actions {
					display: flex;
					flex-wrap: wrap;
					gap: 6px;
				}
				.cmbwc-table td,
				.cmbwc-table th {
					vertical-align: top;
				}
				.cmbwc-quick-preview {
					max-width: 680px;
				}
			</style>

			<form method="get" class="cmbwc-filters">
				<input type="hidden" name="page" value="cmbwc-production-overview" />

				<select name="preset">
					<option value="today" <?php selected( $preset, 'today' ); ?>>I dag</option>
					<option value="week" <?php selected( $preset, 'week' ); ?>>Denne uge</option>
					<option value="month" <?php selected( $preset, 'month' ); ?>>Denne måned</option>
					<option value="upcoming" <?php selected( $preset, 'upcoming' ); ?>>Kommende 14 dage</option>
				</select>

				<label>
					Fra
					<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				</label>

				<label>
					Til
					<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
				</label>

				<label>
					Intern status
					<select name="production_status">
						<option value="">Alle</option>
						<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $production_status, $status_key ); ?>>
								<?php echo esc_html( $status_data['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					<input type="checkbox" name="hide_completed" value="yes" <?php checked( $hide_completed, 'yes' ); ?> />
					Skjul afhentet / leveret
				</label>

				<button type="submit" class="button button-primary">Filtrer</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>" class="button">Nulstil</a>
			</form>

			<?php if ( empty( $rows ) ) : ?>
				<p>Ingen ordrer fundet for det valgte interval.</p>
			<?php else : ?>
				<?php foreach ( $grouped as $group ) : ?>
					<?php $next_unprinted = cmbwc_find_next_unprinted_order_in_rows( $group['rows'] ); ?>
					<div class="cmbwc-day-block">
						<h2 class="cmbwc-day-title">
							<?php echo esc_html( $group['label'] ); ?>
							<span class="cmbwc-day-cover-count">- <?php echo esc_html( (string) $group['covers_total'] ); ?> kuverter</span>
						</h2>

						<?php if ( $next_unprinted && ! empty( $next_unprinted['print_url'] ) ) : ?>
							<a class="cmbwc-next-print" href="<?php echo esc_url( $next_unprinted['print_url'] ); ?>">
								Print næste ikke-printede bon (#<?php echo esc_html( $next_unprinted['order_number'] ); ?>)
							</a>
						<?php endif; ?>

						<table class="widefat striped cmbwc-table" style="margin-top:10px;">
							<thead>
								<tr>
									<th>Ordre</th>
									<th>Kunde</th>
									<th>Tid</th>
									<th>Type</th>
									<th>Kuverter</th>
									<th>Menuer</th>
									<th>Tilvalg</th>
									<th>Service</th>
									<th>Intern status</th>
									<th>Woo-status</th>
									<th>Handlinger</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $group['rows'] as $row ) : ?>
									<tr>
										<td>#<?php echo esc_html( $row['order_number'] ); ?></td>
										<td><?php echo esc_html( $row['customer'] ); ?></td>
										<td><?php echo esc_html( $row['delivery_time'] ? $row['delivery_time'] : '-' ); ?></td>
										<td><?php echo esc_html( $row['delivery_type'] ); ?></td>
										<td><?php echo esc_html( (string) $row['covers_total'] ); ?></td>
										<td>
											<?php if ( ! empty( $row['items'] ) ) : ?>
												<ul class="cmbwc-summary-list">
													<?php foreach ( $row['items'] as $item_line ) : ?>
														<li><?php echo esc_html( $item_line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<span class="cmbwc-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $row['addons'] ) ) : ?>
												<ul class="cmbwc-summary-list">
													<?php foreach ( $row['addons'] as $addon_line ) : ?>
														<li><?php echo esc_html( $addon_line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<span class="cmbwc-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $row['service'] ) ) : ?>
												<ul class="cmbwc-summary-list">
													<?php foreach ( $row['service'] as $service_line ) : ?>
														<li><?php echo esc_html( $service_line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<span class="cmbwc-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="cmbwc-status-cell">
											<?php $status_badge_style = 'background:' . esc_attr( $row['production_status_color'] ) . ';'; ?>
											<button type="button" class="cmbwc-status-toggle" style="<?php echo esc_attr( $status_badge_style ); ?>">
												<span><?php echo esc_html( $row['production_status_label'] ); ?></span>
												<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
											</button>

											<div class="cmbwc-status-menu">
												<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
													<?php
													$status_url = add_query_arg(
														array(
															'order_id'          => $row['order_id'],
															'production_status' => $status_key,
															'_wpnonce'          => $row['production_status_nonce'],
														),
														$row['production_status_update_base_url']
													);
													?>
													<a class="cmbwc-status-option" href="<?php echo esc_url( $status_url ); ?>">
														<strong><?php echo esc_html( $status_data['label'] ); ?></strong>
														<?php if ( $status_key === $row['production_status'] ) : ?>
															<span class="cmbwc-status-help">Nuværende status</span>
														<?php endif; ?>
													</a>
												<?php endforeach; ?>
											</div>

											<?php if ( ! empty( $row['production_status_meta_label'] ) ) : ?>
												<div class="cmbwc-status-meta">
													<?php echo esc_html( $row['production_status_meta_label'] ); ?>
												</div>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $row['status_label'] ); ?></td>
										<td>
											<div class="cmbwc-actions">
												<a class="button button-small" href="<?php echo esc_url( $row['preview_url'] ); ?>" target="_blank" rel="noopener">Forhåndsvis</a>
												<a class="button button-small" href="<?php echo esc_url( $row['print_url'] ); ?>">Print</a>
												<a class="button button-small" href="<?php echo esc_url( $row['admin_order_url'] ); ?>">Åbn ordre</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script>
		document.addEventListener('click', function (event) {
			var clickedToggle = event.target.closest('.cmbwc-status-toggle');
			var clickedCell   = event.target.closest('.cmbwc-status-cell');

			document.querySelectorAll('.cmbwc-status-cell.is-open').forEach(function (cell) {
				if (cell !== clickedCell) {
					cell.classList.remove('is-open');
				}
			});

			if (clickedToggle && clickedCell) {
				event.preventDefault();
				clickedCell.classList.toggle('is-open');
				return;
			}

			if (!clickedCell) {
				document.querySelectorAll('.cmbwc-status-cell.is-open').forEach(function (cell) {
					cell.classList.remove('is-open');
				});
			}
		});
		</script>
		<?php
	}
}
