<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Dashboard {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			self::render_gate_operator_view();
			return;
		}

		$stats = PP_Query::dashboard_stats();
		$notice = get_transient( 'passpress_setup_notice' );
		if ( $notice ) {
			delete_transient( 'passpress_setup_notice' );
		}
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'PassPress Dashboard', 'passpress' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<div class="passpress-stat-tiles">
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $stats['active_memberships'] ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Active Memberships', 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $stats['expiring_soon'] ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Expiring in 7 Days', 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $stats['todays_checkins'] ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( "Today's Check-ins", 'passpress' ); ?></span>
				</div>
				<div class="passpress-stat-tile">
					<span class="passpress-stat-number"><?php echo esc_html( $stats['frozen_suspended'] ); ?></span>
					<span class="passpress-stat-label"><?php esc_html_e( 'Frozen / Suspended', 'passpress' ); ?></span>
				</div>
			</div>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-memberships' ) ); ?>"><?php esc_html_e( 'Manage Memberships', 'passpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-scan-gate' ) ); ?>"><?php esc_html_e( 'Go to Scan Gate', 'passpress' ); ?></a>
				<?php if ( ! get_option( 'passpress_active_business_type' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-setup' ) ); ?>"><?php esc_html_e( 'Run Setup Wizard', 'passpress' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	private static function render_gate_operator_view() {
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'PassPress', 'passpress' ); ?></h1>
			<p><?php esc_html_e( 'Welcome! Use Scan Gate to check members in and out.', 'passpress' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-scan-gate' ) ); ?>"><?php esc_html_e( 'Go to Scan Gate', 'passpress' ); ?></a></p>
		</div>
		<?php
	}
}
