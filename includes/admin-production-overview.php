<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Konverter leveringsdato til Y-m-d så robust som muligt.
 * Understøtter bl.a.:
 * - 2026-03-16
 * - 16/03/2026
 * - 16-03-2026
 * - 16.03.2026
 */
function cmbwc_normalize_delivery_date_to_ymd( $date_string ) {
	$date_string = trim( (string) $date_string );

	if ( '' === $date_string ) {
		return '';
	}

	// Allerede Y-m-d
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
		return $date_string;
	}

	// d/m/Y eller d-m-Y eller d.m.Y
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

/**
 * Byg label for dato i oversigten.
 */
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

/**
 * Returnér preset-datoer.
 */
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
			$weekday = (int) wp_date( 'N', $today_ts ); // 1 = mandag
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

/**
 * Hent catering-ordrer til produktionsoverblik.
 */
function cmbwc_get_production_orders( $args = array() ) {
	$defaults = array(
		'date_from' => '',
		'date_to'   => '',
		'limit'     => -1,
	);

	$args = wp_parse_args( $args, $defaults );

	// Vi henter bredt og filtrerer selv på leveringsdato,
	// så vi ikke mister ordrer pga. status/editor/HPOs-varianter.
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

		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}

			$item_name = trim( (string) $item->get_name() );
			$item_qty  = (int) $item->get_quantity();
			$covers    = trim( (string) $item->get_meta( 'Kuverter', true ) );
			$addons    = trim( (string) $item->get_meta( 'Tilvalg', true ) );
			$service   = trim( (string) $item->get_meta( 'Service', true ) );

			$label = $item_name;

			if ( $item_qty > 1 ) {
				$label = $item_qty . ' x ' . $item_name;
			}

			if ( '' !== $covers ) {
				$label .= ' (' . absint( $covers ) . ' kuverter)';
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
				$service_output[] = $service;
			}
		}

		$customer = trim( $order->get_formatted_billing_full_name() );

		if ( '' === $customer ) {
			$customer = trim(
				$order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()
			);
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

		$rows[] = array(
			'order_id'                => $order->get_id(),
			'order_number'            => $order->get_order_number(),
			'order_edit_url'          => admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() ),
			'fallback_order_edit_url' => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			'preview_url'             => $preview_url,
			'print_url'               => $print_url,
			'delivery_date'           => $delivery_date_raw,
			'delivery_date_ymd'       => $delivery_date_ymd,
			'delivery_date_label'     => cmbwc_get_production_date_label( $delivery_date_raw ),
			'delivery_time'           => $delivery_time,
			'delivery_sort'           => cmbwc_get_production_sort_datetime( $delivery_date_ymd, $delivery_time, $order ),
			'created_sort'            => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
			'customer'                => $customer,
			'items'                   => $items_output,
			'addons'                  => array_values( array_unique( $addons_output ) ),
			'service'                 => array_values( array_unique( $service_output ) ),
			'status'                  => wc_get_order_status_name( $order->get_status() ),
		);
	}

	usort( $rows, 'cmbwc_sort_production_rows' );

	return $rows;
}

/**
 * Sortering: dato/tid først, derefter ordre-oprettelse.
 */
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

/**
 * Opret timestamp til sortering.
 */
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

/**
 * Gruppér rækker efter dato.
 */
function cmbwc_group_production_rows_by_date( $rows ) {
	$grouped = array();

	foreach ( $rows as $row ) {
		$key = ! empty( $row['delivery_date_ymd'] ) ? $row['delivery_date_ymd'] : 'unknown';

		if ( ! isset( $grouped[ $key ] ) ) {
			$grouped[ $key ] = array(
				'label' => ! empty( $row['delivery_date_label'] ) ? $row['delivery_date_label'] : $row['delivery_date'],
				'rows'  => array(),
			);
		}

		$grouped[ $key ]['rows'][] = $row;
	}

	return $grouped;
}

/**
 * HTML helper til lister.
 */
function cmbwc_render_production_list_html( $items ) {
	if ( empty( $items ) || ! is_array( $items ) ) {
		return '<span style="color:#777;">-</span>';
	}

	$html = '<ul style="margin:0; padding-left:18px;">';

	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item ) . '</li>';
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Render adminside.
 */
function cmbwc_render_production_overview_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$preset    = isset( $_GET['preset'] ) ? sanitize_text_field( wp_unslash( $_GET['preset'] ) ) : '';
	$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
	$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

	if ( in_array( $preset, array( 'today', 'week', 'month' ), true ) ) {
		$preset_dates = cmbwc_get_production_preset_dates( $preset );
		$date_from    = $preset_dates['date_from'];
		$date_to      = $preset_dates['date_to'];
	} elseif ( '' === $date_from && '' === $date_to ) {
		$defaults  = cmbwc_get_production_preset_dates( '' );
		$date_from = $defaults['date_from'];
		$date_to   = $defaults['date_to'];
	}

	$rows    = cmbwc_get_production_orders(
		array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		)
	);
	$grouped = cmbwc_group_production_rows_by_date( $rows );

	$base_url = admin_url( 'admin.php?page=cmbwc-production-overview' );
	?>
	<div class="wrap">
		<h1>Produktionsoverblik</h1>

		<form method="get" style="background:#fff; border:1px solid #ddd; padding:16px; margin:16px 0; max-width:1100px;">
			<input type="hidden" name="page" value="cmbwc-production-overview">

			<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
				<a class="button <?php echo ( 'today' === $preset ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'preset', 'today', $base_url ) ); ?>">I dag</a>
				<a class="button <?php echo ( 'week' === $preset ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'preset', 'week', $base_url ) ); ?>">Denne uge</a>
				<a class="button <?php echo ( 'month' === $preset ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'preset', 'month', $base_url ) ); ?>">Denne måned</a>
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Nulstil</a>
			</div>

			<div style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
				<div>
					<label for="cmbwc-date-from" style="display:block; font-weight:600; margin-bottom:4px;">Fra dato</label>
					<input type="date" id="cmbwc-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				</div>

				<div>
					<label for="cmbwc-date-to" style="display:block; font-weight:600; margin-bottom:4px;">Til dato</label>
					<input type="date" id="cmbwc-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				</div>

				<div>
					<button type="submit" class="button button-primary">Filtrer</button>
				</div>
			</div>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<div style="background:#fff; border:1px solid #ddd; padding:16px; max-width:1100px;">
				<p>Ingen cateringordrer fundet i det valgte interval.</p>
			</div>
		<?php else : ?>
			<?php foreach ( $grouped as $group ) : ?>
				<div style="margin:0 0 24px;">
					<h2 style="margin:0 0 10px;"><?php echo esc_html( $group['label'] ); ?></h2>

					<div style="background:#fff; border:1px solid #ddd; overflow:auto;">
						<table class="widefat striped" style="border:0; min-width:1200px;">
							<thead>
								<tr>
									<th style="width:90px;">Tid</th>
									<th style="width:90px;">Ordre</th>
									<th style="width:180px;">Kunde</th>
									<th>Menu / varer</th>
									<th style="width:260px;">Tilvalg</th>
									<th style="width:220px;">Service</th>
									<th style="width:120px;">Status</th>
									<th style="width:240px;">Handlinger</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $group['rows'] as $row ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $row['delivery_time'] ? $row['delivery_time'] : '-' ); ?></strong>
										</td>
										<td>
											#<?php echo esc_html( $row['order_number'] ); ?>
										</td>
										<td>
											<?php echo esc_html( $row['customer'] ); ?>
										</td>
										<td>
											<?php echo wp_kses_post( cmbwc_render_production_list_html( $row['items'] ) ); ?>
										</td>
										<td>
											<?php echo wp_kses_post( cmbwc_render_production_list_html( $row['addons'] ) ); ?>
										</td>
										<td>
											<?php echo wp_kses_post( cmbwc_render_production_list_html( $row['service'] ) ); ?>
										</td>
										<td>
											<?php echo esc_html( $row['status'] ); ?>
										</td>
										<td>
											<div style="display:flex; gap:8px; flex-wrap:wrap;">
												<a class="button button-small" href="<?php echo esc_url( $row['order_edit_url'] ); ?>">Åbn ordre</a>
												<a class="button button-small" href="<?php echo esc_url( $row['fallback_order_edit_url'] ); ?>">Fallback ordre</a>
												<a class="button button-small" href="<?php echo esc_url( $row['preview_url'] ); ?>" target="_blank" rel="noopener">Vis BON</a>
												<a class="button button-small" href="<?php echo esc_url( $row['print_url'] ); ?>">Print BON</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
