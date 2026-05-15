<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'cmbwc_register_admin_menu' );

if ( ! function_exists( 'cmbwc_register_admin_menu' ) ) {
	function cmbwc_register_admin_menu() {
		add_menu_page(
			'Catering',
			'Catering',
			'manage_woocommerce',
			'cmbwc-catering',
			'cmbwc_render_admin_welcome_page',
			'dashicons-food',
			56
		);

		add_submenu_page(
			'cmbwc-catering',
			'Dashboard',
			'Dashboard',
			'manage_woocommerce',
			'cmbwc-catering',
			'cmbwc_render_admin_welcome_page'
		);

		add_submenu_page(
			'cmbwc-catering',
			'Servicevalg',
			'Servicevalg',
			'manage_woocommerce',
			'cmbwc-service-options',
			function() {
				if ( function_exists( 'cmbwc_render_service_options_page' ) ) {
					cmbwc_render_service_options_page();
					return;
				}

				cmbwc_render_missing_admin_page_notice( 'Servicevalg' );
			}
		);

		add_submenu_page(
			'cmbwc-catering',
			'Gebyrer',
			'Gebyrer',
			'manage_woocommerce',
			'cmbwc-surcharges',
			function() {
				if ( function_exists( 'cmbwc_render_surcharges_page' ) ) {
					cmbwc_render_surcharges_page();
					return;
				}

				cmbwc_render_missing_admin_page_notice( 'Gebyrer' );
			}
		);

		add_submenu_page(
			'cmbwc-catering',
			'Produktionsoverblik',
			'Produktionsoverblik',
			'manage_woocommerce',
			'cmbwc-production-overview',
			function() {
				if ( function_exists( 'cmbwc_render_production_overview_page' ) ) {
					cmbwc_render_production_overview_page();
					return;
				}

				cmbwc_render_missing_admin_page_notice( 'Produktionsoverblik' );
			}
		);

		add_submenu_page(
			'cmbwc-catering',
			'Produktionsapp',
			'Produktionsapp',
			'manage_woocommerce',
			'cmbwc-production-app-link',
			'cmbwc_render_production_app_link_page'
		);

		add_submenu_page(
			'cmbwc-catering',
			'Print / Bonner',
			'Print / Bonner',
			'manage_woocommerce',
			'cmbwc-print-settings',
			function() {
				if ( function_exists( 'cmbwc_render_print_settings_page' ) ) {
					cmbwc_render_print_settings_page();
					return;
				}

				cmbwc_render_missing_admin_page_notice( 'Print / Bonner' );
			}
		);
	}
}

if ( ! function_exists( 'cmbwc_render_missing_admin_page_notice' ) ) {
	function cmbwc_render_missing_admin_page_notice( $page_title ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<div class="notice notice-error">
				<p>
					Siden kunne ikke indlæses, fordi den tilhørende funktion mangler. Tjek at alle plugin-filer er uploadet korrekt.
				</p>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_admin_welcome_page' ) ) {
	function cmbwc_render_admin_welcome_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$production_app_url = function_exists( 'cmbwc_get_production_app_url' )
			? cmbwc_get_production_app_url()
			: home_url( '/produktion/' );

		$cards = array(
			array(
				'title'       => 'Servicevalg',
				'description' => 'Opret og vedligehold service, fade, anretning og depositum.',
				'url'         => admin_url( 'admin.php?page=cmbwc-service-options' ),
				'button'      => 'Åbn servicevalg',
			),
			array(
				'title'       => 'Gebyrer',
				'description' => 'Opsæt weekend-, leverings- og datogebyrer.',
				'url'         => admin_url( 'admin.php?page=cmbwc-surcharges' ),
				'button'      => 'Åbn gebyrer',
			),
			array(
				'title'       => 'Produktionsoverblik',
				'description' => 'Se kommende ordrer, intern status og print bonner.',
				'url'         => admin_url( 'admin.php?page=cmbwc-production-overview' ),
				'button'      => 'Åbn overblik',
			),
			array(
				'title'       => 'Produktionsapp',
				'description' => 'Åbn den mobilvenlige produktionsvisning.',
				'url'         => $production_app_url,
				'button'      => 'Åbn app',
				'external'    => true,
			),
			array(
				'title'       => 'Print / Bonner',
				'description' => 'Opsæt bon-layout og PrintNode-relaterede visninger.',
				'url'         => admin_url( 'admin.php?page=cmbwc-print-settings' ),
				'button'      => 'Åbn print',
			),
		);

		?>
		<div class="wrap cmbwc-admin-dashboard">
			<h1>Catering</h1>
			<p>Her samler vi drift, opsætning og produktion for catering.</p>

			<style>
				.cmbwc-admin-dashboard .cmbwc-dashboard-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
					gap: 16px;
					max-width: 1200px;
					margin-top: 20px;
				}

				.cmbwc-admin-dashboard .cmbwc-dashboard-card {
					background: #fff;
					border: 1px solid #dcdcde;
					border-radius: 10px;
					padding: 16px;
					box-shadow: 0 1px 2px rgba(0,0,0,0.03);
				}

				.cmbwc-admin-dashboard .cmbwc-dashboard-card h2 {
					margin: 0 0 8px;
					font-size: 16px;
				}

				.cmbwc-admin-dashboard .cmbwc-dashboard-card p {
					margin: 0 0 14px;
					color: #50575e;
				}
			</style>

			<div class="cmbwc-dashboard-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div class="cmbwc-dashboard-card">
						<h2><?php echo esc_html( $card['title'] ); ?></h2>
						<p><?php echo esc_html( $card['description'] ); ?></p>
						<a
							class="button button-primary"
							href="<?php echo esc_url( $card['url'] ); ?>"
							<?php echo ! empty( $card['external'] ) ? 'target="_blank" rel="noopener"' : ''; ?>
						>
							<?php echo esc_html( $card['button'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'cmbwc_render_production_app_link_page' ) ) {
	function cmbwc_render_production_app_link_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$url = function_exists( 'cmbwc_get_production_app_url' ) ? cmbwc_get_production_app_url() : home_url( '/produktion/' );

		?>
		<div class="wrap">
			<h1>Produktionsapp</h1>
			<p>Åbn den mobilvenlige produktionsvisning her:</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
					Åbn produktionsapp
				</a>
			</p>
			<p><code><?php echo esc_html( $url ); ?></code></p>
		</div>
		<?php
	}
}
