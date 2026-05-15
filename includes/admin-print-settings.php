<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cmbwc_get_print_settings_defaults' ) ) {
	function cmbwc_get_print_settings_defaults() {
		return array(
			'enabled'               => 'yes',
			'store_name'            => get_bloginfo( 'name' ),
			'headline'              => 'KØKKENBON',
			'paper_width_mm'        => 80,
			'show_created'          => 'yes',
			'show_company'          => 'yes',
			'show_phone'            => 'yes',
			'show_shipping_method'  => 'yes',
			'show_shipping_address' => 'yes',
			'show_order_note'       => 'yes',
			'show_prices'           => 'no',
			'show_included'         => 'yes',
			'show_addons'           => 'yes',
			'show_service'          => 'no',
			'auto_mark_printed'     => 'yes',
		);
	}
}

if ( ! function_exists( 'cmbwc_yes_no_setting' ) ) {
	function cmbwc_yes_no_setting( $value ) {
		return ( 'yes' === $value || true === $value || '1' === (string) $value || 1 === $value ) ? 'yes' : 'no';
	}
}

if ( ! function_exists( 'cmbwc_sanitize_print_settings' ) ) {
	function cmbwc_sanitize_print_settings( $raw ) {
		$defaults = cmbwc_get_print_settings_defaults();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$paper_width = isset( $raw['paper_width_mm'] ) ? absint( $raw['paper_width_mm'] ) : absint( $defaults['paper_width_mm'] );

		if ( ! in_array( $paper_width, array( 58, 80 ), true ) ) {
			$paper_width = 80;
		}

		return array(
			'enabled'               => isset( $raw['enabled'] ) ? cmbwc_yes_no_setting( $raw['enabled'] ) : 'no',
			'store_name'            => isset( $raw['store_name'] ) ? sanitize_text_field( $raw['store_name'] ) : $defaults['store_name'],
			'headline'              => isset( $raw['headline'] ) ? sanitize_text_field( $raw['headline'] ) : $defaults['headline'],
			'paper_width_mm'        => $paper_width,
			'show_created'          => isset( $raw['show_created'] ) ? cmbwc_yes_no_setting( $raw['show_created'] ) : 'no',
			'show_company'          => isset( $raw['show_company'] ) ? cmbwc_yes_no_setting( $raw['show_company'] ) : 'no',
			'show_phone'            => isset( $raw['show_phone'] ) ? cmbwc_yes_no_setting( $raw['show_phone'] ) : 'no',
			'show_shipping_method'  => isset( $raw['show_shipping_method'] ) ? cmbwc_yes_no_setting( $raw['show_shipping_method'] ) : 'no',
			'show_shipping_address' => isset( $raw['show_shipping_address'] ) ? cmbwc_yes_no_setting( $raw['show_shipping_address'] ) : 'no',
			'show_order_note'       => isset( $raw['show_order_note'] ) ? cmbwc_yes_no_setting( $raw['show_order_note'] ) : 'no',
			'show_prices'           => isset( $raw['show_prices'] ) ? cmbwc_yes_no_setting( $raw['show_prices'] ) : 'no',
			'show_included'         => isset( $raw['show_included'] ) ? cmbwc_yes_no_setting( $raw['show_included'] ) : 'no',
			'show_addons'           => isset( $raw['show_addons'] ) ? cmbwc_yes_no_setting( $raw['show_addons'] ) : 'no',
			'show_service'          => isset( $raw['show_service'] ) ? cmbwc_yes_no_setting( $raw['show_service'] ) : 'no',
			'auto_mark_printed'     => isset( $raw['auto_mark_printed'] ) ? cmbwc_yes_no_setting( $raw['auto_mark_printed'] ) : 'no',
		);
	}
}

if ( ! function_exists( 'cmbwc_get_print_settings' ) ) {
	function cmbwc_get_print_settings() {
		$saved    = get_option( 'cmbwc_print_settings', array() );
		$defaults = cmbwc_get_print_settings_defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		return cmbwc_sanitize_print_settings( $settings );
	}
}

if ( ! function_exists( 'cmbwc_get_print_setting' ) ) {
	function cmbwc_get_print_setting( $key, $default = null ) {
		$settings = cmbwc_get_print_settings();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}
}

if ( ! function_exists( 'cmbwc_render_print_checkbox' ) ) {
	function cmbwc_render_print_checkbox( $settings, $key, $label ) {
		$value = isset( $settings[ $key ] ) ? $settings[ $key ] : 'no';
		?>
		<label class="cmbwc-print-checkbox">
			<input type="checkbox" name="cmbwc_print_settings[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $value, 'yes' ); ?>>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_print_settings_page' ) ) {
	function cmbwc_render_print_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if (
			isset( $_POST['cmbwc_print_settings_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmbwc_print_settings_nonce'] ) ), 'cmbwc_save_print_settings' )
		) {
			$raw   = isset( $_POST['cmbwc_print_settings'] ) ? wp_unslash( $_POST['cmbwc_print_settings'] ) : array();
			$clean = cmbwc_sanitize_print_settings( $raw );

			update_option( 'cmbwc_print_settings', $clean );

			echo '<div class="notice notice-success is-dismissible"><p>Printindstillinger gemt.</p></div>';
		}

		$settings = cmbwc_get_print_settings();
		?>
		<div class="wrap cmbwc-print-settings-page">
			<h1>Print / Bonner</h1>

			<p>Her styrer du layoutet til den BON, som PrintNode skal bruge.</p>

			<style>
				.cmbwc-print-settings-page .cmbwc-admin-notice-box {
					background: #fff;
					border: 1px solid #ccd0d4;
					padding: 16px 18px;
					margin: 16px 0;
					max-width: 1100px;
					border-radius: 8px;
				}

				.cmbwc-print-settings-page .cmbwc-admin-notice-box h2 {
					margin-top: 0;
				}

				.cmbwc-print-settings-page .cmbwc-print-settings-table {
					max-width: 1100px;
					background: #fff;
					padding: 12px 18px;
					border: 1px solid #ccd0d4;
					border-radius: 8px;
				}

				.cmbwc-print-settings-page .cmbwc-print-checkbox {
					display: block;
					margin-bottom: 8px;
				}

				.cmbwc-print-settings-page .cmbwc-actions {
					margin-top: 16px;
					display: flex;
					gap: 10px;
					flex-wrap: wrap;
					align-items: center;
				}

				.cmbwc-print-settings-page .description {
					max-width: 720px;
				}
			</style>

			<div class="cmbwc-admin-notice-box">
				<h2>Vigtigt i PrintNode-pluginet</h2>
				<ol style="padding-left:18px;">
					<li>Sæt flueben i <strong>Simple order summary</strong>.</li>
					<li>Vælg den printer der skal bruges, og slå <strong>Enable this printer</strong> til.</li>
					<li>Sæt bonbredde i PrintNode til <strong>80 mm</strong> eller <strong>58 mm</strong>.</li>
					<li>Brug gerne <strong>zero margin</strong>.</li>
				</ol>
				<p style="margin-bottom:0;">Dette plugin overtager selve BON-layoutet via template override.</p>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'cmbwc_save_print_settings', 'cmbwc_print_settings_nonce' ); ?>

				<table class="form-table cmbwc-print-settings-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Aktivér catering-BON</th>
							<td>
								<label>
									<input type="checkbox" name="cmbwc_print_settings[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>>
									Brug pluginets egen BON-skabelon til preview og PrintNode
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="cmbwc_print_store_name">Butiksnavn</label>
							</th>
							<td>
								<input
									type="text"
									id="cmbwc_print_store_name"
									class="regular-text"
									name="cmbwc_print_settings[store_name]"
									value="<?php echo esc_attr( $settings['store_name'] ); ?>"
								>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="cmbwc_print_headline">Overskrift</label>
							</th>
							<td>
								<input
									type="text"
									id="cmbwc_print_headline"
									class="regular-text"
									name="cmbwc_print_settings[headline]"
									value="<?php echo esc_attr( $settings['headline'] ); ?>"
								>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="cmbwc_print_paper_width">Bonbredde</label>
							</th>
							<td>
								<select id="cmbwc_print_paper_width" name="cmbwc_print_settings[paper_width_mm]">
									<option value="80" <?php selected( (int) $settings['paper_width_mm'], 80 ); ?>>80 mm</option>
									<option value="58" <?php selected( (int) $settings['paper_width_mm'], 58 ); ?>>58 mm</option>
								</select>
								<p class="description">Vælg den samme bredde som i PrintNode-printeropsætningen.</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Vis oplysninger</th>
							<td>
								<?php cmbwc_render_print_checkbox( $settings, 'show_created', 'Oprettet dato/tid' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_company', 'Firma' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_phone', 'Telefon' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_shipping_method', 'Leveringsmetode' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_shipping_address', 'Adresse' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_order_note', 'Kundebemærkning' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_prices', 'Priser' ); ?>
							</td>
						</tr>

						<tr>
							<th scope="row">Vis produktblokke</th>
							<td>
								<?php cmbwc_render_print_checkbox( $settings, 'show_included', 'Indhold' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_addons', 'Tilvalg' ); ?>
								<?php cmbwc_render_print_checkbox( $settings, 'show_service', 'Service' ); ?>
							</td>
						</tr>

						<tr>
							<th scope="row">Printstatus</th>
							<td>
								<label>
									<input type="checkbox" name="cmbwc_print_settings[auto_mark_printed]" value="yes" <?php checked( $settings['auto_mark_printed'], 'yes' ); ?>>
									Markér ordre som printet når der sendes PrintNode-job manuelt fra Woo
								</label>
								<p class="description">
									Dette påvirker kun pluginets interne BON-status — ikke WooCommerce-ordrestatus.
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="cmbwc-actions">
					<button type="submit" class="button button-primary">Gem printindstillinger</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>" class="button">Gå til produktionsoverblik</a>
				</div>
			</form>
		</div>
		<?php
	}
}
