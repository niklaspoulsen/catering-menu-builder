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

if ( ! function_exists( 'cmbwc_get_print_settings' ) ) {
	function cmbwc_get_print_settings() {
		$saved = get_option( 'cmbwc_print_settings', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, cmbwc_get_print_settings_defaults() );
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

if ( ! function_exists( 'cmbwc_render_print_settings_page' ) ) {
	function cmbwc_render_print_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if (
			isset( $_POST['cmbwc_print_settings_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmbwc_print_settings_nonce'] ) ), 'cmbwc_save_print_settings' )
		) {
			$raw = isset( $_POST['cmbwc_print_settings'] ) ? wp_unslash( $_POST['cmbwc_print_settings'] ) : array();

			$clean = array(
				'enabled'               => ( isset( $raw['enabled'] ) && 'yes' === $raw['enabled'] ) ? 'yes' : 'no',
				'store_name'            => isset( $raw['store_name'] ) ? sanitize_text_field( $raw['store_name'] ) : '',
				'headline'              => isset( $raw['headline'] ) ? sanitize_text_field( $raw['headline'] ) : '',
				'paper_width_mm'        => isset( $raw['paper_width_mm'] ) ? max( 58, min( 80, absint( $raw['paper_width_mm'] ) ) ) : 80,
				'show_created'          => ( isset( $raw['show_created'] ) && 'yes' === $raw['show_created'] ) ? 'yes' : 'no',
				'show_company'          => ( isset( $raw['show_company'] ) && 'yes' === $raw['show_company'] ) ? 'yes' : 'no',
				'show_phone'            => ( isset( $raw['show_phone'] ) && 'yes' === $raw['show_phone'] ) ? 'yes' : 'no',
				'show_shipping_method'  => ( isset( $raw['show_shipping_method'] ) && 'yes' === $raw['show_shipping_method'] ) ? 'yes' : 'no',
				'show_shipping_address' => ( isset( $raw['show_shipping_address'] ) && 'yes' === $raw['show_shipping_address'] ) ? 'yes' : 'no',
				'show_order_note'       => ( isset( $raw['show_order_note'] ) && 'yes' === $raw['show_order_note'] ) ? 'yes' : 'no',
				'show_prices'           => ( isset( $raw['show_prices'] ) && 'yes' === $raw['show_prices'] ) ? 'yes' : 'no',
				'show_included'         => ( isset( $raw['show_included'] ) && 'yes' === $raw['show_included'] ) ? 'yes' : 'no',
				'show_addons'           => ( isset( $raw['show_addons'] ) && 'yes' === $raw['show_addons'] ) ? 'yes' : 'no',
				'show_service'          => ( isset( $raw['show_service'] ) && 'yes' === $raw['show_service'] ) ? 'yes' : 'no',
				'auto_mark_printed'     => ( isset( $raw['auto_mark_printed'] ) && 'yes' === $raw['auto_mark_printed'] ) ? 'yes' : 'no',
			);

			update_option( 'cmbwc_print_settings', $clean );

			echo '<div class="notice notice-success is-dismissible"><p>Printindstillinger gemt.</p></div>';
		}

		$settings = cmbwc_get_print_settings();
		?>
		<div class="wrap">
			<h1>Print / Bonner</h1>

			<p>Her styrer du layoutet til den BON, som PrintNode skal bruge.</p>

			<div style="background:#fff;border:1px solid #ccd0d4;padding:16px 18px;margin:16px 0;max-width:1100px;">
				<h2 style="margin-top:0;">Vigtigt i PrintNode-pluginet</h2>
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

				<table class="form-table" role="presentation" style="max-width:1100px;background:#fff;padding:12px 18px;border:1px solid #ccd0d4;">
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
							<th scope="row">Butiksnavn</th>
							<td>
								<input type="text" class="regular-text" name="cmbwc_print_settings[store_name]" value="<?php echo esc_attr( $settings['store_name'] ); ?>">
							</td>
						</tr>

						<tr>
							<th scope="row">Overskrift</th>
							<td>
								<input type="text" class="regular-text" name="cmbwc_print_settings[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>">
							</td>
						</tr>

						<tr>
							<th scope="row">Bonbredde</th>
							<td>
								<select name="cmbwc_print_settings[paper_width_mm]">
									<option value="80" <?php selected( (int) $settings['paper_width_mm'], 80 ); ?>>80 mm</option>
									<option value="58" <?php selected( (int) $settings['paper_width_mm'], 58 ); ?>>58 mm</option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">Vis oplysninger</th>
							<td>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_created]" value="yes" <?php checked( $settings['show_created'], 'yes' ); ?>> Oprettet dato/tid</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_company]" value="yes" <?php checked( $settings['show_company'], 'yes' ); ?>> Firma</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_phone]" value="yes" <?php checked( $settings['show_phone'], 'yes' ); ?>> Telefon</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_shipping_method]" value="yes" <?php checked( $settings['show_shipping_method'], 'yes' ); ?>> Leveringsmetode</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_shipping_address]" value="yes" <?php checked( $settings['show_shipping_address'], 'yes' ); ?>> Adresse</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_order_note]" value="yes" <?php checked( $settings['show_order_note'], 'yes' ); ?>> Kundebemærkning</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_prices]" value="yes" <?php checked( $settings['show_prices'], 'yes' ); ?>> Priser</label>
							</td>
						</tr>

						<tr>
							<th scope="row">Vis produktblokke</th>
							<td>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_included]" value="yes" <?php checked( $settings['show_included'], 'yes' ); ?>> Indhold</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_addons]" value="yes" <?php checked( $settings['show_addons'], 'yes' ); ?>> Tilvalg</label>
								<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="cmbwc_print_settings[show_service]" value="yes" <?php checked( $settings['show_service'], 'yes' ); ?>> Service</label>
							</td>
						</tr>

						<tr>
							<th scope="row">Printstatus</th>
							<td>
								<label>
									<input type="checkbox" name="cmbwc_print_settings[auto_mark_printed]" value="yes" <?php checked( $settings['auto_mark_printed'], 'yes' ); ?>>
									Markér ordre som printet når der sendes PrintNode-job manuelt fra Woo
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<p style="margin-top:16px;">
					<button type="submit" class="button button-primary">Gem printindstillinger</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>" class="button">Gå til produktionsoverblik</a>
				</p>
			</form>
		</div>
		<?php
	}
}
