<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional WooCommerce storefront bridge. Entirely additive: native checkout
 * (PP_Billing) keeps working unchanged whether or not WooCommerce is installed;
 * this just gives every plan a second, optional path to purchase through the
 * WC cart/checkout. Memberships and renewals stay in PassPress — WooCommerce
 * Subscriptions is not used or required.
 *
 * Each pp_membership_plan gets one auto-synced, hidden (catalog_visibility
 * = 'hidden', not shown in shop/search) WC_Product mirroring its name and
 * price. Customers reach it only via the "Buy via Shop" link this module
 * adds next to the existing native "Subscribe" button on the plan list.
 * When an order containing a linked product is completed, the membership is
 * issued (or renewed, if the buyer already holds an active one on that
 * plan) — mirroring PP_Billing::complete_payment()'s idempotent pattern.
 */
class PP_Shop_WooCommerce {

	const META_PLAN_ID    = '_pp_plan_id';
	const META_PRODUCT_ID = '_pp_wc_product_id';

	public static function init() {
		if ( ! self::is_available() ) {
			return;
		}

		add_action( 'save_post_pp_membership_plan', array( __CLASS__, 'sync_product_for_plan' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order_completed' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'update_option_passpress_billing_settings', array( __CLASS__, 'maybe_sync_all_on_billing_change' ), 10, 2 );

		add_action( 'wp_ajax_passpress_wc_prepare_checkout', array( __CLASS__, 'ajax_prepare_checkout' ) );
		add_action( 'wp_ajax_nopriv_passpress_wc_prepare_checkout', array( __CLASS__, 'ajax_prepare_checkout' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_embed_checkout' ), 5 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_modal_coupon_fee' ) );
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'prefill_checkout_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'keep_embed_on_order_received' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'append_duration_to_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_order_item_name', array( __CLASS__, 'append_duration_to_order_item_name' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_duration_to_order_item' ), 10, 4 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_membership_info_summary' ), 5 );

		// Always register name-normalization for modal checkouts (incl. wc-ajax=checkout).
		add_action( 'woocommerce_before_checkout_process', array( __CLASS__, 'ensure_billing_last_name_before_process' ), 1 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'normalize_embed_posted_data' ), 5 );

		add_action( 'woocommerce_init', array( __CLASS__, 'maybe_register_embed_filters_from_session' ) );

		if ( ! empty( $_GET['passpress_wc_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::register_embed_checkout_filters();
		}
	}

	/**
	 * Whether the current request is the PassPress modal checkout iframe.
	 */
	public static function is_embed_request() {
		if ( ! empty( $_GET['passpress_wc_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		return self::has_embed_session();
	}

	/**
	 * Session flag set by Complete Registration so AJAX place-order still knows
	 * this is a modal checkout (no passpress_wc_embed query arg on wc-ajax).
	 */
	public static function has_embed_session() {
		return function_exists( 'WC' ) && WC()->session && (bool) WC()->session->get( 'pp_modal_embed' );
	}

	public static function maybe_register_embed_filters_from_session() {
		if ( self::has_embed_session() ) {
			self::register_embed_checkout_filters();
		}
	}

	/**
	 * Compact field / chrome filters used only inside the modal iframe checkout.
	 */
	public static function register_embed_checkout_filters() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'compact_embed_checkout_fields' ), 999 );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'compact_embed_default_address_fields' ), 999 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'compact_embed_country_locale' ), 999 );
		add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		add_filter( 'body_class', array( __CLASS__, 'embed_body_class' ) );
		add_filter( 'gettext', array( __CLASS__, 'embed_gettext' ), 20, 3 );
	}

	/**
	 * Slim billing fields for the modal: full name, phone, email, address, city/postcode/country.
	 * Last name stays as a hidden field so WooCommerce validation still receives it.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public static function compact_embed_checkout_fields( $fields ) {
		if ( isset( $fields['billing']['billing_company'] ) ) {
			unset( $fields['billing']['billing_company'] );
		}
		if ( isset( $fields['billing']['billing_address_2'] ) ) {
			unset( $fields['billing']['billing_address_2'] );
		}
		if ( isset( $fields['order']['order_comments'] ) ) {
			unset( $fields['order']['order_comments'] );
		}
		if ( isset( $fields['shipping'] ) ) {
			$fields['shipping'] = array();
		}

		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['label']    = __( 'Full name', 'passpress' );
			$fields['billing']['billing_first_name']['class']    = array( 'form-row-first' );
			$fields['billing']['billing_first_name']['priority'] = 10;
		}

		// Keep last name hidden — derived from full name.
		if ( isset( $fields['billing']['billing_last_name'] ) ) {
			$fields['billing']['billing_last_name']['required'] = false;
			$fields['billing']['billing_last_name']['class']    = array( 'form-row-wide', 'pp-embed-hidden-field' );
			$fields['billing']['billing_last_name']['priority'] = 15;
			$fields['billing']['billing_last_name']['label']    = '';
		}

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['class']    = array( 'form-row-last' );
			$fields['billing']['billing_phone']['priority'] = 20;
			$fields['billing']['billing_phone']['required'] = false;
		}
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['class']    = array( 'form-row-wide' );
			$fields['billing']['billing_email']['priority'] = 30;
		}
		if ( isset( $fields['billing']['billing_address_1'] ) ) {
			$fields['billing']['billing_address_1']['label']    = __( 'Address', 'passpress' );
			$fields['billing']['billing_address_1']['class']    = array( 'form-row-wide', 'address-field' );
			$fields['billing']['billing_address_1']['priority'] = 40;
		}
		if ( isset( $fields['billing']['billing_country'] ) ) {
			$fields['billing']['billing_country']['class']    = array( 'form-row-wide', 'address-field', 'update_totals_on_change' );
			$fields['billing']['billing_country']['priority'] = 50;
		}
		if ( isset( $fields['billing']['billing_city'] ) ) {
			$fields['billing']['billing_city']['class']    = array( 'form-row-first', 'address-field' );
			$fields['billing']['billing_city']['priority'] = 60;
		}
		if ( isset( $fields['billing']['billing_postcode'] ) ) {
			$fields['billing']['billing_postcode']['class']    = array( 'form-row-last', 'address-field' );
			$fields['billing']['billing_postcode']['priority'] = 65;
		}
		if ( isset( $fields['billing']['billing_state'] ) ) {
			$fields['billing']['billing_state']['class']    = array( 'form-row-wide', 'address-field' );
			$fields['billing']['billing_state']['priority'] = 70;
		}

		return $fields;
	}

	/**
	 * Keep city/postcode half-width even when country locale forces form-row-wide.
	 *
	 * @param array $locale Country locale field settings.
	 * @return array
	 */
	public static function compact_embed_country_locale( $locale ) {
		if ( ! self::is_embed_request() && ! self::has_embed_session() ) {
			return $locale;
		}
		foreach ( $locale as $country => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			if ( ! isset( $locale[ $country ]['city'] ) ) {
				$locale[ $country ]['city'] = array();
			}
			$locale[ $country ]['city']['class'] = array( 'form-row-first' );

			if ( ! isset( $locale[ $country ]['postcode'] ) ) {
				$locale[ $country ]['postcode'] = array();
			}
			$locale[ $country ]['postcode']['class'] = array( 'form-row-last' );

			if ( isset( $locale[ $country ]['state'] ) ) {
				$locale[ $country ]['state']['class'] = array( 'form-row-wide' );
			}
			if ( isset( $locale[ $country ]['address_1'] ) ) {
				$locale[ $country ]['address_1']['class'] = array( 'form-row-wide' );
			}
		}
		return $locale;
	}

	/**
	 * Stop locale/default address rules from forcing a visible required last name.
	 *
	 * @param array $fields Default address fields.
	 * @return array
	 */
	public static function compact_embed_default_address_fields( $fields ) {
		if ( isset( $fields['last_name'] ) ) {
			$fields['last_name']['required'] = false;
		}
		return $fields;
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function embed_body_class( $classes ) {
		$classes[] = 'passpress-wc-embed';
		return $classes;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function embed_gettext( $translation, $text, $domain ) {
		if ( 'woocommerce' !== $domain || ! self::is_embed_request() ) {
			return $translation;
		}
		if ( 'Billing details' === $text ) {
			return __( 'Billing location', 'passpress' );
		}
		return $translation;
	}

	/**
	 * Resolve plan ID from a WC product (or cart/order product object).
	 *
	 * @param WC_Product|false|null $product Product.
	 * @return int
	 */
	private static function plan_id_from_product( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return 0;
		}
		return (int) $product->get_meta( self::META_PLAN_ID );
	}

	/**
	 * HTML duration badge for cart / checkout item names.
	 *
	 * @param int $plan_id Plan ID.
	 * @return string
	 */
	private static function duration_badge_html( $plan_id ) {
		$label = pp_get_plan_duration_label( $plan_id );
		if ( ! $label ) {
			return '';
		}
		return ' <span class="passpress-plan-duration-label">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Show plan duration next to the product name in cart / checkout review.
	 *
	 * @param string $name         Item name HTML.
	 * @param array  $cart_item    Cart item.
	 * @param string $cart_item_key Cart key.
	 * @return string
	 */
	public static function append_duration_to_cart_item_name( $name, $cart_item, $cart_item_key ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$plan_id = self::plan_id_from_product( $product );
		if ( ! $plan_id ) {
			return $name;
		}
		return $name . self::duration_badge_html( $plan_id );
	}

	/**
	 * Show plan duration next to the product name on thank-you / order views.
	 *
	 * @param string                $name Item name HTML.
	 * @param WC_Order_Item_Product $item Order item.
	 * @return string
	 */
	public static function append_duration_to_order_item_name( $name, $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return $name;
		}

		$stored = $item->get_meta( '_pp_duration_label' );
		if ( $stored ) {
			return $name . ' <span class="passpress-plan-duration-label">' . esc_html( $stored ) . '</span>';
		}

		$product = $item->get_product();
		$plan_id = self::plan_id_from_product( $product );
		if ( ! $plan_id ) {
			return $name;
		}
		return $name . self::duration_badge_html( $plan_id );
	}

	/**
	 * Persist duration on the order line item for emails / order admin / thank you.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public static function add_duration_to_order_item( $item, $cart_item_key, $values, $order ) {
		$product = isset( $values['data'] ) ? $values['data'] : null;
		$plan_id = self::plan_id_from_product( $product );
		$label   = pp_get_plan_duration_label( $plan_id );
		if ( $label ) {
			$item->add_meta_data( '_pp_duration_label', $label, true );
			$item->add_meta_data( __( 'Duration', 'passpress' ), $label, true );
		}
	}

	/**
	 * Split "Full name" into first/last before WooCommerce validates the order.
	 * Runs for modal sessions and embed URLs (including wc-ajax=checkout).
	 */
	public static function ensure_billing_last_name_before_process() {
		if ( ! self::is_embed_request() ) {
			return;
		}

		$first = isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last  = isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $last || ! $first ) {
			if ( ! $last ) {
				$_POST['billing_last_name'] = '-';
			}
			return;
		}

		$parts = preg_split( '/\s+/', trim( $first ), 2 );
		if ( ! empty( $parts[1] ) ) {
			$_POST['billing_first_name'] = $parts[0];
			$_POST['billing_last_name']  = $parts[1];
		} else {
			$_POST['billing_last_name'] = '-';
		}
	}

	/**
	 * Full-name-only billing: derive last name so WooCommerce validation passes.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public static function normalize_embed_posted_data( $data ) {
		if ( ! self::is_embed_request() ) {
			return $data;
		}

		$first = isset( $data['billing_first_name'] ) ? trim( (string) $data['billing_first_name'] ) : '';
		$last  = isset( $data['billing_last_name'] ) ? trim( (string) $data['billing_last_name'] ) : '';

		if ( $last || ! $first ) {
			if ( ! $last ) {
				$data['billing_last_name'] = '-';
			}
			return $data;
		}

		$parts = preg_split( '/\s+/', $first, 2 );
		if ( ! empty( $parts[1] ) ) {
			$data['billing_first_name'] = $parts[0];
			$data['billing_last_name']  = $parts[1];
		} else {
			$data['billing_last_name'] = '-';
		}

		return $data;
	}

	public static function is_available() {
		return pp_is_woocommerce_active();
	}

	/**
	 * Create-or-update the hidden product for a plan. Guarded on the RAW
	 * meta value (not the cast float) so a premature save_post firing before
	 * _pp_price has actually been written yet (e.g. Business Templates'
	 * wp_insert_post() + separate update_post_meta() calls) is skipped
	 * entirely rather than syncing a $0 product — PP_Business_Templates::import()
	 * calls this again explicitly once the real price is set.
	 */
	public static function sync_product_for_plan( $plan_id ) {
		if ( 'pp_membership_plan' !== get_post_type( $plan_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $plan_id ) || wp_is_post_autosave( $plan_id ) ) {
			return;
		}

		$plan = get_post( $plan_id );
		if ( ! $plan || 'publish' !== $plan->post_status ) {
			return;
		}

		$price_raw = get_post_meta( $plan_id, '_pp_price', true );
		if ( '' === $price_raw ) {
			return;
		}

		$product_id = self::get_product_id_for_plan( $plan_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}

		$product->set_name( $plan->post_title );
		$product->set_regular_price( (string) (float) $price_raw );
		$product->set_price( (string) (float) $price_raw );
		$product->set_virtual( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_sold_individually( true );
		$product->set_status( 'publish' );
		$product->update_meta_data( self::META_PLAN_ID, (int) $plan_id );
		$product->save();

		update_post_meta( $plan_id, self::META_PRODUCT_ID, $product->get_id() );
	}

	public static function get_product_id_for_plan( $plan_id ) {
		return (int) get_post_meta( $plan_id, self::META_PRODUCT_ID, true );
	}

	/**
	 * @return string Empty if WooCommerce (or this plan's linked product)
	 *                isn't available — callers should treat that as "don't
	 *                show a Buy via Shop link", not an error.
	 */
	public static function buy_url( $plan_id ) {
		if ( ! self::is_available() || ! PP_Billing::is_woocommerce_mode() ) {
			return '';
		}

		$plan_id    = absint( $plan_id );
		$product_id = self::get_product_id_for_plan( $plan_id );
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			// Plans created before WooCommerce mode was enabled may not have
			// a linked product yet — sync lazily so the plan list CTA works.
			self::sync_product_for_plan( $plan_id );
			$product_id = self::get_product_id_for_plan( $plan_id );
		}
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			return '';
		}

		$settings = PP_Billing::get_settings();
		$cart_url = wc_get_cart_url();
		$target   = ( 'cart' === $settings['wc_add_to_cart_redirect'] ) ? $cart_url : wc_get_checkout_url();

		if ( ! empty( $settings['wc_require_login'] ) && ! is_user_logged_in() ) {
			return wp_login_url( add_query_arg( 'add-to-cart', $product_id, $target ) );
		}

		return add_query_arg( 'add-to-cart', $product_id, $target );
	}

	/**
	 * Create/update hidden WC products for every published plan. Used when
	 * Payment Method switches to WooCommerce so the front-end "Get this pass"
	 * buttons have add-to-cart URLs immediately.
	 */
	public static function sync_all_plans() {
		if ( ! self::is_available() ) {
			return;
		}

		$plan_ids = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $plan_ids as $plan_id ) {
			self::sync_product_for_plan( (int) $plan_id );
		}
	}

	/**
	 * @param mixed $old Previous option value.
	 * @param mixed $new New option value.
	 */
	public static function maybe_sync_all_on_billing_change( $old, $new ) {
		$new = is_array( $new ) ? $new : array();
		if ( empty( $new['payment_method_type'] ) || 'woocommerce' !== $new['payment_method_type'] ) {
			return;
		}
		self::sync_all_plans();
	}

	/**
	 * Issues or renews a membership per PassPress-linked line item once a
	 * WooCommerce order is completed. Idempotent via a _pp_membership_id
	 * flag stored directly on the order item (HPOS-safe: read/written
	 * through the CRUD API, never raw postmeta), so a status transition
	 * firing more than once can't double-issue.
	 */
	public static function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$plan_id = (int) $product->get_meta( self::META_PLAN_ID );
			if ( ! $plan_id ) {
				continue;
			}

			if ( $item->get_meta( '_pp_membership_id' ) ) {
				continue;
			}

			$user_id = $order->get_customer_id();
			if ( ! $user_id ) {
				continue; // Guest checkout: no WP account to attach a pass to.
			}

			$existing   = self::find_active_membership( $user_id, $plan_id );
			$membership = $existing ? PP_Membership_Renewal::renew( $existing->id ) : PP_Membership::issue( $user_id, $plan_id );

			if ( is_wp_error( $membership ) ) {
				PP_Activity_Logger::log( 'shop_order_membership_failed', 'order', $order_id, 'WooCommerce order completed but membership issuance failed: ' . $membership->get_error_message() );
				continue;
			}

			$item->add_meta_data( '_pp_membership_id', $membership->id, true );
			$item->save();

			PP_Activity_Logger::log(
				$existing ? 'shop_order_membership_renewed' : 'shop_order_membership_issued',
				'membership',
				$membership->id,
				sprintf( 'WooCommerce order #%d completed, membership %s.', $order_id, $existing ? 'renewed' : 'issued' )
			);
		}
	}

	private static function find_active_membership( $user_id, $plan_id ) {
		foreach ( PP_Membership::get_active_for_user( $user_id ) as $membership ) {
			if ( (int) $membership->plan_id === (int) $plan_id && PP_Membership::STATUS_ACTIVE === $membership->status ) {
				return $membership;
			}
		}
		return null;
	}

	/**
	 * Registration step from the Get this Pass modal (WooCommerce mode).
	 * Creates/logs in the member, puts the plan product in cart, applies
	 * any PassPress coupon as a cart fee, and returns an embeddable checkout URL.
	 */
	public static function ajax_prepare_checkout() {
		if ( ! self::is_available() || ! PP_Billing::is_woocommerce_mode() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce checkout is not enabled.', 'passpress' ) ) );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $plan_id || ! wp_verify_nonce( $nonce, 'passpress_checkout_' . $plan_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$address   = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$coupon    = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';

		if ( ! $full_name || ! $phone || ! is_email( $email ) || ! $address ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your full name, phone, email, and address.', 'passpress' ) ) );
		}

		$settings = PP_Billing::get_settings();
		if ( ! empty( $settings['wc_require_login'] ) && ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message'  => __( 'Please log in to complete your purchase.', 'passpress' ),
					'loginUrl' => wp_login_url( self::buy_url( $plan_id ) ),
				)
			);
		}

		$user_id = self::ensure_member_account( $full_name, $email, $phone, $address );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array(
					'message'  => $user_id->get_error_message(),
					'loginUrl' => $user_id->get_error_data( 'login_url' ),
				)
			);
		}

		$product_id = self::get_product_id_for_plan( $plan_id );
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			self::sync_product_for_plan( $plan_id );
			$product_id = self::get_product_id_for_plan( $plan_id );
		}
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This plan is not ready for checkout yet.', 'passpress' ) ) );
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		WC()->cart->empty_cart();
		$added = WC()->cart->add_to_cart( $product_id, 1 );
		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not add this pass to your cart. Please try again.', 'passpress' ) ) );
		}

		$discount_amount = 0;
		$applied_code    = '';
		$price           = (float) get_post_meta( $plan_id, '_pp_price', true );

		if ( $coupon ) {
			$result = PP_Coupon::validate( $coupon, $plan_id, $user_id, $price );
			if ( empty( $result['valid'] ) ) {
				wp_send_json_error( array( 'message' => $result['error'] ) );
			}
			$discount_amount = (float) $result['discount_amount'];
			$applied_code    = $result['code'];
		}

		if ( WC()->session ) {
			WC()->session->set( 'pp_modal_embed', 1 );
			WC()->session->set( 'pp_modal_discount', $discount_amount );
			WC()->session->set( 'pp_modal_coupon', $applied_code );
			WC()->session->set(
				'pp_modal_billing',
				array(
					'first_name' => $full_name,
					'phone'      => $phone,
					'email'      => $email,
					'address_1'  => $address,
				)
			);
		}

		// Persist to the WooCommerce customer object so checkout fields fill immediately.
		if ( WC()->customer ) {
			WC()->customer->set_billing_first_name( $full_name );
			WC()->customer->set_billing_last_name( self::split_last_name( $full_name ) );
			WC()->customer->set_billing_phone( $phone );
			WC()->customer->set_billing_email( $email );
			WC()->customer->set_billing_address_1( $address );
			WC()->customer->save();
		}

		WC()->cart->calculate_totals();

		$checkout_url = add_query_arg(
			array(
				'passpress_wc_embed' => '1',
				'plan_id'            => $plan_id,
			),
			wc_get_checkout_url()
		);

		wp_send_json_success(
			array(
				'checkoutUrl' => $checkout_url,
				'userId'      => $user_id,
			)
		);
	}

	/**
	 * Create or reuse a WP user for modal registration, then auto-login.
	 *
	 * @return int|WP_Error
	 */
	private static function ensure_member_account( $full_name, $email, $phone, $address ) {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $full_name,
				)
			);
			self::save_member_profile( $user_id, $phone, $address, $email, $full_name );
			return $user_id;
		}

		$existing = email_exists( $email );
		if ( $existing ) {
			return new WP_Error(
				'email_exists',
				__( 'An account with this email already exists. Please log in to continue.', 'passpress' ),
				array( 'login_url' => wp_login_url( wc_get_checkout_url() ) )
			);
		}

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! $username || username_exists( $username ) ) {
			$username = sanitize_user( 'member_' . wp_generate_password( 8, false ), true );
		}

		$user_id = wc_create_new_customer( $email, $username, wp_generate_password() );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $full_name,
				'first_name'   => $full_name,
			)
		);
		self::save_member_profile( $user_id, $phone, $address, $email, $full_name );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		return $user_id;
	}

	private static function save_member_profile( $user_id, $phone, $address, $email, $full_name = '' ) {
		if ( $full_name ) {
			update_user_meta( $user_id, 'billing_first_name', $full_name );
			update_user_meta( $user_id, 'first_name', $full_name );
			update_user_meta( $user_id, 'billing_last_name', self::split_last_name( $full_name ) );
		}
		update_user_meta( $user_id, 'billing_phone', $phone );
		update_user_meta( $user_id, 'billing_address_1', $address );
		update_user_meta( $user_id, 'billing_email', $email );
		update_user_meta( $user_id, 'shipping_address_1', $address );
	}

	/**
	 * Last name token from a full name (or "-" when only one word).
	 *
	 * @param string $full_name Full name.
	 * @return string
	 */
	private static function split_last_name( $full_name ) {
		$parts = preg_split( '/\s+/', trim( (string) $full_name ), 2 );
		return ! empty( $parts[1] ) ? $parts[1] : '-';
	}

	/**
	 * Apply PassPress coupon discount as a WooCommerce cart fee (negative).
	 */
	public static function apply_modal_coupon_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! WC()->session ) {
			return;
		}
		$discount = (float) WC()->session->get( 'pp_modal_discount' );
		if ( $discount <= 0 ) {
			return;
		}
		$label = __( 'Coupon discount', 'passpress' );
		$code  = WC()->session->get( 'pp_modal_coupon' );
		if ( $code ) {
			$label = sprintf(
				/* translators: %s: coupon code */
				__( 'Coupon (%s)', 'passpress' ),
				$code
			);
		}
		$cart->add_fee( $label, -1 * $discount );
	}

	/**
	 * Prefill WC checkout billing fields from modal registration session.
	 */
	public static function prefill_checkout_from_session( $value, $input ) {
		if ( ! WC()->session ) {
			return $value;
		}
		$billing = WC()->session->get( 'pp_modal_billing' );
		if ( empty( $billing ) || ! is_array( $billing ) ) {
			return $value;
		}

		$full_name = isset( $billing['first_name'] ) ? trim( (string) $billing['first_name'] ) : '';
		$parts     = $full_name ? preg_split( '/\s+/', $full_name, 2 ) : array();

		if ( 'billing_first_name' === $input && $full_name ) {
			return $full_name; // Keep full name visible in the single "Full name" field.
		}
		if ( 'billing_last_name' === $input ) {
			if ( ! empty( $parts[1] ) ) {
				return $parts[1];
			}
			return $value ? $value : '-';
		}

		$map = array(
			'billing_phone'     => 'phone',
			'billing_email'     => 'email',
			'billing_address_1' => 'address_1',
		);
		if ( isset( $map[ $input ] ) && ! empty( $billing[ $map[ $input ] ] ) ) {
			return $billing[ $map[ $input ] ];
		}
		return $value;
	}

	/**
	 * Show Membership Information collected in the Get this Pass modal above WC checkout.
	 */
	public static function render_membership_info_summary() {
		if ( ! self::is_embed_request() && ! self::has_embed_session() ) {
			return;
		}
		if ( ! WC()->session ) {
			return;
		}

		$billing = WC()->session->get( 'pp_modal_billing' );
		if ( empty( $billing ) || ! is_array( $billing ) ) {
			return;
		}

		$name    = isset( $billing['first_name'] ) ? (string) $billing['first_name'] : '';
		$phone   = isset( $billing['phone'] ) ? (string) $billing['phone'] : '';
		$email   = isset( $billing['email'] ) ? (string) $billing['email'] : '';
		$address = isset( $billing['address_1'] ) ? (string) $billing['address_1'] : '';

		if ( ! $name && ! $phone && ! $email && ! $address ) {
			return;
		}
		?>
		<div
			class="passpress-wc-member-summary"
			data-pp-full-name="<?php echo esc_attr( $name ); ?>"
			data-pp-phone="<?php echo esc_attr( $phone ); ?>"
			data-pp-email="<?php echo esc_attr( $email ); ?>"
			data-pp-address="<?php echo esc_attr( $address ); ?>"
		>
			<h3 class="passpress-wc-member-summary-title"><?php esc_html_e( 'Membership Information', 'passpress' ); ?></h3>
			<div class="passpress-wc-member-summary-grid">
				<div class="passpress-wc-member-summary-col passpress-wc-member-summary-col-left">
					<?php if ( $name ) : ?>
						<div class="passpress-wc-member-summary-row">
							<span class="passpress-wc-member-summary-label"><?php esc_html_e( 'Full Name', 'passpress' ); ?></span>
							<span class="passpress-wc-member-summary-value"><?php echo esc_html( $name ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<div class="passpress-wc-member-summary-row">
							<span class="passpress-wc-member-summary-label"><?php esc_html_e( 'Email', 'passpress' ); ?></span>
							<span class="passpress-wc-member-summary-value"><?php echo esc_html( $email ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="passpress-wc-member-summary-col passpress-wc-member-summary-col-right">
					<?php if ( $phone ) : ?>
						<div class="passpress-wc-member-summary-row">
							<span class="passpress-wc-member-summary-label"><?php esc_html_e( 'Phone Number', 'passpress' ); ?></span>
							<span class="passpress-wc-member-summary-value"><?php echo esc_html( $phone ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( $address ) : ?>
						<div class="passpress-wc-member-summary-row">
							<span class="passpress-wc-member-summary-label"><?php esc_html_e( 'Address', 'passpress' ); ?></span>
							<span class="passpress-wc-member-summary-value"><?php echo esc_html( $address ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Keep the embed flag when WooCommerce redirects to the thank-you page.
	 *
	 * @param string    $url   Order received URL.
	 * @param WC_Order  $order Order object.
	 */
	public static function keep_embed_on_order_received( $url, $order ) {
		$embed = ! empty( $_GET['passpress_wc_embed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $embed && function_exists( 'WC' ) && WC()->session ) {
			$embed = (bool) WC()->session->get( 'pp_modal_embed' );
		}
		if ( $embed ) {
			$url = add_query_arg( 'passpress_wc_embed', '1', $url );
		}
		return $url;
	}

	/**
	 * Serve a chrome-free checkout page inside the modal iframe.
	 */
	public static function maybe_serve_embed_checkout() {
		$embed = ! empty( $_GET['passpress_wc_embed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $embed && function_exists( 'WC' ) && WC()->session ) {
			$embed = (bool) WC()->session->get( 'pp_modal_embed' )
				&& ( is_checkout() || is_wc_endpoint_url( 'order-received' ) || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) );
		}
		if ( ! $embed ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		$on_checkout = is_checkout()
			|| is_wc_endpoint_url( 'order-received' )
			|| ( function_exists( 'is_order_received_page' ) && is_order_received_page() );
		if ( ! $on_checkout ) {
			return;
		}

		header( 'X-Frame-Options: SAMEORIGIN' );
		header( "Content-Security-Policy: frame-ancestors 'self'" );
		show_admin_bar( false );
		self::register_embed_checkout_filters();

		include PASSPRESS_PLUGIN_DIR . '/templates/checkout/wc-embed-checkout.php';
		exit;
	}

	public static function add_meta_box() {
		add_meta_box( 'pp_shop_link', __( 'WooCommerce Shop', 'passpress' ), array( __CLASS__, 'render_meta_box' ), 'pp_membership_plan', 'side', 'default' );
	}

	public static function render_meta_box( $post ) {
		$product_id = self::get_product_id_for_plan( $post->ID );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( $product ) {
			?>
			<p>
				<?php esc_html_e( 'Linked to a hidden WooCommerce product:', 'passpress' ); ?><br>
				<a href="<?php echo esc_url( (string) get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( $product->get_name() . ' (#' . $product_id . ')' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'This product is hidden from the shop/search — customers reach it via the "Buy via Shop" link on the plan list.', 'passpress' ); ?></p>
			<?php
		} else {
			?>
			<p><?php esc_html_e( 'A hidden WooCommerce product will be created automatically when you save this plan.', 'passpress' ); ?></p>
			<?php
		}
	}
}
