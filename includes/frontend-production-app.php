<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cmbwc_register_production_app_rewrite' );
add_filter( 'query_vars', 'cmbwc_register_production_app_query_vars' );
add_action( 'template_redirect', 'cmbwc_maybe_render_production_app' );

if ( ! function_exists( 'cmbwc_register_production_app_rewrite' ) ) {
	function cmbwc_register_production_app_rewrite() {
		add_rewrite_rule( '^produktion/?$', 'index.php?cmbwc_production_app=1', 'top' );
	}
}

if ( ! function_exists( 'cmbwc_register_production_app_query_vars' ) ) {
	function cmbwc_register_production_app_query_vars( $vars ) {
		$vars[] = 'cmbwc_production_app';
		return $vars;
	}
}

if ( ! function_exists( 'cmbwc_get_production_app_url' ) ) {
	function cmbwc_get_production_app_url() {
		return home_url( '/produktion/' );
	}
}

if ( ! function_exists( 'cmbwc_user_can_access_production_app' ) ) {
	function cmbwc_user_can_access_production_app() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
	}
}

if ( ! function_exists( 'cmbwc_get_current_production_app_url' ) ) {
	function cmbwc_get_current_production_app_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/produktion/';
		return home_url( $request_uri );
	}
}

if ( ! function_exists( 'cmbwc_maybe_render_production_app' ) ) {
	function cmbwc_maybe_render_production_app() {
		if ( '1' !== (string) get_query_var( 'cmbwc_production_app' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( cmbwc_get_current_production_app_url() ) );
			exit;
		}

		if ( ! cmbwc_user_can_access_production_app() ) {
			status_header( 403 );
			nocache_headers();
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ingen adgang</title></head><body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:24px;"><h1>Ingen adgang</h1><p>Du har ikke adgang til produktionsvisningen.</p></body></html>';
			exit;
		}

		cmbwc_render_frontend_production_app();
		exit;
	}
}

if ( ! function_exists( 'cmbwc_format_delivery_day_title' ) ) {
	function cmbwc_format_delivery_day_title( $label, $covers_total ) {
		return trim( $label . ' - ' . (int) $covers_total . ' kuverter' );
	}
}

if ( ! function_exists( 'cmbwc_render_frontend_production_app' ) ) {
	function cmbwc_render_frontend_production_app() {
		$preset            = isset( $_GET['preset'] ) ? sanitize_text_field( wp_unslash( $_GET['preset'] ) ) : 'upcoming';
		$date_from         = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to           = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$production_status = isset( $_GET['production_status'] ) ? sanitize_text_field( wp_unslash( $_GET['production_status'] ) ) : '';
		$hide_completed    = isset( $_GET['hide_completed'] ) ? sanitize_text_field( wp_unslash( $_GET['hide_completed'] ) ) : 'no';
		$all_statuses      = cmbwc_get_production_statuses();

		if ( '' === $date_from && '' === $date_to ) {
			$preset_dates = cmbwc_get_production_preset_dates( $preset );
			$date_from    = $preset_dates['date_from'];
			$date_to      = $preset_dates['date_to'];
		}

		$rows = cmbwc_get_production_orders(
			array(
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'production_status' => $production_status,
				'hide_completed'    => $hide_completed,
			)
		);

		$grouped       = cmbwc_group_production_rows_by_date( $rows );
		$app_url       = cmbwc_get_production_app_url();
		$logout_url    = wp_logout_url( $app_url );
		$current_user  = wp_get_current_user();

		?><!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
			<title>Produktion</title>
			<?php wp_head(); ?>
			<style>
				:root {
					--cmbwc-bg: #f5f6f8;
					--cmbwc-card: #ffffff;
					--cmbwc-border: #d9dee5;
					--cmbwc-text: #1f2933;
					--cmbwc-muted: #67727e;
					--cmbwc-accent: #135e96;
					--cmbwc-shadow: 0 10px 24px rgba(16,24,40,.08);
				}

				html {
					-webkit-text-size-adjust: 100%;
				}

				body.cmbwc-production-app {
					margin: 0;
					background: var(--cmbwc-bg);
					color: var(--cmbwc-text);
					font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				}

				.cmbwc-app {
					max-width: 1120px;
					margin: 0 auto;
					padding: 16px 14px 48px;
				}

				.cmbwc-topbar {
					position: sticky;
					top: 0;
					z-index: 20;
					margin: -16px -14px 16px;
					padding: calc(10px + env(safe-area-inset-top)) 14px 12px;
					background: rgba(245,246,248,.92);
					backdrop-filter: blur(10px);
					border-bottom: 1px solid rgba(217,222,229,.9);
				}

				.cmbwc-topbar-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
					gap: 12px;
					flex-wrap: wrap;
				}

				.cmbwc-brand h1 {
					margin: 0;
					font-size: 24px;
					line-height: 1.1;
				}

				.cmbwc-brand p {
					margin: 4px 0 0;
					color: var(--cmbwc-muted);
					font-size: 14px;
				}

				.cmbwc-user-links {
					display: flex;
					gap: 8px;
					align-items: center;
					flex-wrap: wrap;
				}

				.cmbwc-chip,
				.cmbwc-link-btn,
				.cmbwc-action,
				.cmbwc-status-toggle {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					gap: 6px;
					border-radius: 999px;
					padding: 10px 14px;
					font-weight: 600;
					font-size: 14px;
					line-height: 1.1;
					text-decoration: none;
					border: 1px solid transparent;
				}

				.cmbwc-chip {
					background: #fff;
					border-color: var(--cmbwc-border);
					color: var(--cmbwc-text);
				}

				.cmbwc-link-btn {
					background: #fff;
					border-color: var(--cmbwc-border);
					color: var(--cmbwc-text);
				}

				.cmbwc-link-btn--primary,
				.cmbwc-action--primary {
					background: var(--cmbwc-accent);
					color: #fff;
					border-color: var(--cmbwc-accent);
				}

				.cmbwc-filters {
					display: grid;
					grid-template-columns: repeat(2, minmax(0, 1fr));
					gap: 10px;
					margin-bottom: 18px;
				}

				.cmbwc-field,
				.cmbwc-field--wide {
					background: var(--cmbwc-card);
					border: 1px solid var(--cmbwc-border);
					border-radius: 16px;
					padding: 10px 12px;
					box-shadow: var(--cmbwc-shadow);
				}

				.cmbwc-field label {
					display: block;
					font-size: 12px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: .02em;
					color: var(--cmbwc-muted);
					margin-bottom: 6px;
				}

				.cmbwc-field select,
				.cmbwc-field input[type="date"] {
					width: 100%;
					border: 0;
					background: transparent;
					padding: 0;
					font-size: 16px;
					color: var(--cmbwc-text);
					outline: none;
				}

				.cmbwc-field--wide {
					grid-column: 1 / -1;
					display: flex;
					justify-content: space-between;
					align-items: center;
					gap: 10px;
				}

				.cmbwc-check {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					font-size: 14px;
				}

				.cmbwc-filter-actions {
					grid-column: 1 / -1;
					display: flex;
					gap: 10px;
				}

				.cmbwc-filter-actions .cmbwc-link-btn {
					flex: 1;
				}

				.cmbwc-day {
					margin-top: 18px;
				}

				.cmbwc-day-title {
					margin: 0 0 10px;
					font-size: 19px;
					line-height: 1.2;
				}

				.cmbwc-cards {
					display: grid;
					gap: 12px;
				}

				.cmbwc-card {
					background: var(--cmbwc-card);
					border: 1px solid var(--cmbwc-border);
					border-radius: 20px;
					padding: 14px;
					box-shadow: var(--cmbwc-shadow);
				}

				.cmbwc-card-head {
					display: flex;
					justify-content: space-between;
					align-items: flex-start;
					gap: 12px;
					margin-bottom: 12px;
				}

				.cmbwc-order-no {
					font-weight: 800;
					font-size: 16px;
				}

				.cmbwc-customer {
					margin-top: 4px;
					font-size: 18px;
					font-weight: 700;
					line-height: 1.2;
				}

				.cmbwc-status-box {
					position: relative;
					text-align: right;
				}

				.cmbwc-status-toggle {
					border: 0;
					color: #fff;
					cursor: pointer;
					padding: 10px 14px;
				}

				.cmbwc-status-meta {
					margin-top: 6px;
					font-size: 12px;
					color: var(--cmbwc-muted);
					max-width: 180px;
				}

				.cmbwc-status-menu {
					position: absolute;
					top: calc(100% + 6px);
					right: 0;
					z-index: 30;
					display: none;
					width: 220px;
					background: #fff;
					border: 1px solid var(--cmbwc-border);
					border-radius: 16px;
					box-shadow: var(--cmbwc-shadow);
					padding: 6px;
				}

				.cmbwc-status-box.is-open .cmbwc-status-menu {
					display: block;
				}

				.cmbwc-status-option {
					display: block;
					padding: 10px 12px;
					border-radius: 12px;
					text-decoration: none;
					color: var(--cmbwc-text);
					font-weight: 600;
					text-align: left;
				}

				.cmbwc-status-option:hover {
					background: #f3f6f9;
				}

				.cmbwc-meta-grid {
					display: grid;
					grid-template-columns: repeat(3, minmax(0, 1fr));
					gap: 8px;
					margin-bottom: 12px;
				}

				.cmbwc-meta-card {
					background: #f8fafc;
					border-radius: 14px;
					padding: 10px 12px;
				}

				.cmbwc-meta-card span {
					display: block;
					font-size: 12px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: .02em;
					color: var(--cmbwc-muted);
					margin-bottom: 4px;
				}

				.cmbwc-meta-card strong {
					font-size: 16px;
				}

				.cmbwc-sections {
					display: grid;
					gap: 10px;
				}

				.cmbwc-section {
					background: #fbfcfd;
					border: 1px solid #eef2f6;
					border-radius: 16px;
					padding: 12px;
				}

				.cmbwc-section h3 {
					margin: 0 0 8px;
					font-size: 14px;
				}

				.cmbwc-section ul {
					margin: 0;
					padding-left: 18px;
				}

				.cmbwc-section li {
					margin-bottom: 4px;
				}

				.cmbwc-empty {
					color: var(--cmbwc-muted);
				}

				.cmbwc-actions {
					display: grid;
					grid-template-columns: repeat(3, minmax(0, 1fr));
					gap: 8px;
					margin-top: 14px;
				}

				.cmbwc-action {
					background: #fff;
					border-color: var(--cmbwc-border);
					color: var(--cmbwc-text);
					text-align: center;
				}

				.cmbwc-action--primary {
					color: #fff;
				}

				.cmbwc-empty-state {
					background: #fff;
					border: 1px solid var(--cmbwc-border);
					border-radius: 20px;
					padding: 22px;
					box-shadow: var(--cmbwc-shadow);
					text-align: center;
				}

				@media (min-width: 760px) {
					.cmbwc-app {
						padding: 20px 20px 56px;
					}

					.cmbwc-topbar {
						margin: -20px -20px 20px;
						padding-left: 20px;
						padding-right: 20px;
					}

					.cmbwc-filters {
						grid-template-columns: repeat(5, minmax(0, 1fr));
					}

					.cmbwc-field--wide,
					.cmbwc-filter-actions {
						grid-column: auto;
					}

					.cmbwc-filter-actions {
						display: flex;
						align-items: stretch;
					}

					.cmbwc-cards {
						grid-template-columns: repeat(2, minmax(0, 1fr));
					}

					.cmbwc-sections {
						grid-template-columns: repeat(3, minmax(0, 1fr));
					}
				}

				@media (max-width: 759px) {
					.cmbwc-meta-grid {
						grid-template-columns: 1fr 1fr;
					}

					.cmbwc-card-head {
						flex-direction: column;
					}

					.cmbwc-status-box {
						text-align: left;
					}

					.cmbwc-status-menu {
						left: 0;
						right: auto;
					}
				}

				@media (max-width: 520px) {
					.cmbwc-meta-grid,
					.cmbwc-actions {
						grid-template-columns: 1fr;
					}
				}
			</style>
		</head>
		<body <?php body_class( 'cmbwc-production-app' ); ?>>
		<?php wp_body_open(); ?>

		<div class="cmbwc-app">
			<div class="cmbwc-topbar">
				<div class="cmbwc-topbar-row">
					<div class="cmbwc-brand">
						<h1>Produktion</h1>
						<p><?php echo esc_html( $current_user->display_name ); ?> · Catering drift</p>
					</div>

					<div class="cmbwc-user-links">
						<a class="cmbwc-link-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=cmbwc-production-overview' ) ); ?>">Backend</a>
						<a class="cmbwc-link-btn" href="<?php echo esc_url( $logout_url ); ?>">Log ud</a>
					</div>
				</div>
			</div>

			<form method="get" action="<?php echo esc_url( $app_url ); ?>" class="cmbwc-filters">
				<div class="cmbwc-field">
					<label for="cmbwc-preset">Visning</label>
					<select id="cmbwc-preset" name="preset">
						<option value="today" <?php selected( $preset, 'today' ); ?>>I dag</option>
						<option value="week" <?php selected( $preset, 'week' ); ?>>Denne uge</option>
						<option value="month" <?php selected( $preset, 'month' ); ?>>Denne måned</option>
						<option value="upcoming" <?php selected( $preset, 'upcoming' ); ?>>Kommende 14 dage</option>
					</select>
				</div>

				<div class="cmbwc-field">
					<label for="cmbwc-date-from">Fra</label>
					<input id="cmbwc-date-from" type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				</div>

				<div class="cmbwc-field">
					<label for="cmbwc-date-to">Til</label>
					<input id="cmbwc-date-to" type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				</div>

				<div class="cmbwc-field">
					<label for="cmbwc-production-status">Status</label>
					<select id="cmbwc-production-status" name="production_status">
						<option value="">Alle</option>
						<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $production_status, $status_key ); ?>>
								<?php echo esc_html( $status_data['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="cmbwc-field--wide">
					<label class="cmbwc-check">
						<input type="checkbox" name="hide_completed" value="yes" <?php checked( $hide_completed, 'yes' ); ?>>
						Skjul afhentet / leveret
					</label>
					<span class="cmbwc-chip"><?php echo esc_html( (string) count( $rows ) ); ?> ordrer</span>
				</div>

				<div class="cmbwc-filter-actions">
					<button type="submit" class="cmbwc-link-btn cmbwc-link-btn--primary">Opdater</button>
					<a class="cmbwc-link-btn" href="<?php echo esc_url( $app_url ); ?>">Nulstil</a>
				</div>
			</form>

			<?php if ( empty( $grouped ) ) : ?>
				<div class="cmbwc-empty-state">
					<h2>Ingen ordrer fundet</h2>
					<p>Prøv et andet datointerval eller fjern filtre.</p>
				</div>
			<?php else : ?>
				<?php foreach ( $grouped as $group ) : ?>
					<section class="cmbwc-day">
						<h2 class="cmbwc-day-title">
							<?php echo esc_html( cmbwc_format_delivery_day_title( $group['label'], $group['covers_total'] ) ); ?>
						</h2>

						<div class="cmbwc-cards">
							<?php foreach ( $group['rows'] as $row ) : ?>
								<article class="cmbwc-card">
									<div class="cmbwc-card-head">
										<div>
											<div class="cmbwc-order-no">#<?php echo esc_html( $row['order_number'] ); ?></div>
											<div class="cmbwc-customer"><?php echo esc_html( $row['customer'] ); ?></div>
										</div>

										<div class="cmbwc-status-box">
											<button type="button" class="cmbwc-status-toggle" style="background:<?php echo esc_attr( $row['production_status_color'] ); ?>;">
												<span><?php echo esc_html( $row['production_status_label'] ); ?></span>
												<span aria-hidden="true">▾</span>
											</button>

											<div class="cmbwc-status-menu">
												<?php foreach ( $all_statuses as $status_key => $status_data ) : ?>
													<?php
													$status_url = add_query_arg(
														array(
															'order_id'          => $row['order_id'],
															'production_status' => $status_key,
															'_wpnonce'          => $row['production_status_nonce'],
														),
														$row['production_status_update_base_url']
													);
													?>
													<a class="cmbwc-status-option" href="<?php echo esc_url( $status_url ); ?>">
														<?php echo esc_html( $status_data['label'] ); ?>
													</a>
												<?php endforeach; ?>
											</div>

											<?php if ( ! empty( $row['production_status_meta_label'] ) ) : ?>
												<div class="cmbwc-status-meta">
													<?php echo esc_html( $row['production_status_meta_label'] ); ?>
												</div>
											<?php endif; ?>
										</div>
									</div>

									<div class="cmbwc-meta-grid">
										<div class="cmbwc-meta-card">
											<span>Tid</span>
											<strong><?php echo esc_html( $row['delivery_time'] ? $row['delivery_time'] : '-' ); ?></strong>
										</div>

										<div class="cmbwc-meta-card">
											<span>Type</span>
											<strong><?php echo esc_html( $row['delivery_type'] ); ?></strong>
										</div>

										<div class="cmbwc-meta-card">
											<span>Kuverter</span>
											<strong><?php echo esc_html( (string) $row['covers_total'] ); ?></strong>
										</div>
									</div>

									<div class="cmbwc-sections">
										<div class="cmbwc-section">
											<h3>Menuer</h3>
											<?php if ( ! empty( $row['items'] ) ) : ?>
												<ul>
													<?php foreach ( $row['items'] as $line ) : ?>
														<li><?php echo esc_html( $line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<div class="cmbwc-empty">-</div>
											<?php endif; ?>
										</div>

										<div class="cmbwc-section">
											<h3>Tilvalg</h3>
											<?php if ( ! empty( $row['addons'] ) ) : ?>
												<ul>
													<?php foreach ( $row['addons'] as $line ) : ?>
														<li><?php echo esc_html( $line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<div class="cmbwc-empty">-</div>
											<?php endif; ?>
										</div>

										<div class="cmbwc-section">
											<h3>Service</h3>
											<?php if ( ! empty( $row['service'] ) ) : ?>
												<ul>
													<?php foreach ( $row['service'] as $line ) : ?>
														<li><?php echo esc_html( $line ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<div class="cmbwc-empty">-</div>
											<?php endif; ?>
										</div>
									</div>

									<div class="cmbwc-actions">
										<a class="cmbwc-action" href="<?php echo esc_url( $row['preview_url'] ); ?>" target="_blank" rel="noopener">Forhåndsvis</a>
										<a class="cmbwc-action cmbwc-action--primary" href="<?php echo esc_url( $row['print_url'] ); ?>">Print</a>
										<a class="cmbwc-action" href="<?php echo esc_url( $row['admin_order_url'] ); ?>">Åbn ordre</a>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script>
			document.addEventListener('click', function (event) {
				var toggle = event.target.closest('.cmbwc-status-toggle');
				var box = event.target.closest('.cmbwc-status-box');

				document.querySelectorAll('.cmbwc-status-box.is-open').forEach(function (el) {
					if (el !== box) {
						el.classList.remove('is-open');
					}
				});

				if (toggle && box) {
					event.preventDefault();
					box.classList.toggle('is-open');
					return;
				}

				if (!event.target.closest('.cmbwc-status-box')) {
					document.querySelectorAll('.cmbwc-status-box.is-open').forEach(function (el) {
						el.classList.remove('is-open');
					});
				}
			});
		</script>

		<?php wp_footer(); ?>
		</body>
		</html><?php
	}
}
