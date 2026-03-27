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

			$items_output   = array();
			$addons_output  = array();
			$service_output = array();
			$covers_total   = 0;

			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}

				$item_name = trim( (string) $item->get_name() );
				$item_qty  = (int) $item->get_quantity();
				$covers    = absint( $item->get_meta( 'Kuverter', true ) );
				$addons    = trim( (string) $item->get_meta( 'Valgte tilvalg', true ) );
				$service   = trim( (string) $item->get_meta( 'Valgt service', true ) );

				if ( $covers > 0 ) {
					$covers_total += $covers;
				}

				$label = $item_name;

				if ( $item_qty > 1 ) {
					$label = $item_qty . ' x ' . $item_name;
				}

				if ( $covers > 0 ) {
					$label .= ' (' . $covers . ' kuverter)';
				}

				$items_output[] = $label;

				if ( '' !== $addons ) {
					$addon_lines = preg_split( '/\r\n|\r|\n/', $addons );

					foreach ( $addon_lines as $addon_line ) {
						$addon_line = trim( $addon_line );

						if ( '' !== $addon_line ) {
							$addons_output[] = $addon_line;
						}
					}
				}

				if ( '' !== $service ) {
					$service_lines = preg_split( '/\r\n|\r|\n/', $service );

					foreach ( $service_lines as $service_line ) {
						$service_line = trim( $service_line );

						if ( '' !== $service_line ) {
							$service_output[] = $service_line;
						}
					}
				}
			}

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

			$mark_printed_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cmbwc_mark_bon_printed&order_id=' . $order->get_id() ),
				'cmbwc_mark_bon_printed_' . $order->get_id()
			);

			$reset_printed_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cmbwc_reset_bon_printed&order_id=' . $order->get_id() ),
				'cmbwc_reset_bon_printed_' . $order->get_id()
			);

			$status_update_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cmbwc_update_production_status&order_id=' . $order->get_id() ),
				'cmbwc_update_production_status_' . $order->get_id()
			);

			$is_printed = function_exists( 'cmbwc_is_order_bon_printed' ) ? cmbwc_is_order_bon_printed( $order->get_id() ) : false;

			$production_status_label = cmbwc_get_production_status_label( $production_status );
			$production_statuses     = cmbwc_get_production_statuses();
			$production_color        = isset( $production_statuses[ $production_status ]['color'] ) ? $production_statuses[ $production_status ]['color'] : '#5b646d';
			$status_updated_at       = cmbwc_get_order_production_status_updated_at( $order->get_id() );
			$status_updated_by       = cmbwc_get_order_production_status_updated_by( $order->get_id() );

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
				'order_id'                     => $order->get_id(),
				'order_number'                 => $order->get_order_number(),
				'status'                       => $order->get_status(),
				'status_label'                 => wc_get_order_status_name( $order->get_status() ),
				'customer'                     => $customer,
				'delivery_date'                => $delivery_date_raw,
				'delivery_date_ymd'            => $delivery_date_ymd,
				'delivery_date_label'          => cmbwc_get_production_date_label( $delivery_date_raw ),
				'delivery_time'                => $delivery_time,
				'delivery_type'                => cmbwc_get_order_delivery_type_label( $order ),
				'covers_total'                 => $covers_total,
				'items'                        => $items_output,
				'addons'                       => array_values( array_unique( $addons_output ) ),
				'service'                      => array_values( array_unique( $service_output ) ),
				'preview_url'                  => $preview_url,
				'print_url'                    => $print_url,
				'mark_printed_url'             => $mark_printed_url,
				'reset_printed_url'            => $reset_printed_url,
				'admin_order_url'              => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
				'is_printed'                   => $is_printed,
				'quick_data'                   => $quick_data,
				'delivery_sort'                => cmbwc_get_production_sort_datetime( $delivery_date_ymd, $delivery_time, $order ),
				'created_sort'                 => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
				'production_status'            => $production_status,
				'production_status_label'      => $production_status_label,
				'production_status_color'      => $production_color,
				'production_status_update_url' => $status_update_url,
				'production_status_updated_at' => $status_updated_at,
				'production_status_updated_by' => $status_updated_by,
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
				.cmbwc-status-badge {
					display: inline-block;
					padding: 4px 9px;
					border-radius: 999px;
					color: #fff;
					font-size: 12px;
					font-weight: 600;
					line-height: 1.2;
				}
				.cmbwc-status-cell {
					min-width: 180px;
				}
				.cmbwc-status-form {
					margin-top: 8px;
				}
				.cmbwc-status-form select {
					width: 100%;
					max-width: 180px;
				}
				.cmbwc-status-meta {
					margin-top: 6px;
					font-size: 11px;
					color: #646970;
					line-height: 1.35;
				}
				.cmbwc-row-printed {
					background: #f6fff7;
				}
			</style>

			<form method="get" class="cmbwc-filters">
				<input type="hidden" name="page" value="cmbwc-production-overview" />

				<select name="preset">
					<option value="today" <?php selected( $preset, 'today' ); ?>>I dag</option>
					<option value="week" <?php selected( $preset, 'week' ); ?>>Denne uge</option>
					<option value="month" <?php selected( $preset, 'month' ); ?>>Denne måned</option>
					<option value="upcoming" <?php selected( $preset, 'upcoming' ); ?>>Kommende</option>
				</select>

				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />

				<select name="production_status">
					<option value="">Alle interne statusser</option>
					<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $production_status, $status_key ); ?>><?php echo esc_html( $status_data['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<label>
					<input type="checkbox" name="hide_completed" value="yes" <?php checked( $hide_completed, 'yes' ); ?> />
					Skjul afhentet / leveret
				</label>

				<button type="submit" class="button button-primary">Filtrer</button>
			</form>

			<?php if ( empty( $grouped ) ) : ?>
				<p>Ingen ordrer fundet for den valgte periode.</p>
			<?php else : ?>
				<?php foreach ( $grouped as $date_group ) : ?>
					<div class="cmbwc-production-date-group" style="margin-bottom:24px;">
						<h2 style="margin-bottom:8px;">
							<?php echo esc_html( $date_group['label'] ); ?>
							<small style="font-weight:400;">
								- <?php echo esc_html( $date_group['covers_total'] ); ?> kuverter
							</small>
						</h2>

						<table class="widefat striped">
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
								<?php foreach ( $date_group['rows'] as $row ) : ?>
									<tr class="<?php echo ! empty( $row['is_printed'] ) ? 'cmbwc-row-printed' : ''; ?>">
										<td>#<?php echo esc_html( $row['order_number'] ); ?></td>
										<td><?php echo esc_html( $row['customer'] ); ?></td>
										<td><?php echo esc_html( $row['delivery_time'] ? $row['delivery_time'] : '-' ); ?></td>
										<td><?php echo esc_html( $row['delivery_type'] ); ?></td>
										<td><?php echo esc_html( $row['covers_total'] ); ?></td>
										<td>
											<?php if ( ! empty( $row['items'] ) ) : ?>
												<ul style="margin:0;">
													<?php foreach ( $row['items'] as $item_label ) : ?>
														<li><?php echo esc_html( $item_label ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $row['addons'] ) ) : ?>
												<ul style="margin:0;">
													<?php foreach ( $row['addons'] as $addon_label ) : ?>
														<li><?php echo esc_html( $addon_label ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $row['service'] ) ) : ?>
												<ul style="margin:0;">
													<?php foreach ( $row['service'] as $service_label ) : ?>
														<li><?php echo esc_html( $service_label ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
										<td class="cmbwc-status-cell">
											<span class="cmbwc-status-badge" style="background: <?php echo esc_attr( $row['production_status_color'] ); ?>;">
												<?php echo esc_html( $row['production_status_label'] ); ?>
											</span>

											<form method="post" action="<?php echo esc_url( $row['production_status_update_url'] ); ?>" class="cmbwc-status-form">
												<input type="hidden" name="order_id" value="<?php echo esc_attr( $row['order_id'] ); ?>" />
												<?php wp_nonce_field( 'cmbwc_update_production_status_' . $row['order_id'] ); ?>
												<select name="production_status" onchange="this.form.submit()">
													<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
														<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $row['production_status'], $status_key ); ?>><?php echo esc_html( $status_data['label'] ); ?></option>
													<?php endforeach; ?>
												</select>
												<noscript><button type="submit" class="button button-small" style="margin-top:6px;">Gem</button></noscript>
											</form>

											<?php if ( ! empty( $row['production_status_updated_at'] ) || ! empty( $row['production_status_updated_by'] ) ) : ?>
												<div class="cmbwc-status-meta">
													<?php if ( ! empty( $row['production_status_updated_at'] ) ) : ?>
														<div>Opdateret: <?php echo esc_html( $row['production_status_updated_at'] ); ?></div>
													<?php endif; ?>
													<?php if ( ! empty( $row['production_status_updated_by'] ) ) : ?>
														<div>Af: <?php echo esc_html( $row['production_status_updated_by'] ); ?></div>
													<?php endif; ?>
												</div>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $row['status_label'] ); ?></td>
										<td>
											<a class="button button-small" href="<?php echo esc_url( $row['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer">Forhåndsvis</a>
											<a class="button button-small" href="<?php echo esc_url( $row['print_url'] ); ?>">Print</a>
											<a class="button button-small" href="<?php echo esc_url( $row['admin_order_url'] ); ?>">Åbn ordre</a>

											<?php if ( ! empty( $row['is_printed'] ) ) : ?>
												<a class="button button-small" href="<?php echo esc_url( $row['reset_printed_url'] ); ?>">Nulstil print</a>
											<?php else : ?>
												<a class="button button-small" href="<?php echo esc_url( $row['mark_printed_url'] ); ?>">Marker som printet</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
