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
			'date_from' => '',
			'date_to'   => '',
			'limit'     => -1,
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

			$is_printed = function_exists( 'cmbwc_is_order_bon_printed' ) ? cmbwc_is_order_bon_printed( $order->get_id() ) : false;

			$quick_data = array(
				'order_number'    => $order->get_order_number(),
				'customer'        => $customer,
				'delivery_date'   => cmbwc_get_production_date_label( $delivery_date_raw ),
				'delivery_time'   => $delivery_time ? $delivery_time : '-',
				'delivery_type'   => cmbwc_get_order_delivery_type_label( $order ),
				'covers_total'    => $covers_total,
				'service'         => array_values( array_unique( $service_output ) ),
				'status'          => wc_get_order_status_name( $order->get_status() ),
				'items'           => $items_output,
				'addons'          => array_values( array_unique( $addons_output ) ),
				'admin_order_url' => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			);

			$rows[] = array(
				'order_id'           => $order->get_id(),
				'order_number'       => $order->get_order_number(),
				'status'             => $order->get_status(),
				'status_label'       => wc_get_order_status_name( $order->get_status() ),
				'customer'           => $customer,
				'delivery_date'      => $delivery_date_raw,
				'delivery_date_ymd'  => $delivery_date_ymd,
				'delivery_date_label'=> cmbwc_get_production_date_label( $delivery_date_raw ),
				'delivery_time'      => $delivery_time,
				'delivery_type'      => cmbwc_get_order_delivery_type_label( $order ),
				'covers_total'       => $covers_total,
				'items'              => $items_output,
				'addons'             => array_values( array_unique( $addons_output ) ),
				'service'            => array_values( array_unique( $service_output ) ),
				'preview_url'        => $preview_url,
				'print_url'          => $print_url,
				'mark_printed_url'   => $mark_printed_url,
				'reset_printed_url'  => $reset_printed_url,
				'admin_order_url'    => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
				'is_printed'         => $is_printed,
				'quick_data'         => $quick_data,
				'delivery_sort'      => cmbwc_get_production_sort_datetime( $delivery_date_ymd, $delivery_time, $order ),
				'created_sort'       => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
			);
		}

		if ( ! empty( $rows ) ) {
			usort( $rows, 'cmbwc_sort_production_rows' );
		}

		return $rows;
	}
}

if ( ! function_exists( 'cmbwc_render_production_overview_page' ) ) {
	function cmbwc_render_production_overview_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$preset    = isset( $_GET['preset'] ) ? sanitize_text_field( wp_unslash( $_GET['preset'] ) ) : 'upcoming';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		if ( '' === $date_from && '' === $date_to ) {
			$preset_dates = cmbwc_get_production_preset_dates( $preset );
			$date_from    = $preset_dates['date_from'];
			$date_to      = $preset_dates['date_to'];
		}

		$rows    = cmbwc_get_production_orders(
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			)
		);
		$grouped = cmbwc_group_production_rows_by_date( $rows );

		?>
		<div class="wrap">
			<h1>Produktionsoversigt</h1>

			<form method="get" style="margin:16px 0;">
				<input type="hidden" name="page" value="cmbwc-production-overview" />

				<select name="preset">
					<option value="today" <?php selected( $preset, 'today' ); ?>>I dag</option>
					<option value="week" <?php selected( $preset, 'week' ); ?>>Denne uge</option>
					<option value="month" <?php selected( $preset, 'month' ); ?>>Denne måned</option>
					<option value="upcoming" <?php selected( $preset, 'upcoming' ); ?>>Kommende</option>
				</select>

				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />

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
									<th>Status</th>
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
