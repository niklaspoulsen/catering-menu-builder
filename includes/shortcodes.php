<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cmbwc_menu_info', 'cmbwc_shortcode_menu_info' );
add_shortcode( 'cmbwc_menu_contents', 'cmbwc_shortcode_menu_contents' );
add_shortcode( 'cmbwc_menu_options', 'cmbwc_shortcode_menu_options_placeholder' );

add_action( 'woocommerce_before_add_to_cart_button', 'cmbwc_render_menu_options_in_form', 15 );

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

function cmbwc_format_compact_price( $price ) {
	return number_format_i18n( (float) $price, 0 ) . ',-';
}

function cmbwc_get_service_price_suffix( $service_data ) {
	if ( ! is_array( $service_data ) ) {
		return '';
	}

	if ( ! empty( $service_data['price_note'] ) ) {
		return sanitize_text_field( $service_data['price_note'] );
	}

	$price_type = isset( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed';
	$is_deposit = isset( $service_data['is_deposit'] ) ? $service_data['is_deposit'] : 'no';

	if ( 'yes' === $is_deposit ) {
		return 'depositum';
	}

	return 'per_cover' === $price_type ? 'pr. kuvert' : 'fast pris';
}

function cmbwc_get_product_allowed_weekdays( $product_id ) {
	$raw = get_post_meta( $product_id, '_wcr_allowed_weekdays', true );

	if ( empty( $raw ) ) {
		return array();
	}

	$values = is_array( $raw ) ? $raw : array_map( 'trim', explode( ',', (string) $raw ) );
	$clean  = array();

	foreach ( $values as $day ) {
		$day = (string) $day;
		if ( in_array( $day, array( '0', '1', '2', '3', '4', '5', '6' ), true ) ) {
			$clean[] = $day;
		}
	}

	$clean = array_values( array_unique( $clean ) );

	usort(
		$clean,
		function( $a, $b ) {
			$order = array( '1', '2', '3', '4', '5', '6', '0' );
			return array_search( $a, $order, true ) <=> array_search( $b, $order, true );
		}
	);

	return $clean;
}

function cmbwc_get_product_ordering_text( $product_id ) {
	$days = cmbwc_get_product_allowed_weekdays( $product_id );

	$labels = array(
		'1' => 'mandag',
		'2' => 'tirsdag',
		'3' => 'onsdag',
		'4' => 'torsdag',
		'5' => 'fredag',
		'6' => 'lørdag',
		'0' => 'søndag',
	);

	if ( empty( $days ) ) {
		return 'Alle dage';
	}

	$selected = array();

	foreach ( $days as $day ) {
		if ( isset( $labels[ $day ] ) ) {
			$selected[] = $labels[ $day ];
		}
	}

	return empty( $selected ) ? 'Alle dage' : implode( ', ', $selected );
}

function cmbwc_shortcode_menu_info() {
	$product = cmbwc_get_current_product();

	if ( ! $product ) {
		return '';
	}

	$product_id     = $product->get_id();
	$minimum_covers = max( 1, (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true ) );
	$lead_time      = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );
	$ordering_text  = cmbwc_get_product_ordering_text( $product_id );

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-info">
		<h3>Menuinfo</h3>
		<div class="cmbwc-info-list">
			<div><span>Minimum kuverter</span> <span><?php echo esc_html( $minimum_covers ); ?></span></div>
			<?php if ( $lead_time > 0 ) : ?>
				<div><span>Bestilles senest</span> <span><?php echo esc_html( cmbwc_format_day_label( $lead_time ) ); ?></span></div>
			<?php endif; ?>
			<div><span>Kan bestilles</span> <span><?php echo esc_html( $ordering_text ); ?></span></div>
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

	$product_id        = $product->get_id();
	$included_products = get_post_meta( $product_id, '_cmbwc_included_products', true );

	if ( empty( $included_products ) || ! is_array( $included_products ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="cmbwc-box cmbwc-menu-contents">
		<h3>Indhold i menuen</h3>
		<div class="cmbwc-card-list">
			<?php foreach ( $included_products as $included_product_id ) : ?>
				<?php
				$included_product_id = absint( $included_product_id );
				$included_product    = wc_get_product( $included_product_id );

				if ( ! $included_product || 'publish' !== get_post_status( $included_product_id ) ) {
					continue;
				}
				?>
				<div class="cmbwc-card">
					<?php $img = cmbwc_get_product_image_url( $included_product_id ); ?>
					<?php if ( $img ) : ?>
						<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $included_product->get_name() ); ?>">
					<?php endif; ?>
					<div><?php echo esc_html( $included_product->get_name() ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function cmbwc_shortcode_menu_options_placeholder() {
	return '';
}

function cmbwc_render_menu_options_in_form() {
	global $product;

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return;
	}

	$product_id = $product->get_id();

	if ( 'yes' !== get_post_meta( $product_id, '_cmbwc_is_menu', true ) ) {
		return;
	}

	$minimum_covers = max( 1, (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true ) );
	$cover_step     = max( 1, (int) get_post_meta( $product_id, '_cmbwc_cover_step', true ) );
	$menu_addons    = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
	$service_keys   = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
	$service_all    = function_exists( 'cmbwc_get_service_options' ) ? cmbwc_get_service_options() : array();

	if ( ! is_array( $menu_addons ) ) {
		$menu_addons = array();
	}

	if ( ! is_array( $service_keys ) ) {
		$service_keys = array();
	}

	$price_per_cover   = (float) $product->get_price();
	$addons_for_output = array();

	foreach ( $menu_addons as $addon_product_id => $addon_data ) {
		$addon_product_id = absint( $addon_product_id );
		$addon_product    = wc_get_product( $addon_product_id );

		if ( ! $addon_product || 'publish' !== get_post_status( $addon_product_id ) ) {
			continue;
		}

		$follow_covers = isset( $addon_data['follow_covers'] ) && 'yes' === $addon_data['follow_covers'] ? 'yes' : 'no';
		$addon_price   = (float) $addon_product->get_price();

		$addons_for_output[] = array(
			'id'            => $addon_product_id,
			'name'          => $addon_product->get_name(),
			'price'         => $addon_price,
			'price_compact' => cmbwc_format_compact_price( $addon_price ),
			'follow_covers' => $follow_covers,
			'price_suffix'  => 'yes' === $follow_covers ? 'pr. kuvert' : 'stk.',
			'image'         => cmbwc_get_product_image_url( $addon_product_id ),
		);
	}

	$services_for_output = array();

	foreach ( $service_keys as $service_key ) {
		if ( empty( $service_all[ $service_key ] ) || ! is_array( $service_all[ $service_key ] ) ) {
			continue;
		}

		$service_data = $service_all[ $service_key ];
		$price_type   = isset( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed';
		$price        = isset( $service_data['price'] ) ? (float) $service_data['price'] : 0;
		$label        = isset( $service_data['title'] ) ? $service_data['title'] : ( isset( $service_data['label'] ) ? $service_data['label'] : $service_key );

		$services_for_output[] = array(
			'key'           => $service_key,
			'label'         => $label,
			'price'         => $price,
			'price_type'    => $price_type,
			'price_compact' => cmbwc_format_compact_price( $price ),
			'price_suffix'  => cmbwc_get_service_price_suffix( $service_data ),
		);
	}

	$default_service = ! empty( $services_for_output[0]['key'] ) ? $services_for_output[0]['key'] : '';
	?>
	<div class="cmbwc-box cmbwc-menu-options" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-price-per-cover="<?php echo esc_attr( $price_per_cover ); ?>" data-minimum-covers="<?php echo esc_attr( $minimum_covers ); ?>" data-cover-step="<?php echo esc_attr( $cover_step ); ?>">
		<h3>Menuvalg</h3>

		<div class="cmbwc-form-row">
			<label for="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>"><strong>Antal kuverter</strong></label>
			<input type="number" id="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>" class="cmbwc-covers" min="<?php echo esc_attr( $minimum_covers ); ?>" step="<?php echo esc_attr( $cover_step ); ?>" value="<?php echo esc_attr( $minimum_covers ); ?>">
		</div>

		<?php if ( ! empty( $addons_for_output ) ) : ?>
			<div class="cmbwc-section cmbwc-group-addons">
				<h4>Tilvalg</h4>
				<div class="cmbwc-addon-list">
					<?php foreach ( $addons_for_output as $addon ) : ?>
						<div class="cmbwc-addon-item cmbwc-choice-addon" data-addon-id="<?php echo esc_attr( $addon['id'] ); ?>" data-addon-price="<?php echo esc_attr( $addon['price'] ); ?>" data-follow-covers="<?php echo esc_attr( $addon['follow_covers'] ); ?>">
							<label class="cmbwc-addon-label">
								<input type="checkbox" class="cmbwc-addon-checkbox" value="<?php echo esc_attr( $addon['id'] ); ?>">
								<span class="cmbwc-addon-name"><?php echo esc_html( $addon['name'] ); ?></span>
								<span class="cmbwc-addon-price"><?php echo esc_html( $addon['price_compact'] . ' ' . $addon['price_suffix'] ); ?></span>
								<?php if ( 'no' === $addon['follow_covers'] ) : ?>
									<input type="number" class="cmbwc-addon-qty" min="1" step="1" value="1" disabled>
								<?php endif; ?>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $services_for_output ) ) : ?>
			<div class="cmbwc-section cmbwc-group-services">
				<h4>Type af fad / anretning</h4>
				<div class="cmbwc-service-list">
					<?php foreach ( $services_for_output as $index => $service ) : ?>
						<label class="cmbwc-service-item" data-service-price="<?php echo esc_attr( $service['price'] ); ?>" data-service-price-type="<?php echo esc_attr( $service['price_type'] ); ?>">
							<input type="radio" name="cmbwc_service_choice_<?php echo esc_attr( $product_id ); ?>" class="cmbwc-service-radio" value="<?php echo esc_attr( $service['key'] ); ?>" <?php checked( 0 === $index ); ?>>
							<span class="cmbwc-service-name"><?php echo esc_html( $service['label'] ); ?></span>
							<span class="cmbwc-service-price"><?php echo esc_html( $service['price_compact'] . ' ' . $service['price_suffix'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="cmbwc-pricing-box">
			<div><span>Pris pr. kuvert</span> <strong class="cmbwc-price-per-cover"><?php echo wp_kses_post( wc_price( $price_per_cover ) ); ?></strong></div>
			<div><span>Antal kuverter</span> <strong class="cmbwc-cover-count"><?php echo esc_html( $minimum_covers ); ?></strong></div>
			<div><span>Menupris</span> <strong class="cmbwc-menu-total"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong></div>
			<div><span>Tilvalg</span> <strong class="cmbwc-addon-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong></div>
			<div><span>Service</span> <strong class="cmbwc-service-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong></div>
			<div><span>Samlet pris</span> <strong class="cmbwc-total-price"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong></div>
		</div>

		<input type="hidden" name="cmbwc_covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
		<input type="hidden" name="cmbwc_selected_service" value="<?php echo esc_attr( $default_service ); ?>">
		<input type="hidden" name="cmbwc_selected_addons" value="[]">
	</div>
	<?php
}
