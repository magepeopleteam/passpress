<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily/monthly attendance, peak-hour breakdown, and late class-arrivals —
 * all derived from pp_access_logs / pp_bookings, no independent data entry.
 * See PP_Attendance's docblock for why Early Exit isn't included.
 */
class PP_Attendance_Reports_Page {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) && ! current_user_can( PP_Roles::CAP_CLASSES ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$today      = current_time( 'Y-m-d' );
		$week_ago   = gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) );
		$month_ago  = gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) );
		$this_year  = (int) current_time( 'Y' );
		$this_month = (int) current_time( 'n' );

		$today_count   = PP_Attendance::get_daily_count( $today );
		$week_counts   = PP_Attendance::get_range_counts( $week_ago, $today );
		$month_total   = PP_Attendance::get_monthly_total( $this_year, $this_month );
		$peak_hours    = PP_Attendance::get_peak_hours( $month_ago, $today );
		$late_arrivals = PP_Attendance::get_late_class_arrivals( $month_ago, $today );
		$week_total    = array_sum( $week_counts );
		$week_max      = max( 1, max( $week_counts ) ?: 0 );
		$peak_values   = array_filter( $peak_hours );
		$peak_max      = max( 1, $peak_values ? max( $peak_values ) : 0 );
		?>
		<div class="wrap passpress-wrap passpress-attendance-page">
			<div class="passpress-attendance-page-header">
				<div class="passpress-attendance-page-copy">
					<p class="passpress-attendance-page-eyebrow"><?php esc_html_e( 'Insights', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Attendance', 'passpress' ); ?></h1>
					<p class="passpress-attendance-page-desc"><?php esc_html_e( 'Check-ins, peak hours, and late class arrivals from access logs and bookings.', 'passpress' ); ?></p>
				</div>
			</div>

			<div class="passpress-attendance-stat-row">
				<div class="passpress-attendance-stat">
					<span class="passpress-attendance-stat-label"><?php esc_html_e( "Today's check-ins", 'passpress' ); ?></span>
					<span class="passpress-attendance-stat-number is-today"><?php echo esc_html( number_format_i18n( $today_count ) ); ?></span>
				</div>
				<div class="passpress-attendance-stat">
					<span class="passpress-attendance-stat-label"><?php esc_html_e( 'Last 7 days', 'passpress' ); ?></span>
					<span class="passpress-attendance-stat-number"><?php echo esc_html( number_format_i18n( $week_total ) ); ?></span>
				</div>
				<div class="passpress-attendance-stat">
					<span class="passpress-attendance-stat-label"><?php esc_html_e( 'This month', 'passpress' ); ?></span>
					<span class="passpress-attendance-stat-number"><?php echo esc_html( number_format_i18n( $month_total ) ); ?></span>
				</div>
			</div>

			<div class="passpress-attendance-grid">
				<section class="passpress-attendance-card">
					<div class="passpress-attendance-card-header">
						<p class="passpress-attendance-card-eyebrow"><?php esc_html_e( 'Trend', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Daily attendance', 'passpress' ); ?></h2>
						<p class="passpress-attendance-card-desc"><?php esc_html_e( 'Last 7 days', 'passpress' ); ?></p>
					</div>
					<ul class="passpress-attendance-daily-list">
						<?php foreach ( $week_counts as $day => $count ) : ?>
							<li class="passpress-attendance-daily-row<?php echo $day === $today ? ' is-today' : ''; ?>">
								<div class="passpress-attendance-daily-meta">
									<strong><?php echo esc_html( pp_format_date( $day ) ); ?></strong>
									<span><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
								</div>
								<div class="passpress-attendance-bar-track" aria-hidden="true">
									<span class="passpress-attendance-bar-fill" style="width:<?php echo esc_attr( (string) round( ( $count / $week_max ) * 100 ) ); ?>%;"></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="passpress-attendance-card">
					<div class="passpress-attendance-card-header">
						<p class="passpress-attendance-card-eyebrow"><?php esc_html_e( 'Busy times', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Peak hours', 'passpress' ); ?></h2>
						<p class="passpress-attendance-card-desc"><?php esc_html_e( 'Last 30 days', 'passpress' ); ?></p>
					</div>
					<?php if ( ! $peak_values ) : ?>
						<div class="passpress-attendance-card-empty">
							<p><?php esc_html_e( 'No peak-hour data yet. Check-ins will appear here once members start scanning in.', 'passpress' ); ?></p>
						</div>
					<?php else : ?>
						<ul class="passpress-attendance-peak-list">
							<?php foreach ( $peak_hours as $hour => $count ) : ?>
								<?php if ( ! $count ) { continue; } ?>
								<li class="passpress-attendance-peak-row">
									<span class="passpress-attendance-peak-hour"><?php echo esc_html( sprintf( '%02d:00–%02d:00', $hour, ( $hour + 1 ) % 24 ) ); ?></span>
									<div class="passpress-attendance-bar-track" aria-hidden="true">
										<span class="passpress-attendance-bar-fill is-peak" style="width:<?php echo esc_attr( (string) round( ( $count / $peak_max ) * 100 ) ); ?>%;"></span>
									</div>
									<span class="passpress-attendance-peak-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>

			<section class="passpress-attendance-late">
				<div class="passpress-attendance-late-header">
					<div>
						<p class="passpress-attendance-card-eyebrow"><?php esc_html_e( 'Classes', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Late class arrivals', 'passpress' ); ?></h2>
						<p class="passpress-attendance-card-desc"><?php esc_html_e( 'Marked “Complete” after the scheduled start time. Early exit isn’t tracked — there’s no reliable checkout signal for classes.', 'passpress' ); ?></p>
					</div>
					<span class="passpress-attendance-late-range"><?php esc_html_e( 'Last 30 days', 'passpress' ); ?></span>
				</div>

				<?php if ( ! $late_arrivals ) : ?>
					<div class="passpress-attendance-empty">
						<p class="passpress-attendance-empty-eyebrow"><?php esc_html_e( 'On time', 'passpress' ); ?></p>
						<h3 class="passpress-attendance-empty-title"><?php esc_html_e( 'No late arrivals recorded', 'passpress' ); ?></h3>
						<p class="passpress-attendance-empty-desc"><?php esc_html_e( 'When staff complete a class booking after the start time, it will show up here.', 'passpress' ); ?></p>
					</div>
				<?php else : ?>
					<div class="passpress-attendance-table-wrap">
						<table class="passpress-attendance-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Class', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Scheduled', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Minutes late', 'passpress' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $late_arrivals as $row ) : ?>
									<?php
									$member = isset( $row->member_name ) ? $row->member_name : __( 'Unknown', 'passpress' );
									?>
									<tr>
										<td>
											<div class="passpress-attendance-when">
												<strong><?php echo esc_html( pp_format_date( $row->booking_date ) ); ?></strong>
											</div>
										</td>
										<td><span class="passpress-attendance-class"><?php echo esc_html( $row->class_title ); ?></span></td>
										<td>
											<div class="passpress-attendance-person">
												<span class="passpress-attendance-avatar"><?php echo esc_html( self::initials( $member ) ); ?></span>
												<strong><?php echo esc_html( $member ); ?></strong>
											</div>
										</td>
										<td><span class="passpress-attendance-scheduled"><?php echo esc_html( substr( $row->start_time, 0, 5 ) ); ?></span></td>
										<td>
											<span class="passpress-attendance-late-pill">
												<?php
												printf(
													/* translators: %d: minutes late */
													esc_html( _n( '%d min', '%d mins', (int) $row->late_minutes, 'passpress' ) ),
													(int) $row->late_minutes
												);
												?>
											</span>
										</td>
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
