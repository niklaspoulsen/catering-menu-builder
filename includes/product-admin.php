<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers.
 */
if ( ! function_exists( 'cmbwc_get_public_products_grouped_by_category' ) ) {
	function cmbwc_get_public_products_grouped_by_category( $exclude_product_id = 0 ) {
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
			$product_id = absint( $product_id );

			if ( ! $product_id ) {
				continue;
			}

			if ( $exclude_product_id && (int) $product_id === (int) $exclude_product_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$terms = get_the_terms( $product_id, 'product_cat' );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$valid_terms = array();

			foreach ( $terms as $term ) {
				if ( ! $term || empty( $term->term_id ) ) {
					continue;
				}

				$slug       = isset( $term->slug ) ? (string) $term->slug : '';
				$name       = isset( $term->name ) ? trim( (string) $term->name ) : '';
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

				if ( $is_uncategorized ) {
					continue;
				}

				$valid_terms[] = $term;
			}

			if ( empty( $valid_terms ) ) {
				continue;
			}

			usort(
				$valid_terms,
				function( $a, $b ) {
					return (int) $a->term_id <=> (int) $b->term_id;
				}
			);

			$primary_name = $valid_terms[0]->name;

			if ( ! isset( $grouped[ $primary_name ] ) ) {
				$grouped[ $primary_name ] = array();
			}

			$grouped[ $primary_name ][] = $product_id;
		}

		ksort( $grouped, SORT_NATURAL | SORT_FLAG_CASE );

		return $grouped;
	}
}

if ( ! function_exists( 'cmbwc_render_product_picker_group' ) ) {
	function cmbwc_render_product_picker_group( $title, $product_ids, $selected_ids, $field_name ) {
		if ( empty( $product_ids ) ) {
			return;
		}

		if ( ! is_array( $selected_ids ) ) {
			$selected_ids = array();
		}

		$selected_ids = array_map( 'absint', $selected_ids );
		?>
		<div style="margin-bottom:18px; border:1px solid #ddd; border-radius:10px; background:#fff; overflow:hidden;">
			<div style="padding:12px 14px; border-bottom:1px solid #eee; font-weight:600; background:#fafafa;">
				<?php echo esc_html( $title ); ?>
			</div>
			<div style="padding:12px 14px;">
				<?php foreach ( $product_ids as $product_id ) : ?>
					<?php
					$product_id = absint( $product_id );
					$product    = wc_get_product( $product_id );

					if ( ! $product ) {
						continue;
					}
					?>
					<label style="display:block; margin:0 0 10px;">
						<input
							type="checkbox"
							name="<?php echo esc_attr( $field_name ); ?>[]"
							value="<?php echo esc_attr( $product_id ); ?>"
							<?php checked( in_array( $product_id, $selected_ids, true ) ); ?>
						>
						<?php echo esc_html( $product->get_name() ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_addon_group' ) ) {
	function cmbwc_render_addon_group( $title, $product_ids, $menu_addons ) {
		if ( empty( $product_ids ) ) {
			return;
		}

		if ( ! is_array( $menu_addons ) ) {
			$menu_addons = array();
		}
		?>
		<div style="margin-bottom:18px; border:1px solid #d9d9d9; border-radius:10px; background:#fff; overflow:hidden;">
			<div style="padding:12px 14px; border-bottom:1px solid #eee; font-weight:700; background:#f8f8f8;">
				<?php echo esc_html( $title ); ?>
			</div>
			<div style="padding:12px 14px;">
				<?php foreach ( $product_ids as $product_id ) : ?>
					<?php
					$product_id = absint( $product_id );
					$product    = wc_get_product( $product_id );

					if ( ! $product ) {
						continue;
					}

					$addon_settings = isset( $menu_addons[ $product_id ] ) && is_array( $menu_addons[ $product_id ] )
						? $menu_addons[ $product_id ]
						: array(
							'enabled'       => 'no',
							'follow_covers' => 'yes',
						);

					$enabled       = isset( $addon_settings['enabled'] ) ? $addon_settings['enabled'] : 'no';
					$follow_covers = isset( $addon_settings['follow_covers'] ) ? $addon_settings['follow_covers'] : 'yes';
					?>
					<div style="padding:12px 0; border-bottom:1px dashed #e5e5e5;">
						<label style="display:block; margin-bottom:6px; font-weight:500;">
							<input
								type="checkbox"
								name="_cmbwc_menu_addons[<?php echo esc_attr( $product_id ); ?>][enabled]"
								value="yes"
								<?php checked( $enabled, 'yes' ); ?>
							>
							<?php echo esc_html( $product->get_name() ); ?>
							<?php if ( '' !== $product->get_price() ) : ?>
								- <?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?>
							<?php endif; ?>
						</label>

						<label style="display:block; margin-left:22px; color:#555;">
							<input
								type="checkbox"
								name="_cmbwc_menu_addons[<?php echo esc_attr( $product_id ); ?>][follow_covers]"
								value="yes"
								<?php checked( $follow_covers, 'yes' ); ?>
							>
							Følger kuvertantal
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_section_start' ) ) {
	function cmbwc_render_section_start( $title, $count = 0, $description = '', $open = false, $accent = '#dfe3e8', $bg = '#ffffff' ) {
		$title_with_count = $title . ' (' . (int) $count . ' valgt)';
		?>
		<details <?php echo $open ? 'open' : ''; ?> style="margin:0 0 18px; border:1px solid <?php echo esc_attr( $accent ); ?>; border-radius:12px; background:<?php echo esc_attr( $bg ); ?>; overflow:hidden;">
			<summary style="cursor:pointer; list-style:none; padding:16px 18px; font-size:18px; font-weight:700; border-bottom:1px solid <?php echo esc_attr( $accent ); ?>; background:#f9fafb;">
				<?php echo esc_html( $title_with_count ); ?>
			</summary>
			<div style="padding:18px;">
				<?php if ( $description ) : ?>
					<div style="padding:14px 16px; margin:0 0 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; color:#334155;">
						<?php echo wp_kses_post( $description ); ?>
					</div>
				<?php endif; ?>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_section_end' ) ) {
	function cmbwc_render_section_end() {
		?>
			</div>
		</details>
		<?php
	}
}

/**
 * Small checkbox in WooCommerce general tab.
 */
add_action( 'woocommerce_product_options_general_product_data', 'cmbwc_add_menu_enable_field' );

if ( ! function_exists( 'cmbwc_add_menu_enable_field' ) ) {
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
}

add_action( 'woocommerce_process_product_meta', 'cmbwc_save_menu_enable_field' );

if ( ! function_exists( 'cmbwc_save_menu_enable_field' ) ) {
	function cmbwc_save_menu_enable_field( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['_cmbwc_is_menu'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_cmbwc_is_menu', $value );
	}
}

/**
 * Add separate metabox.
 */
add_action( 'add_meta_boxes', 'cmbwc_register_product_metabox' );

if ( ! function_exists( 'cmbwc_register_product_metabox' ) ) {
	function cmbwc_register_product_metabox() {
		add_meta_box(
			'cmbwc_menu_builder_metabox',
			'Catering Menu Builder',
			'cmbwc_render_product_metabox',
			'product',
			'normal',
			'high'
		);
	}
}

if ( ! function_exists( 'cmbwc_render_product_metabox' ) ) {
	function cmbwc_render_product_metabox( $post ) {
		$product_id        = $post->ID;
		$is_menu           = get_post_meta( $product_id, '_cmbwc_is_menu', true );
		$included_products = get_post_meta( $product_id, '_cmbwc_included_products', true );
		$menu_addons       = get_post_meta( $product_id, '_cmbwc_menu_addons', true );
		$service_allowed   = get_post_meta( $product_id, '_cmbwc_service_allowed', true );
		$minimum_covers    = (int) get_post_meta( $product_id, '_cmbwc_minimum_covers', true );
		$cover_step        = (int) get_post_meta( $product_id, '_cmbwc_cover_step', true );
		$lead_time_days    = (int) get_post_meta( $product_id, '_cmbwc_lead_time_days', true );

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

		$grouped_products = cmbwc_get_public_products_grouped_by_category( $product_id );
		$service_options  = function_exists( 'cmbwc_get_service_options' ) ? cmbwc_get_service_options() : array();

		$included_count = count( $included_products );
		$addon_count    = count( $menu_addons );
		$service_count  = count( $service_allowed );

		wp_nonce_field( 'cmbwc_save_metabox', 'cmbwc_metabox_nonce' );
		?>
		<div style="padding:8px 0 0;">
			<p style="margin-bottom:16px;">
				<label>
					<input type="checkbox" name="_cmbwc_is_menu_duplicate" value="yes" <?php checked( $is_menu, 'yes' ); ?>>
					<strong>Brug dette produkt som menu</strong>
				</label>
			</p>

			<div style="display:grid; grid-template-columns:repeat(3, minmax(180px, 1fr)); gap:16px; margin-bottom:24px;">
				<p style="margin:0;">
					<label for="_cmbwc_minimum_covers"><strong>Minimum antal kuverter</strong></label><br>
					<input type="number" min="1" step="1" id="_cmbwc_minimum_covers" name="_cmbwc_minimum_covers" value="<?php echo esc_attr( $minimum_covers ); ?>" style="width:100%;">
				</p>

				<p style="margin:0;">
					<label for="_cmbwc_cover_step"><strong>Kuvert-interval</strong></label><br>
					<input type="number" min="1" step="1" id="_cmbwc_cover_step" name="_cmbwc_cover_step" value="<?php echo esc_attr( $cover_step ); ?>" style="width:100%;">
				</p>

				<p style="margin:0;">
					<label for="_cmbwc_lead_time_days"><strong>Bestilles senest (dage før)</strong></label><br>
					<input type="number" min="0" step="1" id="_cmbwc_lead_time_days" name="_cmbwc_lead_time_days" value="<?php echo esc_attr( $lead_time_days ); ?>" style="width:100%;">
				</p>
			</div>

			<?php
			cmbwc_render_section_start(
				'Retter i menuen',
				$included_count,
				'Vælg her de produkter, som skal indgå som <strong>faste retter i menuen</strong>.',
				true,
				'#e2e8f0',
				'#ffffff'
			);
			?>

			<?php if ( ! empty( $grouped_products ) ) : ?>
				<?php foreach ( $grouped_products as $category_name => $product_ids ) : ?>
					<?php cmbwc_render_product_picker_group( $category_name, $product_ids, $included_products, '_cmbwc_included_products' ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div style="padding:14px 16px; background:#fff; border:1px solid #ddd; border-radius:10px;">
					Ingen kategoriserede produkter fundet. Produkter i “Ukategoriseret” vises ikke her.
				</div>
			<?php endif; ?>

			<?php cmbwc_render_section_end(); ?>

			<?php
			cmbwc_render_section_start(
				'Mulige tilvalg',
				$addon_count,
				'Vælg her produkter, som <strong>kan tilkøbes som tilvalg</strong>. Disse er ikke en del af selve menuen, medmindre kunden aktivt vælger dem.',
				false,
				'#fed7aa',
				'#fffbf5'
			);
			?>

			<?php if ( ! empty( $grouped_products ) ) : ?>
				<?php foreach ( $grouped_products as $category_name => $product_ids ) : ?>
					<?php cmbwc_render_addon_group( $category_name, $product_ids, $menu_addons ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div style="padding:14px 16px; background:#fff; border:1px solid #ddd; border-radius:10px;">
					Ingen kategoriserede produkter fundet til tilvalg.
				</div>
			<?php endif; ?>

			<?php cmbwc_render_section_end(); ?>

			<?php
			cmbwc_render_section_start(
				'Service / anretning',
				$service_count,
				'Vælg hvilke servicevalg og anretninger der må kunne bruges sammen med denne menu.',
				false,
				'#d6d3d1',
				'#fcfcfc'
			);
			?>

			<div style="border:1px solid #ddd; border-radius:10px; background:#fff; padding:14px;">
				<?php if ( ! empty( $service_options ) ) : ?>
					<?php foreach ( $service_options as $service_key => $service_data ) : ?>
						<?php
						$label      = isset( $service_data['label'] ) ? $service_data['label'] : $service_key;
						$price      = isset( $service_data['price'] ) ? (float) $service_data['price'] : 0;
						$price_type = isset( $service_data['price_type'] ) ? $service_data['price_type'] : 'fixed';
						$is_deposit = isset( $service_data['is_deposit'] ) ? $service_data['is_deposit'] : 'no';
						?>
						<p style="margin:0 0 10px;">
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
								<?php if ( 'yes' === $is_deposit ) : ?>
									<strong>(depositum)</strong>
								<?php endif; ?>
							</label>
						</p>
					<?php endforeach; ?>
				<?php else : ?>
					<p>Ingen servicevalg fundet endnu. Opret dem under Catering → Servicevalg.</p>
				<?php endif; ?>
			</div>

			<?php cmbwc_render_section_end(); ?>
		</div>
		<?php
	}
}

/**
 * Save metabox.
 */
add_action( 'save_post_product', 'cmbwc_save_product_metabox' );

if ( ! function_exists( 'cmbwc_save_product_metabox' ) ) {
	function cmbwc_save_product_metabox( $post_id ) {
		if ( ! isset( $_POST['cmbwc_metabox_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmbwc_metabox_nonce'] ) ), 'cmbwc_save_metabox' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

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

		update_post_meta( $post_id, '_cmbwc_included_products', array_values( array_unique( $included_products ) ) );

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

		update_post_meta( $post_id, '_cmbwc_service_allowed', array_values( array_unique( $service_allowed ) ) );
	}
}
