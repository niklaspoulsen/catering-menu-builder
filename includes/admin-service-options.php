<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hent servicevalg.
 */
function cmbwc_get_service_options() {
	$service_options = get_option( 'cmbwc_service_options', array() );

	if ( empty( $service_options ) || ! is_array( $service_options ) ) {
		$service_options = array(
			'tray_glass' => array(
				'label'      => 'Træfad & glas skåle',
				'price'      => 200,
				'price_type' => 'fixed',
				'is_deposit' => 'yes',
			),
			'disposable' => array(
				'label'      => 'Engangsfad & -skåle',
				'price'      => 4,
				'price_type' => 'per_cover',
				'is_deposit' => 'no',
			),
		);

		update_option( 'cmbwc_service_options', $service_options );
	}

	return $service_options;
}

/**
 * Hent ét servicevalg.
 */
function cmbwc_get_service_option_by_key( $service_key ) {
	$all = cmbwc_get_service_options();

	if ( empty( $all[ $service_key ] ) || ! is_array( $all[ $service_key ] ) ) {
		return null;
	}

	return $all[ $service_key ];
}

/**
 * Render adminside.
 */
function cmbwc_render_service_options_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if (
		isset( $_POST['cmbwc_service_options_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmbwc_service_options_nonce'] ) ), 'cmbwc_save_service_options' )
	) {
		$submitted = isset( $_POST['cmbwc_service_options'] ) ? wp_unslash( $_POST['cmbwc_service_options'] ) : array();
		$cleaned   = array();

		if ( is_array( $submitted ) ) {
			foreach ( $submitted as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$key_raw      = isset( $row['key'] ) ? sanitize_title( $row['key'] ) : '';
				$label        = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
				$price        = isset( $row['price'] ) ? wc_format_decimal( $row['price'] ) : 0;
				$price_type   = isset( $row['price_type'] ) && 'per_cover' === $row['price_type'] ? 'per_cover' : 'fixed';
				$is_deposit   = isset( $row['is_deposit'] ) && 'yes' === $row['is_deposit'] ? 'yes' : 'no';

				if ( '' === $label ) {
					continue;
				}

				$key = $key_raw ? $key_raw : sanitize_title( $label );

				if ( '' === $key ) {
					continue;
				}

				$cleaned[ $key ] = array(
					'label'      => $label,
					'price'      => (float) $price,
					'price_type' => $price_type,
					'is_deposit' => $is_deposit,
				);
			}
		}

		update_option( 'cmbwc_service_options', $cleaned );

		echo '<div class="notice notice-success is-dismissible"><p>Servicevalg gemt.</p></div>';
	}

	$options = cmbwc_get_service_options();
	?>
	<div class="wrap">
		<h1>Servicevalg</h1>
		<p>Her vedligeholder du servicevalg, pris og om linjen er depositum.</p>

		<form method="post">
			<?php wp_nonce_field( 'cmbwc_save_service_options', 'cmbwc_service_options_nonce' ); ?>

			<table class="widefat striped" style="max-width:1100px;">
				<thead>
					<tr>
						<th style="width:180px;">Nøgle</th>
						<th>Navn</th>
						<th style="width:140px;">Pris</th>
						<th style="width:180px;">Prismodel</th>
						<th style="width:140px;">Depositum</th>
					</tr>
				</thead>
				<tbody id="cmbwc-service-options-rows">
					<?php $index = 0; ?>
					<?php foreach ( $options as $key => $option ) : ?>
						<tr>
							<td>
								<input type="text" class="regular-text" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $key ); ?>">
							</td>
							<td>
								<input type="text" class="regular-text" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $option['label'] ?? '' ); ?>" style="width:100%;">
							</td>
							<td>
								<input type="number" step="0.01" min="0" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $option['price'] ?? 0 ); ?>">
							</td>
							<td>
								<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_type]">
									<option value="fixed" <?php selected( $option['price_type'] ?? '', 'fixed' ); ?>>Fast pris</option>
									<option value="per_cover" <?php selected( $option['price_type'] ?? '', 'per_cover' ); ?>>Pr. kuvert</option>
								</select>
							</td>
							<td>
								<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][is_deposit]">
									<option value="no" <?php selected( $option['is_deposit'] ?? 'no', 'no' ); ?>>Nej</option>
									<option value="yes" <?php selected( $option['is_deposit'] ?? 'no', 'yes' ); ?>>Ja</option>
								</select>
							</td>
						</tr>
						<?php $index++; ?>
					<?php endforeach; ?>

					<?php for ( $i = 0; $i < 5; $i++, $index++ ) : ?>
						<tr>
							<td>
								<input type="text" class="regular-text" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][key]" value="">
							</td>
							<td>
								<input type="text" class="regular-text" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][label]" value="" style="width:100%;">
							</td>
							<td>
								<input type="number" step="0.01" min="0" name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price]" value="">
							</td>
							<td>
								<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_type]">
									<option value="fixed">Fast pris</option>
									<option value="per_cover">Pr. kuvert</option>
								</select>
							</td>
							<td>
								<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][is_deposit]">
									<option value="no">Nej</option>
									<option value="yes">Ja</option>
								</select>
							</td>
						</tr>
					<?php endfor; ?>
				</tbody>
			</table>

			<p style="margin-top:16px;">
				<button type="submit" class="button button-primary">Gem servicevalg</button>
			</p>
		</form>
	</div>
	<?php
}
