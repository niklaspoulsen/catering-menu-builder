<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cmbwc_menu_info', 'cmbwc_shortcode_menu_info' );
add_shortcode( 'cmbwc_menu_contents', 'cmbwc_shortcode_menu_contents' );
add_shortcode( 'cmbwc_menu_options', 'cmbwc_shortcode_menu_options' );

add_action( 'woocommerce_before_add_to_cart_button', 'cmbwc_render_form_sync_fields', 5 );

if ( ! function_exists( 'cmbwc_render_form_sync_fields' ) ) {
	function cmbwc_render_form_sync_fields() {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$product_id = $product->get_id();

		if ( 'yes' !== get_post_meta( $product_id, '_cmbwc_is_menu', true ) ) {
			return;
		}

		$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );

		if ( $minimum_covers < 1 ) {
			$minimum_covers = 1;
		}

		$service_keys = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
		if ( ! is_array( $service_keys ) ) {
			$service_keys = array();
		}

		$default_service = ! empty( $service_keys[0] ) ? (string) $service_keys[0] : '';
		?>
		<input type="hidden" name="cmbwc_covers" class="cmbwc-form-sync-covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
		<input type="hidden" name="cmbwc_selected_service" class="cmbwc-form-sync-service" value="<?php echo esc_attr( $default_service ); ?>">
		<input type="hidden" name="cmbwc_selected_addons" class="cmbwc-form-sync-addons" value="[]">
		<?php
	}
}

if ( ! function_exists( 'cmbwc_get_current_product' ) ) {
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
}

if ( ! function_exists( 'cmbwc_is_menu_product' ) ) {
	function cmbwc_is_menu_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return false;
		}

		return 'yes' === get_post_meta( $product_id, '_cmbwc_is_menu', true );
	}
}

if ( ! function_exists( 'cmbwc_format_day_label' ) ) {
	function cmbwc_format_day_label( $days ) {
		$days = absint( $days );

		if ( 1 === $days ) {
			return '1 dag før';
		}

		return $days . ' dage før';
	}
}

if ( ! function_exists( 'cmbwc_get_product_image_url' ) ) {
	function cmbwc_get_product_image_url( $product_id, $size = 'thumbnail' ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return '';
		}

		$image_id = get_post_thumbnail_id( $product_id );

		if ( ! $image_id ) {
			return '';
		}

		$image = wp_get_attachment_image_src( $image_id, $size );

		return ! empty( $image[0] ) ? $image[0] : '';
	}
}

if ( ! function_exists( 'cmbwc_format_compact_price' ) ) {
	function cmbwc_format_compact_price( $price ) {
		$price = (float) $price;

		return number_format_i18n( $price, 0 ) . ',-';
	}
}

if ( ! function_exists( 'cmbwc_get_service_price_suffix' ) ) {
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
}

if ( ! function_exists( 'cmbwc_get_product_allowed_weekdays' ) ) {
	function cmbwc_get_product_allowed_weekdays( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return array();
		}

		$raw = get_post_meta( $product_id, '_wcr_allowed_weekdays', true );

		if ( empty( $raw ) ) {
			return array();
		}

		if ( is_array( $raw ) ) {
			$values = $raw;
		} else {
			$values = array_map( 'trim', explode( ',', (string) $raw ) );
		}

		$clean = array();

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
}

if ( ! function_exists( 'cmbwc_get_product_ordering_text' ) ) {
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

		if ( empty( $selected ) ) {
			return 'Alle dage';
		}

		return implode( ', ', $selected );
	}
}

if ( ! function_exists( 'cmbwc_get_primary_product_category_name' ) ) {
	function cmbwc_get_primary_product_category_name( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return 'Retter';
		}

		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 'Retter';
		}

		foreach ( $terms as $term ) {
			if ( ! $term || empty( $term->name ) ) {
				continue;
			}

			$slug       = isset( $term->slug ) ? (string) $term->slug : '';
			$name       = trim( (string) $term->name );
			$name_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );

			$is_uncategorized = in_array(
				$slug,
				array( 'uncategorized', 'ukategoriseret' ),
				true
			) || in_array(
				$name_lower,
				array( 'uncategorized', 'ukategoriseret', 'uden kategori' ),
				true
			);

			if ( ! $is_uncategorized ) {
				return $name;
			}
		}

		return 'Retter';
	}
}

if ( ! function_exists( 'cmbwc_shortcode_menu_info' ) ) {
	function cmbwc_shortcode_menu_info() {
		$product = cmbwc_get_current_product();

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$product_id     = $product->get_id();
		$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
		$lead_time      = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );
		$ordering_text  = cmbwc_get_product_ordering_text( $product_id );

		if ( $minimum_covers < 1 ) {
			$minimum_covers = 1;
		}

		ob_start();
		?>
		<div class="cmbwc-box cmbwc-menu-info cmbwc-block cmbwc-block-menu-info">
			<h3 class="cmbwc-title cmbwc-block-title">Menuinfo</h3>

			<div class="cmbwc-info-list cmbwc-list cmbwc-list-info">
				<div class="cmbwc-info-item cmbwc-info-row cmbwc-row">
					<span class="cmbwc-info-label cmbwc-label">Minimum kuverter</span>
					<span class="cmbwc-info-value cmbwc-value"><?php echo esc_html( $minimum_covers ); ?></span>
				</div>

				<?php if ( $lead_time > 0 ) : ?>
					<div class="cmbwc-info-item cmbwc-info-row cmbwc-row">
						<span class="cmbwc-info-label cmbwc-label">Bestilles senest</span>
						<span class="cmbwc-info-value cmbwc-value"><?php echo esc_html( cmbwc_format_day_label( $lead_time ) ); ?></span>
					</div>
				<?php endif; ?>

				<div class="cmbwc-info-item cmbwc-info-row cmbwc-row">
					<span class="cmbwc-info-label cmbwc-label">Kan bestilles</span>
					<span class="cmbwc-info-value cmbwc-value"><?php echo esc_html( $ordering_text ); ?></span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'cmbwc_shortcode_menu_contents' ) ) {
	function cmbwc_shortcode_menu_contents() {
		$product = cmbwc_get_current_product();

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$product_id        = $product->get_id();
		$included_products = get_post_meta( $product_id, '_cmbwc_included_products', true );

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

			$category_name = cmbwc_get_primary_product_category_name( $included_product_id );

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
		<div class="cmbwc-box cmbwc-menu-contents cmbwc-block cmbwc-block-menu-contents">
			<h3 class="cmbwc-title cmbwc-block-title">Indhold i menuen</h3>

			<?php foreach ( $grouped as $category_name => $items ) : ?>
				<div class="cmbwc-content-group cmbwc-group cmbwc-content-category">
					<h4 class="cmbwc-subtitle cmbwc-group-title"><?php echo esc_html( $category_name ); ?></h4>

					<div class="cmbwc-card-list cmbwc-list cmbwc-list-cards">
						<?php foreach ( $items as $item ) : ?>
							<div class="cmbwc-card cmbwc-ui-card cmbwc-content-card">
								<?php if ( ! empty( $item['image'] ) ) : ?>
									<div class="cmbwc-card-image-wrap cmbwc-media-wrap">
										<img class="cmbwc-card-image cmbwc-media" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
									</div>
								<?php endif; ?>

								<div class="cmbwc-card-content cmbwc-content">
									<div class="cmbwc-card-title cmbwc-ui-title"><?php echo esc_html( $item['name'] ); ?></div>
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
}

if ( ! function_exists( 'cmbwc_shortcode_menu_options' ) ) {
	function cmbwc_shortcode_menu_options() {
		$product = cmbwc_get_current_product();

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$product_id     = $product->get_id();
		$minimum_covers = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
		$cover_step     = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );
		$menu_addons    = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
		$service_keys   = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
		$service_all    = function_exists( 'cmbwc_get_service_options' ) ? cmbwc_get_service_options() : array();

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

		$price_per_cover   = (float) $product->get_price();
		$addons_for_output = array();

		foreach ( $menu_addons as $addon_product_id => $addon_data ) {
			$addon_product_id = absint( $addon_product_id );
			$addon_product    = wc_get_product( $addon_product_id );

			if ( ! $addon_product || 'publish' !== get_post_status( $addon_product_id ) ) {
				continue;
			}

			$enabled = isset( $addon_data['enabled'] ) ? $addon_data['enabled'] : 'yes';

			if ( 'yes' !== $enabled ) {
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
			$service_key = sanitize_title( $service_key );

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

		ob_start();
		?>
		<div
			class="cmbwc-box cmbwc-menu-options cmbwc-block cmbwc-block-menu-options"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-price-per-cover="<?php echo esc_attr( $price_per_cover ); ?>"
			data-minimum-covers="<?php echo esc_attr( $minimum_covers ); ?>"
			data-cover-step="<?php echo esc_attr( $cover_step ); ?>"
		>
			<h3 class="cmbwc-title cmbwc-block-title">Menuvalg</h3>

			<div class="cmbwc-field cmbwc-form-row cmbwc-form-row-covers">
				<label for="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>" class="cmbwc-label cmbwc-field-label"><strong>Antal kuverter</strong></label>
				<input
					type="number"
					id="cmbwc_covers_<?php echo esc_attr( $product_id ); ?>"
					class="cmbwc-covers cmbwc-input cmbwc-input-covers"
					min="<?php echo esc_attr( $minimum_covers ); ?>"
					step="<?php echo esc_attr( $cover_step ); ?>"
					value="<?php echo esc_attr( $minimum_covers ); ?>"
				>
			</div>

			<?php if ( ! empty( $addons_for_output ) ) : ?>
				<div class="cmbwc-section cmbwc-group cmbwc-group-addons">
					<h4 class="cmbwc-subtitle cmbwc-group-title">Tilvalg</h4>

					<div class="cmbwc-addon-list cmbwc-list cmbwc-list-addons">
						<?php foreach ( $addons_for_output as $addon ) : ?>
							<div
								class="cmbwc-addon-item cmbwc-ui-card cmbwc-choice-card cmbwc-choice-addon"
								data-addon-id="<?php echo esc_attr( $addon['id'] ); ?>"
								data-addon-price="<?php echo esc_attr( $addon['price'] ); ?>"
								data-follow-covers="<?php echo esc_attr( $addon['follow_covers'] ); ?>"
							>
								<label class="cmbwc-addon-label cmbwc-choice-label">
									<span class="cmbwc-addon-left cmbwc-choice-left">
										<input
											type="checkbox"
											class="cmbwc-addon-checkbox cmbwc-choice-input cmbwc-visually-hidden-input"
											value="<?php echo esc_attr( $addon['id'] ); ?>"
										>

										<span class="cmbwc-choice-control cmbwc-choice-control-checkbox" aria-hidden="true"></span>

										<?php if ( ! empty( $addon['image'] ) ) : ?>
											<img class="cmbwc-addon-image cmbwc-choice-image" src="<?php echo esc_url( $addon['image'] ); ?>" alt="<?php echo esc_attr( $addon['name'] ); ?>">
										<?php endif; ?>

										<span class="cmbwc-addon-name-wrap cmbwc-choice-content">
											<span class="cmbwc-addon-name cmbwc-ui-title"><?php echo esc_html( $addon['name'] ); ?></span>
										</span>
									</span>

									<?php if ( 'no' === $addon['follow_covers'] ) : ?>
										<span class="cmbwc-addon-right-wrap cmbwc-choice-right cmbwc-choice-right-qty">
											<span class="cmbwc-addon-right-price cmbwc-choice-right-price" aria-hidden="true">
												<span class="cmbwc-addon-follow-price-value cmbwc-price-big"><?php echo esc_html( $addon['price_compact'] ); ?></span>
												<span class="cmbwc-addon-follow-price-note cmbwc-price-small"><?php echo esc_html( $addon['price_suffix'] ); ?></span>
											</span>

											<input
												type="number"
												class="cmbwc-addon-qty cmbwc-input cmbwc-input-addon-qty"
												min="1"
												step="1"
												value="1"
												disabled
											>
										</span>
									<?php else : ?>
										<span class="cmbwc-addon-follow-price cmbwc-choice-right cmbwc-choice-right-price" aria-hidden="true">
											<span class="cmbwc-addon-follow-price-value cmbwc-price-big"><?php echo esc_html( $addon['price_compact'] ); ?></span>
											<span class="cmbwc-addon-follow-price-note cmbwc-price-small"><?php echo esc_html( $addon['price_suffix'] ); ?></span>
										</span>
									<?php endif; ?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $services_for_output ) ) : ?>
				<div class="cmbwc-section cmbwc-group cmbwc-group-services">
					<h4 class="cmbwc-subtitle cmbwc-group-title">Type af fad / anretning</h4>

					<div class="cmbwc-service-list cmbwc-list cmbwc-list-services">
						<?php foreach ( $services_for_output as $index => $service ) : ?>
							<label
								class="cmbwc-service-item cmbwc-ui-card cmbwc-choice-card cmbwc-choice-service"
								data-service-price="<?php echo esc_attr( $service['price'] ); ?>"
								data-service-price-type="<?php echo esc_attr( $service['price_type'] ); ?>"
							>
								<span class="cmbwc-service-left cmbwc-choice-left">
									<input
										type="radio"
										name="cmbwc_service_choice_<?php echo esc_attr( $product_id ); ?>"
										class="cmbwc-service-radio cmbwc-choice-input cmbwc-visually-hidden-input"
										value="<?php echo esc_attr( $service['key'] ); ?>"
										<?php checked( 0 === $index ); ?>
									>

									<span class="cmbwc-choice-control cmbwc-choice-control-radio" aria-hidden="true"></span>

									<span class="cmbwc-service-content cmbwc-choice-content">
										<span class="cmbwc-service-name cmbwc-ui-title"><?php echo esc_html( $service['label'] ); ?></span>
									</span>
								</span>

								<span class="cmbwc-service-price-wrap cmbwc-choice-right cmbwc-choice-right-price" aria-hidden="true">
									<span class="cmbwc-service-price-value cmbwc-price-big"><?php echo esc_html( $service['price_compact'] ); ?></span>
									<span class="cmbwc-service-price-note cmbwc-price-small"><?php echo esc_html( $service['price_suffix'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="cmbwc-section cmbwc-group cmbwc-group-pricing">
				<h4 class="cmbwc-subtitle cmbwc-group-title">Prisoversigt</h4>

				<div class="cmbwc-price-box cmbwc-pricing-box">
					<div class="cmbwc-price-row cmbwc-row">
						<span class="cmbwc-label">Pris pr. kuvert</span>
						<strong class="cmbwc-price-per-cover cmbwc-value"><?php echo wp_kses_post( wc_price( $price_per_cover ) ); ?></strong>
					</div>

					<div class="cmbwc-price-row cmbwc-row">
						<span class="cmbwc-label">Antal kuverter</span>
						<strong class="cmbwc-cover-count cmbwc-value"><?php echo esc_html( $minimum_covers ); ?></strong>
					</div>

					<div class="cmbwc-price-row cmbwc-row">
						<span class="cmbwc-label">Menupris</span>
						<strong class="cmbwc-menu-total cmbwc-value"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
					</div>

					<div class="cmbwc-price-row cmbwc-row">
						<span class="cmbwc-label">Tilvalg</span>
						<strong class="cmbwc-addon-total cmbwc-value"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
					</div>

					<div class="cmbwc-price-row cmbwc-row">
						<span class="cmbwc-label">Service</span>
						<strong class="cmbwc-service-total cmbwc-value"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
					</div>

					<div class="cmbwc-price-row cmbwc-price-row-total cmbwc-row cmbwc-row-total">
						<span class="cmbwc-label">Samlet pris</span>
						<strong class="cmbwc-total-price cmbwc-value"><?php echo wp_kses_post( wc_price( $price_per_cover * $minimum_covers ) ); ?></strong>
					</div>
				</div>
			</div>

			<input type="hidden" class="cmbwc-local-sync-covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
			<input type="hidden" class="cmbwc-local-sync-service" value="<?php echo esc_attr( $default_service ); ?>">
			<input type="hidden" class="cmbwc-local-sync-addons" value="[]">
		</div>
		<?php
		return ob_get_clean();
	}
}
