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
 * Produkter til service-kobling.
 */
function cmbwc_get_product_options_for_service_select() {
	$product_ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$options = array();

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			continue;
		}

		$label = $product->get_name() . ' (#' . $product_id . ')';

		if ( '' !== $product->get_price() ) {
			$label .= ' — ' . wp_strip_all_tags( wc_price( $product->get_price() ) );
		}

		$options[ $product_id ] = $label;
	}

	return $options;
}

/**
 * Hent servicevalg.
 */
function cmbwc_get_service_options() {
	$service_options = get_option( 'cmbwc_service_options', array() );

	if ( empty( $service_options ) || ! is_array( $service_options ) ) {
		$service_options = array(
			'tray_glass' => array(
				'label'             => 'Træfad & glas skåle',
				'price'             => 200,
				'price_type'        => 'fixed',
				'is_deposit'        => 'yes',
				'price_note'        => 'depositum',
				'linked_product_id' => 0,
			),
			'single_use' => array(
				'label'             => 'Engangsfad & -skåle',
				'price'             => 4,
				'price_type'        => 'per_cover',
				'is_deposit'        => 'no',
				'price_note'        => 'pr. kuvert',
				'linked_product_id' => 0,
			),
		);

		update_option( 'cmbwc_service_options', $service_options );
	}

	foreach ( $service_options as $key => $option ) {
		$price_type = isset( $option['price_type'] ) ? $option['price_type'] : 'fixed';
		$is_deposit = isset( $option['is_deposit'] ) ? $option['is_deposit'] : 'no';

		if ( empty( $option['price_note'] ) ) {
			$service_options[ $key ]['price_note'] = cmbwc_get_default_service_price_note( $price_type, $is_deposit );
		}

		if ( empty( $option['linked_product_id'] ) ) {
			$service_options[ $key ]['linked_product_id'] = 0;
		} else {
			$service_options[ $key ]['linked_product_id'] = absint( $option['linked_product_id'] );
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
function cmbwc_render_service_option_row( $index, $key = '', $option = array(), $product_options = array() ) {
	$label             = isset( $option['label'] ) ? $option['label'] : '';
	$price             = isset( $option['price'] ) ? $option['price'] : '';
	$price_type        = isset( $option['price_type'] ) ? $option['price_type'] : 'fixed';
	$is_deposit        = isset( $option['is_deposit'] ) ? $option['is_deposit'] : 'no';
	$price_note        = isset( $option['price_note'] ) ? $option['price_note'] : cmbwc_get_default_service_price_note( $price_type, $is_deposit );
	$linked_product_id = isset( $option['linked_product_id'] ) ? absint( $option['linked_product_id'] ) : 0;
	?>
	<tr class="cmbwc-service-row">
		<td class="cmbwc-col-key">
			<input
				type="text"
				class="cmbwc-admin-input cmbwc-admin-input-key"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][key]"
				value="<?php echo esc_attr( $key ); ?>"
				placeholder="fx tray_glass"
			>
		</td>

		<td class="cmbwc-col-label">
			<input
				type="text"
				class="cmbwc-admin-input cmbwc-admin-input-label"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][label]"
				value="<?php echo esc_attr( $label ); ?>"
				placeholder="Navn"
			>
		</td>

		<td class="cmbwc-col-product">
			<select
				class="cmbwc-admin-select cmbwc-admin-select-product"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][linked_product_id]"
			>
				<option value="0">— Intet koblet produkt —</option>
				<?php foreach ( $product_options as $product_id => $product_label ) : ?>
					<option value="<?php echo esc_attr( $product_id ); ?>" <?php selected( $linked_product_id, $product_id ); ?>>
						<?php echo esc_html( $product_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>

		<td class="cmbwc-col-price">
			<input
				type="number"
				step="0.01"
				min="0"
				class="cmbwc-admin-input cmbwc-admin-input-price"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price]"
				value="<?php echo esc_attr( $price ); ?>"
				placeholder="0"
			>
		</td>

		<td class="cmbwc-col-price-type">
			<select
				class="cmbwc-admin-select cmbwc-admin-select-price-type"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_type]"
			>
				<option value="fixed" <?php selected( $price_type, 'fixed' ); ?>>Fast pris</option>
				<option value="per_cover" <?php selected( $price_type, 'per_cover' ); ?>>Pr. kuvert</option>
			</select>
		</td>

		<td class="cmbwc-col-deposit">
			<select
				class="cmbwc-admin-select cmbwc-admin-select-deposit"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][is_deposit]"
			>
				<option value="no" <?php selected( $is_deposit, 'no' ); ?>>Nej</option>
				<option value="yes" <?php selected( $is_deposit, 'yes' ); ?>>Ja</option>
			</select>
		</td>

		<td class="cmbwc-col-note">
			<input
				type="text"
				class="cmbwc-admin-input cmbwc-admin-input-note"
				name="cmbwc_service_options[<?php echo esc_attr( $index ); ?>][price_note]"
				value="<?php echo esc_attr( $price_note ); ?>"
				placeholder="fx depositum / pr. kuvert"
			>
		</td>

		<td class="cmbwc-col-actions">
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

				$key_raw           = isset( $row['key'] ) ? sanitize_title( $row['key'] ) : '';
				$label             = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
				$price             = isset( $row['price'] ) ? wc_format_decimal( $row['price'] ) : 0;
				$price_type        = isset( $row['price_type'] ) && 'per_cover' === $row['price_type'] ? 'per_cover' : 'fixed';
				$is_deposit        = isset( $row['is_deposit'] ) && 'yes' === $row['is_deposit'] ? 'yes' : 'no';
				$price_note        = isset( $row['price_note'] ) ? sanitize_text_field( $row['price_note'] ) : '';
				$linked_product_id = isset( $row['linked_product_id'] ) ? absint( $row['linked_product_id'] ) : 0;

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
					'label'             => $label,
					'price'             => (float) $price,
					'price_type'        => $price_type,
					'is_deposit'        => $is_deposit,
					'price_note'        => $price_note,
					'linked_product_id' => $linked_product_id,
				);
			}
		}

		update_option( 'cmbwc_service_options', $cleaned );

		echo '<div class="notice notice-success is-dismissible"><p>Servicevalg gemt.</p></div>';
	}

	$options         = cmbwc_get_service_options();
	$product_options = cmbwc_get_product_options_for_service_select();
	?>
	<div class="wrap cmbwc-admin-page cmbwc-admin-service-options-page">
		<h1>Servicevalg</h1>
		<p>Her vedligeholder du servicevalg, pris, koblet WooCommerce-produkt og teksten under prisen på frontend.</p>

		<style>
			.cmbwc-admin-service-options-page .cmbwc-service-options-table {
				width: 100%;
				max-width: 1440px;
				table-layout: fixed;
				border-collapse: collapse;
			}

			.cmbwc-admin-service-options-page .cmbwc-service-options-table th,
			.cmbwc-admin-service-options-page .cmbwc-service-options-table td {
				vertical-align: middle;
			}

			.cmbwc-admin-service-options-page .cmbwc-service-options-table th {
				padding: 10px 12px;
			}

			.cmbwc-admin-service-options-page .cmbwc-service-options-table td {
				padding: 8px 10px;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-key {
				width: 13%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-label {
				width: 20%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-product {
				width: 25%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-price {
				width: 9%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-price-type {
				width: 11%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-deposit {
				width: 8%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-note {
				width: 10%;
			}

			.cmbwc-admin-service-options-page .cmbwc-col-actions {
				width: 4%;
				white-space: nowrap;
			}

			.cmbwc-admin-service-options-page .cmbwc-admin-input,
			.cmbwc-admin-service-options-page .cmbwc-admin-select {
				width: 100%;
				max-width: 100%;
				box-sizing: border-box;
			}

			.cmbwc-admin-service-options-page .cmbwc-admin-input-price {
				min-width: 90px;
			}

			.cmbwc-admin-service-options-page .cmbwc-admin-actions {
				margin-top: 14px;
				display: flex;
				gap: 12px;
				flex-wrap: wrap;
				align-items: center;
			}

			.cmbwc-admin-service-options-page .cmbwc-remove-service-row {
				color: #b32d2e;
			}

			@media (max-width: 1100px) {
				.cmbwc-admin-service-options-page .cmbwc-service-options-table {
					display: block;
					overflow-x: auto;
				}

				.cmbwc-admin-service-options-page .cmbwc-service-options-table th,
				.cmbwc-admin-service-options-page .cmbwc-service-options-table td {
					min-width: 120px;
				}
			}
		</style>

		<form method="post">
			<?php wp_nonce_field( 'cmbwc_save_service_options', 'cmbwc_service_options_nonce' ); ?>

			<table class="widefat striped cmbwc-service-options-table">
				<thead>
					<tr>
						<th class="cmbwc-col-key">Nøgle</th>
						<th class="cmbwc-col-label">Navn</th>
						<th class="cmbwc-col-product">Koblet produkt</th>
						<th class="cmbwc-col-price">Pris</th>
						<th class="cmbwc-col-price-type">Prismodel</th>
						<th class="cmbwc-col-deposit">Depositum</th>
						<th class="cmbwc-col-note">Tekst under pris</th>
						<th class="cmbwc-col-actions">Handling</th>
					</tr>
				</thead>
				<tbody id="cmbwc-service-options-rows">
					<?php $index = 0; ?>
					<?php foreach ( $options as $key => $option ) : ?>
						<?php cmbwc_render_service_option_row( $index, $key, $option, $product_options ); ?>
						<?php $index++; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="cmbwc-admin-actions">
				<button type="button" class="button" id="cmbwc-add-service-row">Tilføj servicevalg</button>
				<button type="submit" class="button button-primary">Gem servicevalg</button>
			</div>
		</form>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const tbody = document.getElementById('cmbwc-service-options-rows');
			const addBtn = document.getElementById('cmbwc-add-service-row');
			const productOptionsHtml = <?php echo wp_json_encode( implode( '', array_map( static function ( $product_id, $product_label ) {
				return '<option value="' . esc_attr( $product_id ) . '">' . esc_html( $product_label ) . '</option>';
			}, array_keys( $product_options ), $product_options ) ) ); ?>;

			if (!tbody || !addBtn) {
				return;
			}

			let nextIndex = <?php echo (int) $index; ?>;

			addBtn.addEventListener('click', function () {
				const row = document.createElement('tr');
				row.className = 'cmbwc-service-row';
				row.innerHTML = `
					<td class="cmbwc-col-key">
						<input type="text" class="cmbwc-admin-input cmbwc-admin-input-key" name="cmbwc_service_options[${nextIndex}][key]" value="" placeholder="fx tray_glass">
					</td>
					<td class="cmbwc-col-label">
						<input type="text" class="cmbwc-admin-input cmbwc-admin-input-label" name="cmbwc_service_options[${nextIndex}][label]" value="" placeholder="Navn">
					</td>
					<td class="cmbwc-col-product">
						<select class="cmbwc-admin-select cmbwc-admin-select-product" name="cmbwc_service_options[${nextIndex}][linked_product_id]">
							<option value="0">— Intet koblet produkt —</option>
							${productOptionsHtml}
						</select>
					</td>
					<td class="cmbwc-col-price">
						<input type="number" step="0.01" min="0" class="cmbwc-admin-input cmbwc-admin-input-price" name="cmbwc_service_options[${nextIndex}][price]" value="" placeholder="0">
					</td>
					<td class="cmbwc-col-price-type">
						<select class="cmbwc-admin-select cmbwc-admin-select-price-type" name="cmbwc_service_options[${nextIndex}][price_type]">
							<option value="fixed">Fast pris</option>
							<option value="per_cover">Pr. kuvert</option>
						</select>
					</td>
					<td class="cmbwc-col-deposit">
						<select class="cmbwc-admin-select cmbwc-admin-select-deposit" name="cmbwc_service_options[${nextIndex}][is_deposit]">
							<option value="no">Nej</option>
							<option value="yes">Ja</option>
						</select>
					</td>
					<td class="cmbwc-col-note">
						<input type="text" class="cmbwc-admin-input cmbwc-admin-input-note" name="cmbwc_service_options[${nextIndex}][price_note]" value="" placeholder="fx depositum / pr. kuvert">
					</td>
					<td class="cmbwc-col-actions">
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
