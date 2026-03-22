<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard note under pris.
 */
function cmbwc_get_default_service_price_note( $price_type, $is_deposit = 'no' ) {
	if ( 'yes' === $is_deposit ) {
		return 'depositum';
	}

	return 'per_cover' === $price_type ? 'pr. kuvert' : 'fast pris';
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
				'price_note' => 'depositum',
			),
			'disposable' => array(
				'label'      => 'Engangsfad & -skåle',
				'price'      => 4,
				'price_type' => 'per_cover',
				'is_deposit' => 'no',
				'price_note' => 'pr. kuvert',
			),
		);

		update_option( 'cmbwc_service_options', $service_options );
	}

	// Sørg for bagudkompatibilitet hvis gamle entries mangler price_note.
	foreach ( $service_options as $key => $option ) {
		$price_type = isset( $option['price_type'] ) ? $option['price_type'] : 'fixed';
		$is_deposit = isset( $option['is_deposit'] ) ? $option['is_deposit'] : 'no';

		if ( empty( $option['price_note'] ) ) {
			$service_options[ $key ]['price_note'] = cmbwc_get_default_service_price_note( $price_type, $is_deposit );
		}
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
 * Render én admin-række.
 */
function cmbwc_render_service_option_row( $index, $key = '', $option = array() ) {
	$label      = isset( $option['label'] ) ? $option['label'] : '';
	$price      = isset( $option['price'] ) ? $option['price'] : '';
	$price_type = isset( $option['price_type'] ) ? $option['price_type'] : 'fixed';
	$is_deposit = isset( $option['is_deposit'] ) ? $option['is_deposit'] : 'no';
	$price_note = isset( $option['price_note'] ) ? $option['price_note'] : cmbwc_get_default_service_price_note( $price_type, $is_deposit );
	?>
	<tr class="cmbwc-service-row">
		<td>
			<input
				type="text"
				class="regular-text"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][key]"
				value="<?php echo esc_attr( $key ); ?>"
				placeholder="fx tray_glass"
			>
		</td>
		<td>
			<input
				type="text"
				class="regular-text"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][label]"
				value="<?php echo esc_attr( $label ); ?>"
				style="width:100%;"
				placeholder="Navn"
			>
		</td>
		<td>
			<input
				type="number"
				step="0.01"
				min="0"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price]"
				value="<?php echo esc_attr( $price ); ?>"
				placeholder="0"
			>
		</td>
		<td>
			<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_type]">
				<option value="fixed" <?php selected( $price_type, 'fixed' ); ?>>Fast pris</option>
				<option value="per_cover" <?php selected( $price_type, 'per_cover' ); ?>>Pr. kuvert</option>
			</select>
		</td>
		<td>
			<select name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][is_deposit]">
				<option value="no" <?php selected( $is_deposit, 'no' ); ?>>Nej</option>
				<option value="yes" <?php selected( $is_deposit, 'yes' ); ?>>Ja</option>
			</select>
		</td>
		<td>
			<input
				type="text"
				class="regular-text"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_note]"
				value="<?php echo esc_attr( $price_note ); ?>"
				style="width:100%;"
				placeholder="fx depositum / pr. kuvert"
			>
		</td>
		<td style="width:90px;">
			<button type="button" class="button-link-delete cmbwc-remove-service-row">Fjern</button>
		</td>
	</tr>
	<?php
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
				$price_note   = isset( $row['price_note'] ) ? sanitize_text_field( $row['price_note'] ) : '';

				if ( '' === $label ) {
					continue;
				}

				$key = $key_raw ? $key_raw : sanitize_title( $label );

				if ( '' === $key ) {
					continue;
				}

				if ( '' === $price_note ) {
					$price_note = cmbwc_get_default_service_price_note( $price_type, $is_deposit );
				}

				$cleaned[ $key ] = array(
					'label'      => $label,
					'price'      => (float) $price,
					'price_type' => $price_type,
					'is_deposit' => $is_deposit,
					'price_note' => $price_note,
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
		<p>Her vedligeholder du servicevalg, pris og teksten under prisen på frontend.</p>

		<form method="post">
			<?php wp_nonce_field( 'cmbwc_save_service_options', 'cmbwc_service_options_nonce' ); ?>

			<table class="widefat striped" style="max-width:1300px;">
				<thead>
					<tr>
						<th style="width:180px;">Nøgle</th>
						<th>Navn</th>
						<th style="width:120px;">Pris</th>
						<th style="width:160px;">Prismodel</th>
						<th style="width:130px;">Depositum</th>
						<th style="width:220px;">Tekst under pris</th>
						<th style="width:90px;">Handling</th>
					</tr>
				</thead>
				<tbody id="cmbwc-service-options-rows">
					<?php $index = 0; ?>
					<?php foreach ( $options as $key => $option ) : ?>
						<?php cmbwc_render_service_option_row( $index, $key, $option ); ?>
						<?php $index++; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:12px;">
				<button type="button" class="button" id="cmbwc-add-service-row">Tilføj servicevalg</button>
			</p>

			<p style="margin-top:16px;">
				<button type="submit" class="button button-primary">Gem servicevalg</button>
			</p>
		</form>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const tbody = document.getElementById('cmbwc-service-options-rows');
			const addBtn = document.getElementById('cmbwc-add-service-row');

			if (!tbody || !addBtn) {
				return;
			}

			let nextIndex = <?php echo (int) $index; ?>;

			addBtn.addEventListener('click', function () {
				const row = document.createElement('tr');
				row.className = 'cmbwc-service-row';
				row.innerHTML = `
					<td>
						<input type="text" class="regular-text" name="cmbwc_service_options[${nextIndex}][key]" value="" placeholder="fx tray_glass">
					</td>
					<td>
						<input type="text" class="regular-text" name="cmbwc_service_options[${nextIndex}][label]" value="" style="width:100%;" placeholder="Navn">
					</td>
					<td>
						<input type="number" step="0.01" min="0" name="cmbwc_service_options[${nextIndex}][price]" value="" placeholder="0">
					</td>
					<td>
						<select name="cmbwc_service_options[${nextIndex}][price_type]">
							<option value="fixed">Fast pris</option>
							<option value="per_cover">Pr. kuvert</option>
						</select>
					</td>
					<td>
						<select name="cmbwc_service_options[${nextIndex}][is_deposit]">
							<option value="no">Nej</option>
							<option value="yes">Ja</option>
						</select>
					</td>
					<td>
						<input type="text" class="regular-text" name="cmbwc_service_options[${nextIndex}][price_note]" value="" style="width:100%;" placeholder="fx depositum / pr. kuvert">
					</td>
					<td style="width:90px;">
						<button type="button" class="button-link-delete cmbwc-remove-service-row">Fjern</button>
					</td>
				`;
				tbody.appendChild(row);
				nextIndex++;
			});

			document.addEventListener('click', function (event) {
				if (!event.target.classList.contains('cmbwc-remove-service-row')) {
					return;
				}

				const row = event.target.closest('tr');
				if (row) {
					row.remove();
				}
			});
		});
	</script>
	<?php
}
