<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every payment gateway implements. PP_Billing owns the checkout
 * page/orchestration; gateways only know how to start and confirm a single
 * charge for one billing_history row. `initiate()` never redirects/exits
 * itself — it returns an instruction and lets PP_Billing act on it, which
 * keeps every gateway independently unit-testable.
 */
interface PP_Gateway_Interface {

	/** @return string Unique gateway id, e.g. 'stripe'. */
	public function id();

	/** @return string Human-readable label shown at checkout. */
	public function label();

	/** @return bool Whether this gateway has the credentials it needs to run. */
	public function is_configured();

	/**
	 * Starts a charge for a pending billing_history row.
	 *
	 * @param object $billing_row Row from pp_billing_history.
	 * @param WP_Post $plan pp_membership_plan post.
	 * @param WP_User $user
	 * @return array{redirect?: string, completed?: bool, pending?: bool, error?: string}
	 *   redirect  - send the browser here (Stripe Checkout / PayPal approve link)
	 *   completed - payment confirmed synchronously, membership already issued/renewed
	 *   pending   - no redirect needed, but not yet paid (manual-confirm offline mode)
	 *   error     - show this message back on the checkout form
	 */
	public function initiate( $billing_row, $plan, $user );
}
