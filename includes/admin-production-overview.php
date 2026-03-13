<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cmbwc_render_production_overview_page() {
	?>
	<div class="wrap">
		<h1>Produktionsoverblik</h1>
		<p>Her bygger vi næste modul til køkkenet.</p>

		<div style="background:#fff; border:1px solid #ddd; padding:16px; max-width:900px;">
			<p>Plan for næste version:</p>
			<ul style="list-style:disc; padding-left:18px;">
				<li>Ordrer grupperet efter leveringsdato</li>
				<li>Sorteret efter leveringstid</li>
				<li>Visning af kunde, menu, kuverter, tilvalg og status</li>
				<li>Nye ordrer skal automatisk falde ind det rigtige sted</li>
			</ul>
		</div>
	</div>
	<?php
}
