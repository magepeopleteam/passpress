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

		$peak_max = max( 1, max( $peak_hours ) );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Attendance', 'passpress' ); ?></h1>

			<div class="passpress-stat-tiles">
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $today_count ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( "Today's Check-ins", 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( array_sum( $week_counts ) ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Last 7 Days', 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $month_total ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'This Month', 'passpress' ); ?></span>
				</div>
			</div>

			<h2><?php esc_html_e( 'Daily Attendance — Last 7 Days', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped" style="max-width:500px;">
				<thead><tr><th><?php esc_html_e( 'Date', 'passpress' ); ?></th><th><?php esc_html_e( 'Check-ins', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $week_counts as $day => $count ) : ?>
						<tr>
							<td><?php echo esc_html( pp_format_date( $day ) ); ?></td>
							<td><?php echo esc_html( $count ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Peak Hours — Last 30 Days', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped passpress-peak-hours" style="max-width:600px;">
				<thead><tr><th><?php esc_html_e( 'Hour', 'passpress' ); ?></th><th><?php esc_html_e( 'Check-ins', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $peak_hours as $hour => $count ) : if ( ! $count ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( sprintf( '%02d:00–%02d:00', $hour, ( $hour + 1 ) % 24 ) ); ?></td>
							<td>
								<div class="passpress-peak-bar-wrap">
									<div class="passpress-peak-bar" style="width:<?php echo esc_attr( round( ( $count / $peak_max ) * 100 ) ); ?>%;"></div>
									<span><?php echo esc_html( $count ); ?></span>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Late Class Arrivals — Last 30 Days', 'passpress' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Marked "Complete" after the scheduled start time. Early Exit isn\'t tracked — there\'s no reliable checkout signal for classes today.', 'passpress' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Class', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Scheduled', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Minutes Late', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $late_arrivals ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No late arrivals recorded.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $late_arrivals as $row ) : ?>
							<tr>
								<td><?php echo esc_html( pp_format_date( $row->booking_date ) ); ?></td>
								<td><?php echo esc_html( $row->class_title ); ?></td>
								<td><?php echo esc_html( $row->member_name ); ?></td>
								<td><?php echo esc_html( substr( $row->start_time, 0, 5 ) ); ?></td>
								<td><?php echo esc_html( $row->late_minutes ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
