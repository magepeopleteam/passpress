<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe Checkout Sessions via wp_remote_post — no Stripe SDK/Composer
 * dependency. Assumes 2-decimal currencies (usd/eur/gbp/...); zero-decimal
 * currencies like JPY are a known limitation, not handled.
 *
 * NOTE: this code follows Stripe's documented REST API + webhook signature
 * algorithm, but has not been exercised against a real Stripe account in
 * this environment (no sandbox/test secret key was available at build
 * time) — the request/response shapes are correct per Stripe's docs, but
 * treat as unverified until run once with a real test key. See CLAUDE.md.
 */
class PP_Gateway_Stripe implements PP_Gateway_Interface {

	const API_BASE = 'https://api.stripe.com/v1';

	public function id() {
		return 'stripe';
	}

	public function label() {
		return __( 'Credit/Debit Card (Stripe)', 'passpress' );
	}

	public function is_configured() {
		$settings = PP_Billing::get_settings();
		return ! empty( $settings['stripe_secret_key'] );
	}

	public function initiate( $billing_row, $plan, $user ) {
		$settings   = PP_Billing::get_settings();
		$secret_key = $settings['stripe_secret_key'];

		$success_url = add_query_arg(
			array(
				'passpress_return' => 1,
				'gateway'          => 'stripe',
				'session_id'       => '{CHECKOUT_SESSION_ID}',
			),
			PP_Billing::checkout_url( $plan->ID, $billing_row->type === 'renewal' ? $billing_row->membership_id : 0 )
		);
		// {CHECKOUT_SESSION_ID} must stay a literal placeholder — Stripe substitutes it,
		// but add_query_arg() would otherwise urlencode the braces.
		$success_url = str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $success_url );

		$cancel_url = add_query_arg( 'passpress_cancelled', 1, PP_Billing::checkout_url( $plan->ID, $billing_row->type === 'renewal' ? $billing_row->membership_id : 0 ) );

		$body = array(
			'mode'                  => 'payment',
			'client_reference_id'   => $billing_row->checkout_token,
			'customer_email'        => $user->user_email,
			'success_url'           => $success_url,
			'cancel_url'            => $cancel_url,
			'payment_method_types'  => array( 'card' ),
			'line_items'            => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $billing_row->currency,
						'unit_amount'  => (int) round( $billing_row->amount * 100 ),
						'product_data' => array(
							'name' => $plan->post_title,
						),
					),
				),
			),
		);

		$response = wp_remote_post(
			self::API_BASE . '/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $secret_key . ':' ),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $data['url'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Stripe could not start the checkout session.', 'passpress' );
			return array( 'error' => $message );
		}

		PP_Billing_History::set_gateway_ref( $billing_row->id, $data['id'] );

		return array( 'redirect' => $data['url'] );
	}

	public function handle_return() {
		$settings   = PP_Billing::get_settings();
		$secret_key = $settings['stripe_secret_key'];
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- confirmed server-side against Stripe's API below, not trusted as-is

		if ( ! $session_id || ! $secret_key ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Unable to confirm Stripe payment.', 'passpress' ),
			);
		}

		$response = wp_remote_get(
			self::API_BASE . '/checkout/sessions/' . rawurlencode( $session_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $secret_key . ':' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'state'   => 'error',
				'message' => $response->get_error_message(),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['payment_status'] ) || 'paid' !== $data['payment_status'] || empty( $data['client_reference_id'] ) ) {
			if ( ! empty( $data['client_reference_id'] ) ) {
				PP_Billing::fail_payment( $data['client_reference_id'], 'Stripe session not paid: ' . ( $data['payment_status'] ?? 'unknown' ) );
			}
			return array(
				'state'   => 'error',
				'message' => __( 'Payment was not completed.', 'passpress' ),
			);
		}

		PP_Billing::complete_payment(
			$data['client_reference_id'],
			'stripe',
			isset( $data['payment_intent'] ) ? $data['payment_intent'] : $session_id,
			wp_json_encode( $data )
		);

		return array(
			'state'   => 'success',
			'message' => '',
		);
	}

	/**
	 * wp_ajax_nopriv_passpress_stripe_webhook — server-to-server, no nonce
	 * possible; authenticity comes from the Stripe-Signature HMAC instead.
	 */
	public static function handle_webhook() {
		$settings = PP_Billing::get_settings();
		$secret   = $settings['stripe_webhook_secret'];
		$payload  = file_get_contents( 'php://input' );
		$sig      = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

		if ( ! $secret || ! $sig || ! self::verify_signature( $payload, $sig, $secret ) ) {
			status_header( 400 );
			echo 'invalid signature';
			exit;
		}

		$event = json_decode( $payload, true );

		if ( isset( $event['type'] ) && 'checkout.session.completed' === $event['type'] ) {
			$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
			if ( ! empty( $session['payment_status'] ) && 'paid' === $session['payment_status'] && ! empty( $session['client_reference_id'] ) ) {
				PP_Billing::complete_payment(
					$session['client_reference_id'],
					'stripe',
					isset( $session['payment_intent'] ) ? $session['payment_intent'] : $session['id'],
					wp_json_encode( $session )
				);
			}
		}

		status_header( 200 );
		echo 'ok';
		exit;
	}

	/**
	 * Stripe-Signature header: "t=<timestamp>,v1=<hmac>[,v1=<hmac>...]".
	 * Signed string is "{timestamp}.{raw_payload}", HMAC-SHA256 with the
	 * webhook signing secret. Multiple v1 values can appear during secret
	 * rotation — any match is accepted.
	 */
	private static function verify_signature( $payload, $sig_header, $secret ) {
		$parts = array();
		foreach ( explode( ',', $sig_header ) as $part ) {
			$kv = explode( '=', $part, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parts['t'][0] . '.' . $payload, $secret );

		foreach ( $parts['v1'] as $candidate ) {
			if ( hash_equals( $expected, $candidate ) ) {
				return true;
			}
		}

		return false;
	}
}
