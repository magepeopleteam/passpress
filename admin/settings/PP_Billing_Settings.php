<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment Method settings panel (Billing tab of PP_Settings_Page).
 * Modeled on service-booking-manager's MPWPB_Native_Checkout_Settings:
 * WooCommerce / Custom Payment toggle, gateway cards, configure panels.
 */
class PP_Billing_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_passpress_toggle_wc_gateway', array( __CLASS__, 'ajax_toggle_wc_gateway' ) );
		add_action( 'wp_ajax_passpress_save_wc_gateway_settings', array( __CLASS__, 'ajax_save_wc_gateway_settings' ) );
		add_action( 'wp_ajax_passpress_install_activate_woocommerce', array( __CLASS__, 'ajax_install_activate_woocommerce' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_billing_settings_group', 'passpress_billing_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = PP_Billing::default_settings();
		$sanitized = array();

		$type = isset( $input['payment_method_type'] ) ? sanitize_key( $input['payment_method_type'] ) : 'native';
		if ( ! in_array( $type, array( 'native', 'woocommerce', 'none' ), true ) ) {
			$type = 'native';
		}
		$sanitized['payment_method_type'] = $type;

		$sanitized['offline_enabled']      = ! empty( $input['offline_enabled'] ) ? 1 : 0;
		$sanitized['offline_auto_confirm'] = ! empty( $input['offline_auto_confirm'] ) ? 1 : 0;
		$sanitized['offline_instructions'] = isset( $input['offline_instructions'] ) ? sanitize_textarea_field( $input['offline_instructions'] ) : '';

		$sanitized['stripe_enabled']         = ! empty( $input['stripe_enabled'] ) ? 1 : 0;
		$sanitized['stripe_mode']            = isset( $input['stripe_mode'] ) && 'live' === $input['stripe_mode'] ? 'live' : 'test';
		$sanitized['stripe_publishable_key'] = isset( $input['stripe_publishable_key'] ) ? sanitize_text_field( $input['stripe_publishable_key'] ) : '';
		$sanitized['stripe_secret_key']      = isset( $input['stripe_secret_key'] ) ? sanitize_text_field( $input['stripe_secret_key'] ) : '';
		$sanitized['stripe_webhook_secret']  = isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( $input['stripe_webhook_secret'] ) : '';

		$sanitized['paypal_enabled']       = ! empty( $input['paypal_enabled'] ) ? 1 : 0;
		$sanitized['paypal_mode']          = isset( $input['paypal_mode'] ) && 'live' === $input['paypal_mode'] ? 'live' : 'sandbox';
		$sanitized['paypal_client_id']     = isset( $input['paypal_client_id'] ) ? sanitize_text_field( $input['paypal_client_id'] ) : '';
		$sanitized['paypal_client_secret'] = isset( $input['paypal_client_secret'] ) ? sanitize_text_field( $input['paypal_client_secret'] ) : '';
		$sanitized['paypal_webhook_id']    = isset( $input['paypal_webhook_id'] ) ? sanitize_text_field( $input['paypal_webhook_id'] ) : '';

		$sanitized['renewal_reminder_days'] = isset( $input['renewal_reminder_days'] ) ? max( 1, absint( $input['renewal_reminder_days'] ) ) : $defaults['renewal_reminder_days'];

		$sanitized['wc_add_to_cart_redirect'] = ( isset( $input['wc_add_to_cart_redirect'] ) && 'cart' === $input['wc_add_to_cart_redirect'] ) ? 'cart' : 'checkout';
		$sanitized['wc_require_login']        = ! empty( $input['wc_require_login'] ) ? 1 : 0;

		return $sanitized;
	}

	/**
	 * @deprecated Use PP_Settings_Page::render() with tab=billing.
	 */
	public static function render() {
		$_GET['tab'] = 'billing';
		PP_Settings_Page::render();
	}

	public static function render_panel() {
		$settings     = PP_Billing::get_settings();
		$payment_type = $settings['payment_method_type'];
		$wc_status    = pp_woocommerce_status();
		$wc_active    = 1 === $wc_status;
		$option       = 'passpress_billing_settings';
		$webhook_base = admin_url( 'admin-ajax.php' );

		$gateways = array(
			'paypal'  => array(
				'label'    => __( 'PayPal', 'passpress' ),
				'icon'     => 'dashicons-money-alt',
				'gradient' => 'linear-gradient(135deg,#003b7a,#0073c4)',
			),
			'stripe'  => array(
				'label'    => __( 'Credit/Debit Card (Stripe)', 'passpress' ),
				'icon'     => 'dashicons-credit-card',
				'gradient' => 'linear-gradient(135deg,#4338ca,#6d5bf0)',
			),
			'offline' => array(
				'label'    => __( 'Offline Payment', 'passpress' ),
				'icon'     => 'dashicons-clipboard',
				'gradient' => 'linear-gradient(135deg,#0f5f52,#1f9c82)',
			),
		);

		$wc_name     = '<strong>' . esc_html__( 'WooCommerce', 'passpress' ) . '</strong>';
		$custom_name = '<strong>' . esc_html__( 'Custom Payment', 'passpress' ) . '</strong>';
		$confirm_copy = array(
			'woocommerce' => array(
				'intro'    => sprintf(
					/* translators: %s: payment method name */
					esc_html__( 'You currently have %s active. Only one payment method mode is supported at a time.', 'passpress' ),
					$custom_name
				),
				'question' => sprintf(
					/* translators: 1: method to disable, 2: method to activate */
					esc_html__( 'Do you want to disable %1$s and activate %2$s instead?', 'passpress' ),
					$custom_name,
					$wc_name
				),
			),
			'native'      => array(
				'intro'    => sprintf(
					esc_html__( 'You currently have %s active. Only one payment method mode is supported at a time.', 'passpress' ),
					$wc_name
				),
				'question' => sprintf(
					esc_html__( 'Do you want to disable %1$s and activate %2$s instead?', 'passpress' ),
					$wc_name,
					$custom_name
				),
			),
		);

		$offline_instructions = $settings['offline_instructions']
			? $settings['offline_instructions']
			: __( 'Please pay via bank transfer or at the front desk. We will confirm your membership once payment is received.', 'passpress' );
		?>
		<div class="passpress-settings-panel" id="passpress-panel-billing">
			<header class="passpress-settings-panel-header">
				<p class="passpress-settings-panel-eyebrow"><?php esc_html_e( 'Checkout', 'passpress' ); ?></p>
				<h2><?php esc_html_e( 'Payment Method', 'passpress' ); ?></h2>
				<p><?php esc_html_e( 'Choose WooCommerce checkout or PassPress custom payment (Offline, Stripe, PayPal). Memberships and renewals are always managed by PassPress.', 'passpress' ); ?></p>
			</header>

			<form method="post" action="options.php" id="passpress-payment-method-form">
				<?php settings_fields( 'passpress_billing_settings_group' ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( PP_Settings_Page::url( 'billing' ) ); ?>">

				<div class="passpress-pm-toggle">
					<button type="button" class="passpress-pm-toggle-btn <?php echo 'native' !== $payment_type ? 'is-active' : ''; ?>" data-value="woocommerce"><?php esc_html_e( 'WooCommerce', 'passpress' ); ?></button>
					<button type="button" class="passpress-pm-toggle-btn <?php echo 'native' === $payment_type ? 'is-active' : ''; ?>" data-value="native"><?php esc_html_e( 'Custom Payment', 'passpress' ); ?></button>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $option ); ?>[payment_method_type]" id="passpress_payment_method_type_input" value="<?php echo esc_attr( $payment_type ); ?>">

				<div id="passpress-pm-confirm-modal" class="passpress-pm-confirm-overlay" style="display:none;">
					<div class="passpress-pm-confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="passpress-pm-confirm-title">
						<div class="passpress-pm-confirm-head">
							<span class="passpress-pm-confirm-icon dashicons dashicons-warning"></span>
							<h3 class="passpress-pm-confirm-title" id="passpress-pm-confirm-title"><?php esc_html_e( 'Only One Payment Method Allowed', 'passpress' ); ?></h3>
						</div>
						<div class="passpress-pm-confirm-body" id="passpress-pm-confirm-text"></div>
						<div class="passpress-pm-confirm-actions">
							<button type="button" class="passpress-pm-btn-outline" data-pp-confirm-cancel><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
							<button type="button" class="passpress-pm-btn-primary" data-pp-confirm-ok><?php esc_html_e( 'Yes, Switch', 'passpress' ); ?></button>
						</div>
					</div>
				</div>

				<div class="passpress-pm-panel" data-panel="woocommerce" style="<?php echo 'native' === $payment_type ? 'display:none;' : ''; ?>">
					<?php if ( ! $wc_active ) : ?>
						<div class="passpress-pm-notice passpress-pm-notice-warning">
							<strong><?php esc_html_e( 'WooCommerce is not activated', 'passpress' ); ?></strong>
							<p><?php esc_html_e( 'To use WooCommerce as your payment method, install and activate WooCommerce. PassPress will still create and renew memberships when shop orders complete.', 'passpress' ); ?></p>
							<button type="button" id="passpress_install_wc_btn" class="passpress-pm-btn-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'passpress_install_wc' ) ); ?>">
								<?php echo 2 === $wc_status ? esc_html__( 'Activate Now', 'passpress' ) : esc_html__( 'Install & Activate Now', 'passpress' ); ?>
							</button>
							<span id="passpress_install_wc_status" class="passpress-pm-status-text"></span>
						</div>
					<?php else : ?>
						<div class="passpress-pm-toggle-row">
							<div>
								<strong><?php esc_html_e( 'Enable WooCommerce Payment', 'passpress' ); ?></strong>
								<p class="description"><?php esc_html_e( 'If enabled, members buy plans through the WooCommerce cart/checkout. Completed orders issue or renew PassPress memberships.', 'passpress' ); ?></p>
							</div>
							<label class="passpress-pm-switch">
								<input type="checkbox" id="passpress_wc_enable_toggle" <?php checked( 'woocommerce' === $payment_type ); ?>>
								<span class="passpress-pm-switch-slider"></span>
							</label>
						</div>

						<div class="passpress-pm-accordion passpress-wc-dependent" <?php echo 'woocommerce' === $payment_type ? '' : 'style="display:none;"'; ?>>
							<button type="button" class="passpress-pm-accordion-header" data-target="passpress-acc-wc-gateways">
								<?php esc_html_e( 'WooCommerce Payment Methods', 'passpress' ); ?>
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</button>
							<div class="passpress-pm-accordion-body" id="passpress-acc-wc-gateways" style="display:none;">
								<p>
									<a class="passpress-pm-btn-outline" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" target="_blank" rel="noopener">
										<?php esc_html_e( 'Open in WooCommerce', 'passpress' ); ?>
										<span class="dashicons dashicons-external"></span>
									</a>
								</p>
								<?php
								foreach ( WC()->payment_gateways()->payment_gateways() as $wc_gateway ) :
									$config_panel_id = 'passpress-wc-gw-config-' . $wc_gateway->id;
									$config_fields   = $wc_gateway->get_form_fields();
									unset( $config_fields['enabled'] );
									?>
									<div class="passpress-wc-gateway-card">
										<label class="passpress-pm-switch">
											<input
												type="checkbox"
												class="passpress-wc-gateway-toggle"
												data-gateway="<?php echo esc_attr( $wc_gateway->id ); ?>"
												data-nonce="<?php echo esc_attr( wp_create_nonce( 'passpress_toggle_wc_gateway_' . $wc_gateway->id ) ); ?>"
												data-enabled-field="<?php echo esc_attr( $wc_gateway->get_field_key( 'enabled' ) ); ?>"
												<?php checked( 'yes' === $wc_gateway->enabled ); ?>
											>
											<span class="passpress-pm-switch-slider"></span>
										</label>
										<span class="passpress-wc-gateway-name"><?php echo esc_html( $wc_gateway->get_method_title() ); ?></span>
										<span class="passpress-wc-gateway-status <?php echo 'yes' === $wc_gateway->enabled ? 'is-enabled' : ''; ?>" data-status-for="<?php echo esc_attr( $wc_gateway->id ); ?>">
											<?php echo 'yes' === $wc_gateway->enabled ? esc_html__( 'ENABLED', 'passpress' ) : esc_html__( 'DISABLED', 'passpress' ); ?>
										</span>
										<button type="button" class="passpress-pm-btn-outline passpress-gateway-configure" data-target="<?php echo esc_attr( $config_panel_id ); ?>"><?php esc_html_e( 'Configure', 'passpress' ); ?></button>
										<div class="passpress-wc-gateway-desc"><?php echo wp_kses_post( $wc_gateway->get_method_description() ); ?></div>
									</div>
									<div class="passpress-wc-gw-panel" id="<?php echo esc_attr( $config_panel_id ); ?>" style="display:none;">
										<table class="form-table">
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC generates its own admin HTML.
											echo $wc_gateway->generate_settings_html( $config_fields, false );
											?>
										</table>
										<p>
											<button type="button" class="passpress-pm-btn-primary passpress-wc-gw-save" data-gateway="<?php echo esc_attr( $wc_gateway->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'passpress_save_wc_gateway_' . $wc_gateway->id ) ); ?>"><?php esc_html_e( 'Save Changes', 'passpress' ); ?></button>
											<span class="passpress-wc-gw-save-status passpress-pm-status-text"></span>
										</p>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="passpress-pm-accordion passpress-wc-dependent" <?php echo 'woocommerce' === $payment_type ? '' : 'style="display:none;"'; ?>>
							<button type="button" class="passpress-pm-accordion-header" data-target="passpress-acc-wc-additional">
								<?php esc_html_e( 'Additional Settings', 'passpress' ); ?>
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</button>
							<div class="passpress-pm-accordion-body" id="passpress-acc-wc-additional" style="display:none;">
								<div class="passpress-pm-settings-row">
									<div>
										<strong><?php esc_html_e( 'After Adding to Cart, Redirect to', 'passpress' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Where members go after clicking Buy on a membership plan.', 'passpress' ); ?></p>
									</div>
									<select name="<?php echo esc_attr( $option ); ?>[wc_add_to_cart_redirect]">
										<option value="checkout" <?php selected( $settings['wc_add_to_cart_redirect'], 'checkout' ); ?>><?php esc_html_e( 'Checkout', 'passpress' ); ?></option>
										<option value="cart" <?php selected( $settings['wc_add_to_cart_redirect'], 'cart' ); ?>><?php esc_html_e( 'Cart', 'passpress' ); ?></option>
									</select>
								</div>
								<div class="passpress-pm-settings-row">
									<div>
										<strong><?php esc_html_e( 'Require Account Login', 'passpress' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Memberships need a WordPress user account. Guest checkout cannot receive a pass.', 'passpress' ); ?></p>
									</div>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[wc_require_login]" value="1" <?php checked( ! empty( $settings['wc_require_login'] ) ); ?>>
										<?php esc_html_e( 'Require login before buying a plan via shop', 'passpress' ); ?>
									</label>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="passpress-pm-panel" data-panel="native" style="<?php echo 'native' === $payment_type ? '' : 'display:none;'; ?>">
					<div class="passpress-pm-toggle-row">
						<div>
							<strong><?php esc_html_e( 'Enable Custom Payment Method', 'passpress' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Use PassPress native checkout with Offline, Stripe, and/or PayPal. No WooCommerce Subscriptions plugin required.', 'passpress' ); ?></p>
						</div>
						<label class="passpress-pm-switch">
							<input type="checkbox" id="passpress_native_enable_toggle" <?php checked( 'native' === $payment_type ); ?>>
							<span class="passpress-pm-switch-slider"></span>
						</label>
					</div>

					<div class="passpress-native-dependent <?php echo 'native' === $payment_type ? '' : 'is-locked'; ?>">
						<?php foreach ( $gateways as $key => $gw ) :
							$enabled = ! empty( $settings[ $key . '_enabled' ] );
							?>
							<div class="passpress-gateway-card" style="background:<?php echo esc_attr( $gw['gradient'] ); ?>">
								<div class="passpress-gateway-row">
									<span class="passpress-gateway-icon dashicons <?php echo esc_attr( $gw['icon'] ); ?>"></span>
									<span class="passpress-gateway-name"><?php echo esc_html( $gw['label'] ); ?></span>
									<span class="passpress-gateway-status <?php echo $enabled ? 'is-enabled' : ''; ?>" data-status-for="<?php echo esc_attr( $key ); ?>">
										<?php echo $enabled ? esc_html__( 'Enabled', 'passpress' ) : esc_html__( 'Disabled', 'passpress' ); ?>
									</span>
									<button type="button" class="passpress-gateway-configure" data-target="passpress-gw-panel-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Configure', 'passpress' ); ?></button>
								</div>
							</div>
							<div class="passpress-gw-panel" id="passpress-gw-panel-<?php echo esc_attr( $key ); ?>" style="display:none;">
								<label class="passpress-gw-enable-label">
									<input type="hidden" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $key ); ?>_enabled]" value="0">
									<span class="passpress-pm-switch">
										<input type="checkbox" class="passpress-gw-enable-toggle" data-status-for="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $key ); ?>_enabled]" value="1" <?php checked( $enabled ); ?>>
										<span class="passpress-pm-switch-slider"></span>
									</span>
									<?php esc_html_e( 'Enable this payment method', 'passpress' ); ?>
								</label>

								<?php if ( 'offline' === $key ) : ?>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Confirmation', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<label>
												<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[offline_auto_confirm]" value="1" <?php checked( ! empty( $settings['offline_auto_confirm'] ) ); ?>>
												<?php esc_html_e( 'Auto-confirm immediately (uncheck to require staff confirmation in Billing History)', 'passpress' ); ?>
											</label>
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Instructions shown at checkout', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<textarea rows="3" name="<?php echo esc_attr( $option ); ?>[offline_instructions]"><?php echo esc_textarea( $offline_instructions ); ?></textarea>
										</span>
									</label>
								<?php elseif ( 'stripe' === $key ) : ?>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Mode', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<select name="<?php echo esc_attr( $option ); ?>[stripe_mode]">
												<option value="test" <?php selected( $settings['stripe_mode'], 'test' ); ?>><?php esc_html_e( 'Test', 'passpress' ); ?></option>
												<option value="live" <?php selected( $settings['stripe_mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'passpress' ); ?></option>
											</select>
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Publishable Key', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="text" name="<?php echo esc_attr( $option ); ?>[stripe_publishable_key]" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ); ?>">
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Secret Key', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="password" name="<?php echo esc_attr( $option ); ?>[stripe_secret_key]" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>" autocomplete="off">
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Webhook Secret', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="password" name="<?php echo esc_attr( $option ); ?>[stripe_webhook_secret]" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>" autocomplete="off">
										</span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Webhook URL (checkout.session.completed):', 'passpress' ); ?>
										<code><?php echo esc_html( add_query_arg( 'action', 'passpress_stripe_webhook', $webhook_base ) ); ?></code>
									</p>
								<?php elseif ( 'paypal' === $key ) : ?>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Mode', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<select name="<?php echo esc_attr( $option ); ?>[paypal_mode]">
												<option value="sandbox" <?php selected( $settings['paypal_mode'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'passpress' ); ?></option>
												<option value="live" <?php selected( $settings['paypal_mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'passpress' ); ?></option>
											</select>
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Client ID', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="text" name="<?php echo esc_attr( $option ); ?>[paypal_client_id]" value="<?php echo esc_attr( $settings['paypal_client_id'] ); ?>">
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Client Secret', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="password" name="<?php echo esc_attr( $option ); ?>[paypal_client_secret]" value="<?php echo esc_attr( $settings['paypal_client_secret'] ); ?>" autocomplete="off">
										</span>
									</label>
									<label class="passpress-gw-field">
										<span class="passpress-gw-field-label"><?php esc_html_e( 'Webhook ID', 'passpress' ); ?></span>
										<span class="passpress-gw-field-control">
											<input type="text" name="<?php echo esc_attr( $option ); ?>[paypal_webhook_id]" value="<?php echo esc_attr( $settings['paypal_webhook_id'] ); ?>">
										</span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Webhook URL (PAYMENT.CAPTURE.COMPLETED):', 'passpress' ); ?>
										<code><?php echo esc_html( add_query_arg( 'action', 'passpress_paypal_webhook', $webhook_base ) ); ?></code>
									</p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<div class="passpress-pm-confirmation-row">
							<div>
								<strong><?php esc_html_e( 'Renewal Reminders', 'passpress' ); ?></strong>
								<p class="description"><?php esc_html_e( 'Email members this many days before their membership expires.', 'passpress' ); ?></p>
							</div>
							<input type="number" name="<?php echo esc_attr( $option ); ?>[renewal_reminder_days]" value="<?php echo esc_attr( $settings['renewal_reminder_days'] ); ?>" min="1" max="60" class="small-text">
						</div>
					</div>
				</div>

				<?php submit_button( __( 'Save Payment Settings', 'passpress' ) ); ?>
			</form>
		</div>
		<script type="application/json" id="passpress-pm-confirm-copy"><?php echo wp_json_encode( $confirm_copy ); ?></script>
		<?php
	}

	public static function ajax_toggle_wc_gateway() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$gateway_id = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		if ( ! $gateway_id || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'passpress_toggle_wc_gateway_' . $gateway_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		if ( ! pp_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'passpress' ) ) );
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown payment gateway.', 'passpress' ) ) );
		}

		$enabled = ! empty( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		$gateways[ $gateway_id ]->update_option( 'enabled', $enabled ? 'yes' : 'no' );
		wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	public static function ajax_save_wc_gateway_settings() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$gateway_id = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		if ( ! $gateway_id || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'passpress_save_wc_gateway_' . $gateway_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		if ( ! pp_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'passpress' ) ) );
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown payment gateway.', 'passpress' ) ) );
		}

		$gateway = $gateways[ $gateway_id ];
		$gateway->process_admin_options();
		$errors = $gateway->get_errors();
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', array_map( 'wp_strip_all_tags', $errors ) ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'passpress' ) ) );
	}

	public static function ajax_install_activate_woocommerce() {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'passpress_install_wc' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		$status = pp_woocommerce_status();
		if ( 1 === $status ) {
			wp_send_json_success( array( 'message' => __( 'WooCommerce is already active.', 'passpress' ) ) );
		}

		if ( 2 === $status ) {
			$result = activate_plugin( 'woocommerce/woocommerce.php' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'WooCommerce activated.', 'passpress' ) ) );
		}

		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => 'woocommerce',
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			wp_send_json_error( array( 'message' => $api->get_error_message() ) );
		}

		$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$installed = $upgrader->install( $api->download_link );
		if ( is_wp_error( $installed ) || ! $installed ) {
			wp_send_json_error( array( 'message' => __( 'Could not install WooCommerce.', 'passpress' ) ) );
		}

		$result = activate_plugin( 'woocommerce/woocommerce.php' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'WooCommerce installed and activated.', 'passpress' ) ) );
	}
}
