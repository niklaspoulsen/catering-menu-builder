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

function cmbwc_is_menu_product( $product_id ) {
	return 'yes' === get_post_meta( $product_id, '_cmbwc_is_menu', true );
}

function cmbwc_format_day_label( $days ) {
	$days = absint( $days );

	if ( 1 === $days ) {
		return '1 dag før';
	}

	return $days . ' dage før';
}

function cmbwc_get_product_image_url( $product_id, $size = 'thumbnail' ) {
	$image_id = get_post_thumbnail_id( $product_id );

	if ( ! $image_id ) {
		return '';
	}

	$image = wp_get_attachment_image_src( $image_id, $size );

	return ! empty( $image[0] ) ? $image[0] : '';
}

function cmbwc_shortcode_menu_info() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id = $product->get_id();
	$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$lead_time      = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-info">
		<h3 class="cmbwc-title">Menuinfo</h3>

		<div class="cmbwc-info-list">
			<div class="cmbwc-info-item">
				<span class="cmbwc-info-label">Minimum kuverter</span>
				<span class="cmbwc-info-value"><?php echo esc_html( $minimum_covers ); ?></span>
			</div>

			<?php if ( $lead_time > 0 ) : ?>
				<div class="cmbwc-info-item">
					<span class="cmbwc-info-label">Bestilles senest</span>
					<span class="cmbwc-info-value"><?php echo esc_html( cmbwc_format_day_label( $lead_time ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_shortcode_menu_contents() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id         = $product->get_id();
	$included_products  = get_post_meta( $product_id, '_cmbwc_included_products', true );

	if ( empty( $included_products ) || ! is_array( $included_products ) ) {
		return '';
	}

	$grouped = array();

	foreach ( $included_products as $included_product_id ) {
		$included_product_id = absint( $included_product_id );
		$included_product    = wc_get_product( $included_product_id );

		if ( ! $included_product || 'publish' !== get_post_status( $included_product_id ) ) {
			continue;
		}

		$terms         = get_the_terms( $included_product_id, 'product_cat' );
		$category_name = 'Retter';

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$category_name = $terms[0]->name;
		}

		if ( ! isset( $grouped[ $category_name ] ) ) {
			$grouped[ $category_name ] = array();
		}

		$grouped[ $category_name ][] = array(
			'id'    => $included_product_id,
			'name'  => $included_product->get_name(),
			'image' => cmbwc_get_product_image_url( $included_product_id ),
		);
	}

	if ( empty( $grouped ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-contents">
		<h3 class="cmbwc-title">Indhold i menuen</h3>

		<?php foreach ( $grouped as $category_name => $items ) : ?>
			<div class="cmbwc-content-group">
				<h4 class="cmbwc-subtitle"><?php echo esc_html( $category_name ); ?></h4>

				<div class="cmbwc-card-list">
					<?php foreach ( $items as $item ) : ?>
						<div class="cmbwc-card">
							<?php if ( ! empty( $item['image'] ) ) : ?>
								<div class="cmbwc-card-image-wrap">
									<img class="cmbwc-card-image" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
								</div>
							<?php endif; ?>

							<div class="cmbwc-card-content">
								<div class="cmbwc-card-title"><?php echo esc_html( $item['name'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_shortcode_menu_options() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id      = $product->get_id();
	$minimum_covers  = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$cover_step      = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );
	$menu_addons     = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
	$service_keys    = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
	$service_all     = function_exists( 'cmbwc_get_service_options' ) ? cmbwc_get_service_options() : array();

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	if ( $cover_step < 1 ) {
		$cover_step = 1;
	}

	if ( ! is_array( $menu_addons ) ) {
		$menu_addons = array();
	}

	if ( ! is_array( $service_keys ) ) {
		$service_keys = array();
	}

	$price_per_cover = (float) $product->get_price();
	$addons_for_output = array();

	foreach ( $menu_addons as $addon_product_id => $addon_data ) {
		$addon_product_id = absint( $addon_product_id );
		$addon_product    = wc_get_product( $addon_product_id );

		if ( ! $addon_product || 'publish' !== get_post_status( $addon_product_id ) ) {
			continue;
		}

		$addons_for_output[] = array(
			'id'            => $addon_product_id,
			'name'          => $addon_product->get_name(),
			'price'         => (float) $addon_product->get_price(),
			'follow_covers' => isset( $addon_data['follow_covers'] ) && 'yes' === $addon_data['follow_covers'] ? 'yes' : 'no',
			'image'         => cmbwc_get_product_image_url( $addon_product_id ),
		);
	}

	$services_for_output = array();

	foreach ( $service_keys as $service_key ) {
		if ( empty( $service_all[ $service_key ] ) || ! is_array( $service_all[ $service_key ] ) ) {
			continue;
		}

		$service_data = $service_all[ $service_key ];

		$services_for_output[] = array(
			'key'        => $service_key,
			'label'      => isset( $service_data['label'] ) ? $service_data['label'] : $service_key,
			'price'      => isset( $service_data['price'] ) ? (float) $service_data['price'] : 0,
			'price_type' => isset( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed',
		);
	}

	$form_id = 'cmbwc-form-' . $product_id;

	ob_start();
	?>
	<div
		class="cmbwc-box cmbwc-menu-options"
		id="<?php echo esc_attr( $form_id ); ?>"
		data-product-id="<?php echo esc_attr( $product_id ); ?>"
		data-price-per-cover="<?php echo esc_attr( $price_per_cover ); ?>"
		data-minimum-covers="<?php echo esc_attr( $minimum_covers ); ?>"
		data-cover-step="<?php echo esc_attr( $cover_step ); ?>"
	>
		<h3 class="cmbwc-title">Menuvalg</h3>

		<div class="cmbwc-field">
			<label for="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>"><strong>Antal kuverter</strong></label>
			<input
				type="number"
				id="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>"
				class="cmbwc-covers"
				min="<?php echo esc_attr( $minimum_covers ); ?>"
				step="<?php echo esc_attr( $cover_step ); ?>"
				value="<?php echo esc_attr( $minimum_covers ); ?>"
			>
		</div>

		<?php if ( ! empty( $addons_for_output ) ) : ?>
			<div class="cmbwc-section">
				<h4 class="cmbwc-subtitle">Tilvalg</h4>

				<div class="cmbwc-addon-list">
					<?php foreach ( $addons_for_output as $addon ) : ?>
						<div
							class="cmbwc-addon-item"
							data-addon-id="<?php echo esc_attr( $addon['id'] ); ?>"
							data-addon-price="<?php echo esc_attr( $addon['price'] ); ?>"
							data-follow-covers="<?php echo esc_attr( $addon['follow_covers'] ); ?>"
						>
							<label class="cmbwc-addon-label">
								<span class="cmbwc-addon-left">
									<input
										type="checkbox"
										class="cmbwc-addon-checkbox"
										value="<?php echo esc_attr( $addon['id'] ); ?>"
									>

									<?php if ( ! empty( $addon['image'] ) ) : ?>
										<img class="cmbwc-addon-image" src="<?php echo esc_url( $addon['image'] ); ?>" alt="<?php echo esc_attr( $addon['name'] ); ?>">
									<?php endif; ?>

									<span class="cmbwc-addon-name-wrap">
										<span class="cmbwc-addon-name"><?php echo esc_html( $addon['name'] ); ?></span>
										<span class="cmbwc-addon-price">
											<?php echo wp_kses_post( wc_price( $addon['price'] ) ); ?>
											<?php if ( 'yes' === $addon['follow_covers'] ) : ?>
												<span class="cmbwc-addon-note">pr. kuvert</span>
											<?php endif; ?>
										</span>
									</span>
								</span>

								<?php if ( 'no' === $addon['follow_covers'] ) : ?>
									<span class="cmbwc-addon-qty-wrap">
										<input
											type="number"
											class="cmbwc-addon-qty"
											min="1"
											step="1"
											value="1"
											disabled
										>
									</span>
								<?php else : ?>
									<span class="cmbwc-addon-follow-text">Følger kuvertantal</span>
								<?php endif; ?>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $services_for_output ) ) : ?>
			<div class="cmbwc-section">
				<h4 class="cmbwc-subtitle">Type af fad / anretning</h4>

				<div class="cmbwc-service-list">
					<?php foreach ( $services_for_output as $index => $service ) : ?>
						<label
							class="cmbwc-service-item"
							data-service-price="<?php echo esc_attr( $service['price'] ); ?>"
							data-service-price-type="<?php echo esc_attr( $service['price_type'] ); ?>"
						>
							<input
								type="radio"
								name="cmbwc_service_choice_<?php echo esc_attr( $product_id ); ?>"
								class="cmbwc-service-radio"
								value="<?php echo esc_attr( $service['key'] ); ?>"
								<?php checked( 0 === $index ); ?>
							>

							<span class="cmbwc-service-content">
								<span class="cmbwc-service-name"><?php echo esc_html( $service['label'] ); ?></span>
								<span class="cmbwc-service-price">
									<?php
									echo wp_kses_post(
										'fixed' === $service['price_type']
											? wc_price( $service['price'] )
											: wc_price( $service['price'] ) . ' pr. kuvert'
									);
									?>
								</span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="cmbwc-section">
			<h4 class="cmbwc-subtitle">Prisoversigt</h4>

			<div class="cmbwc-price-box">
				<div class="cmbwc-price-row">
					<span>Pris pr. kuvert</span>
					<strong class="cmbwc-price-per-cover"><?php echo wp_kses_post( wc_price( $price_per_cover ) ); ?></strong>
				</div>

				<div class="cmbwc-price-row">
					<span>Antal kuverter</span>
					<strong class="cmbwc-cover-count"><?php echo esc_html( $minimum_covers ); ?></strong>
				</div>

				<div class="cmbwc-price-row">
					<span>Menupris</span>
					<strong class="cmbwc-menu-total"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
				</div>

				<div class="cmbwc-price-row">
					<span>Tilvalg</span>
					<strong class="cmbwc-addon-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
				</div>

				<div class="cmbwc-price-row">
					<span>Service</span>
					<strong class="cmbwc-service-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
				</div>

				<div class="cmbwc-price-row cmbwc-price-row-total">
					<span>Samlet pris</span>
					<strong class="cmbwc-total-price"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
				</div>
			</div>
		</div>

		<div class="cmbwc-hidden-sync" style="display:none;"></div>
	</div>
	<?php
	return ob_get_clean();
}
