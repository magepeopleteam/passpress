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

		$rows = PP_Billing_History::get_recent( 100 );

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Billing History', 'passpress' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
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
						<th><?php esc_html_e( 'Actions', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No billing activity yet.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( pp_format_datetime( $row->created_at ) ); ?></td>
								<td><?php echo esc_html( self::user_name( $row->user_id ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $row->plan_id ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->type ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->gateway ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $row->amount, 2 ) . ' ' . strtoupper( $row->currency ) ); ?></td>
								<td><?php echo $row->coupon_code ? esc_html( $row->coupon_code . ' (-' . number_format_i18n( (float) $row->discount_amount, 2 ) . ')' ) : '—'; ?></td>
								<td><span class="passpress-badge passpress-badge-<?php echo esc_attr( self::status_badge_class( $row->status ) ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
								<td>
									<?php if ( PP_Billing_History::STATUS_PENDING === $row->status && 'offline' === $row->gateway ) : ?>
										<?php self::render_pending_actions( $row ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function status_badge_class( $status ) {
		if ( PP_Billing_History::STATUS_PAID === $status ) {
			return 'active';
		}
		if ( in_array( $status, array( PP_Billing_History::STATUS_FAILED, PP_Billing_History::STATUS_CANCELLED ), true ) ) {
			return 'expired';
		}
		return 'frozen';
	}

	private static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function render_pending_actions( $row ) {
		$confirm_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'          => 'passpress-billing-history',
					'pp_bh_action'  => 'confirm',
					'id'            => $row->id,
				),
				admin_url( 'admin.php' )
			),
			'pp_billing_history_action_' . $row->id
		);
		$fail_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'          => 'passpress-billing-history',
					'pp_bh_action'  => 'fail',
					'id'            => $row->id,
				),
				admin_url( 'admin.php' )
			),
			'pp_billing_history_action_' . $row->id
		);
		?>
		<a href="<?php echo esc_url( $confirm_url ); ?>"><?php esc_html_e( 'Mark as Paid', 'passpress' ); ?></a> |
		<a href="<?php echo esc_url( $fail_url ); ?>"><?php esc_html_e( 'Mark as Failed', 'passpress' ); ?></a>
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
