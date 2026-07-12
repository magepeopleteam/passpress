<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway configuration: Payment Method type, Offline/Stripe/PayPal
 * credentials, renewal reminder window. Mirrors the "explicit admin-chosen
 * Payment Method, not just auto-detection" pattern proven out in the
 * sibling wpbookingly plugin (see CLAUDE.md / [[project_wc_optional_native_checkout]]).
 */
class PP_Billing_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_billing_settings_group', 'passpress_billing_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = PP_Billing::default_settings();
		$sanitized = array();

		$sanitized['payment_method_type'] = ( isset( $input['payment_method_type'] ) && 'wc_subscriptions' === $input['payment_method_type'] && pp_is_woocommerce_subscriptions_active() )
			? 'wc_subscriptions'
			: 'native';

		$sanitized['offline_enabled']       = ! empty( $input['offline_enabled'] ) ? 1 : 0;
		$sanitized['offline_auto_confirm']  = ! empty( $input['offline_auto_confirm'] ) ? 1 : 0;

		$sanitized['stripe_enabled']         = ! empty( $input['stripe_enabled'] ) ? 1 : 0;
		$sanitized['stripe_mode']            = isset( $input['stripe_mode'] ) && 'live' === $input['stripe_mode'] ? 'live' : 'test';
		$sanitized['stripe_publishable_key'] = isset( $input['stripe_publishable_key'] ) ? sanitize_text_field( $input['stripe_publishable_key'] ) : '';
		$sanitized['stripe_secret_key']      = isset( $input['stripe_secret_key'] ) ? sanitize_text_field( $input['stripe_secret_key'] ) : '';
		$sanitized['stripe_webhook_secret']  = isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( $input['stripe_webhook_secret'] ) : '';

		$sanitized['paypal_enabled']        = ! empty( $input['paypal_enabled'] ) ? 1 : 0;
		$sanitized['paypal_mode']           = isset( $input['paypal_mode'] ) && 'live' === $input['paypal_mode'] ? 'live' : 'sandbox';
		$sanitized['paypal_client_id']      = isset( $input['paypal_client_id'] ) ? sanitize_text_field( $input['paypal_client_id'] ) : '';
		$sanitized['paypal_client_secret']  = isset( $input['paypal_client_secret'] ) ? sanitize_text_field( $input['paypal_client_secret'] ) : '';
		$sanitized['paypal_webhook_id']     = isset( $input['paypal_webhook_id'] ) ? sanitize_text_field( $input['paypal_webhook_id'] ) : '';

		$sanitized['renewal_reminder_days'] = isset( $input['renewal_reminder_days'] ) ? max( 1, absint( $input['renewal_reminder_days'] ) ) : $defaults['renewal_reminder_days'];

		return $sanitized;
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$settings     = PP_Billing::get_settings();
		$webhook_base = admin_url( 'admin-ajax.php' );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Billing Settings', 'passpress' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'passpress_billing_settings_group' ); ?>

				<h2><?php esc_html_e( 'Payment Method', 'passpress' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Mode', 'passpress' ); ?></th>
						<td>
							<label>
								<input type="radio" name="passpress_billing_settings[payment_method_type]" value="native" <?php checked( 'native', $settings['payment_method_type'] ); ?>>
								<?php esc_html_e( 'Native (this plugin\'s own checkout: Offline/Stripe/PayPal below)', 'passpress' ); ?>
							</label><br>
							<label>
								<input type="radio" name="passpress_billing_settings[payment_method_type]" value="wc_subscriptions" <?php checked( 'wc_subscriptions', $settings['payment_method_type'] ); disabled( ! pp_is_woocommerce_subscriptions_active() ); ?>>
								<?php esc_html_e( 'WooCommerce Subscriptions bridge', 'passpress' ); ?>
								<?php if ( ! pp_is_woocommerce_subscriptions_active() ) : ?>
									<em>(<?php esc_html_e( 'requires WooCommerce Subscriptions — not detected, and not yet implemented in this build', 'passpress' ); ?>)</em>
								<?php endif; ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Offline / Manual', 'passpress' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_billing_settings[offline_enabled]" value="1" <?php checked( ! empty( $settings['offline_enabled'] ) ); ?>> <?php esc_html_e( 'Accept offline/manual payment', 'passpress' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Confirmation', 'passpress' ); ?></th>
						<td>
							<label><input type="checkbox" name="passpress_billing_settings[offline_auto_confirm]" value="1" <?php checked( ! empty( $settings['offline_auto_confirm'] ) ); ?>> <?php esc_html_e( 'Auto-confirm immediately (uncheck to require staff to manually mark paid from Billing History)', 'passpress' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Stripe', 'passpress' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_billing_settings[stripe_enabled]" value="1" <?php checked( ! empty( $settings['stripe_enabled'] ) ); ?>> <?php esc_html_e( 'Accept Stripe payments', 'passpress' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode', 'passpress' ); ?></th>
						<td>
							<label><input type="radio" name="passpress_billing_settings[stripe_mode]" value="test" <?php checked( 'test', $settings['stripe_mode'] ); ?>> <?php esc_html_e( 'Test', 'passpress' ); ?></label>
							<label><input type="radio" name="passpress_billing_settings[stripe_mode]" value="live" <?php checked( 'live', $settings['stripe_mode'] ); ?>> <?php esc_html_e( 'Live', 'passpress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="pp_stripe_pk"><?php esc_html_e( 'Publishable Key', 'passpress' ); ?></label></th>
						<td><input type="text" id="pp_stripe_pk" name="passpress_billing_settings[stripe_publishable_key]" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="pp_stripe_sk"><?php esc_html_e( 'Secret Key', 'passpress' ); ?></label></th>
						<td><input type="password" id="pp_stripe_sk" name="passpress_billing_settings[stripe_secret_key]" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th><label for="pp_stripe_wh"><?php esc_html_e( 'Webhook Signing Secret', 'passpress' ); ?></label></th>
						<td>
							<input type="password" id="pp_stripe_wh" name="passpress_billing_settings[stripe_webhook_secret]" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>" class="regular-text" autocomplete="off">
							<p class="description">
								<?php esc_html_e( 'Webhook URL to register in the Stripe dashboard (send it the "checkout.session.completed" event):', 'passpress' ); ?><br>
								<code><?php echo esc_html( add_query_arg( 'action', 'passpress_stripe_webhook', $webhook_base ) ); ?></code>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'PayPal', 'passpress' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_billing_settings[paypal_enabled]" value="1" <?php checked( ! empty( $settings['paypal_enabled'] ) ); ?>> <?php esc_html_e( 'Accept PayPal payments', 'passpress' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode', 'passpress' ); ?></th>
						<td>
							<label><input type="radio" name="passpress_billing_settings[paypal_mode]" value="sandbox" <?php checked( 'sandbox', $settings['paypal_mode'] ); ?>> <?php esc_html_e( 'Sandbox', 'passpress' ); ?></label>
							<label><input type="radio" name="passpress_billing_settings[paypal_mode]" value="live" <?php checked( 'live', $settings['paypal_mode'] ); ?>> <?php esc_html_e( 'Live', 'passpress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="pp_paypal_id"><?php esc_html_e( 'Client ID', 'passpress' ); ?></label></th>
						<td><input type="text" id="pp_paypal_id" name="passpress_billing_settings[paypal_client_id]" value="<?php echo esc_attr( $settings['paypal_client_id'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="pp_paypal_secret"><?php esc_html_e( 'Client Secret', 'passpress' ); ?></label></th>
						<td><input type="password" id="pp_paypal_secret" name="passpress_billing_settings[paypal_client_secret]" value="<?php echo esc_attr( $settings['paypal_client_secret'] ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th><label for="pp_paypal_wh"><?php esc_html_e( 'Webhook ID', 'passpress' ); ?></label></th>
						<td>
							<input type="text" id="pp_paypal_wh" name="passpress_billing_settings[paypal_webhook_id]" value="<?php echo esc_attr( $settings['paypal_webhook_id'] ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Webhook URL to register in the PayPal dashboard (send it the "PAYMENT.CAPTURE.COMPLETED" event):', 'passpress' ); ?><br>
								<code><?php echo esc_html( add_query_arg( 'action', 'passpress_paypal_webhook', $webhook_base ) ); ?></code>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Renewal Reminders', 'passpress' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pp_reminder_days"><?php esc_html_e( 'Send reminder this many days before expiry', 'passpress' ); ?></label></th>
						<td><input type="number" id="pp_reminder_days" name="passpress_billing_settings[renewal_reminder_days]" value="<?php echo esc_attr( $settings['renewal_reminder_days'] ); ?>" min="1" max="60" class="small-text"></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
