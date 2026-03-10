<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cmbwc_menu_info', 'cmbwc_shortcode_menu_info' );
add_shortcode( 'cmbwc_menu_contents', 'cmbwc_shortcode_menu_contents' );
add_shortcode( 'cmbwc_menu_options', 'cmbwc_shortcode_menu_options' );

function cmbwc_shortcode_menu_info() {
	return '<div class="cmbwc-box"><strong>Menu info</strong><br>Her kommer minimum kuverter og leveringstid.</div>';
}

function cmbwc_shortcode_menu_contents() {
	return '<div class="cmbwc-box"><strong>Menuindhold</strong><br>Her kommer menuens retter.</div>';
}

function cmbwc_shortcode_menu_options() {
	return '<div class="cmbwc-box"><strong>Menuvalg</strong><br>Her kommer tilvalg, service og prisboks.</div>';
}
