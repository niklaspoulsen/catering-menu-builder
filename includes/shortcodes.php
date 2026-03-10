<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cmbwc_menu_info', 'cmbwc_shortcode_menu_info' );
add_shortcode( 'cmbwc_menu_contents', 'cmbwc_shortcode_menu_contents' );
add_shortcode( 'cmbwc_menu_options', 'cmbwc_shortcode_menu_options' );

function cmbwc_get_current_product() {
	global $product, $post;

	if ( $product instanceof WC_Product ) {
		return $product;
	}

	if ( $post && isset( $post->ID ) && 'product' === get_post_type( $post->ID ) ) {
		return wc_get_product( $post->ID );
	}

	return null;
}

function cmbwc_shortcode_menu_info() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id = $product->get_id();

	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$lead_time      = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-info">
		<h3 class="cmbwc-title">Menuinfo</h3>

		<?php if ( $minimum_covers > 0 ) : ?>
			<div class="cmbwc-info-row">
				<strong>Minimum kuverter:</strong> <?php echo esc_html( $minimum_covers ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $lead_time > 0 ) : ?>
			<div class="cmbwc-info-row">
				<strong>Bestilles senest:</strong>
				<?php echo esc_html( $lead_time ); ?>
				<?php echo 1 === $lead_time ? ' dag før' : ' dage før'; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_shortcode_menu_contents() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id = $product->get_id();

	$included_products = get_post_meta( $product_id, '_cmbwc_included_products', true );

	if ( empty( $included_products ) || ! is_array( $included_products ) ) {
		return '<div class="cmbwc-box"><h3 class="cmbwc-title">Indhold i menuen</h3><p>Ingen retter valgt endnu.</p></div>';
	}

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-contents">
		<h3 class="cmbwc-title">Indhold i menuen</h3>
		<ul class="cmbwc-list">
			<?php foreach ( $included_products as $included_product_id ) : ?>
				<?php
				$included_product = wc_get_product( $included_product_id );

				if ( ! $included_product || 'publish' !== get_post_status( $included_product_id ) ) {
					continue;
				}
				?>
				<li class="cmbwc-list-item">
					<?php echo esc_html( $included_product->get_name() ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_shortcode_menu_options() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id = $product->get_id();

	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	$price_per_cover = (float) $product->get_price();

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-options" data-price-per-cover="<?php echo esc_attr( $price_per_cover ); ?>">
		<h3 class="cmbwc-title">Menuvalg</h3>

		<div class="cmbwc-field">
			<label for="cmbwc_covers"><strong>Antal kuverter</strong></label><br>
			<input
				type="number"
				id="cmbwc_covers"
				class="cmbwc-covers"
				min="<?php echo esc_attr( $minimum_covers ); ?>"
				value="<?php echo esc_attr( $minimum_covers ); ?>"
				step="1"
			>
		</div>

		<div class="cmbwc-price-box">
			<div><strong>Pris pr. kuvert:</strong> <span class="cmbwc-price-per-cover"><?php echo wc_price( $price_per_cover ); ?></span></div>
			<div><strong>Antal kuverter:</strong> <span class="cmbwc-cover-count"><?php echo esc_html( $minimum_covers ); ?></span></div>
			<div><strong>Samlet pris:</strong> <span class="cmbwc-total-price"><?php echo wc_price( $price_per_cover * $minimum_covers ); ?></span></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
