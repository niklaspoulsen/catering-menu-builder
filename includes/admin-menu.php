<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'cmbwc_register_admin_menu' );

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
		'Servicevalg',
		'Servicevalg',
		'manage_woocommerce',
		'cmbwc-service-options',
		'cmbwc_render_service_options_page'
	);

	add_submenu_page(
		'cmbwc-catering',
		'Produktionsoverblik',
		'Produktionsoverblik',
		'manage_woocommerce',
		'cmbwc-production-overview',
		'cmbwc_render_production_overview_page'
	);
}

function cmbwc_render_admin_welcome_page() {
	?>
	<div class="wrap">
		<h1>Catering</h1>
		<p>Her samler vi drift for catering.</p>

		<ul style="list-style:disc; padding-left:18px;">
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-service-options' ) ); ?>">Servicevalg</a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>">Produktionsoverblik</a></li>
		</ul>
	</div>
	<?php
}
