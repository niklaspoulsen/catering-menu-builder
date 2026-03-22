<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cmbwc_get_service_option_label( $option ) {
	$title       = isset( $option['title'] ) ? trim( (string) $option['title'] ) : '';
	$price       = isset( $option['price'] ) ? (float) $option['price'] : 0;
	$price_type  = isset( $option['price_type'] ) ? (string) $option['price_type'] : 'fixed';
	$price_label = 'per_cover' === $price_type ? 'pr. kuvert' : 'fast pris';

	if ( '' === $title ) {
		$title = 'Service';
	}

	return sprintf(
		'%s (%s %s)',
		$title,
		wc_price( $price ),
		$price_label
	);
}

function cmbwc_get_menu_addons_for_product( $product_id ) {
	$addons = get_post_meta( $product_id, '_cmbwc_menu_addons', true );

	if ( ! is_array( $addons ) ) {
		return array();
	}

	$rows = array();

	foreach ( $addons as $addon_product_id => $addon_settings ) {
		$addon_product_id = absint( $addon_product_id );
		$product          = wc_get_product( $addon_product_id );

		if ( ! $product || 'publish' !== get_post_status( $addon_product_id ) ) {
			continue;
		}

		$rows[] = array(
			'id'            => $addon_product_id,
			'name'          => $product->get_name(),
			'price'         => (float) $product->get_price(),
			'follow_covers' => ! empty( $addon_settings['follow_covers'] ) && 'yes' === $addon_settings['follow_covers'] ? 'yes' : 'no',
		);
	}

	return $rows;
}

function cmbwc_get_allowed_service_options_for_product( $product_id ) {
	$allowed_keys = get_post_meta( $product_id, '_cmbwc_service_allowed', true );

	if ( ! is_array( $allowed_keys ) || empty( $allowed_keys ) ) {
		return array();
	}

	if ( ! function_exists( 'cmbwc_get_service_options' ) ) {
		return array();
	}

	$all_options = cmbwc_get_service_options();
	$rows        = array();

	foreach ( $allowed_keys as $key ) {
		$key = (string) $key;

		if ( empty( $all_options[ $key ] ) || ! is_array( $all_options[ $key ] ) ) {
			continue;
		}

		$option        = $all_options[ $key ];
		$option['key'] = $key;
		$rows[]        = $option;
	}

	return $rows;
}

function cmbwc_render_menu_options_box() {
	global $product;

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return;
	}

	$product_id = $product->get_id();

	if ( 'yes' !== get_post_meta( $product_id, '_cmbwc_is_menu', true ) ) {
		return;
	}

	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$cover_step     = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	if ( $cover_step < 1 ) {
		$cover_step = 1;
	}

	$price_per_cover = (float) $product->get_price();
	$addons          = cmbwc_get_menu_addons_for_product( $product_id );
	$services        = cmbwc_get_allowed_service_options_for_product( $product_id );
	?>
	<div
		class="cmbwc-menu-options"
		data-product-id="<?php echo esc_attr( $product_id ); ?>"
		data-price-per-cover="<?php echo esc_attr( wc_format_decimal( $price_per_cover, 2 ) ); ?>"
		data-minimum-covers="<?php echo esc_attr( $minimum_covers ); ?>"
		data-cover-step="<?php echo esc_attr( $cover_step ); ?>"
	>
		<div class="cmbwc-field cmbwc-form-row cmbwc-form-row-covers">
			<label for="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>" class="cmbwc-label cmbwc-field-label">
				<strong>Antal kuverter</strong>
			</label>
			<input
				type="number"
				id="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>"
				class="cmbwc-covers cmbwc-input cmbwc-input-covers"
				min="<?php echo esc_attr( $minimum_covers ); ?>"
				step="<?php echo esc_attr( $cover_step ); ?>"
				value="<?php echo esc_attr( $minimum_covers ); ?>"
			/>
		</div>

		<?php if ( ! empty( $addons ) ) : ?>
			<div class="cmbwc-section cmbwc-addons">
				<h3 class="cmbwc-section-title">Tilvalg</h3>

				<?php foreach ( $addons as $addon ) : ?>
					<div
						class="cmbwc-addon-item"
						data-addon-id="<?php echo esc_attr( $addon['id'] ); ?>"
						data-addon-price="<?php echo esc_attr( wc_format_decimal( $addon['price'], 2 ) ); ?>"
						data-follow-covers="<?php echo esc_attr( $addon['follow_covers'] ); ?>"
					>
						<label class="cmbwc-addon-label">
							<input type="checkbox" class="cmbwc-addon-checkbox" />
							<span class="cmbwc-addon-name"><?php echo esc_html( $addon['name'] ); ?></span>
							<span class="cmbwc-addon-price"><?php echo wp_kses_post( wc_price( $addon['price'] ) ); ?></span>
						</label>

						<?php if ( 'yes' !== $addon['follow_covers'] ) : ?>
							<input
								type="number"
								class="cmbwc-addon-qty cmbwc-input"
								min="1"
								step="1"
								value="1"
								disabled
							/>
						<?php else : ?>
							<input
								type="number"
								class="cmbwc-addon-qty cmbwc-input"
								min="1"
								step="1"
								value="<?php echo esc_attr( $minimum_covers ); ?>"
								disabled
							/>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $services ) ) : ?>
			<div class="cmbwc-section cmbwc-services">
				<h3 class="cmbwc-section-title">Servicevalg</h3>

				<?php foreach ( $services as $service ) : ?>
					<?php
					$service_key        = isset( $service['key'] ) ? (string) $service['key'] : '';
					$service_title      = isset( $service['title'] ) ? (string) $service['title'] : 'Service';
					$service_price      = isset( $service['price'] ) ? (float) $service['price'] : 0;
					$service_price_type = isset( $service['price_type'] ) ? (string) $service['price_type'] : 'fixed';
					$service_subtitle   = 'per_cover' === $service_price_type ? 'Pr. kuvert' : 'Fast pris';
					?>
					<div
						class="cmbwc-service-item"
						data-service-price="<?php echo esc_attr( wc_format_decimal( $service_price, 2 ) ); ?>"
						data-service-price-type="<?php echo esc_attr( $service_price_type ); ?>"
					>
						<label class="cmbwc-service-label">
							<input
								type="radio"
								name="cmbwc_service_choice_<?php echo esc_attr( $product_id ); ?>"
								class="cmbwc-service-radio"
								value="<?php echo esc_attr( $service_key ); ?>"
							/>
							<span class="cmbwc-service-name"><?php echo esc_html( $service_title ); ?></span>
							<span class="cmbwc-service-price-wrap">
								<span class="cmbwc-service-price"><?php echo wp_kses_post( wc_price( $service_price ) ); ?></span>
								<span class="cmbwc-service-subtitle"><?php echo esc_html( $service_subtitle ); ?></span>
							</span>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="cmbwc-summary">
			<div class="cmbwc-summary-row">
				<span>Pris pr. kuvert</span>
				<strong class="cmbwc-price-per-cover"><?php echo wp_kses_post( wc_price( $price_per_cover ) ); ?></strong>
			</div>
			<div class="cmbwc-summary-row">
				<span>Kuverter</span>
				<strong class="cmbwc-cover-count"><?php echo esc_html( $minimum_covers ); ?></strong>
			</div>
			<div class="cmbwc-summary-row">
				<span>Menu i alt</span>
				<strong class="cmbwc-menu-total"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
			</div>
			<div class="cmbwc-summary-row">
				<span>Tilvalg i alt</span>
				<strong class="cmbwc-addon-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
			</div>
			<div class="cmbwc-summary-row">
				<span>Service i alt</span>
				<strong class="cmbwc-service-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
			</div>
			<div class="cmbwc-summary-row cmbwc-summary-total">
				<span>Samlet pris</span>
				<strong class="cmbwc-total-price"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
			</div>
		</div>

		<input type="hidden" name="cmbwc_covers" class="cmbwc-woo-sync-covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
		<input type="hidden" name="cmbwc_selected_service" class="cmbwc-woo-sync-service" value="">
		<input type="hidden" name="cmbwc_selected_addons" class="cmbwc-woo-sync-addons" value="[]">

		<input type="hidden" class="cmbwc-local-sync-covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
		<input type="hidden" class="cmbwc-local-sync-service" value="">
		<input type="hidden" class="cmbwc-local-sync-addons" value="">
	</div>
	<?php
}

add_action( 'woocommerce_before_add_to_cart_button', 'cmbwc_render_menu_options_box', 15 );
