<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PassPress home screen: KPIs, quick actions, setup guidance, and recent activity.
 * Gate Operators see a focused Scan Gate landing instead of manage stats.
 */
class PP_Dashboard {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			self::render_gate_operator_view();
			return;
		}

		$stats         = PP_Query::dashboard_stats();
		$activity      = PP_Activity_Logger::get_recent( 8 );
		$roadmap       = class_exists( 'PP_Business_Templates' ) ? PP_Business_Templates::get_roadmap() : array();
		$active_type   = get_option( 'passpress_active_business_type', '' );
		$active_label  = ( $active_type && isset( $roadmap[ $active_type ] ) ) ? $roadmap[ $active_type ] : '';
		$plans_count   = self::count_plans();
		$needs_setup   = empty( $active_type );
		$notice        = get_transient( 'passpress_setup_notice' );
		if ( $notice ) {
			delete_transient( 'passpress_setup_notice' );
		}
		?>
		<div class="wrap passpress-wrap passpress-dashboard-page">
			<div class="passpress-dashboard-page-header">
				<div class="passpress-dashboard-page-copy">
					<p class="passpress-dashboard-page-eyebrow"><?php esc_html_e( 'Overview', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Dashboard', 'passpress' ); ?></h1>
					<p class="passpress-dashboard-page-desc">
						<?php
						if ( $active_label ) {
							printf(
								/* translators: %s: business type label */
								esc_html__( 'Running as %s — memberships, check-ins, and what’s next.', 'passpress' ),
								esc_html( $active_label )
							);
						} else {
							esc_html_e( 'Membership health, today’s attendance, and shortcuts to run the front desk.', 'passpress' );
						}
						?>
					</p>
				</div>
				<div class="passpress-dashboard-header-actions">
					<a class="passpress-dashboard-primary-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-scan-gate' ) ); ?>">
						<span class="dashicons dashicons-id" aria-hidden="true"></span>
						<?php esc_html_e( 'Open Scan Gate', 'passpress' ); ?>
					</a>
					<a class="passpress-dashboard-secondary-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-memberships' ) ); ?>">
						<?php esc_html_e( 'Manage members', 'passpress' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $notice ) : ?>
				<div class="passpress-dashboard-flash is-success">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $needs_setup ) : ?>
				<div class="passpress-dashboard-setup-cta">
					<div class="passpress-dashboard-setup-copy">
						<p class="passpress-dashboard-setup-eyebrow"><?php esc_html_e( 'Get started', 'passpress' ); ?></p>
						<strong><?php esc_html_e( 'Finish setup with a business template', 'passpress' ); ?></strong>
						<span><?php esc_html_e( 'Import sample plans, facilities, and pages so you can start selling and scanning sooner.', 'passpress' ); ?></span>
					</div>
					<a class="pp-btn-solid" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-setup' ) ); ?>">
						<?php esc_html_e( 'Open Setup Wizard', 'passpress' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="passpress-dashboard-kpis">
				<a class="passpress-dashboard-kpi is-active" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-memberships&status=active' ) ); ?>">
					<span class="passpress-dashboard-kpi-icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></span>
					<span class="passpress-dashboard-kpi-meta">
						<span class="passpress-dashboard-kpi-label"><?php esc_html_e( 'Active members', 'passpress' ); ?></span>
						<strong class="passpress-dashboard-kpi-value"><?php echo esc_html( (string) $stats['active_memberships'] ); ?></strong>
						<span class="passpress-dashboard-kpi-hint"><?php esc_html_e( 'View active list', 'passpress' ); ?></span>
					</span>
				</a>
				<a class="passpress-dashboard-kpi is-warn" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-memberships&status=active' ) ); ?>">
					<span class="passpress-dashboard-kpi-icon"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></span>
					<span class="passpress-dashboard-kpi-meta">
						<span class="passpress-dashboard-kpi-label"><?php esc_html_e( 'Expiring in 7 days', 'passpress' ); ?></span>
						<strong class="passpress-dashboard-kpi-value"><?php echo esc_html( (string) $stats['expiring_soon'] ); ?></strong>
						<span class="passpress-dashboard-kpi-hint"><?php esc_html_e( 'Renew or follow up', 'passpress' ); ?></span>
					</span>
				</a>
				<a class="passpress-dashboard-kpi is-checkin" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-attendance' ) ); ?>">
					<span class="passpress-dashboard-kpi-icon"><span class="dashicons dashicons-yes" aria-hidden="true"></span></span>
					<span class="passpress-dashboard-kpi-meta">
						<span class="passpress-dashboard-kpi-label"><?php esc_html_e( "Today's check-ins", 'passpress' ); ?></span>
						<strong class="passpress-dashboard-kpi-value"><?php echo esc_html( (string) $stats['todays_checkins'] ); ?></strong>
						<span class="passpress-dashboard-kpi-hint"><?php esc_html_e( 'Attendance reports', 'passpress' ); ?></span>
					</span>
				</a>
				<a class="passpress-dashboard-kpi is-muted" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-memberships' ) ); ?>">
					<span class="passpress-dashboard-kpi-icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></span>
					<span class="passpress-dashboard-kpi-meta">
						<span class="passpress-dashboard-kpi-label"><?php esc_html_e( 'Frozen / suspended', 'passpress' ); ?></span>
						<strong class="passpress-dashboard-kpi-value"><?php echo esc_html( (string) $stats['frozen_suspended'] ); ?></strong>
						<span class="passpress-dashboard-kpi-hint"><?php esc_html_e( 'Review holds', 'passpress' ); ?></span>
					</span>
				</a>
			</div>

			<div class="passpress-dashboard-grid">
				<section class="passpress-dashboard-panel">
					<header class="passpress-dashboard-panel-head">
						<div>
							<p class="passpress-dashboard-panel-eyebrow"><?php esc_html_e( 'Shortcuts', 'passpress' ); ?></p>
							<h2><?php esc_html_e( 'Quick actions', 'passpress' ); ?></h2>
						</div>
					</header>
					<div class="passpress-dashboard-actions">
						<?php
						$actions = array(
							array(
								'href'  => admin_url( 'admin.php?page=passpress-memberships' ),
								'icon'  => 'dashicons-id-alt',
								'title' => __( 'Issue or manage memberships', 'passpress' ),
								'desc'  => __( 'Front-desk walk-ins and renewals', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-plans' ),
								'icon'  => 'dashicons-tickets-alt',
								'title' => __( 'Membership plans', 'passpress' ),
								'desc'  => sprintf(
									/* translators: %d: number of plans */
									_n( '%d plan in catalog', '%d plans in catalog', $plans_count, 'passpress' ),
									$plans_count
								),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-bookings' ),
								'icon'  => 'dashicons-calendar',
								'title' => __( 'Bookings', 'passpress' ),
								'desc'  => __( 'Facility slots and class sessions', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-visitors' ),
								'icon'  => 'dashicons-admin-users',
								'title' => __( 'Visitors', 'passpress' ),
								'desc'  => __( 'Day passes and guest invites', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-coupons' ),
								'icon'  => 'dashicons-tag',
								'title' => __( 'Coupons', 'passpress' ),
								'desc'  => __( 'Promo codes for checkout', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-reports' ),
								'icon'  => 'dashicons-chart-area',
								'title' => __( 'Reports', 'passpress' ),
								'desc'  => __( 'Revenue, growth, and usage', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-settings' ),
								'icon'  => 'dashicons-admin-generic',
								'title' => __( 'Settings', 'passpress' ),
								'desc'  => __( 'Currency, payments, emails', 'passpress' ),
							),
							array(
								'href'  => admin_url( 'admin.php?page=passpress-setup' ),
								'icon'  => 'dashicons-admin-home',
								'title' => __( 'Setup Wizard', 'passpress' ),
								'desc'  => $active_label
									? sprintf(
										/* translators: %s: business type */
										__( 'Business type: %s', 'passpress' ),
										$active_label
									)
									: __( 'Import a business template', 'passpress' ),
							),
						);
						foreach ( $actions as $action ) :
							?>
							<a class="passpress-dashboard-action" href="<?php echo esc_url( $action['href'] ); ?>">
								<span class="passpress-dashboard-action-icon">
									<span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>" aria-hidden="true"></span>
								</span>
								<span class="passpress-dashboard-action-copy">
									<strong><?php echo esc_html( $action['title'] ); ?></strong>
									<em><?php echo esc_html( $action['desc'] ); ?></em>
								</span>
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="passpress-dashboard-panel">
					<header class="passpress-dashboard-panel-head">
						<div>
							<p class="passpress-dashboard-panel-eyebrow"><?php esc_html_e( 'Live', 'passpress' ); ?></p>
							<h2><?php esc_html_e( 'Recent activity', 'passpress' ); ?></h2>
						</div>
						<a class="passpress-dashboard-panel-link" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-activity-log' ) ); ?>">
							<?php esc_html_e( 'View all', 'passpress' ); ?>
						</a>
					</header>

					<?php if ( ! $activity ) : ?>
						<div class="passpress-dashboard-empty">
							<p class="passpress-dashboard-empty-eyebrow"><?php esc_html_e( 'Nothing yet', 'passpress' ); ?></p>
							<strong><?php esc_html_e( 'Activity will show up here', 'passpress' ); ?></strong>
							<span><?php esc_html_e( 'Issuing passes, scans, bookings, and payments all land in this feed.', 'passpress' ); ?></span>
						</div>
					<?php else : ?>
						<ul class="passpress-dashboard-activity">
							<?php foreach ( $activity as $row ) : ?>
								<li>
									<span class="passpress-dashboard-activity-dot" aria-hidden="true"></span>
									<div class="passpress-dashboard-activity-body">
										<strong><?php echo esc_html( self::format_activity_message( $row ) ); ?></strong>
										<span>
											<?php echo esc_html( self::format_activity_meta( $row ) ); ?>
										</span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_gate_operator_view() {
		?>
		<div class="wrap passpress-wrap passpress-dashboard-page passpress-dashboard-gate">
			<div class="passpress-dashboard-page-header">
				<div class="passpress-dashboard-page-copy">
					<p class="passpress-dashboard-page-eyebrow"><?php esc_html_e( 'Front desk', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'PassPress', 'passpress' ); ?></h1>
					<p class="passpress-dashboard-page-desc"><?php esc_html_e( 'Check members and visitors in or out with QR or PIN.', 'passpress' ); ?></p>
				</div>
			</div>
			<div class="passpress-dashboard-gate-card">
				<span class="passpress-dashboard-gate-icon"><span class="dashicons dashicons-id" aria-hidden="true"></span></span>
				<div>
					<strong><?php esc_html_e( 'Ready when you are', 'passpress' ); ?></strong>
					<p><?php esc_html_e( 'Open Scan Gate to validate passes at the door.', 'passpress' ); ?></p>
				</div>
				<a class="pp-btn-solid" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress-scan-gate' ) ); ?>">
					<?php esc_html_e( 'Go to Scan Gate', 'passpress' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private static function count_plans() {
		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'fields'         => 'ids',
			)
		);
		return is_array( $plans ) ? count( $plans ) : 0;
	}

	/**
	 * @param object $row Activity log row.
	 */
	private static function format_activity_message( $row ) {
		if ( ! empty( $row->message ) ) {
			return $row->message;
		}
		$event = isset( $row->event ) ? $row->event : '';
		return $event ? ucwords( str_replace( '_', ' ', $event ) ) : __( 'Activity', 'passpress' );
	}

	/**
	 * @param object $row Activity log row.
	 */
	private static function format_activity_meta( $row ) {
		$parts = array();
		if ( ! empty( $row->event ) ) {
			$parts[] = ucwords( str_replace( '_', ' ', $row->event ) );
		}
		if ( ! empty( $row->created_at ) ) {
			$parts[] = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at );
		}
		return implode( ' · ', $parts );
	}
}
