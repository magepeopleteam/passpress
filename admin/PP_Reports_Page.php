<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Revenue, Membership Growth, Expired Members, Renewal Rate, Facility Usage,
 * Popular Plans, Payment Reports, Trainer Performance — all read-only
 * queries via PP_Reports. Peak Hours lives on the Attendance page (not
 * duplicated here); linked instead.
 */
class PP_Reports_Page {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( $end_date . ' -29 days' ) );

		$settings = pp_get_settings();
		$symbol   = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

		$revenue     = PP_Reports::get_revenue( $start_date, $end_date );
		$growth      = PP_Reports::get_membership_growth( $start_date, $end_date );
		$expired     = PP_Reports::get_expired_members( 20 );
		$renewal     = PP_Reports::get_renewal_rate( $start_date, $end_date );
		$facilities  = PP_Reports::get_facility_usage( $start_date, $end_date );
		$plans       = PP_Reports::get_popular_plans();
		$payments    = PP_Reports::get_payment_report( $start_date, $end_date );
		$instructors = PP_Reports::get_trainer_performance( $start_date, $end_date );

		$renewal_display = null === $renewal['rate_percent'] ? '—' : $renewal['rate_percent'] . '%';
		?>
		<div class="wrap passpress-wrap passpress-reports-page">
			<div class="passpress-reports-page-header">
				<div class="passpress-reports-page-copy">
					<p class="passpress-reports-page-eyebrow"><?php esc_html_e( 'Analytics', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Reports', 'passpress' ); ?></h1>
					<p class="passpress-reports-page-desc"><?php esc_html_e( 'Revenue, growth, facility usage, and payment activity for the selected date range.', 'passpress' ); ?></p>
				</div>
				<a class="passpress-reports-peak-link" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-attendance' ) ); ?>">
					<?php esc_html_e( 'Peak hours →', 'passpress' ); ?>
				</a>
			</div>

			<form method="get" class="passpress-reports-toolbar">
				<input type="hidden" name="page" value="passpress-reports">
				<div class="passpress-reports-date-fields">
					<label class="passpress-reports-date-field">
						<span><?php esc_html_e( 'From', 'passpress' ); ?></span>
						<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
					</label>
					<label class="passpress-reports-date-field">
						<span><?php esc_html_e( 'To', 'passpress' ); ?></span>
						<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
					</label>
				</div>
				<button type="submit" class="passpress-reports-update-btn"><?php esc_html_e( 'Update', 'passpress' ); ?></button>
			</form>

			<div class="passpress-reports-stat-row">
				<div class="passpress-reports-stat">
					<span class="passpress-reports-stat-label"><?php esc_html_e( 'Revenue', 'passpress' ); ?></span>
					<span class="passpress-reports-stat-number is-revenue"><?php echo esc_html( $symbol . number_format_i18n( $revenue['total'], 2 ) ); ?></span>
				</div>
				<div class="passpress-reports-stat">
					<span class="passpress-reports-stat-label"><?php esc_html_e( 'New members', 'passpress' ); ?></span>
					<span class="passpress-reports-stat-number"><?php echo esc_html( number_format_i18n( $growth['total'] ) ); ?></span>
				</div>
				<div class="passpress-reports-stat">
					<span class="passpress-reports-stat-label"><?php esc_html_e( 'Renewal rate', 'passpress' ); ?></span>
					<span class="passpress-reports-stat-number"><?php echo esc_html( $renewal_display ); ?></span>
				</div>
			</div>

			<div class="passpress-reports-grid">
				<section class="passpress-reports-card">
					<div class="passpress-reports-card-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Money', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Revenue by day', 'passpress' ); ?></h2>
					</div>
					<?php
					self::render_bar_list(
						$revenue['by_day'],
						function ( $v ) use ( $symbol ) {
							return $symbol . number_format_i18n( $v, 2 );
						},
						'revenue'
					);
					?>
				</section>

				<section class="passpress-reports-card">
					<div class="passpress-reports-card-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Growth', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Membership growth', 'passpress' ); ?></h2>
					</div>
					<?php self::render_bar_list( $growth['by_day'], null, 'growth' ); ?>
				</section>
			</div>

			<section class="passpress-reports-renewal">
				<div class="passpress-reports-renewal-copy">
					<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Retention', 'passpress' ); ?></p>
					<h2><?php esc_html_e( 'Renewal rate', 'passpress' ); ?></h2>
					<p class="passpress-reports-renewal-desc">
						<?php
						printf(
							/* translators: 1: renewed count, 2: lapsed count */
							esc_html__( '%1$d renewed, %2$d lapsed without renewing in this window.', 'passpress' ),
							(int) $renewal['renewed'],
							(int) $renewal['lapsed']
						);
						?>
					</p>
				</div>
				<div class="passpress-reports-renewal-rate">
					<span class="passpress-reports-renewal-value"><?php echo esc_html( $renewal_display ); ?></span>
					<span class="passpress-reports-renewal-label"><?php esc_html_e( 'of eligible cycles', 'passpress' ); ?></span>
				</div>
			</section>

			<div class="passpress-reports-grid passpress-reports-grid-tables">
				<section class="passpress-reports-section">
					<div class="passpress-reports-section-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Members', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Expired members', 'passpress' ); ?></h2>
					</div>
					<?php if ( ! $expired ) : ?>
						<?php self::render_empty( __( 'No expired members', 'passpress' ), __( 'Members who lapse will appear in this list.', 'passpress' ) ); ?>
					<?php else : ?>
						<div class="passpress-reports-table-wrap">
							<table class="passpress-reports-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Expired on', 'passpress' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $expired as $m ) : ?>
										<?php
										$user   = get_userdata( $m->user_id );
										$member = $user ? $user->display_name : __( 'Unknown', 'passpress' );
										?>
										<tr>
											<td>
												<div class="passpress-reports-person">
													<span class="passpress-reports-avatar"><?php echo esc_html( self::initials( $member ) ); ?></span>
													<strong><?php echo esc_html( $member ); ?></strong>
												</div>
											</td>
											<td><span class="passpress-reports-pill"><?php echo esc_html( get_the_title( $m->plan_id ) ); ?></span></td>
											<td><?php echo esc_html( pp_format_date( $m->expiry_date ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>

				<section class="passpress-reports-section">
					<div class="passpress-reports-section-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Usage', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Facility usage', 'passpress' ); ?></h2>
					</div>
					<?php if ( ! $facilities ) : ?>
						<?php self::render_empty( __( 'No activity in this window', 'passpress' ), __( 'Bookings and door entries will show up here.', 'passpress' ) ); ?>
					<?php else : ?>
						<div class="passpress-reports-table-wrap">
							<table class="passpress-reports-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Facility', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Bookings', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Entries', 'passpress' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $facilities as $f ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $f['name'] ); ?></strong></td>
											<td><?php echo esc_html( number_format_i18n( $f['bookings'] ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( $f['entries'] ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<div class="passpress-reports-grid passpress-reports-grid-tables">
				<section class="passpress-reports-section">
					<div class="passpress-reports-section-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Catalog', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Popular plans', 'passpress' ); ?></h2>
					</div>
					<?php if ( ! $plans ) : ?>
						<?php self::render_empty( __( 'No memberships yet', 'passpress' ), __( 'Issued plans will rank here by member count.', 'passpress' ) ); ?>
					<?php else : ?>
						<div class="passpress-reports-table-wrap">
							<table class="passpress-reports-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Active / total', 'passpress' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $plans as $p ) : ?>
										<tr>
											<td><span class="passpress-reports-pill"><?php echo esc_html( $p['name'] ); ?></span></td>
											<td><strong><?php echo esc_html( number_format_i18n( $p['count'] ) ); ?></strong></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>

				<section class="passpress-reports-section">
					<div class="passpress-reports-section-header">
						<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Checkout', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Payment reports', 'passpress' ); ?></h2>
					</div>
					<?php if ( ! $payments ) : ?>
						<?php self::render_empty( __( 'No payment activity', 'passpress' ), __( 'Gateway totals for this range will appear here.', 'passpress' ) ); ?>
					<?php else : ?>
						<div class="passpress-reports-table-wrap">
							<table class="passpress-reports-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Gateway', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Count', 'passpress' ); ?></th>
										<th><?php esc_html_e( 'Total', 'passpress' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $payments as $gateway => $statuses ) : ?>
										<?php foreach ( $statuses as $status => $data ) : ?>
											<tr>
												<td><strong><?php echo esc_html( self::gateway_label( $gateway ) ); ?></strong></td>
												<td>
													<span class="passpress-reports-status passpress-reports-status-<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
														<span class="passpress-reports-status-dot"></span>
														<?php echo esc_html( ucfirst( $status ) ); ?>
													</span>
												</td>
												<td><?php echo esc_html( number_format_i18n( $data['count'] ) ); ?></td>
												<td><strong><?php echo esc_html( $symbol . number_format_i18n( $data['total'], 2 ) ); ?></strong></td>
											</tr>
										<?php endforeach; ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<section class="passpress-reports-section">
				<div class="passpress-reports-section-header">
					<p class="passpress-reports-card-eyebrow"><?php esc_html_e( 'Classes', 'passpress' ); ?></p>
					<h2><?php esc_html_e( 'Trainer performance', 'passpress' ); ?></h2>
				</div>
				<?php if ( ! $instructors ) : ?>
					<?php self::render_empty( __( 'No instructor activity', 'passpress' ), __( 'No instructor-assigned classes with activity in this window.', 'passpress' ) ); ?>
				<?php else : ?>
					<div class="passpress-reports-table-wrap">
						<table class="passpress-reports-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Instructor', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Classes', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Bookings', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Attended', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'No-shows', 'passpress' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $instructors as $data ) : ?>
									<tr>
										<td>
											<div class="passpress-reports-person">
												<span class="passpress-reports-avatar"><?php echo esc_html( self::initials( $data['name'] ) ); ?></span>
												<strong><?php echo esc_html( $data['name'] ); ?></strong>
											</div>
										</td>
										<td><?php echo esc_html( number_format_i18n( $data['classes'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $data['total_bookings'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $data['attended'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $data['no_shows'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * @param array<string,float|int> $by_day
	 * @param callable|null           $format_callback
	 * @param string                  $variant
	 */
	private static function render_bar_list( $by_day, $format_callback = null, $variant = 'default' ) {
		if ( ! $by_day ) {
			echo '<div class="passpress-reports-card-empty"><p>' . esc_html__( 'No data in this window.', 'passpress' ) . '</p></div>';
			return;
		}

		$max = max( 1, (float) max( $by_day ) );
		echo '<ul class="passpress-reports-bar-list">';
		foreach ( $by_day as $day => $value ) {
			$display = $format_callback ? call_user_func( $format_callback, $value ) : number_format_i18n( $value );
			$width   = round( ( (float) $value / $max ) * 100 );
			?>
			<li class="passpress-reports-bar-row">
				<span class="passpress-reports-bar-day"><?php echo esc_html( pp_format_date( $day ) ); ?></span>
				<div class="passpress-reports-bar-track" aria-hidden="true">
					<span class="passpress-reports-bar-fill passpress-reports-bar-fill-<?php echo esc_attr( $variant ); ?>" style="width:<?php echo esc_attr( (string) $width ); ?>%;"></span>
				</div>
				<span class="passpress-reports-bar-value"><?php echo esc_html( $display ); ?></span>
			</li>
			<?php
		}
		echo '</ul>';
	}

	private static function render_empty( $title, $desc ) {
		?>
		<div class="passpress-reports-empty">
			<p class="passpress-reports-empty-eyebrow"><?php esc_html_e( 'Empty', 'passpress' ); ?></p>
			<h3 class="passpress-reports-empty-title"><?php echo esc_html( $title ); ?></h3>
			<p class="passpress-reports-empty-desc"><?php echo esc_html( $desc ); ?></p>
		</div>
		<?php
	}

	private static function gateway_label( $gateway ) {
		$map = array(
			'offline' => __( 'Offline', 'passpress' ),
			'stripe'  => __( 'Stripe', 'passpress' ),
			'paypal'  => __( 'PayPal', 'passpress' ),
		);
		$key = strtolower( (string) $gateway );
		return isset( $map[ $key ] ) ? $map[ $key ] : ucfirst( (string) $gateway );
	}

	private static function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		if ( ! $parts ) {
			return '?';
		}
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		return strtoupper( $first . $last );
	}
}
