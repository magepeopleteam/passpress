<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/settings — direct REST port of admin/settings/{PP_Settings,
 * PP_Notification_Settings,PP_Billing_Settings}.php. Sanitize logic is
 * copied verbatim from each class's sanitize() (options.php's own callback
 * chain doesn't apply to REST writes, so this calls update_option() directly
 * after running the same sanitizer).
 *
 * The one deliberate exception (see the plan): a WooCommerce gateway's own
 * "Configure" fields are WC's own dynamically-generated settings form
 * (WC_Settings_API::generate_settings_html()/process_admin_options()) — full
 * of arbitrary per-gateway fields (Stripe/PayPal/etc. all differ). Rather
 * than reimplement that generically in React, /wc-gateways/{id}/configure
 * returns the raw HTML WooCommerce itself renders, and the save endpoint
 * re-populates $_POST with the submitted field values and calls the
 * gateway's own process_admin_options() — the same mechanism the legacy
 * AJAX handler used, just fed from a REST body instead of a jQuery-serialized
 * form post.
 */
class PP_REST_Settings extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/general',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_general' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_general' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/notifications',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_notifications' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_notifications' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/billing',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_billing' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_billing' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/billing/wc-gateways',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_wc_gateways' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/billing/wc-gateways/(?P<id>[\w-]+)/configure',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_wc_gateway_configure' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_wc_gateway_configure' ), 'permission_callback' => array( __CLASS__, 'permission_manage' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/billing/wc-gateways/(?P<id>[\w-]+)/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_wc_gateway' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/wc-install',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'install_activate_woocommerce' ),
				'permission_callback' => array( __CLASS__, 'permission_install_plugins' ),
			)
		);
	}

	public static function permission_install_plugins() {
		return current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
	}

	/* ---------------- General ---------------- */

	public function get_general( $request ) {
		return rest_ensure_response( pp_get_settings() );
	}

	public function update_general( $request ) {
		update_option( 'passpress_settings', self::sanitize_general( (array) $request->get_json_params() ) );
		return rest_ensure_response( pp_get_settings() );
	}

	private static function sanitize_general( $input ) {
		return array(
			'currency_symbol'  => isset( $input['currency_symbol'] ) ? sanitize_text_field( $input['currency_symbol'] ) : '$',
			'currency_code'    => isset( $input['currency_code'] ) ? strtolower( sanitize_text_field( $input['currency_code'] ) ) : 'usd',
			'date_format'      => isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : 'F j, Y',
			'qr_size'          => isset( $input['qr_size'] ) ? max( 100, min( 400, absint( $input['qr_size'] ) ) ) : 200,
			'show_pin_on_pass' => ! empty( $input['show_pin_on_pass'] ) ? 1 : 0,
		);
	}

	/* ---------------- Notifications ---------------- */

	public function get_notifications( $request ) {
		return rest_ensure_response( PP_Notifications::get_settings() );
	}

	public function update_notifications( $request ) {
		update_option( 'passpress_notification_settings', self::sanitize_notifications( (array) $request->get_json_params() ) );
		return rest_ensure_response( PP_Notifications::get_settings() );
	}

	private static function sanitize_notifications( $input ) {
		$defaults = PP_Notifications::default_settings();
		return array(
			'welcome_enabled'          => ! empty( $input['welcome_enabled'] ) ? 1 : 0,
			'booking_reminder_enabled' => ! empty( $input['booking_reminder_enabled'] ) ? 1 : 0,
			'booking_reminder_days'    => isset( $input['booking_reminder_days'] ) ? max( 1, absint( $input['booking_reminder_days'] ) ) : $defaults['booking_reminder_days'],
			'payment_failed_enabled'   => ! empty( $input['payment_failed_enabled'] ) ? 1 : 0,
			'birthday_enabled'         => ! empty( $input['birthday_enabled'] ) ? 1 : 0,
		);
	}

	/* ---------------- Billing ---------------- */

	public function get_billing( $request ) {
		$wc_status = pp_woocommerce_status();
		return rest_ensure_response(
			array(
				'settings'  => PP_Billing::get_settings(),
				'wc_status' => $wc_status,
				'wc_active' => ( 1 === $wc_status ),
				'webhook_urls' => array(
					'stripe' => add_query_arg( 'action', 'passpress_stripe_webhook', admin_url( 'admin-ajax.php' ) ),
					'paypal' => add_query_arg( 'action', 'passpress_paypal_webhook', admin_url( 'admin-ajax.php' ) ),
				),
			)
		);
	}

	public function update_billing( $request ) {
		update_option( 'passpress_billing_settings', self::sanitize_billing( (array) $request->get_json_params() ) );
		return rest_ensure_response( PP_Billing::get_settings() );
	}

	private static function sanitize_billing( $input ) {
		$defaults = PP_Billing::default_settings();
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

	/* ---------------- WooCommerce gateways ---------------- */

	public function list_wc_gateways( $request ) {
		if ( ! pp_is_woocommerce_active() ) {
			return new WP_Error( 'pp_wc_inactive', __( 'WooCommerce is not active.', 'passpress' ), array( 'status' => 400 ) );
		}

		$items = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			$items[] = array(
				'id'          => $gateway->id,
				'title'       => $gateway->get_method_title(),
				'description' => wp_kses_post( $gateway->get_method_description() ),
				'enabled'     => ( 'yes' === $gateway->enabled ),
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	public function get_wc_gateway_configure( $request ) {
		$gateway = self::find_wc_gateway( (string) $request['id'] );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		$fields = $gateway->get_form_fields();
		unset( $fields['enabled'] );

		return rest_ensure_response(
			array(
				'enabled_field_key' => $gateway->get_field_key( 'enabled' ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC generates its own admin HTML; passed through unchanged, same as the legacy page did.
				'html'              => $gateway->generate_settings_html( $fields, false ),
			)
		);
	}

	public function save_wc_gateway_configure( $request ) {
		$gateway = self::find_wc_gateway( (string) $request['id'] );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		$fields  = (array) $request->get_param( 'fields' );
		$enabled = (bool) $request->get_param( 'enabled' );

		// WC_Settings_API::process_admin_options() reads directly from the
		// $_POST superglobal (it takes no arguments) — repopulate it from the
		// REST body, same values a serialized browser form post would have
		// sent, so the gateway's own sanitizer/validator runs unchanged.
		foreach ( $fields as $key => $value ) {
			$_POST[ sanitize_text_field( $key ) ] = wp_unslash( $value );
		}
		if ( $enabled ) {
			$_POST[ $gateway->get_field_key( 'enabled' ) ] = 'yes';
		}

		$gateway->process_admin_options();
		$errors = $gateway->get_errors();
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'pp_wc_gateway_error', implode( ' ', array_map( 'wp_strip_all_tags', $errors ) ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'Settings saved.', 'passpress' ) ) );
	}

	public function toggle_wc_gateway( $request ) {
		$gateway = self::find_wc_gateway( (string) $request['id'] );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		$enabled = (bool) $request->get_param( 'enabled' );
		$gateway->update_option( 'enabled', $enabled ? 'yes' : 'no' );

		return rest_ensure_response( array( 'enabled' => $enabled ) );
	}

	/**
	 * @param string $gateway_id
	 * @return WC_Payment_Gateway|WP_Error
	 */
	private static function find_wc_gateway( $gateway_id ) {
		if ( ! pp_is_woocommerce_active() ) {
			return new WP_Error( 'pp_wc_inactive', __( 'WooCommerce is not active.', 'passpress' ), array( 'status' => 400 ) );
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return new WP_Error( 'pp_wc_gateway_not_found', __( 'Unknown payment gateway.', 'passpress' ), array( 'status' => 404 ) );
		}
		return $gateways[ $gateway_id ];
	}

	/* ---------------- WooCommerce install/activate ---------------- */

	public function install_activate_woocommerce( $request ) {
		$status = pp_woocommerce_status();
		if ( 1 === $status ) {
			return rest_ensure_response( array( 'message' => __( 'WooCommerce is already active.', 'passpress' ) ) );
		}

		if ( 2 === $status ) {
			$result = activate_plugin( 'woocommerce/woocommerce.php' );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
			}
			return rest_ensure_response( array( 'message' => __( 'WooCommerce activated.', 'passpress' ) ) );
		}

		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api( 'plugin_information', array( 'slug' => 'woocommerce', 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return new WP_Error( $api->get_error_code(), $api->get_error_message(), array( 'status' => 400 ) );
		}

		$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$installed = $upgrader->install( $api->download_link );
		if ( is_wp_error( $installed ) || ! $installed ) {
			return new WP_Error( 'pp_wc_install_failed', __( 'Could not install WooCommerce.', 'passpress' ), array( 'status' => 400 ) );
		}

		$result = activate_plugin( 'woocommerce/woocommerce.php' );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'WooCommerce installed and activated.', 'passpress' ) ) );
	}
}
