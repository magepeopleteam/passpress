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

		$revenue     = PP_Reports::get_revenue( $start_date, $end_date );
		$growth      = PP_Reports::get_membership_growth( $start_date, $end_date );
		$expired     = PP_Reports::get_expired_members( 20 );
		$renewal     = PP_Reports::get_renewal_rate( $start_date, $end_date );
		$facilities  = PP_Reports::get_facility_usage( $start_date, $end_date );
		$plans       = PP_Reports::get_popular_plans();
		$payments    = PP_Reports::get_payment_report( $start_date, $end_date );
		$instructors = PP_Reports::get_trainer_performance( $start_date, $end_date );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Reports', 'passpress' ); ?></h1>

			<form method="get" style="margin-bottom:16px;">
				<input type="hidden" name="page" value="passpress-reports">
				<label><?php esc_html_e( 'From', 'passpress' ); ?> <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>"></label>
				<label><?php esc_html_e( 'To', 'passpress' ); ?> <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>"></label>
				<?php submit_button( __( 'Update', 'passpress' ), '', '', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-attendance' ) ); ?>"><?php esc_html_e( 'Peak Hours →', 'passpress' ); ?></a>
			</form>

			<div class="passpress-stat-tiles">
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $revenue['total'], 2 ) ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Revenue', 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $growth['total'] ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'New Members', 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( null === $renewal['rate_percent'] ? '—' : $renewal['rate_percent'] . '%' ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Renewal Rate', 'passpress' ); ?></span>
				</div>
			</div>

			<h2><?php esc_html_e( 'Revenue by Day', 'passpress' ); ?></h2>
			<?php self::render_bar_table( $revenue['by_day'], function ( $v ) use ( $settings ) { return $settings['currency_symbol'] . number_format_i18n( $v, 2 ); } ); ?>

			<h2><?php esc_html_e( 'Membership Growth', 'passpress' ); ?></h2>
			<?php self::render_bar_table( $growth['by_day'] ); ?>

			<h2><?php esc_html_e( 'Renewal Rate', 'passpress' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: renewed count, 2: lapsed count */
					esc_html__( '%1$d renewed, %2$d lapsed without renewing in this window.', 'passpress' ),
					(int) $renewal['renewed'],
					(int) $renewal['lapsed']
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Expired Members', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Member', 'passpress' ); ?></th><th><?php esc_html_e( 'Plan', 'passpress' ); ?></th><th><?php esc_html_e( 'Expired On', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $expired ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No expired members.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $expired as $m ) : $user = get_userdata( $m->user_id ); ?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'passpress' ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $m->plan_id ) ); ?></td>
								<td><?php echo esc_html( pp_format_date( $m->expiry_date ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Facility Usage', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Facility', 'passpress' ); ?></th><th><?php esc_html_e( 'Bookings', 'passpress' ); ?></th><th><?php esc_html_e( 'Entries', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $facilities ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No activity in this window.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $facilities as $f ) : ?>
							<tr><td><?php echo esc_html( $f['name'] ); ?></td><td><?php echo esc_html( $f['bookings'] ); ?></td><td><?php echo esc_html( $f['entries'] ); ?></td></tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Popular Plans', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Plan', 'passpress' ); ?></th><th><?php esc_html_e( 'Active/Total Members', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $plans ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No memberships yet.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $plans as $p ) : ?>
							<tr><td><?php echo esc_html( $p['name'] ); ?></td><td><?php echo esc_html( $p['count'] ); ?></td></tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Payment Reports', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Gateway', 'passpress' ); ?></th><th><?php esc_html_e( 'Status', 'passpress' ); ?></th><th><?php esc_html_e( 'Count', 'passpress' ); ?></th><th><?php esc_html_e( 'Total', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $payments ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No payment activity in this window.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $payments as $gateway => $statuses ) : ?>
							<?php foreach ( $statuses as $status => $data ) : ?>
								<tr>
									<td><?php echo esc_html( ucfirst( $gateway ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
									<td><?php echo esc_html( $data['count'] ); ?></td>
									<td><?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $data['total'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Trainer Performance', 'passpress' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Instructor', 'passpress' ); ?></th><th><?php esc_html_e( 'Classes', 'passpress' ); ?></th><th><?php esc_html_e( 'Bookings', 'passpress' ); ?></th><th><?php esc_html_e( 'Attended', 'passpress' ); ?></th><th><?php esc_html_e( 'No-shows', 'passpress' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $instructors ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No instructor-assigned classes with activity in this window.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $instructors as $data ) : ?>
							<tr>
								<td><?php echo esc_html( $data['name'] ); ?></td>
								<td><?php echo esc_html( $data['classes'] ); ?></td>
								<td><?php echo esc_html( $data['total_bookings'] ); ?></td>
								<td><?php echo esc_html( $data['attended'] ); ?></td>
								<td><?php echo esc_html( $data['no_shows'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_bar_table( $by_day, $format_callback = null ) {
		if ( ! $by_day ) {
			echo '<p>' . esc_html__( 'No data in this window.', 'passpress' ) . '</p>';
			return;
		}
		$max = max( 1, max( $by_day ) );
		echo '<table class="wp-list-table widefat fixed striped passpress-peak-hours" style="max-width:600px;"><tbody>';
		foreach ( $by_day as $day => $value ) {
			$display = $format_callback ? $format_callback( $value ) : $value;
			echo '<tr><td style="width:110px;">' . esc_html( pp_format_date( $day ) ) . '</td><td>';
			echo '<div class="passpress-peak-bar-wrap"><div class="passpress-peak-bar" style="width:' . esc_attr( round( ( $value / $max ) * 100 ) ) . '%;"></div><span>' . esc_html( $display ) . '</span></div>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
