<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend checkout modal — opened by .passpress-open-checkout Buy buttons.
 *
 * @var array $gateways PP_Gateway_Interface[]
 * @var array $settings
 */
$gateways = isset( $gateways ) ? $gateways : PP_Billing::get_checkout_gateways();
$settings = isset( $settings ) ? $settings : pp_get_settings();
$user     = is_user_logged_in() ? wp_get_current_user() : null;
$symbol   = $settings['currency_symbol'];
$default_gateway = $gateways ? array_key_first( $gateways ) : 'offline';
?>
<div id="passpress-checkout-modal" class="passpress-checkout-modal-overlay" hidden>
	<div class="passpress-checkout-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-checkout-modal-title">
		<button type="button" class="passpress-checkout-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>

		<header class="passpress-checkout-modal-header">
			<h2 id="passpress-checkout-modal-title"><?php esc_html_e( 'Checkout', 'passpress' ); ?></h2>
			<p class="passpress-checkout-modal-subtitle"><?php esc_html_e( "You're one step from your pass.", 'passpress' ); ?></p>
		</header>

		<div class="passpress-checkout-modal-notice" hidden role="alert"></div>

		<form id="passpress-checkout-modal-form" class="passpress-checkout-modal-form" novalidate>
			<input type="hidden" name="plan_id" id="pp_modal_plan_id" value="">
			<input type="hidden" name="renew" id="pp_modal_renew_id" value="0">
			<input type="hidden" name="nonce" id="pp_modal_nonce" value="">
			<input type="hidden" name="gateway" id="pp_modal_gateway" value="<?php echo esc_attr( $default_gateway ); ?>">

			<label class="passpress-checkout-gift">
				<input type="checkbox" name="is_gift" id="pp_modal_is_gift" value="1">
				<span class="passpress-checkout-gift-icon" aria-hidden="true">🎁</span>
				<span><?php esc_html_e( 'This is a gift for someone else', 'passpress' ); ?></span>
			</label>

			<div class="passpress-checkout-field">
				<label for="pp_modal_full_name"><?php esc_html_e( 'Your full name', 'passpress' ); ?></label>
				<input type="text" id="pp_modal_full_name" name="full_name" placeholder="<?php esc_attr_e( 'Alex Fernandez', 'passpress' ); ?>" value="<?php echo $user ? esc_attr( $user->display_name ) : ''; ?>" required>
			</div>

			<div class="passpress-checkout-field">
				<label for="pp_modal_email"><?php esc_html_e( 'Your email', 'passpress' ); ?></label>
				<input type="email" id="pp_modal_email" name="email" placeholder="<?php esc_attr_e( 'alex@email.com', 'passpress' ); ?>" value="<?php echo $user ? esc_attr( $user->user_email ) : ''; ?>" required>
			</div>

			<?php if ( ! empty( $gateways ) ) : ?>
				<div class="passpress-checkout-field">
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

			<div class="passpress-checkout-field">
				<label for="pp_modal_coupon"><?php esc_html_e( 'Discount code', 'passpress' ); ?></label>
				<div class="passpress-checkout-coupon-row">
					<input type="text" id="pp_modal_coupon" name="coupon_code" placeholder="<?php esc_attr_e( 'WELCOME10', 'passpress' ); ?>">
					<button type="button" class="passpress-checkout-coupon-apply" id="pp_modal_coupon_apply"><?php esc_html_e( 'Apply', 'passpress' ); ?></button>
				</div>
				<p class="passpress-checkout-coupon-msg" id="pp_modal_coupon_msg" hidden></p>
			</div>

			<div class="passpress-checkout-summary">
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

			<button type="submit" class="passpress-checkout-submit" id="pp_modal_submit">
				<?php esc_html_e( 'Pay now', 'passpress' ); ?>
			</button>

			<p class="passpress-checkout-modal-footer">
				<?php esc_html_e( 'Demo checkout — no real payment is processed.', 'passpress' ); ?>
			</p>
		</form>
	</div>
</div>
