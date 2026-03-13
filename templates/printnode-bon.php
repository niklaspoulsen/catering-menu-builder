<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $order ) || ! is_a( $order, 'WC_Order' ) ) {
	echo '<p>Ordre mangler.</p>';
	return;
}

$data = cmbwc_get_order_bon_data( $order );
?>

<style>
@page {
	size: 80mm auto;
	margin: 4mm;
}

html,
body {
	margin: 0;
	padding: 0;
	background: #fff;
	color: #000;
	font-family: Arial, Helvetica, sans-serif;
	font-size: 11px;
	line-height: 1.35;
}

body {
	display: block;
}

.bon {
	width: 72mm;
	margin: 0 auto;
	padding: 2mm 0;
}

.bon-header {
	text-align: center;
	margin-bottom: 8px;
}

.bon-title {
	font-size: 18px;
	font-weight: 700;
	letter-spacing: 0.4px;
	margin-bottom: 4px;
}

.bon-subtitle {
	font-size: 11px;
}

.rule {
	border-top: 1px dashed #000;
	margin: 8px 0;
}

.meta-row {
	margin: 1px 0;
	word-wrap: break-word;
}

.meta-label {
	font-weight: 700;
}

.delivery-box {
	border: 1px solid #000;
	padding: 6px;
	margin: 8px 0;
	text-align: center;
}

.delivery-label {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.4px;
}

.delivery-date {
	margin-top: 2px;
	font-size: 11px;
}

.delivery-time {
	font-size: 20px;
	font-weight: 700;
	line-height: 1.1;
	margin-top: 3px;
}

.address-box,
.note-box {
	border: 1px solid #000;
	padding: 6px;
	margin: 8px 0;
	word-wrap: break-word;
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
	.print-actions {
		display: none !important;
	}
}
</style>

<div class="bon">
	<div class="print-actions">
		<button onclick="window.print()">Print / Gem som PDF</button>
	</div>

	<div class="bon-header">
		<div class="bon-title">CATERING BON</div>
		<div class="bon-subtitle">Ordre #<?php echo esc_html( $data['order_number'] ); ?></div>
	</div>

	<div class="meta-row">
		<span class="meta-label">Oprettet:</span>
		<?php echo esc_html( $data['created'] ); ?>
	</div>

	<div class="meta-row">
		<span class="meta-label">Kunde:</span>
		<?php echo esc_html( $data['customer'] ?: '-' ); ?>
	</div>

	<?php if ( ! empty( $data['company'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Firma:</span>
			<?php echo esc_html( $data['company'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $data['phone'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Tlf:</span>
			<?php echo esc_html( $data['phone'] ); ?>
		</div>
	<?php endif; ?>

	<div class="delivery-box">
		<div class="delivery-label">LEVERING</div>
		<div class="delivery-date"><?php echo esc_html( $data['delivery_date'] ?: '-' ); ?></div>
		<div class="delivery-time"><?php echo esc_html( $data['delivery_time'] ?: '-' ); ?></div>
	</div>

	<?php if ( ! empty( $data['shipping_method'] ) ) : ?>
		<div class="meta-row">
			<span class="meta-label">Levering:</span>
			<?php echo esc_html( $data['shipping_method'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $data['shipping_address'] ) ) : ?>
		<div class="address-box">
			<div class="meta-label">Leveringsadresse</div>
			<div><?php echo wp_kses_post( nl2br( $data['shipping_address'] ) ); ?></div>
		</div>
	<?php endif; ?>

	<div class="rule"></div>

	<div class="section-title">Produktion</div>

	<?php if ( ! empty( $data['items'] ) ) : ?>
		<?php foreach ( $data['items'] as $item ) : ?>
			<div class="item">
				<div class="item-head">
					<?php
					if ( ! empty( $item['qty'] ) && $item['qty'] > 1 ) {
						echo esc_html( $item['qty'] . ' x ' . $item['name'] );
					} else {
						echo esc_html( $item['name'] );
					}
					?>
				</div>

				<?php if ( ! empty( $item['covers'] ) ) : ?>
					<div class="item-meta">
						<strong>Kuverter:</strong>
						<?php echo esc_html( $item['covers'] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['included'] ) ) : ?>
					<div class="item-block">
						<div class="item-block-title">Indhold</div>
						<ul class="item-list">
							<?php foreach ( $item['included'] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['addons'] ) ) : ?>
					<div class="item-block">
						<div class="item-block-title">Tilvalg</div>
						<ul class="item-list">
							<?php foreach ( $item['addons'] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['service'] ) ) : ?>
					<div class="item-block">
						<div class="item-block-title">Service</div>
						<div><?php echo esc_html( $item['service'] ); ?></div>
					</div>
				<?php endif; ?>

				<div class="rule"></div>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="small-muted">Ingen produktlinjer fundet.</div>
	<?php endif; ?>

	<?php if ( ! empty( $data['order_note'] ) ) : ?>
		<div class="section-title">Kundebemærkning</div>
		<div class="note-box"><?php echo esc_html( $data['order_note'] ); ?></div>
	<?php endif; ?>

	<div class="section-title">Pris</div>
	<div class="totals">
		<?php if ( ! empty( $data['deposit_items'] ) ) : ?>
			<?php foreach ( $data['deposit_items'] as $deposit_item ) : ?>
				<div class="total-row">
					<span><?php echo esc_html( $deposit_item['name'] ); ?></span>
					<span><?php echo wp_kses_post( cmbwc_bon_price( $deposit_item['line_total'], $order ) ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<div class="total-row grand">
			<span>Total</span>
			<span><?php echo wp_kses_post( cmbwc_bon_price( $data['grand_total'], $order ) ); ?></span>
		</div>
	</div>
</div>
