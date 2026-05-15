<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'cmbwc_register_surcharge_settings' );

if ( ! function_exists( 'cmbwc_register_surcharge_settings' ) ) {
	function cmbwc_register_surcharge_settings() {
		register_setting(
			'cmbwc_surcharge_settings_group',
			'cmbwc_surcharge_settings',
			'cmbwc_sanitize_surcharge_settings'
		);
	}
}

if ( ! function_exists( 'cmbwc_get_default_surcharge_settings' ) ) {
	function cmbwc_get_default_surcharge_settings() {
		return array(
			'pickup_methods'    => array(),
			'delivery_methods'  => array(),
			'pickup_weekdays'   => array(
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
			'special_dates'     => array(),
		);
	}
}

if ( ! function_exists( 'cmbwc_get_surcharge_settings' ) ) {
	function cmbwc_get_surcharge_settings() {
		$saved    = get_option( 'cmbwc_surcharge_settings', array() );
		$defaults = cmbwc_get_default_surcharge_settings();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		foreach ( array( 'pickup_methods', 'delivery_methods', 'pickup_weekdays', 'delivery_weekdays', 'special_dates' ) as $key ) {
			if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				$settings[ $key ] = isset( $defaults[ $key ] ) ? $defaults[ $key ] : array();
			}
		}

		foreach ( array( 'pickup_weekdays', 'delivery_weekdays' ) as $weekday_group ) {
			foreach ( array( '1', '2', '3', '4', '5', '6', '0' ) as $weekday_key ) {
				if ( ! isset( $settings[ $weekday_group ][ $weekday_key ] ) ) {
					$settings[ $weekday_group ][ $weekday_key ] = 0;
				}
			}
		}

		return $settings;
	}
}

if ( ! function_exists( 'cmbwc_get_all_shipping_method_instances' ) ) {
	function cmbwc_get_all_shipping_method_instances() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) {
			return array();
		}

		$methods = array();
		$zones   = WC_Shipping_Zones::get_zones();

		$rest = new WC_Shipping_Zone( 0 );

		$zones[] = array(
			'zone_id'          => 0,
			'zone_name'        => __( 'Resten af verden', 'woocommerce' ),
			'shipping_methods' => $rest->get_shipping_methods( true ),
		);

		foreach ( $zones as $zone ) {
			$zone_name = isset( $zone['zone_name'] ) ? $zone['zone_name'] : __( 'Ukendt zone', 'woocommerce' );
			$zone_id   = isset( $zone['zone_id'] ) ? absint( $zone['zone_id'] ) : 0;

			if ( empty( $zone['shipping_methods'] ) || ! is_array( $zone['shipping_methods'] ) ) {
				continue;
			}

			foreach ( $zone['shipping_methods'] as $method ) {
				if ( ! is_object( $method ) || empty( $method->id ) ) {
					continue;
				}

				$instance_id = ! empty( $method->instance_id ) ? absint( $method->instance_id ) : 0;
				$key         = $instance_id > 0 ? $method->id . ':' . $instance_id : $method->id;
				$title       = method_exists( $method, 'get_method_title' ) ? $method->get_method_title() : $method->id;
				$instance    = ! empty( $method->title ) ? $method->title : $title;

				$methods[ $key ] = array(
					'key'         => $key,
					'method_id'   => $method->id,
					'instance_id' => $instance_id,
					'zone_id'     => $zone_id,
					'zone_name'   => $zone_name,
					'label'       => sprintf( '%s — %s (%s)', $zone_name, $instance, $key ),
					'title'       => $instance,
				);
			}
		}

		ksort( $methods, SORT_NATURAL | SORT_FLAG_CASE );

		return $methods;
	}
}

if ( ! function_exists( 'cmbwc_parse_admin_date_to_ymd' ) ) {
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

		if ( preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $m ) ) {
			if ( checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
				return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cmbwc_sanitize_money_value' ) ) {
	function cmbwc_sanitize_money_value( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return 0;
		}

		$value = wc_format_decimal( $value, 2 );

		return max( 0, (float) $value );
	}
}

if ( ! function_exists( 'cmbwc_sanitize_surcharge_settings' ) ) {
	function cmbwc_sanitize_surcharge_settings( $input ) {
		$defaults          = cmbwc_get_default_surcharge_settings();
		$output            = $defaults;
		$available_methods = cmbwc_get_all_shipping_method_instances();
		$weekday_keys      = array( '1', '2', '3', '4', '5', '6', '0' );

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output['pickup_methods'] = array();

		if ( ! empty( $input['pickup_methods'] ) && is_array( $input['pickup_methods'] ) ) {
			foreach ( $input['pickup_methods'] as $method_key ) {
				$method_key = sanitize_text_field( wp_unslash( $method_key ) );

				if ( isset( $available_methods[ $method_key ] ) ) {
					$output['pickup_methods'][] = $method_key;
				}
			}

			$output['pickup_methods'] = array_values( array_unique( $output['pickup_methods'] ) );
		}

		$output['delivery_methods'] = array();

		if ( ! empty( $input['delivery_methods'] ) && is_array( $input['delivery_methods'] ) ) {
			foreach ( $input['delivery_methods'] as $method_key ) {
				$method_key = sanitize_text_field( wp_unslash( $method_key ) );

				if ( isset( $available_methods[ $method_key ] ) ) {
					$output['delivery_methods'][] = $method_key;
				}
			}

			$output['delivery_methods'] = array_values( array_unique( $output['delivery_methods'] ) );
		}

		foreach ( $weekday_keys as $key ) {
			$output['pickup_weekdays'][ $key ] = isset( $input['pickup_weekdays'][ $key ] )
				? cmbwc_sanitize_money_value( wp_unslash( $input['pickup_weekdays'][ $key ] ) )
				: 0;

			$output['delivery_weekdays'][ $key ] = isset( $input['delivery_weekdays'][ $key ] )
				? cmbwc_sanitize_money_value( wp_unslash( $input['delivery_weekdays'][ $key ] ) )
				: 0;
		}

		$output['special_dates'] = array();

		if ( ! empty( $input['special_dates'] ) && is_array( $input['special_dates'] ) ) {
			foreach ( $input['special_dates'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$row = wp_unslash( $row );

				$date = isset( $row['date'] ) ? cmbwc_parse_admin_date_to_ymd( $row['date'] ) : '';

				if ( '' === $date ) {
					continue;
				}

				$output['special_dates'][] = array(
					'date'         => $date,
					'title'        => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'pickup_fee'   => isset( $row['pickup_fee'] ) ? cmbwc_sanitize_money_value( $row['pickup_fee'] ) : 0,
					'delivery_fee' => isset( $row['delivery_fee'] ) ? cmbwc_sanitize_money_value( $row['delivery_fee'] ) : 0,
					'enabled'      => ( ! empty( $row['enabled'] ) && 'yes' === $row['enabled'] ) ? 'yes' : 'no',
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
}

if ( ! function_exists( 'cmbwc_render_shipping_method_picker' ) ) {
	function cmbwc_render_shipping_method_picker( $name, $selected ) {
		$methods = cmbwc_get_all_shipping_method_instances();

		if ( ! is_array( $selected ) ) {
			$selected = array();
		}

		if ( empty( $methods ) ) {
			echo '<p><em>Ingen WooCommerce leveringsmetoder fundet.</em></p>';
			return;
		}

		echo '<div style="display:grid; gap:8px;">';

		foreach ( $methods as $method ) {
			$is_checked = in_array( $method['key'], $selected, true );

			echo '<label style="display:flex; align-items:flex-start; gap:8px; padding:8px 10px; border:1px solid #ddd; border-radius:8px; background:#fff;">';
			echo '<input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $method['key'] ) . '" ' . checked( $is_checked, true, false ) . '>';
			echo '<span>' . esc_html( $method['label'] ) . '</span>';
			echo '</label>';
		}

		echo '</div>';
	}
}

if ( ! function_exists( 'cmbwc_render_surcharge_weekday_table' ) ) {
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

		if ( ! is_array( $values ) ) {
			$values = array();
		}
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
							<td>
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
}

if ( ! function_exists( 'cmbwc_render_surcharges_page' ) ) {
	function cmbwc_render_surcharges_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

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
		<div class="wrap cmbwc-surcharges-page">
			<h1>Gebyrer</h1>
			<p>Vælg først hvilke WooCommerce-leveringsmetoder der er Afhent selv og Levering. Derefter bruges de rigtige gebyrer automatisk.</p>

			<style>
				.cmbwc-surcharges-page .cmbwc-surcharge-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
					gap: 20px;
					align-items: start;
				}

				.cmbwc-surcharges-page .cmbwc-admin-box {
					background: #fff;
					border: 1px solid #ddd;
					border-radius: 8px;
					padding: 14px;
				}

				.cmbwc-surcharges-page .cmbwc-admin-box h2 {
					margin-top: 0;
				}

				.cmbwc-surcharges-page .cmbwc-special-dates-box {
					margin: 24px 0 0;
					background: #fff;
					border: 1px solid #ddd;
					border-radius: 8px;
					overflow: hidden;
				}

				.cmbwc-surcharges-page .cmbwc-special-dates-heading {
					padding: 12px 14px;
					border-bottom: 1px solid #eee;
					font-weight: 600;
				}

				.cmbwc-surcharges-page .cmbwc-special-dates-inner {
					padding: 14px;
				}

				.cmbwc-surcharges-page .cmbwc-special-date-row {
					display: grid;
					grid-template-columns: 160px 1fr 120px 120px 130px auto;
					gap: 10px;
					align-items: center;
					margin: 0 0 10px;
				}

				.cmbwc-surcharges-page .cmbwc-special-date-row input[type="date"],
				.cmbwc-surcharges-page .cmbwc-special-date-row input[type="text"],
				.cmbwc-surcharges-page .cmbwc-special-date-row input[type="number"] {
					width: 100%;
					box-sizing: border-box;
				}

				.cmbwc-surcharges-page .cmbwc-enabled-label {
					display: flex;
					align-items: center;
					gap: 6px;
					white-space: nowrap;
				}

				@media (max-width: 960px) {
					.cmbwc-surcharges-page .cmbwc-special-date-row {
						grid-template-columns: 1fr 1fr;
					}
				}

				@media (max-width: 560px) {
					.cmbwc-surcharges-page .cmbwc-special-date-row {
						grid-template-columns: 1fr;
					}
				}
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'cmbwc_surcharge_settings_group' ); ?>

				<div class="cmbwc-surcharge-grid" style="margin-bottom:24px;">
					<div class="cmbwc-admin-box">
						<h2>Afhent selv-metoder</h2>
						<?php cmbwc_render_shipping_method_picker( 'cmbwc_surcharge_settings[pickup_methods]', $settings['pickup_methods'] ); ?>
					</div>

					<div class="cmbwc-admin-box">
						<h2>Leveringsmetoder</h2>
						<?php cmbwc_render_shipping_method_picker( 'cmbwc_surcharge_settings[delivery_methods]', $settings['delivery_methods'] ); ?>
					</div>
				</div>

				<div class="cmbwc-surcharge-grid">
					<div>
						<?php cmbwc_render_surcharge_weekday_table( 'Afhent selv – ugedage', 'pickup_weekdays', $settings['pickup_weekdays'] ); ?>
					</div>

					<div>
						<?php cmbwc_render_surcharge_weekday_table( 'Levering – ugedage', 'delivery_weekdays', $settings['delivery_weekdays'] ); ?>
					</div>
				</div>

				<div class="cmbwc-special-dates-box">
					<div class="cmbwc-special-dates-heading">
						Specifikke datoer med ekstra gebyr
					</div>

					<div class="cmbwc-special-dates-inner">
						<p style="margin-top:0;">Gebyr på specifikke datoer lægges oven i uge-/weekendgebyr.</p>

						<div id="cmbwc-special-dates-list">
							<?php foreach ( $special_dates as $index => $row ) : ?>
								<div class="cmbwc-special-date-row">
									<input type="date" name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( ! empty( $row['date'] ) ? $row['date'] : '' ); ?>">
									<input type="text" name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $row['title'] ) ? $row['title'] : '' ); ?>" placeholder="Titel, fx Påskedag">
									<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][pickup_fee]" value="<?php echo esc_attr( isset( $row['pickup_fee'] ) ? $row['pickup_fee'] : '' ); ?>" placeholder="Afhent">
									<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][delivery_fee]" value="<?php echo esc_attr( isset( $row['delivery_fee'] ) ? $row['delivery_fee'] : '' ); ?>" placeholder="Levering">
									<label class="cmbwc-enabled-label">
										<input type="checkbox" name="cmbwc_surcharge_settings[special_dates][<?php echo esc_attr( $index ); ?>][enabled]" value="yes" <?php checked( isset( $row['enabled'] ) ? $row['enabled'] : 'yes', 'yes' ); ?>>
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
					'<div class="cmbwc-special-date-row">' +
						'<input type="date" name="cmbwc_surcharge_settings[special_dates][' + index + '][date]" value="">' +
						'<input type="text" name="cmbwc_surcharge_settings[special_dates][' + index + '][title]" value="" placeholder="Titel, fx Påskedag">' +
						'<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][' + index + '][pickup_fee]" value="" placeholder="Afhent">' +
						'<input type="number" step="0.01" min="0" name="cmbwc_surcharge_settings[special_dates][' + index + '][delivery_fee]" value="" placeholder="Levering">' +
						'<label class="cmbwc-enabled-label">' +
							'<input type="checkbox" name="cmbwc_surcharge_settings[special_dates][' + index + '][enabled]" value="yes" checked> Aktiv' +
						'</label>' +
						'<button type="button" class="button cmbwc-remove-special-date">Fjern</button>' +
					'</div>';

				$('#cmbwc-special-dates-list').append(html);
			});

			$(document).on('click', '.cmbwc-remove-special-date', function(e){
				e.preventDefault();

				var $rows = $('#cmbwc-special-dates-list .cmbwc-special-date-row');
				var $row  = $(this).closest('.cmbwc-special-date-row');

				if ($rows.length <= 1) {
					$row.find('input[type="date"], input[type="text"], input[type="number"]').val('');
					$row.find('input[type="checkbox"]').prop('checked', true);
					return;
				}

				$row.remove();
			});
		});
		</script>
		<?php
	}
}
