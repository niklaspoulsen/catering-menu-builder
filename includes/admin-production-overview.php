<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

		$delivery_date = trim( (string) $order->get_meta( '_delivery_date' ) );
		$delivery_time = trim( (string) $order->get_meta( '_delivery_time' ) );

		if ( '' === $delivery_date ) {
			continue;
		}

		$delivery_timestamp = strtotime( $delivery_date );
		if ( ! $delivery_timestamp ) {
			continue;
		}

		$delivery_date_ymd = wp_date( 'Y-m-d', $delivery_timestamp );

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

			$item_name  = trim( (string) $item->get_name() );
			$item_qty   = (int) $item->get_quantity();
			$covers     = trim( (string) $item->get_meta( 'Kuverter', true ) );
			$included   = trim( (string) $item->get_meta( 'Indhold', true ) );
			$addons     = trim( (string) $item->get_meta( 'Tilvalg', true ) );
			$service    = trim( (string) $item->get_meta( 'Service', true ) );

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
			'delivery_date'           => $delivery_date,
			'delivery_date_ymd'       => $delivery_date_ymd,
			'delivery_date_label'     => function_exists( 'cmbwc_bon_format_delivery_date' )
				? cmbwc_bon_format_delivery_date( $delivery_date )
				: $delivery_date,
			'delivery_time'           => $delivery_time,
			'delivery_sort'           => cmbwc_get_production_sort_datetime( $delivery_date, $delivery_time, $order ),
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

	$date_part = date( 'Y-m-d', strtotime( $delivery_date ) );

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
 * Returnér standard datofilter.
 */
function cmbwc_get_production_default_dates() {
	$today = current_time( 'Y-m-d' );

	return array(
		'date_from' => $today,
		'date_to'   => date( 'Y-m-d', strtotime( '+14 days', current_time( 'timestamp' ) ) ),
	);
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

	$defaults = cmbwc_get_production_default_dates();

	$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : $defaults['date_from'];
	$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : $defaults['date_to'];

	$rows    = cmbwc_get_production_orders(
		array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		)
	);
	$grouped = cmbwc_group_production_rows_by_date( $rows );

	?>
	<div class="wrap">
		<h1>Produktionsoverblik</h1>

		<form method="get" style="background:#fff; border:1px solid #ddd; padding:16px; margin:16px 0; max-width:980px;">
			<input type="hidden" name="page" value="cmbwc-production-overview">

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
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>">Nulstil</a>
				</div>
			</div>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<div style="background:#fff; border:1px solid #ddd; padding:16px; max-width:980px;">
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
