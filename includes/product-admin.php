<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers
 */
function cmbwc_get_public_products_grouped_by_category() {
	$products = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$grouped = array();

	foreach ( $products as $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$grouped['Uden kategori'][] = $product_id;
			continue;
		}

		$primary_name = $terms[0]->name;

		if ( ! isset( $grouped[ $primary_name ] ) ) {
			$grouped[ $primary_name ] = array();
		}

		$grouped[ $primary_name ][] = $product_id;
	}

	ksort( $grouped );

	return $grouped;
}

function cmbwc_get_service_options() {
	$service_options = get_option( 'cmbwc_service_options', array() );

	if ( empty( $service_options ) || ! is_array( $service_options ) ) {
		$service_options = array(
			'tray_glass' => array(
				'label'      => 'Træfad & glas skåle',
				'price'      => 200,
				'price_type' => 'fixed',
			),
			'disposable' => array(
				'label'      => 'Engangsfad & -skåle',
				'price'      => 4,
				'price_type' => 'per_cover',
			),
		);

		update_option( 'cmbwc_service_options', $service_options );
	}

	return $service_options;
}

/**
 * Product type checkbox
 */
add_action( 'woocommerce_product_options_general_product_data', 'cmbwc_add_menu_enable_field' );

function cmbwc_add_menu_enable_field() {
	echo '<div class="options_group">';

	woocommerce_wp_checkbox(
		array(
			'id'          => '_cmbwc_is_menu',
			'label'       => __( 'Brug dette produkt som menu', 'catering-menu-builder' ),
			'description' => __( 'Aktivér for at bruge produktet som catering-menu.', 'catering-menu-builder' ),
		)
	);

	echo '</div>';
}

add_action( 'woocommerce_process_product_meta', 'cmbwc_save_menu_enable_field' );

function cmbwc_save_menu_enable_field( $post_id ) {
	$value = isset( $_POST['_cmbwc_is_menu'] ) ? 'yes' : 'no';
	update_post_meta( $post_id, '_cmbwc_is_menu', $value );
}

/**
 * Product data tab
 */
add_filter( 'woocommerce_product_data_tabs', 'cmbwc_product_data_tab' );

function cmbwc_product_data_tab( $tabs ) {
	$tabs['cmbwc_menu_builder'] = array(
		'label'    => __( 'Catering Menu', 'catering-menu-builder' ),
		'target'   => 'cmbwc_menu_builder_data',
		'class'    => array(),
		'priority' => 80,
	);

	return $tabs;
}

add_action( 'woocommerce_product_data_panels', 'cmbwc_product_data_panel' );

function cmbwc_product_data_panel() {
	global $post;

	$product_id         = $post->ID;
	$is_menu            = get_post_meta( $product_id, '_cmbwc_is_menu', true );
	$included_products  = get_post_meta( $product_id, '_cmbwc_included_products', true );
	$menu_addons        = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
	$service_allowed    = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
	$minimum_covers     = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
	$cover_step         = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );
	$lead_time_days     = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );

	if ( ! is_array( $included_products ) ) {
		$included_products = array();
	}

	if ( ! is_array( $menu_addons ) ) {
		$menu_addons = array();
	}

	if ( ! is_array( $service_allowed ) ) {
		$service_allowed = array();
	}

	if ( $minimum_covers < 1 ) {
		$minimum_covers = 1;
	}

	if ( $cover_step < 1 ) {
		$cover_step = 1;
	}

	$grouped_products = cmbwc_get_public_products_grouped_by_category();
	$service_options  = cmbwc_get_service_options();
	?>
	<div id="cmbwc_menu_builder_data" class="panel woocommerce_options_panel hidden">

		<div class="options_group">
			<p class="form-field">
				<label>
					<input type="checkbox" name="_cmbwc_is_menu_duplicate" value="yes" <?php checked( $is_menu, 'yes' ); ?>>
					<?php esc_html_e( 'Brug dette produkt som menu', 'catering-menu-builder' ); ?>
				</label>
			</p>
		</div>

		<div class="options_group">
			<p class="form-field">
				<label for="_cmbwc_minimum_covers"><?php esc_html_e( 'Minimum antal kuverter', 'catering-menu-builder' ); ?></label>
				<input type="number" class="short" min="1" step="1" id="_cmbwc_minimum_covers" name="_cmbwc_minimum_covers" value="<?php echo esc_attr( $minimum_covers ); ?>">
			</p>

			<p class="form-field">
				<label for="_cmbwc_cover_step"><?php esc_html_e( 'Kuvert-interval', 'catering-menu-builder' ); ?></label>
				<input type="number" class="short" min="1" step="1" id="_cmbwc_cover_step" name="_cmbwc_cover_step" value="<?php echo esc_attr( $cover_step ); ?>">
			</p>

			<p class="form-field">
				<label for="_cmbwc_lead_time_days"><?php esc_html_e( 'Bestilles senest (dage før)', 'catering-menu-builder' ); ?></label>
				<input type="number" class="short" min="0" step="1" id="_cmbwc_lead_time_days" name="_cmbwc_lead_time_days" value="<?php echo esc_attr( $lead_time_days ); ?>">
			</p>
		</div>

		<div class="options_group">
			<p class="form-field"><strong><?php esc_html_e( 'Retter i menuen', 'catering-menu-builder' ); ?></strong></p>

			<div style="padding: 0 12px 12px;">
				<?php foreach ( $grouped_products as $category_name => $product_ids ) : ?>
					<div style="margin-bottom:16px; border:1px solid #e5e5e5; padding:10px; border-radius:6px;">
						<h4 style="margin:0 0 10px;"><?php echo esc_html( $category_name ); ?></h4>

						<?php foreach ( $product_ids as $loop_product_id ) : ?>
							<?php
							$loop_product = wc_get_product( $loop_product_id );
							if ( ! $loop_product ) {
								continue;
							}
							?>
							<p style="margin:0 0 6px;">
								<label>
									<input
										type="checkbox"
										name="_cmbwc_included_products[]"
										value="<?php echo esc_attr( $loop_product_id ); ?>"
										<?php checked( in_array( $loop_product_id, $included_products, true ) ); ?>
									>
									<?php echo esc_html( $loop_product->get_name() ); ?>
								</label>
							</p>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="options_group">
			<p class="form-field"><strong><?php esc_html_e( 'Mulige tilvalg', 'catering-menu-builder' ); ?></strong></p>

			<div style="padding: 0 12px 12px;">
				<?php foreach ( $grouped_products as $category_name => $product_ids ) : ?>
					<div style="margin-bottom:16px; border:1px solid #e5e5e5; padding:10px; border-radius:6px;">
						<h4 style="margin:0 0 10px;"><?php echo esc_html( $category_name ); ?></h4>

						<?php foreach ( $product_ids as $loop_product_id ) : ?>
							<?php
							$loop_product = wc_get_product( $loop_product_id );
							if ( ! $loop_product ) {
								continue;
							}

							$addon_settings = isset( $menu_addons[ $loop_product_id ] ) && is_array( $menu_addons[ $loop_product_id ] )
								? $menu_addons[ $loop_product_id ]
								: array(
									'enabled'       => 'no',
									'follow_covers' => 'yes',
								);

							$enabled       = isset( $addon_settings['enabled'] ) ? $addon_settings['enabled'] : 'no';
							$follow_covers = isset( $addon_settings['follow_covers'] ) ? $addon_settings['follow_covers'] : 'yes';
							?>
							<div style="margin-bottom:10px; padding-bottom:10px; border-bottom:1px dashed #eee;">
								<p style="margin:0 0 6px;">
									<label>
										<input
											type="checkbox"
											name="_cmbwc_menu_addons[<?php echo esc_attr( $loop_product_id ); ?>][enabled]"
											value="yes"
											<?php checked( $enabled, 'yes' ); ?>
										>
										<?php echo esc_html( $loop_product->get_name() ); ?>
										<?php if ( '' !== $loop_product->get_price() ) : ?>
											- <?php echo wp_kses_post( wc_price( $loop_product->get_price() ) ); ?>
										<?php endif; ?>
									</label>
								</p>

								<p style="margin:0 0 0 22px;">
									<label>
										<input
											type="checkbox"
											name="_cmbwc_menu_addons[<?php echo esc_attr( $loop_product_id ); ?>][follow_covers]"
											value="yes"
											<?php checked( $follow_covers, 'yes' ); ?>
										>
										<?php esc_html_e( 'Følger kuvertantal', 'catering-menu-builder' ); ?>
									</label>
								</p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="options_group">
			<p class="form-field"><strong><?php esc_html_e( 'Service / anretning', 'catering-menu-builder' ); ?></strong></p>

			<div style="padding: 0 12px 12px;">
				<?php foreach ( $service_options as $service_key => $service_data ) : ?>
					<?php
					$label      = isset( $service_data['label'] ) ? $service_data['label'] : $service_key;
					$price      = isset( $service_data['price'] ) ? (float) $service_data['price'] : 0;
					$price_type = isset( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed';
					?>
					<p style="margin:0 0 8px;">
						<label>
							<input
								type="checkbox"
								name="_cmbwc_service_allowed[]"
								value="<?php echo esc_attr( $service_key ); ?>"
								<?php checked( in_array( $service_key, $service_allowed, true ) ); ?>
							>
							<?php echo esc_html( $label ); ?>
							—
							<?php
							echo wp_kses_post(
								'fixed' === $price_type
									? wc_price( $price )
									: wc_price( $price ) . ' pr. kuvert'
							);
							?>
						</label>
					</p>
				<?php endforeach; ?>
			</div>
		</div>

	</div>
	<?php
}

add_action( 'woocommerce_process_product_meta', 'cmbwc_save_product_data_panel' );

function cmbwc_save_product_data_panel( $post_id ) {
	$is_menu = ( isset( $_POST['_cmbwc_is_menu'] ) || isset( $_POST['_cmbwc_is_menu_duplicate'] ) ) ? 'yes' : 'no';
	update_post_meta( $post_id, '_cmbwc_is_menu', $is_menu );

	$minimum_covers = isset( $_POST['_cmbwc_minimum_covers'] ) ? absint( wp_unslash( $_POST['_cmbwc_minimum_covers'] ) ) : 1;
	$cover_step     = isset( $_POST['_cmbwc_cover_step'] ) ? absint( wp_unslash( $_POST['_cmbwc_cover_step'] ) ) : 1;
	$lead_time_days = isset( $_POST['_cmbwc_lead_time_days'] ) ? absint( wp_unslash( $_POST['_cmbwc_lead_time_days'] ) ) : 0;

	update_post_meta( $post_id, '_cmbwc_minimum_covers', max( 1, $minimum_covers ) );
	update_post_meta( $post_id, '_cmbwc_cover_step', max( 1, $cover_step ) );
	update_post_meta( $post_id, '_cmbwc_lead_time_days', max( 0, $lead_time_days ) );

	$included_products = array();
	if ( isset( $_POST['_cmbwc_included_products'] ) && is_array( $_POST['_cmbwc_included_products'] ) ) {
		$included_products = array_map( 'absint', wp_unslash( $_POST['_cmbwc_included_products'] ) );
		$included_products = array_filter( $included_products );
	}
	update_post_meta( $post_id, '_cmbwc_included_products', array_values( $included_products ) );

	$addons = array();
	if ( isset( $_POST['_cmbwc_menu_addons'] ) && is_array( $_POST['_cmbwc_menu_addons'] ) ) {
		$raw_addons = wp_unslash( $_POST['_cmbwc_menu_addons'] );

		foreach ( $raw_addons as $addon_product_id => $addon_data ) {
			$addon_product_id = absint( $addon_product_id );
			if ( ! $addon_product_id || ! is_array( $addon_data ) ) {
				continue;
			}

			$enabled = isset( $addon_data['enabled'] ) ? 'yes' : 'no';

			if ( 'yes' !== $enabled ) {
				continue;
			}

			$addons[ $addon_product_id ] = array(
				'enabled'       => 'yes',
				'follow_covers' => isset( $addon_data['follow_covers'] ) ? 'yes' : 'no',
			);
		}
	}
	update_post_meta( $post_id, '_cmbwc_menu_addons', $addons );

	$service_allowed = array();
	if ( isset( $_POST['_cmbwc_service_allowed'] ) && is_array( $_POST['_cmbwc_service_allowed'] ) ) {
		$service_allowed = array_map( 'sanitize_text_field', wp_unslash( $_POST['_cmbwc_service_allowed'] ) );
		$service_allowed = array_filter( $service_allowed );
	}
	update_post_meta( $post_id, '_cmbwc_service_allowed', array_values( $service_allowed ) );
}
