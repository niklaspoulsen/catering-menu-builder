<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $order ) || ! is_a( $order, 'WC_Order' ) ) {
	echo '<p>Ordre mangler.</p>';
	return;
}

if ( ! function_exists( 'cmbwc_get_order_bon_data' ) ) {
	echo '<p>BON-data kunne ikke indlæses.</p>';
	return;
}

$data = cmbwc_get_order_bon_data( $order );

if ( empty( $data ) || ! is_array( $data ) ) {
	echo '<p>BON-data mangler.</p>';
	return;
}

$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();

$paper_width = isset( $settings['paper_width_mm'] ) ? (int) $settings['paper_width_mm'] : 80;
$paper_width = in_array( $paper_width, array( 58, 80 ), true ) ? $paper_width : 80;

$content_width = ( 58 === $paper_width ) ? 50 : 72;
$font_size     = ( 58 === $paper_width ) ? 10 : 11;
$title_size    = ( 58 === $paper_width ) ? 18 : 22;
$time_size     = ( 58 === $paper_width ) ? 18 : 22;

$store_name = ! empty( $settings['store_name'] ) ? $settings['store_name'] : get_bloginfo( 'name' );
$headline   = ! empty( $settings['headline'] ) ? $settings['headline'] : 'KØKKENBON';

$is_pickup = ( isset( $data['delivery_type'] ) && 'Afhent selv' === $data['delivery_type'] );

$show_created          = 'yes' === ( $settings['show_created'] ?? 'yes' );
$show_company          = 'yes' === ( $settings['show_company'] ?? 'yes' );
$show_phone            = 'yes' === ( $settings['show_phone'] ?? 'yes' );
$show_shipping_method  = 'yes' === ( $settings['show_shipping_method'] ?? 'yes' );
$show_shipping_address = 'yes' === ( $settings['show_shipping_address'] ?? 'yes' );
$show_order_note       = 'yes' === ( $settings['show_order_note'] ?? 'yes' );
$show_prices           = 'yes' === ( $settings['show_prices'] ?? 'no' );
$show_included         = 'yes' === ( $settings['show_included'] ?? 'yes' );
$show_addons           = 'yes' === ( $settings['show_addons'] ?? 'yes' );
$show_service          = 'yes' === ( $settings['show_service'] ?? 'no' );

if ( ! function_exists( 'cmbwc_bon_template_lines' ) ) {
	function cmbwc_bon_template_lines( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}

		return $clean;
	}
}

if ( ! function_exists( 'cmbwc_bon_template_multiline' ) ) {
	function cmbwc_bon_template_multiline( $value ) {
		$lines = cmbwc_bon_template_lines( $value );

		if ( empty( $lines ) ) {
			return '';
		}

		$output = array();

		foreach ( $lines as $line ) {
			$output[] = esc_html( $line );
		}

		return implode( '<br>', $output );
	}
}

?>

<style>
@page {
	size: <?php echo esc_html( $paper_width ); ?>mm auto;
	margin: 2mm;
}

html,
body {
	margin: 0;
	padding: 0;
	background: #fff;
	color: #000;
	font-family: Arial, Helvetica, sans-serif;
	font-size: <?php echo esc_html( $font_size ); ?>px;
	line-height: 1.35;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}

body {
	display: block;
}

.bon {
	width: <?php echo esc_html( $content_width ); ?>mm;
	margin: 0 auto;
	padding: 2mm 0;
	box-sizing: border-box;
}

.bon-header {
	text-align: center;
	margin-bottom: 8px;
}

.store-name {
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	word-wrap: break-word;
}

.bon-title {
	font-size: <?php echo esc_html( $title_size ); ?>px;
	font-weight: 700;
	letter-spacing: 0.4px;
	margin: 2px 0 0;
	word-wrap: break-word;
}

.rule {
	border-top: 1px dashed #000;
	margin: 8px 0;
	height: 0;
}

.meta-row {
	margin: 1px 0;
	word-wrap: break-word;
	overflow-wrap: anywhere;
}

.meta-label {
	font-weight: 700;
}

.delivery-box {
	border: 2px solid #000;
	padding: 7px 6px;
	margin: 10px 0;
	text-align: center;
	page-break-inside: avoid;
}

.delivery-label {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.4px;
	text-transform: uppercase;
}

.delivery-type {
	font-size: 16px;
	font-weight: 700;
	line-height: 1.1;
	margin-top: 4px;
	text-transform: uppercase;
	word-wrap: break-word;
}

.delivery-date {
	margin-top: 4px;
	font-size: 13px;
	font-weight: 700;
	line-height: 1.2;
	word-wrap: break-word;
}

.delivery-time {
	font-size: <?php echo esc_html( $time_size ); ?>px;
	font-weight: 700;
	line-height: 1.1;
	margin-top: 4px;
	word-wrap: break-word;
}

.address-box,
.note-box {
	border: 1px solid #000;
	padding: 6px;
	margin: 8px 0;
	word-wrap: break-word;
	overflow-wrap: anywhere;
	page-break-inside: avoid;
}

.section-title {
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	margin: 8px 0 6px;
	letter-spacing: 0.3px;
}

.item {
	padding: 0 0 6px;
	margin: 0 0 6px;
	page-break-inside: avoid;
}

.item-head {
	font-weight: 700;
	font-size: 13px;
	line-height: 1.25;
	word-wrap: break-word;
	overflow-wrap: anywhere;
}

.item-meta {
	margin-top: 3px;
}

.item-block {
	margin-top: 5px;
}

.item-block-title {
	font-weight: 700;
	margin-bottom: 2px;
}

.item-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.item-list li {
	margin: 0 0 2px;
	padding-left: 10px;
	position: relative;
	word-wrap: break-word;
	overflow-wrap: anywhere;
}

.item-list li::before {
	content: "-";
	position: absolute;
	left: 0;
	top: 0;
}

.totals {
	margin-top: 8px;
}

.total-row {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 8px;
	margin: 2px 0;
}

.total-row span:first-child {
	flex: 1;
	word-wrap: break-word;
	overflow-wrap: anywhere;
}

.total-row span:last-child {
	white-space: nowrap;
}

.total-row.grand {
	font-weight: 700;
	font-size: 13px;
	border-top: 1px solid #000;
	padding-top: 4px;
	margin-top: 4px;
}

.small-muted {
	font-size: 10px;
}

.status-line {
	font-size: 10px;
	margin-top: 6px;
	text-align: center;
	word-wrap: break-word;
}

.print-actions {
	margin: 12px 0;
	text-align: center;
}

.print-actions button {
	padding: 6px 10px;
	font-size: 11px;
	cursor: pointer;
}

@media print {
	html,
	body {
		width: <?php echo esc_html( $paper_width ); ?>mm;
	}

	.bon {
		margin: 0;
	}

	.print-actions {
		display: none !important;
		visibility: hidden !important;
		height: 0 !important;
		margin: 0 !important;
		padding: 0 !important;
		overflow: hidden !important;
	}
}
</style>

<div class="bon">
	<div class="print-actions">
		<button type="button" onclick="window.print()">Print / Gem som PDF</button>
	</div>

	<div class="bon-header">
		<?php if ( ! empty( $store_name ) ) : ?>
			<div class="store-name"><?php echo esc_html( $store_name ); ?></div>
		<?php endif; ?>

		<div class="bon-title"><?php echo esc_html( $headline ); ?></div>
	</div>

	<div class="meta-row">
		<span class="meta-label">Ordre:</span>
		#<?php echo esc_html( $data['order_number'] ?? $order->get_order_number() ); ?>
	</div>

	<?php if ( $show_created && ! empty( $data['created'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Oprettet:</span>
			<?php echo esc_html( $data['created'] ); ?>
		</div>
	<?php endif; ?>

	<div class="meta-row">
		<span class="meta-label">Kunde:</span>
		<?php echo esc_html( ! empty( $data['customer'] ) ? $data['customer'] : '-' ); ?>
	</div>

	<?php if ( $show_company && ! empty( $data['company'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Firma:</span>
			<?php echo esc_html( $data['company'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_phone && ! empty( $data['phone'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Tlf:</span>
			<?php echo esc_html( $data['phone'] ); ?>
		</div>
	<?php endif; ?>

	<div class="delivery-box">
		<div class="delivery-label"><?php echo $is_pickup ? 'UDLEVERING' : 'LEVERING'; ?></div>
		<div class="delivery-type"><?php echo esc_html( ! empty( $data['delivery_type'] ) ? $data['delivery_type'] : '-' ); ?></div>
		<div class="delivery-date">
			<?php
			echo esc_html(
				! empty( $data['delivery_date_formatted'] )
					? $data['delivery_date_formatted']
					: ( ! empty( $data['delivery_date'] ) ? $data['delivery_date'] : '-' )
			);
			?>
		</div>
		<div class="delivery-time"><?php echo esc_html( ! empty( $data['delivery_time'] ) ? $data['delivery_time'] : '-' ); ?></div>
	</div>

	<?php if ( $show_shipping_method && ! empty( $data['shipping_method'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Metode:</span>
			<?php echo esc_html( $data['shipping_method'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $is_pickup && $show_shipping_address && ! empty( $data['shipping_address'] ) ) : ?>
		<div class="address-box">
			<div class="meta-label">Leveringsadresse</div>
			<div><?php echo cmbwc_bon_template_multiline( $data['shipping_address'] ); ?></div>
		</div>
	<?php endif; ?>

	<div class="rule"></div>

	<div class="section-title">Produktion</div>

	<?php if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) : ?>
		<?php foreach ( $data['items'] as $item ) : ?>
			<?php
			if ( empty( $item ) || ! is_array( $item ) ) {
				continue;
			}

			$item_name = ! empty( $item['name'] ) ? $item['name'] : 'Produkt';
			$item_qty  = ! empty( $item['qty'] ) ? absint( $item['qty'] ) : 1;
			?>
			<div class="item">
				<div class="item-head">
					<?php
					if ( $item_qty > 1 ) {
						echo esc_html( $item_qty . ' x ' . $item_name );
					} else {
						echo esc_html( $item_name );
					}
					?>
				</div>

				<?php if ( ! empty( $item['covers'] ) ) : ?>
					<div class="item-meta">
						<strong>Kuverter:</strong>
						<?php echo esc_html( $item['covers'] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_included && ! empty( $item['included'] ) && is_array( $item['included'] ) ) : ?>
					<div class="item-block">
						<div class="item-block-title">Indhold</div>
						<ul class="item-list">
							<?php foreach ( $item['included'] as $line ) : ?>
								<?php if ( '' !== trim( (string) $line ) ) : ?>
									<li><?php echo esc_html( $line ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $show_addons && ! empty( $item['addons'] ) && is_array( $item['addons'] ) ) : ?>
					<div class="item-block">
						<div class="item-block-title">Tilvalg</div>
						<ul class="item-list">
							<?php foreach ( $item['addons'] as $line ) : ?>
								<?php if ( '' !== trim( (string) $line ) ) : ?>
									<li><?php echo esc_html( $line ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $show_service && ! empty( $item['service'] ) ) : ?>
					<?php $service_lines = cmbwc_bon_template_lines( $item['service'] ); ?>

					<?php if ( ! empty( $service_lines ) ) : ?>
						<div class="item-block">
							<div class="item-block-title">Service</div>

							<?php if ( count( $service_lines ) > 1 ) : ?>
								<ul class="item-list">
									<?php foreach ( $service_lines as $service_line ) : ?>
										<li><?php echo esc_html( $service_line ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<div><?php echo esc_html( $service_lines[0] ); ?></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $show_prices ) : ?>
					<div class="item-block">
						<div class="total-row">
							<span>Linjetotal</span>
							<span>
								<?php
								echo wp_kses_post(
									function_exists( 'cmbwc_bon_price' )
										? cmbwc_bon_price( $item['line_total'] ?? 0, $order )
										: wc_price( (float) ( $item['line_total'] ?? 0 ), array( 'currency' => $order->get_currency() ) )
								);
								?>
							</span>
						</div>
					</div>
				<?php endif; ?>

				<div class="rule"></div>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="small-muted">Ingen produktlinjer fundet.</div>
		<div class="rule"></div>
	<?php endif; ?>

	<?php if ( $show_order_note && ! empty( $data['order_note'] ) ) : ?>
		<div class="section-title">Kundebemærkning</div>
		<div class="note-box"><?php echo cmbwc_bon_template_multiline( $data['order_note'] ); ?></div>
	<?php endif; ?>

	<?php if ( $show_prices ) : ?>
		<div class="section-title">Pris</div>

		<div class="totals">
			<?php if ( ! empty( $data['deposit_items'] ) && is_array( $data['deposit_items'] ) ) : ?>
				<?php foreach ( $data['deposit_items'] as $deposit_item ) : ?>
					<?php
					if ( empty( $deposit_item ) || ! is_array( $deposit_item ) ) {
						continue;
					}
					?>
					<div class="total-row">
						<span><?php echo esc_html( $deposit_item['display_name'] ?? 'Depositum' ); ?></span>
						<span>
							<?php
							echo wp_kses_post(
								function_exists( 'cmbwc_bon_price' )
									? cmbwc_bon_price( $deposit_item['amount'] ?? 0, $order )
									: wc_price( (float) ( $deposit_item['amount'] ?? 0 ), array( 'currency' => $order->get_currency() ) )
							);
							?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $data['coupon_lines'] ) && is_array( $data['coupon_lines'] ) ) : ?>
				<?php foreach ( $data['coupon_lines'] as $coupon_line ) : ?>
					<?php
					if ( empty( $coupon_line ) || ! is_array( $coupon_line ) ) {
						continue;
					}
					?>
					<div class="total-row">
						<span>
							<?php
							echo esc_html(
								! empty( $coupon_line['code'] )
									? 'Rabatkode: ' . $coupon_line['code']
									: 'Rabatkode'
							);
							?>
						</span>
						<span>
							-<?php echo wp_kses_post( wc_price( (float) ( $coupon_line['discount'] ?? 0 ), array( 'currency' => $order->get_currency() ) ) ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php elseif ( ! empty( $data['discount_total'] ) ) : ?>
				<div class="total-row">
					<span>Rabat</span>
					<span>-<?php echo wp_kses_post( wc_price( (float) $data['discount_total'], array( 'currency' => $order->get_currency() ) ) ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $data['shipping_total'] ) ) : ?>
				<div class="total-row">
					<span>Levering</span>
					<span><?php echo wp_kses_post( wc_price( (float) $data['shipping_total'], array( 'currency' => $order->get_currency() ) ) ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $data['fees_total'] ) ) : ?>
				<div class="total-row">
					<span>Gebyrer</span>
					<span><?php echo wp_kses_post( wc_price( (float) $data['fees_total'], array( 'currency' => $order->get_currency() ) ) ); ?></span>
				</div>
			<?php endif; ?>

			<div class="total-row grand">
				<span>
					<?php echo ! empty( $data['has_deposit'] ) ? 'Total inkl. depositum' : 'Total'; ?>
				</span>
				<span>
					<?php
					echo wp_kses_post(
						function_exists( 'cmbwc_bon_price' )
							? cmbwc_bon_price( $data['grand_total'] ?? $order->get_total(), $order )
							: wc_price( (float) ( $data['grand_total'] ?? $order->get_total() ), array( 'currency' => $order->get_currency() ) )
					);
					?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $data['printed'] ) && ! empty( $data['printed_at'] ) ) : ?>
		<div class="status-line">Tidligere printet: <?php echo esc_html( $data['printed_at'] ); ?></div>
	<?php endif; ?>
</div>
