<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'cmbwc_register_surcharge_settings' );

function cmbwc_register_surcharge_settings() {
	register_setting(
		'cmbwc_surcharge_settings_group',
		'cmbwc_surcharge_settings',
		'cmbwc_sanitize_surcharge_settings'
	);
}

function cmbwc_get_default_surcharge_settings() {
	return array(
		'pickup_weekdays' => array(
			'1' => 0,
			'2' => 0,
			'3' => 0,
			'4' => 0,
			'5' => 0,
			'6' => 0,
			'0' => 300,
		),
		'delivery_weekdays' => array(
			'1' => 0,
			'2' => 0,
			'3' => 0,
			'4' => 0,
			'5' => 0,
			'6' => 150,
			'0' => 300,
		),
		'special_dates' => array(),
	);
}

function cmbwc_get_surcharge_settings() {
	$saved    = get_option( 'cmbwc_surcharge_settings', array() );
	$defaults = cmbwc_get_default_surcharge_settings();

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$settings = wp_parse_args( $saved, $defaults );

	if ( empty( $settings['pickup_weekdays'] ) || ! is_array( $settings['pickup_weekdays'] ) ) {
		$settings['pickup_weekdays'] = $defaults['pickup_weekdays'];
	}

	if ( empty( $settings['delivery_weekdays'] ) || ! is_array( $settings['delivery_weekdays'] ) ) {
		$settings['delivery_weekdays'] = $defaults['delivery_weekdays'];
	}

	if ( empty( $settings['special_dates'] ) || ! is_array( $settings['special_dates'] ) ) {
		$settings['special_dates'] = array();
	}

	return $settings;
}

function cmbwc_parse_admin_date_to_ymd( $date ) {
	$date = trim( (string) $date );

	if ( '' === $date ) {
		return '';
	}

	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
		if ( checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
		}
	}

	if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m ) ) {
		if ( checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
		}
	}

	return '';
}

function cmbwc_format_admin_date_from_ymd( $date ) {
	$date = cmbwc_parse_admin_date_to_ymd( $date );

	if ( '' === $date ) {
		return '';
	}

	$parts = explode( '-', $date );

	if ( 3 !== count( $parts ) ) {
		return '';
	}

	return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
}

function cmbwc_sanitize_money_value( $value ) {
	$value = wc_format_decimal( $value, 2 );
	return max( 0, (float) $value );
}

function cmbwc_sanitize_surcharge_settings( $input ) {
	$defaults = cmbwc_get_default_surcharge_settings();
	$output   = $defaults;

	$weekday_keys = array( '1', '2', '3', '4', '5', '6', '0' );

	if ( ! empty( $input['pickup_weekdays'] ) && is_array( $input['pickup_weekdays'] ) ) {
		foreach ( $weekday_keys as $key ) {
			$output['pickup_weekdays'][ $key ] = isset( $input['pickup_weekdays'][ $key ] )
				? cmbwc_sanitize_money_value( $input['pickup_weekdays'][ $key ] )
				: 0;
		}
	}

	if ( ! empty( $input['delivery_weekdays'] ) && is_array( $input['delivery_weekdays'] ) ) {
		foreach ( $weekday_keys as $key ) {
			$output['delivery_weekdays'][ $key ] = isset( $input['delivery_weekdays'][ $key ] )
				? cmbwc_sanitize_money_value( $input['delivery_weekdays'][ $key ] )
				: 0;
		}
	}

	$output['special_dates'] = array();

	if ( ! empty( $input['special_dates'] ) && is_array( $input['special_dates'] ) ) {
		foreach ( $input['special_dates'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$date = isset( $row['date'] ) ? cmbwc_parse_admin_date_to_ymd( $row['date'] ) : '';

			if ( '' === $date ) {
				continue;
			}

			$title        = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$pickup_fee   = isset( $row['pickup_fee'] ) ? cmbwc_sanitize_money_value( $row['pickup_fee'] ) : 0;
			$delivery_fee = isset( $row['delivery_fee'] ) ? cmbwc_sanitize_money_value( $row['delivery_fee'] ) : 0;
			$enabled      = ! empty( $row['enabled'] ) && 'yes' === $row['enabled'] ? 'yes' : 'no';

			$output['special_dates'][] = array(
				'date'         => $date,
				'title'        => $title,
				'pickup_fee'   => $pickup_fee,
				'delivery_fee' => $delivery_fee,
				'enabled'      => $enabled,
			);
		}
	}

	usort(
		$output['special_dates'],
		function( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		}
	);

	return $output;
}

function cmbwc_render_surcharge_weekday_table( $title, $field_key, $values ) {
	$labels = array(
		'1' => 'Mandag',
		'2' => 'Tirsdag',
		'3' => 'Onsdag',
		'4' => 'Torsdag',
		'5' => 'Fredag',
		'6' => 'Lørdag',
		'0' => 'Søndag',
	);
	?>
	<div style="margin:0 0 24px; background:#fff; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
		<div style="padding:12px 14px; border-bottom:1px solid #eee; font-weight:600;">
			<?php echo esc_html( $title ); ?>
		</div>
		<table class="widefat striped" style="margin:0;">
			<thead>
				<tr>
					<th>Dag</th>
					<th>Gebyr (kr.)</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $labels as $key => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td style="max-width:160px;">
							<input
								type="number"
								step="0.01"
								min="0"
								name="cmbwc_surcharge_settings[<?php echo esc_attr( $field_key ); ?>][<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( isset( $values[ $key ] ) ? $values[ $key ] : 0 ); ?>"
								style="width:120px;"
							>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function cmbwc_render_surcharges_page() {
	$settings      = cmbwc_get_surcharge_settings();
	$special_dates = ! empty( $settings['special_dates'] ) ? $settings['special_dates'] : array();

	if ( empty( $special_dates ) ) {
		$special_dates[] = array(
			'date'         => '',
			'title'        => '',
			'pickup_fee'   => '',
			'delivery_fee' => '',
			'enabled'      => 'yes',
		);
	}
	?>
	<div class="wrap">
		<h1>Gebyrer</h1>
		<p>Her kan du sætte gebyrer for afhentning og levering på bestemte ugedage samt ekstra gebyrer på specifikke datoer som fx helligdage.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'cmbwc_surcharge_settings_group' ); ?>

			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px; align-items:start;">
				<div>
					<?php cmbwc_render_surcharge_weekday_table( 'Afhent selv – ugedage', 'pickup_weekdays', $settings['pickup_weekdays'] ); ?>
				</div>

				<div>
					<?php cmbwc_render_surcharge_weekday_table( 'Levering – ugedage', 'delivery_weekdays', $settings['delivery_weekdays'] ); ?>
				</div>
			</div>

			<div style="margin:24px 0 0; background:#fff; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
				<div style="padding:12px 14px; border-bottom:1px solid #eee; font-weight:600;">
					Specifikke datoer med ekstra gebyr
				</div>

				<div style="padding:14px;">
					<p style="margin-top:0;">Gebyr på specifikke datoer lægges oven i uge-/weekendgebyr.</p>

					<div id="cmbwc-special-dates-list">
						<?php foreach ( $special_dates as $index => $row ) : ?>
							<div class="cmbwc-special-date-row" style="display:grid; grid-template-columns:160px 1fr 120px 120px 130px auto; gap:10px; align-items:center; margin:0 0 10px;">
								<input
									type="date"
									name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][date]"
									value="<?php echo esc_attr( ! empty( $row['date'] ) ? $row['date'] : '' ); ?>"
								>

								<input
									type="text"
									name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][title]"
									value="<?php echo esc_attr( isset( $row['title'] ) ? $row['title'] : '' ); ?>"
									placeholder="Titel, fx Påskedag"
								>

								<input
									type="number"
									step="0.01"
									min="0"
									name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][pickup_fee]"
									value="<?php echo esc_attr( isset( $row['pickup_fee'] ) ? $row['pickup_fee'] : '' ); ?>"
									placeholder="Afhent"
								>

								<input
									type="number"
									step="0.01"
									min="0"
									name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][delivery_fee]"
									value="<?php echo esc_attr( isset( $row['delivery_fee'] ) ? $row['delivery_fee'] : '' ); ?>"
									placeholder="Levering"
								>

								<label style="display:flex; align-items:center; gap:6px;">
									<input
										type="checkbox"
										name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][enabled]"
										value="yes"
										<?php checked( isset( $row['enabled'] ) ? $row['enabled'] : 'yes', 'yes' ); ?>
									>
									Aktiv
								</label>

								<button type="button" class="button cmbwc-remove-special-date">Fjern</button>
							</div>
						<?php endforeach; ?>
					</div>

					<p style="margin:14px 0 0;">
						<button type="button" class="button" id="cmbwc-add-special-date">Tilføj dato</button>
					</p>
				</div>
			</div>

			<?php submit_button( 'Gem gebyrer' ); ?>
		</form>
	</div>

	<script>
	jQuery(function($){
		function nextSpecialDateIndex() {
			var max = -1;

			$('#cmbwc-special-dates-list .cmbwc-special-date-row').each(function() {
				$(this).find('input').each(function() {
					var name = $(this).attr('name') || '';
					var match = name.match(/special_dates\]\[(\d+)\]/);
					if (match) {
						var index = parseInt(match[1], 10);
						if (index > max) {
							max = index;
						}
					}
				});
			});

			return max + 1;
		}

		$(document).on('click', '#cmbwc-add-special-date', function(e){
			e.preventDefault();

			var index = nextSpecialDateIndex();

			var html = '' +
				'<div class="cmbwc-special-date-row" style="display:grid; grid-template-columns:160px 1fr 120px 120px 130px auto; gap:10px; align-items:center; margin:0 0 10px;">' +
					'<input type="date" name="cmbwc_surcharge_settings[special_dates][' + index + '][date]" value="">' +
					'<input type="text" name="cmbwc_surcharge_settings[special_dates][' + index + '][title]" value="" placeholder="Titel, fx Påskedag">' +
					'<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][' + index + '][pickup_fee]" value="" placeholder="Afhent">' +
					'<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][' + index + '][delivery_fee]" value="" placeholder="Levering">' +
					'<label style="display:flex; align-items:center; gap:6px;">' +
						'<input type="checkbox" name="cmbwc_surcharge_settings[special_dates][' + index + '][enabled]" value="yes" checked> Aktiv' +
					'</label>' +
					'<button type="button" class="button cmbwc-remove-special-date">Fjern</button>' +
				'</div>';

			$('#cmbwc-special-dates-list').append(html);
		});

		$(document).on('click', '.cmbwc-remove-special-date', function(e){
			e.preventDefault();

			var $rows = $('#cmbwc-special-dates-list .cmbwc-special-date-row');

			if ($rows.length <= 1) {
				$(this).closest('.cmbwc-special-date-row').find('input[type="date"], input[type="text"], input[type="number"]').val('');
				$(this).closest('.cmbwc-special-date-row').find('input[type="checkbox"]').prop('checked', true);
				return;
			}

			$(this).closest('.cmbwc-special-date-row').remove();
		});
	});
	</script>
	<?php
}
