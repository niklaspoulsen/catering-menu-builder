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

		$grouped      = cmbwc_group_production_rows_by_date( $rows );
		$app_url      = cmbwc_get_production_app_url();
		$logout_url   = wp_logout_url( $app_url );
		$current_user = wp_get_current_user();

		?><!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
			<title>Produktion</title>
			<?php wp_head(); ?>
			<style>
				:root {
					--cmbwc-bg: #f3f4f6;
					--cmbwc-card: #ffffff;
					--cmbwc-border: #d8dee6;
					--cmbwc-text: #1f2937;
					--cmbwc-muted: #6b7280;
					--cmbwc-accent: #135e96;
					--cmbwc-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
					--cmbwc-radius: 18px;
				}

				*,
				*::before,
				*::after {
					box-sizing: border-box;
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

				body.cmbwc-production-app ul,
				body.cmbwc-production-app li {
					max-width: 100%;
				}

				.cmbwc-app {
					max-width: 980px;
					margin: 0 auto;
					padding: 14px 12px 40px;
				}

				.cmbwc-topbar {
					position: sticky;
					top: 0;
					z-index: 20;
					margin: -14px -12px 14px;
					padding: calc(10px + env(safe-area-inset-top)) 12px 12px;
					background: rgba(243,244,246,.94);
					backdrop-filter: blur(12px);
					border-bottom: 1px solid rgba(216,222,230,.9);
				}

				.cmbwc-topbar-row {
					display: flex;
					flex-direction: column;
					gap: 10px;
				}

				.cmbwc-brand h1 {
					margin: 0;
					font-size: 22px;
					line-height: 1.1;
				}

				.cmbwc-brand p {
					margin: 4px 0 0;
					font-size: 14px;
					color: var(--cmbwc-muted);
				}

				.cmbwc-user-links {
					display: flex;
					gap: 8px;
				}

				.cmbwc-link-btn,
				.cmbwc-action,
				.cmbwc-status-toggle {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					border-radius: 999px;
					padding: 11px 14px;
					font-size: 15px;
					font-weight: 700;
					line-height: 1;
					text-decoration: none;
					border: 1px solid var(--cmbwc-border);
					background: #fff;
					color: var(--cmbwc-text);
				}

				.cmbwc-link-btn--primary,
				.cmbwc-action--primary {
					background: var(--cmbwc-accent);
					border-color: var(--cmbwc-accent);
					color: #fff;
				}

				.cmbwc-user-links .cmbwc-link-btn {
					flex: 1;
				}

				.cmbwc-filters {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 10px;
					margin-bottom: 16px;
				}

				.cmbwc-field,
				.cmbwc-field--wide {
					background: var(--cmbwc-card);
					border: 1px solid var(--cmbwc-border);
					border-radius: var(--cmbwc-radius);
					padding: 12px 14px;
					box-shadow: var(--cmbwc-shadow);
					min-width: 0;
				}

				.cmbwc-field label {
					display: block;
					margin-bottom: 8px;
					font-size: 11px;
					font-weight: 800;
					letter-spacing: .05em;
					text-transform: uppercase;
					color: var(--cmbwc-muted);
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
					align-items: center;
					justify-content: space-between;
					gap: 10px;
				}

				.cmbwc-check {
					display: inline-flex;
					align-items: center;
					gap: 10px;
					font-size: 14px;
				}

				.cmbwc-check input {
					width: 22px;
					height: 22px;
					margin: 0;
					flex: 0 0 auto;
				}

				.cmbwc-chip {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					border-radius: 999px;
					padding: 10px 14px;
					background: #fff;
					border: 1px solid var(--cmbwc-border);
					font-weight: 700;
					white-space: nowrap;
					flex: 0 0 auto;
				}

				.cmbwc-filter-actions {
					grid-column: 1 / -1;
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 10px;
				}

				.cmbwc-filter-actions .cmbwc-link-btn,
				.cmbwc-filter-actions button.cmbwc-link-btn {
					width: 100%;
					cursor: pointer;
				}

				.cmbwc-day {
					margin-top: 18px;
				}

				.cmbwc-day-title {
					margin: 0 0 10px;
					font-size: 18px;
					line-height: 1.25;
				}

				.cmbwc-cards {
					display: grid;
					gap: 12px;
				}

				.cmbwc-card {
					background: var(--cmbwc-card);
					border: 1px solid var(--cmbwc-border);
					border-radius: 22px;
					padding: 14px;
					box-shadow: var(--cmbwc-shadow);
					overflow: hidden;
				}

				.cmbwc-card-header {
					display: grid;
					grid-template-columns: 1fr;
					gap: 12px;
					margin-bottom: 12px;
				}

				.cmbwc-order-no {
					font-size: 16px;
					font-weight: 800;
					margin-bottom: 2px;
				}

				.cmbwc-customer {
					font-size: 18px;
					font-weight: 800;
					line-height: 1.15;
					word-break: break-word;
				}

				.cmbwc-status-box {
					position: relative;
					max-width: 100%;
				}

				.cmbwc-status-toggle {
					border: 0;
					color: #fff;
					cursor: pointer;
					gap: 8px;
					max-width: 100%;
				}

				.cmbwc-status-meta {
					margin-top: 8px;
					font-size: 12px;
					line-height: 1.4;
					color: var(--cmbwc-muted);
					word-break: break-word;
				}

				.cmbwc-status-menu {
					position: absolute;
					top: calc(100% + 6px);
					left: 0;
					z-index: 30;
					display: none;
					width: 220px;
					max-width: calc(100vw - 40px);
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
					padding: 11px 12px;
					border-radius: 12px;
					text-decoration: none;
					color: var(--cmbwc-text);
					font-weight: 700;
				}

				.cmbwc-status-option:hover {
					background: #f4f7fa;
				}

				.cmbwc-meta-grid {
					display: grid;
					grid-template-columns: 1fr;
					gap: 10px;
					margin-bottom: 12px;
				}

				.cmbwc-meta-card {
					background: #f7fafc;
					border: 1px solid #edf2f7;
					border-radius: 16px;
					padding: 12px;
					min-width: 0;
				}

				.cmbwc-meta-card span {
					display: block;
					margin-bottom: 6px;
					font-size: 11px;
					font-weight: 800;
					letter-spacing: .05em;
					text-transform: uppercase;
					color: var(--cmbwc-muted);
				}

				.cmbwc-meta-card strong {
					display: block;
					font-size: 16px;
					line-height: 1.2;
					word-break: break-word;
				}

				.cmbwc-sections {
					display: grid;
					grid-template-columns: 1fr;
					gap: 10px;
					margin-bottom: 14px;
				}

				.cmbwc-section {
					background: #fbfcfd;
					border: 1px solid #edf2f7;
					border-radius: 16px;
					padding: 12px 13px;
					min-width: 0;
					width: 100%;
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
					line-height: 1.4;
					white-space: normal;
					word-break: break-word;
					overflow-wrap: anywhere;
				}

				.cmbwc-empty {
					color: var(--cmbwc-muted);
				}

				.cmbwc-actions {
					display: grid;
					grid-template-columns: 1fr;
					gap: 8px;
				}

				.cmbwc-action {
					min-height: 46px;
					text-align: center;
					width: 100%;
					white-space: nowrap;
				}

				.cmbwc-empty-state {
					background: #fff;
					border: 1px solid var(--cmbwc-border);
					border-radius: 22px;
					padding: 24px;
					box-shadow: var(--cmbwc-shadow);
					text-align: center;
				}

				@media (min-width: 640px) {
					.cmbwc-meta-grid {
						grid-template-columns: repeat(3, minmax(0, 1fr));
					}

					.cmbwc-actions {
						grid-template-columns: repeat(3, minmax(0, 1fr));
					}
				}

				@media (min-width: 900px) {
					.cmbwc-app {
						padding: 18px 18px 48px;
					}

					.cmbwc-topbar {
						margin: -18px -18px 18px;
						padding-left: 18px;
						padding-right: 18px;
					}

					.cmbwc-topbar-row {
						flex-direction: row;
						align-items: center;
						justify-content: space-between;
					}

					.cmbwc-user-links .cmbwc-link-btn {
						flex: 0 0 auto;
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
						gap: 10px;
					}

					.cmbwc-filter-actions .cmbwc-link-btn,
					.cmbwc-filter-actions button.cmbwc-link-btn {
						flex: 1;
					}

					.cmbwc-card-header {
						grid-template-columns: minmax(220px, 280px) 1fr;
						align-items: start;
					}

					.cmbwc-meta-grid {
						grid-template-columns: repeat(3, minmax(120px, 150px));
						justify-content: start;
						margin-bottom: 0;
					}

					.cmbwc-sections {
						grid-template-columns: repeat(3, minmax(0, 1fr));
					}

					.cmbwc-actions {
						grid-template-columns: repeat(3, max-content);
						justify-content: start;
					}

					.cmbwc-action {
						width: auto;
						padding-left: 18px;
						padding-right: 18px;
					}
				}

				@media (max-width: 640px) {
					.cmbwc-filters {
						grid-template-columns: 1fr;
					}

					.cmbwc-field--wide {
						flex-direction: column;
						align-items: stretch;
					}

					.cmbwc-chip {
						align-self: flex-start;
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
									<div class="cmbwc-card-header">
										<div>
											<div class="cmbwc-order-no">#<?php echo esc_html( $row['order_number'] ); ?></div>
											<div class="cmbwc-customer"><?php echo esc_html( $row['customer'] ); ?></div>

											<div class="cmbwc-status-box" style="margin-top:12px;">
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
