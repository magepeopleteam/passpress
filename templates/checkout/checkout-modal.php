<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Get this Pass modal — used for both Custom Payment and WooCommerce.
 *
 * @var array  $gateways PP_Gateway_Interface[] (native mode only)
 * @var array  $settings
 * @var string $payment_mode 'native'|'woocommerce'
 */
$gateways     = isset( $gateways ) ? $gateways : array();
$settings     = isset( $settings ) ? $settings : pp_get_settings();
$payment_mode = isset( $payment_mode ) ? $payment_mode : ( PP_Billing::is_woocommerce_mode() ? 'woocommerce' : 'native' );
$user         = is_user_logged_in() ? wp_get_current_user() : null;
$symbol       = $settings['currency_symbol'];
$default_gateway = $gateways ? array_key_first( $gateways ) : 'offline';
$phone        = $user ? (string) get_user_meta( $user->ID, 'billing_phone', true ) : '';
$address      = $user ? (string) get_user_meta( $user->ID, 'billing_address_1', true ) : '';
?>
<div
	id="passpress-checkout-modal"
	class="passpress-checkout-modal-overlay"
	data-payment-mode="<?php echo esc_attr( $payment_mode ); ?>"
	hidden
>
	<div class="passpress-checkout-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-checkout-modal-title">
		<button type="button" class="passpress-checkout-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>

		<header class="passpress-checkout-modal-header">
			<h2 id="passpress-checkout-modal-title"><?php esc_html_e( 'Get this pass', 'passpress' ); ?></h2>
			<p class="passpress-checkout-modal-subtitle"><?php esc_html_e( "You're one step from your membership.", 'passpress' ); ?></p>
		</header>

		<div class="passpress-checkout-modal-notice" hidden role="alert"></div>

		<!-- Step 1: registration / payment details -->
		<div class="passpress-checkout-modal-step" id="pp_modal_step_form" data-step="form">
			<form id="passpress-checkout-modal-form" class="passpress-checkout-modal-form" novalidate>
				<input type="hidden" name="plan_id" id="pp_modal_plan_id" value="">
				<input type="hidden" name="renew" id="pp_modal_renew_id" value="0">
				<input type="hidden" name="nonce" id="pp_modal_nonce" value="">
				<input type="hidden" name="gateway" id="pp_modal_gateway" value="<?php echo esc_attr( $default_gateway ); ?>">
				<input type="hidden" name="payment_mode" id="pp_modal_payment_mode" value="<?php echo esc_attr( $payment_mode ); ?>">

				<div class="passpress-checkout-summary passpress-checkout-summary-top">
					<div class="passpress-checkout-summary-row">
						<span class="passpress-checkout-summary-plan" id="pp_modal_plan_name"><?php esc_html_e( 'Pass', 'passpress' ); ?></span>
						<span class="passpress-checkout-summary-price" id="pp_modal_plan_price"><?php echo esc_html( $symbol ); ?>0.00</span>
					</div>
					<div class="passpress-checkout-summary-row passpress-checkout-summary-discount" id="pp_modal_discount_row" hidden>
						<span><?php esc_html_e( 'Discount', 'passpress' ); ?></span>
						<span id="pp_modal_discount_amount">−<?php echo esc_html( $symbol ); ?>0.00</span>
					</div>
					<hr class="passpress-checkout-summary-rule">
					<div class="passpress-checkout-summary-row passpress-checkout-summary-total">
						<span><?php esc_html_e( 'Total', 'passpress' ); ?></span>
						<span id="pp_modal_total"><?php echo esc_html( $symbol ); ?>0.00</span>
					</div>
				</div>

				<div class="passpress-checkout-field">
					<label for="pp_modal_coupon"><?php esc_html_e( 'Coupon code', 'passpress' ); ?></label>
					<div class="passpress-checkout-coupon-row">
						<input type="text" id="pp_modal_coupon" name="coupon_code" placeholder="<?php esc_attr_e( 'WELCOME10', 'passpress' ); ?>" autocomplete="off">
						<button type="button" class="passpress-checkout-coupon-apply" id="pp_modal_coupon_apply"><?php esc_html_e( 'Apply', 'passpress' ); ?></button>
					</div>
					<p class="passpress-checkout-coupon-msg" id="pp_modal_coupon_msg" hidden></p>
				</div>

				<div class="passpress-checkout-section">
					<h3 class="passpress-checkout-section-title"><?php esc_html_e( 'Membership Information', 'passpress' ); ?></h3>

					<div class="passpress-checkout-field">
						<label for="pp_modal_full_name"><?php esc_html_e( 'Full Name', 'passpress' ); ?></label>
						<input type="text" id="pp_modal_full_name" name="full_name" placeholder="<?php esc_attr_e( 'Alex Fernandez', 'passpress' ); ?>" value="<?php echo $user ? esc_attr( $user->display_name ) : ''; ?>" required>
					</div>

					<div class="passpress-checkout-field">
						<label for="pp_modal_phone"><?php esc_html_e( 'Phone Number', 'passpress' ); ?></label>
						<input type="tel" id="pp_modal_phone" name="phone" placeholder="<?php esc_attr_e( '+1 555 0100', 'passpress' ); ?>" value="<?php echo esc_attr( $phone ); ?>" required>
					</div>

					<div class="passpress-checkout-field">
						<label for="pp_modal_email"><?php esc_html_e( 'Email', 'passpress' ); ?></label>
						<input type="email" id="pp_modal_email" name="email" placeholder="<?php esc_attr_e( 'alex@email.com', 'passpress' ); ?>" value="<?php echo $user ? esc_attr( $user->user_email ) : ''; ?>" required>
					</div>

					<div class="passpress-checkout-field">
						<label for="pp_modal_address"><?php esc_html_e( 'Address', 'passpress' ); ?></label>
						<input type="text" id="pp_modal_address" name="address" placeholder="<?php esc_attr_e( 'Street, city, postal code', 'passpress' ); ?>" value="<?php echo esc_attr( $address ); ?>" required>
					</div>
				</div>

				<?php if ( 'native' === $payment_mode && ! empty( $gateways ) ) : ?>
					<div class="passpress-checkout-field passpress-checkout-native-only" id="pp_modal_payment_methods">
						<span class="passpress-checkout-label"><?php esc_html_e( 'Payment method', 'passpress' ); ?></span>
						<div class="passpress-checkout-gateways" id="pp_modal_gateways">
							<?php
							$i = 0;
							foreach ( $gateways as $id => $gateway ) :
								?>
								<label class="passpress-checkout-gateway">
									<input type="radio" name="gateway_ui" value="<?php echo esc_attr( $id ); ?>" <?php checked( 0 === $i ); ?> data-sync-gateway>
									<span><?php echo esc_html( $gateway->label() ); ?></span>
								</label>
								<?php if ( 'stripe' === $id ) : ?>
									<div class="passpress-checkout-card-fields" id="pp_modal_card_fields" hidden>
										<div class="passpress-checkout-field-row">
											<div class="passpress-checkout-field passpress-checkout-field-grow">
												<label for="pp_modal_card_number"><?php esc_html_e( 'Card number', 'passpress' ); ?></label>
												<input type="text" id="pp_modal_card_number" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" maxlength="19">
											</div>
											<div class="passpress-checkout-field passpress-checkout-field-exp">
												<label for="pp_modal_card_exp"><?php esc_html_e( 'Exp / CVC', 'passpress' ); ?></label>
												<input type="text" id="pp_modal_card_exp" name="card_exp_cvc" autocomplete="cc-exp" placeholder="12/28 · 123" maxlength="14">
											</div>
										</div>
									</div>
								<?php endif; ?>
								<?php
								++$i;
							endforeach;
							?>
						</div>
					</div>
				<?php endif; ?>

				<button type="submit" class="passpress-checkout-submit" id="pp_modal_submit">
					<?php
					echo esc_html(
						'woocommerce' === $payment_mode
							? __( 'Complete Registration', 'passpress' )
							: __( 'Pay now', 'passpress' )
					);
					?>
				</button>
			</form>
		</div>

		<!-- Step 2: WooCommerce checkout embed (WC mode only) -->
		<div class="passpress-checkout-modal-step" id="pp_modal_step_wc" data-step="wc" hidden>
			<p class="passpress-checkout-wc-intro"><?php esc_html_e( 'Confirm billing and payment — everything is on one screen.', 'passpress' ); ?></p>
			<div class="passpress-checkout-wc-frame-wrap">
				<iframe
					id="pp_modal_wc_iframe"
					class="passpress-checkout-wc-iframe"
					title="<?php esc_attr_e( 'WooCommerce checkout', 'passpress' ); ?>"
					src="about:blank"
				></iframe>
			</div>
		</div>
	</div>
</div>
