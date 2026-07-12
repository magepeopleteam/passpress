<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual/Offline gateway: no external API, works with zero configuration.
 * Two modes (see Billing Settings): auto-confirm (member is checked in
 * immediately — a "pay at the front desk / bank transfer, we trust it" flow)
 * or manual-confirm (stays pending until staff marks it paid from Billing
 * History, e.g. for bank-transfer businesses that need to see funds land
 * first). This is the gateway every other one is verified against, since it
 * needs no sandbox credentials to test end-to-end.
 */
class PP_Gateway_Offline implements PP_Gateway_Interface {

	public function id() {
		return 'offline';
	}

	public function label() {
		return __( 'Offline / Manual Payment', 'passpress' );
	}

	public function is_configured() {
		return true;
	}

	public function initiate( $billing_row, $plan, $user ) {
		$settings = PP_Billing::get_settings();

		if ( ! empty( $settings['offline_auto_confirm'] ) ) {
			PP_Billing::complete_payment( $billing_row->checkout_token, 'offline', 'manual-' . $billing_row->id, 'Auto-confirmed offline payment.' );
			return array( 'completed' => true );
		}

		PP_Activity_Logger::log( 'billing_pending_manual_confirmation', 'billing', $billing_row->id, sprintf( 'Awaiting manual confirmation for user #%d, plan #%d.', $user->ID, $plan->ID ) );

		return array( 'pending' => true );
	}
}
