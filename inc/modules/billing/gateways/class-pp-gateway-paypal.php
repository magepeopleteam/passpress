<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPal Orders v2 REST via wp_remote_post/get — no PayPal SDK dependency.
 * Flow: create order (custom_id = our checkout token) -> redirect to the
 * "approve" link -> PayPal redirects back with its own order id as `token`
 * -> capture the order synchronously on return. The webhook handler exists
 * as a redundant/idempotent confirmation path, not the primary one (unlike
 * Stripe, PayPal's Orders v2 flow completes the charge synchronously via the
 * capture call on return, so it doesn't strictly depend on a webhook).
 *
 * NOTE: like the Stripe gateway, this follows PayPal's documented REST API
 * shape but has not been exercised against a real PayPal sandbox account in
 * this environment (no sandbox credentials were available at build time).
 * Treat as unverified until run once with real sandbox credentials.
 */
class PP_Gateway_Paypal implements PP_Gateway_Interface {

	public function id() {
		return 'paypal';
	}

	public function label() {
		return __( 'PayPal', 'passpress' );
	}

	public function is_configured() {
		$settings = PP_Billing::get_settings();
		return ! empty( $settings['paypal_client_id'] ) && ! empty( $settings['paypal_client_secret'] );
	}

	private function api_base() {
		$settings = PP_Billing::get_settings();
		return 'live' === $settings['paypal_mode'] ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
	}

	private function get_access_token() {
		$settings  = PP_Billing::get_settings();
		$cache_key = 'pp_paypal_token_' . md5( $settings['paypal_mode'] . $settings['paypal_client_id'] );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$this->api_base() . '/v1/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $settings['paypal_client_id'] . ':' . $settings['paypal_client_secret'] ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pp_paypal_auth', $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'pp_paypal_auth', isset( $data['error_description'] ) ? $data['error_description'] : __( 'PayPal authentication failed.', 'passpress' ) );
		}

		set_transient( $cache_key, $data['access_token'], max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ) );

		return $data['access_token'];
	}

	public function initiate( $billing_row, $plan, $user ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'error' => $token->get_error_message() );
		}

		$success_url = add_query_arg(
			array(
				'passpress_return' => 1,
				'gateway'          => 'paypal',
			),
			PP_Billing::checkout_url( $plan->ID, 'renewal' === $billing_row->type ? $billing_row->membership_id : 0 )
		);
		$cancel_url = add_query_arg( 'passpress_cancelled', 1, PP_Billing::checkout_url( $plan->ID, 'renewal' === $billing_row->type ? $billing_row->membership_id : 0 ) );

		$body = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(
				array(
					'custom_id'   => $billing_row->checkout_token,
					'description' => wp_strip_all_tags( $plan->post_title ),
					'amount'      => array(
						'currency_code' => strtoupper( $billing_row->currency ),
						'value'         => number_format( (float) $billing_row->amount, 2, '.', '' ),
					),
				),
			),
			'application_context' => array(
				'return_url' => $success_url,
				'cancel_url' => $cancel_url,
				'user_action' => 'PAY_NOW',
			),
		);

		$response = wp_remote_post(
			$this->api_base() . '/v2/checkout/orders',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'    => 'Bearer ' . $token,
					'Content-Type'     => 'application/json',
					'PayPal-Request-Id' => $billing_row->checkout_token,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 || empty( $data['id'] ) ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'PayPal could not start the order.', 'passpress' );
			return array( 'error' => $message );
		}

		PP_Billing_History::set_gateway_ref( $billing_row->id, $data['id'] );

		$approve_url = '';
		foreach ( (array) ( $data['links'] ?? array() ) as $link ) {
			if ( isset( $link['rel'] ) && 'approve' === $link['rel'] ) {
				$approve_url = $link['href'];
				break;
			}
		}

		if ( ! $approve_url ) {
			return array( 'error' => __( 'PayPal did not return an approval link.', 'passpress' ) );
		}

		return array( 'redirect' => $approve_url );
	}

	public function handle_return() {
		$order_id = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- PayPal's own order id, confirmed via the capture API call below

		if ( ! $order_id ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Unable to confirm PayPal payment.', 'passpress' ),
			);
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array(
				'state'   => 'error',
				'message' => $token->get_error_message(),
			);
		}

		$response = wp_remote_post(
			$this->api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'state'   => 'error',
				'message' => $response->get_error_message(),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['status'] ) || 'COMPLETED' !== $data['status'] ) {
			return array(
				'state'   => 'error',
				'message' => __( 'PayPal payment was not completed.', 'passpress' ),
			);
		}

		$purchase_unit = $data['purchase_units'][0] ?? array();
		$capture       = $purchase_unit['payments']['captures'][0] ?? array();
		$checkout_token = ! empty( $capture['custom_id'] ) ? $capture['custom_id'] : ( $purchase_unit['custom_id'] ?? '' );

		if ( ! $checkout_token ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Could not match this PayPal payment to a pending order.', 'passpress' ),
			);
		}

		PP_Billing::complete_payment(
			$checkout_token,
			'paypal',
			! empty( $capture['id'] ) ? $capture['id'] : $order_id,
			wp_json_encode( $data )
		);

		return array(
			'state'   => 'success',
			'message' => '',
		);
	}

	/**
	 * wp_ajax_nopriv_passpress_paypal_webhook — redundant confirmation only;
	 * verifies via PayPal's own verify-webhook-signature API rather than a
	 * self-implemented HMAC (PayPal doesn't document a simple local scheme
	 * the way Stripe does).
	 */
	public static function handle_webhook() {
		$settings = PP_Billing::get_settings();
		$payload  = file_get_contents( 'php://input' );
		$event    = json_decode( $payload, true );

		if ( empty( $settings['paypal_webhook_id'] ) || empty( $event['event_type'] ) ) {
			status_header( 400 );
			exit;
		}

		$gateway = new self();
		$token   = $gateway->get_access_token();
		if ( is_wp_error( $token ) ) {
			status_header( 500 );
			exit;
		}

		$verify_body = array(
			'transmission_id'   => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) : '',
			'transmission_time' => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) : '',
			'cert_url'          => isset( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) : '',
			'auth_algo'         => isset( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) : '',
			'transmission_sig'  => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) : '',
			'webhook_id'        => $settings['paypal_webhook_id'],
			'webhook_event'     => $event,
		);

		$verify_response = wp_remote_post(
			$gateway->api_base() . '/v1/notifications/verify-webhook-signature',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $verify_body ),
			)
		);

		$verify_data = is_wp_error( $verify_response ) ? array() : json_decode( wp_remote_retrieve_body( $verify_response ), true );

		if ( empty( $verify_data['verification_status'] ) || 'SUCCESS' !== $verify_data['verification_status'] ) {
			status_header( 400 );
			echo 'invalid signature';
			exit;
		}

		if ( 'PAYMENT.CAPTURE.COMPLETED' === $event['event_type'] ) {
			$resource       = $event['resource'] ?? array();
			$checkout_token = $resource['custom_id'] ?? '';
			if ( $checkout_token ) {
				PP_Billing::complete_payment( $checkout_token, 'paypal', $resource['id'] ?? '', wp_json_encode( $resource ) );
			}
		}

		status_header( 200 );
		echo 'ok';
		exit;
	}
}
