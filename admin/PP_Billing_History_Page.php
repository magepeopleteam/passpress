<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-mostly ledger of every checkout attempt. The one write action is
 * confirming a pending Offline/Manual payment (or marking one failed) —
 * everything else (Stripe/PayPal) is confirmed automatically by the
 * gateway's return/webhook handlers.
 */
class PP_Billing_History_Page {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		self::maybe_handle_actions();

		$rows   = PP_Billing_History::get_recent( 100 );
		$counts = self::status_counts( $rows );

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-billing-page">
			<div class="passpress-billing-page-header">
				<div class="passpress-billing-page-copy">
					<p class="passpress-billing-page-eyebrow"><?php esc_html_e( 'Payments', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Billing History', 'passpress' ); ?></h1>
					<p class="passpress-billing-page-desc">
						<?php
						printf(
							/* translators: %d: number of billing rows shown */
							esc_html( _n( 'Showing %d recent transaction', 'Showing %d recent transactions', count( $rows ), 'passpress' ) ),
							count( $rows )
						);
						?>
					</p>
				</div>
			</div>

			<div class="passpress-billing-stat-row">
				<div class="passpress-billing-stat">
					<span class="passpress-billing-stat-label"><?php esc_html_e( 'Paid', 'passpress' ); ?></span>
					<span class="passpress-billing-stat-number is-paid"><?php echo esc_html( number_format_i18n( $counts['paid'] ) ); ?></span>
				</div>
				<div class="passpress-billing-stat">
					<span class="passpress-billing-stat-label"><?php esc_html_e( 'Pending', 'passpress' ); ?></span>
					<span class="passpress-billing-stat-number is-pending"><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></span>
				</div>
				<div class="passpress-billing-stat">
					<span class="passpress-billing-stat-label"><?php esc_html_e( 'Failed', 'passpress' ); ?></span>
					<span class="passpress-billing-stat-number is-failed"><?php echo esc_html( number_format_i18n( $counts['failed'] ) ); ?></span>
				</div>
				<div class="passpress-billing-stat">
					<span class="passpress-billing-stat-label"><?php esc_html_e( 'Other', 'passpress' ); ?></span>
					<span class="passpress-billing-stat-number"><?php echo esc_html( number_format_i18n( $counts['other'] ) ); ?></span>
				</div>
			</div>

			<?php if ( ! $rows ) : ?>
				<div class="passpress-billing-empty">
					<p class="passpress-billing-empty-eyebrow"><?php esc_html_e( 'Ledger', 'passpress' ); ?></p>
					<h2 class="passpress-billing-empty-title"><?php esc_html_e( 'No billing activity yet', 'passpress' ); ?></h2>
					<p class="passpress-billing-empty-desc"><?php esc_html_e( 'Checkout attempts will show up here once members start purchasing plans.', 'passpress' ); ?></p>
				</div>
			<?php else : ?>
				<div class="passpress-billing-table-wrap">
					<table class="passpress-billing-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Type', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Gateway', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Coupon', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
								<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'passpress' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$user  = get_userdata( $row->user_id );
								$name  = $user ? $user->display_name : __( 'Unknown', 'passpress' );
								$email = $user ? $user->user_email : '';
								?>
								<tr>
									<td>
										<div class="passpress-billing-date">
											<strong><?php echo esc_html( pp_format_date( $row->created_at ) ); ?></strong>
											<span><?php echo esc_html( self::format_time( $row->created_at ) ); ?></span>
										</div>
									</td>
									<td>
										<div class="passpress-billing-member">
											<span class="passpress-billing-avatar"><?php echo esc_html( self::initials( $name ) ); ?></span>
											<span class="passpress-billing-member-info">
												<strong><?php echo esc_html( $name ); ?></strong>
												<?php if ( $email ) : ?>
													<span><?php echo esc_html( $email ); ?></span>
												<?php endif; ?>
											</span>
										</div>
									</td>
									<td><span class="passpress-billing-plan"><?php echo esc_html( get_the_title( $row->plan_id ) ); ?></span></td>
									<td><span class="passpress-billing-meta"><?php echo esc_html( ucfirst( $row->type ) ); ?></span></td>
									<td><span class="passpress-billing-gateway passpress-billing-gateway-<?php echo esc_attr( sanitize_html_class( $row->gateway ) ); ?>"><?php echo esc_html( self::gateway_label( $row->gateway ) ); ?></span></td>
									<td>
										<div class="passpress-billing-amount">
											<strong><?php echo esc_html( number_format_i18n( (float) $row->amount, 2 ) ); ?></strong>
											<span><?php echo esc_html( strtoupper( $row->currency ) ); ?></span>
										</div>
									</td>
									<td>
										<?php if ( $row->coupon_code ) : ?>
											<span class="passpress-billing-coupon">
												<?php echo esc_html( $row->coupon_code ); ?>
												<em>−<?php echo esc_html( number_format_i18n( (float) $row->discount_amount, 2 ) ); ?></em>
											</span>
										<?php else : ?>
											<span class="passpress-billing-empty-cell">—</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="passpress-billing-status passpress-billing-status-<?php echo esc_attr( sanitize_html_class( $row->status ) ); ?>">
											<span class="passpress-billing-status-dot"></span>
											<?php echo esc_html( ucfirst( $row->status ) ); ?>
										</span>
									</td>
									<td class="passpress-billing-actions-cell">
										<?php if ( PP_Billing_History::STATUS_PENDING === $row->status && 'offline' === $row->gateway ) : ?>
											<?php self::render_pending_actions( $row ); ?>
										<?php else : ?>
											<span class="passpress-billing-empty-cell">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param object[] $rows
	 * @return array{paid:int,pending:int,failed:int,other:int}
	 */
	private static function status_counts( $rows ) {
		$counts = array(
			'paid'    => 0,
			'pending' => 0,
			'failed'  => 0,
			'other'   => 0,
		);
		foreach ( $rows as $row ) {
			if ( PP_Billing_History::STATUS_PAID === $row->status ) {
				++$counts['paid'];
			} elseif ( PP_Billing_History::STATUS_PENDING === $row->status ) {
				++$counts['pending'];
			} elseif ( PP_Billing_History::STATUS_FAILED === $row->status ) {
				++$counts['failed'];
			} else {
				++$counts['other'];
			}
		}
		return $counts;
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

	private static function format_time( $mysql_datetime ) {
		$ts = strtotime( $mysql_datetime );
		if ( ! $ts ) {
			return '';
		}
		return date_i18n( get_option( 'time_format' ), $ts );
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

	private static function render_pending_actions( $row ) {
		$confirm_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => 'passpress-billing-history',
					'pp_bh_action' => 'confirm',
					'id'           => $row->id,
				),
				admin_url( 'admin.php' )
			),
			'pp_billing_history_action_' . $row->id
		);
		$fail_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => 'passpress-billing-history',
					'pp_bh_action' => 'fail',
					'id'           => $row->id,
				),
				admin_url( 'admin.php' )
			),
			'pp_billing_history_action_' . $row->id
		);
		?>
		<div class="passpress-billing-actions">
			<a class="passpress-billing-action is-confirm" href="<?php echo esc_url( $confirm_url ); ?>"><?php esc_html_e( 'Mark paid', 'passpress' ); ?></a>
			<a class="passpress-billing-action is-fail" href="<?php echo esc_url( $fail_url ); ?>"><?php esc_html_e( 'Mark failed', 'passpress' ); ?></a>
		</div>
		<?php
	}

	private static function maybe_handle_actions() {
		if ( ! isset( $_GET['pp_bh_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$id     = absint( $_GET['id'] );
		$action = sanitize_key( $_GET['pp_bh_action'] );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'pp_billing_history_action_' . $id ) ) {
			return;
		}

		$row = PP_Billing_History::get( $id );
		if ( ! $row || PP_Billing_History::STATUS_PENDING !== $row->status ) {
			return;
		}

		if ( 'confirm' === $action ) {
			PP_Billing::complete_payment( $row->checkout_token, 'offline', 'manual-admin-confirm-' . get_current_user_id(), 'Manually confirmed by staff.' );
			add_settings_error( 'passpress', 'pp_bh_done', __( 'Payment confirmed and membership issued.', 'passpress' ), 'success' );
		} elseif ( 'fail' === $action ) {
			$reason = 'Manually marked failed by staff.';
			PP_Billing_History::mark_failed( $id, $reason );
			PP_Notifications::payment_failed( $row, $reason );
			add_settings_error( 'passpress', 'pp_bh_done', __( 'Payment marked as failed.', 'passpress' ), 'success' );
		}
	}
}
