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
body {
	font-family: Arial, sans-serif;
	font-size: 12px;
	line-height: 1.4;
	margin: 20px;
}
.bon {
	max-width: 420px;
}
.bon h1 {
	font-size: 22px;
	margin: 0 0 12px;
}
.section {
	margin-top: 16px;
}
ul {
	margin: 4px 0 8px 18px;
	padding: 0;
}
hr {
	border: 0;
	border-top: 1px dashed #000;
	margin: 12px 0;
}
.label {
	font-weight: 700;
}
.item-title {
	font-weight: 700;
	font-size: 14px;
	margin-bottom: 4px;
}
</style>

<div class="bon">
	<h1>CATERING BON</h1>

	<div><span class="label">Ordre:</span> #<?php echo esc_html( $data['order_number'] ); ?></div>
	<div><span class="label">Oprettet:</span> <?php echo esc_html( $data['created'] ); ?></div>

	<br>

	<div><span class="label">Leveringsdato:</span> <?php echo esc_html( $data['delivery_date'] ?: '-' ); ?></div>
	<div><span class="label">Tid:</span> <?php echo esc_html( $data['delivery_time'] ?: '-' ); ?></div>

	<br>

	<div><span class="label">Kunde:</span> <?php echo esc_html( $data['customer'] ?: '-' ); ?></div>
	<div><span class="label">Firma:</span> <?php echo esc_html( $data['company'] ?: '-' ); ?></div>
	<div><span class="label">Tlf:</span> <?php echo esc_html( $data['phone'] ?: '-' ); ?></div>
	<div><span class="label">Levering:</span> <?php echo esc_html( $data['shipping_method'] ?: '-' ); ?></div>

	<hr>

	<div class="section">
		<h3>Produktion</h3>

		<?php foreach ( $data['items'] as $item ) : ?>
			<div class="item-title"><?php echo esc_html( $item['name'] ); ?></div>

			<?php if ( ! empty( $item['covers'] ) ) : ?>
				<div><span class="label">Kuverter:</span> <?php echo esc_html( $item['covers'] ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $item['included'] ) ) : ?>
				<div><span class="label">Indhold:</span></div>
				<ul>
					<?php foreach ( $item['included'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $item['addons'] ) ) : ?>
				<div><span class="label">Tilvalg:</span></div>
				<ul>
					<?php foreach ( $item['addons'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $item['service'] ) ) : ?>
				<div><span class="label">Service:</span> <?php echo esc_html( $item['service'] ); ?></div>
			<?php endif; ?>

			<hr>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $data['order_note'] ) ) : ?>
		<div class="section">
			<h3>Kundebemærkning</h3>
			<div><?php echo nl2br( esc_html( $data['order_note'] ) ); ?></div>
		</div>
	<?php endif; ?>
</div>
