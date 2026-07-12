<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Zero-config checkout page, rendered directly from PP_Billing::render_checkout()
 * via template_redirect — no WP page needs to exist for this URL to work.
 *
 * @var string       $state      form|login_required|error|success|pending|cancelled
 * @var string       $message
 * @var WP_Post|null $plan
 * @var object|null  $membership Present when this checkout is a renewal.
 * @var array        $gateways   PP_Gateway_Interface[] id => instance
 * @var string        $coupon_code Sticky value of the coupon field after a failed submit.
 */
get_header();
?>
<div class="wrap passpress-checkout-wrap" style="max-width:640px;margin:40px auto;padding:0 20px;">

	<?php if ( 'login_required' === $state ) : ?>
		<h1><?php esc_html_e( 'Please log in to continue', 'passpress' ); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url( wp_login_url( add_query_arg( array() ) ) ); ?>"><?php esc_html_e( 'Log in', 'passpress' ); ?></a></p>

	<?php elseif ( 'error' === $state ) : ?>
		<h1><?php esc_html_e( 'Checkout unavailable', 'passpress' ); ?></h1>
		<p class="passpress-checkout-notice passpress-checkout-notice-error"><?php echo esc_html( $message ); ?></p>

	<?php elseif ( 'success' === $state ) : ?>
		<h1><?php esc_html_e( 'Payment received!', 'passpress' ); ?></h1>
		<p class="passpress-checkout-notice passpress-checkout-notice-success"><?php esc_html_e( 'Your membership is now active. You can view your pass any time from the My Pass page.', 'passpress' ); ?></p>
		<?php $my_pass_url = pp_find_shortcode_page_url( 'passpress_my_pass' ); ?>
		<?php if ( $my_pass_url ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( $my_pass_url ); ?>"><?php esc_html_e( 'View My Pass', 'passpress' ); ?></a></p>
		<?php endif; ?>

	<?php elseif ( 'pending' === $state ) : ?>
		<h1><?php esc_html_e( 'Payment received — awaiting confirmation', 'passpress' ); ?></h1>
		<p class="passpress-checkout-notice"><?php echo esc_html( $message ); ?></p>

	<?php else : // form (default) or cancelled, which re-shows the form with a notice ?>

		<h1><?php echo $membership ? esc_html__( 'Renew Membership', 'passpress' ) : esc_html__( 'Join Plan', 'passpress' ); ?></h1>

		<?php if ( 'cancelled' === $state ) : ?>
			<p class="passpress-checkout-notice"><?php echo esc_html( $message ); ?></p>
		<?php elseif ( $message ) : ?>
			<p class="passpress-checkout-notice passpress-checkout-notice-error"><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>

		<?php if ( $plan ) :
			$price          = (float) get_post_meta( $plan->ID, '_pp_price', true );
			$duration_value = (int) get_post_meta( $plan->ID, '_pp_duration_value', true );
			$duration_unit  = get_post_meta( $plan->ID, '_pp_duration_unit', true );
			$settings       = pp_get_settings();
			?>
			<div class="passpress-checkout-summary">
				<h2><?php echo esc_html( $plan->post_title ); ?></h2>
				<p class="passpress-plan-price">
					<?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $price, 2 ) ); ?>
					<?php if ( $duration_unit && 'lifetime' !== $duration_unit ) : ?>
						/ <?php echo esc_html( $duration_value . ' ' . $duration_unit . ( $duration_value > 1 ? 's' : '' ) ); ?>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( empty( $gateways ) ) : ?>
				<p class="passpress-checkout-notice"><?php esc_html_e( 'No payment method is configured yet. Please contact the front desk to join this plan.', 'passpress' ); ?></p>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'passpress_checkout_' . $plan->ID ); ?>
					<fieldset>
						<legend><?php esc_html_e( 'Payment Method', 'passpress' ); ?></legend>
						<?php foreach ( $gateways as $id => $gateway ) : ?>
							<label class="passpress-gateway-option">
								<input type="radio" name="gateway" value="<?php echo esc_attr( $id ); ?>" <?php checked( 1 === count( $gateways ) ); ?>>
								<?php echo esc_html( $gateway->label() ); ?>
							</label><br>
						<?php endforeach; ?>
					</fieldset>
					<p>
						<label for="passpress-coupon-code"><?php esc_html_e( 'Coupon Code (optional)', 'passpress' ); ?></label><br>
						<input type="text" id="passpress-coupon-code" name="coupon_code" value="<?php echo esc_attr( $coupon_code ); ?>" class="regular-text">
					</p>
					<p><button type="submit" name="passpress_pay" value="1" class="button button-primary button-hero"><?php esc_html_e( 'Pay Now', 'passpress' ); ?></button></p>
				</form>
			<?php endif; ?>
		<?php endif; ?>

	<?php endif; ?>

</div>
<?php
get_footer();
