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
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
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

		$product_id = self::get_product_id_for_plan( $plan_id );
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			return '';
		}

		$settings  = PP_Billing::get_settings();
		$cart_url  = wc_get_cart_url();
		$target    = ( 'cart' === $settings['wc_add_to_cart_redirect'] ) ? $cart_url : wc_get_checkout_url();

		if ( ! empty( $settings['wc_require_login'] ) && ! is_user_logged_in() ) {
			return wp_login_url( add_query_arg( 'add-to-cart', $product_id, $target ) );
		}

		return add_query_arg( 'add-to-cart', $product_id, $target );
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
