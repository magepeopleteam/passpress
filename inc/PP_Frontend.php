<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers frontend assets and conditionally enqueues+localizes them.
 *
 * IMPORTANT: the enqueue+localize calls happen here, on 'wp_enqueue_scripts',
 * detected via has_shortcode() on the current post — NOT inside the
 * shortcode render callbacks themselves. On a block theme (full site
 * editing), the site's block template pre-renders post content (running
 * `the_content` / shortcodes, confirmed via debugging) BEFORE the
 * `wp_enqueue_scripts` action fires. A wp_localize_script() call made from
 * inside a shortcode callback gets silently discarded once the real
 * wp_register_script() call for that handle runs afterward on
 * 'wp_enqueue_scripts' — the script tag still prints, but its localized
 * data never does. Detecting the shortcode early and localizing here avoids
 * relying on shortcode-callback timing at all.
 */
class PP_Frontend {

	private static $checkout_modal_queued = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_for_current_page' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_print_checkout_modal' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'add_body_classes' ) );
	}

	public static function register_assets() {
		wp_register_style( 'passpress-frontend', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-frontend.css', array(), PASSPRESS_PLUGIN_VERSION );
		wp_register_script( 'passpress-qrcodejs', PASSPRESS_PLUGIN_URL . '/assets/helper/qrcode/qrcode.min.js', array(), '0.0.2', true );
		wp_register_script( 'passpress-my-pass', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-my-pass.js', array( 'passpress-qrcodejs' ), PASSPRESS_PLUGIN_VERSION, true );
		wp_register_script( 'passpress-booking', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-booking.js', array(), PASSPRESS_PLUGIN_VERSION, true );
		wp_register_script( 'passpress-my-bookings', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-my-bookings.js', array(), PASSPRESS_PLUGIN_VERSION, true );
		wp_register_script( 'passpress-invite-guest', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-invite-guest.js', array(), PASSPRESS_PLUGIN_VERSION, true );
		wp_register_script( 'passpress-class-schedule', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-class-schedule.js', array(), PASSPRESS_PLUGIN_VERSION, true );
		wp_register_script( 'passpress-checkout-modal', PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-checkout-modal.js', array(), PASSPRESS_PLUGIN_VERSION, true );
	}

	/**
	 * Content-based detection, independent of whether the shortcode/block
	 * render callback itself has run yet. Checks both the shortcode and its
	 * Gutenberg block equivalent (inc/PP_Blocks.php) since a page can use
	 * either surface. Shared by maybe_enqueue_for_current_page() and
	 * add_body_classes() so the two can't drift apart.
	 */
	private static function detect_features( $post ) {
		$features = array();

		if ( has_shortcode( $post->post_content, 'passpress_my_pass' ) || has_block( 'passpress/my-pass', $post ) ) {
			$features[] = 'my-pass';
		}

		if ( has_shortcode( $post->post_content, 'passpress_membership_plans' ) || has_block( 'passpress/plan-list', $post ) ) {
			$features[] = 'plan-list';
		}

		if ( has_shortcode( $post->post_content, 'passpress_booking_calendar' ) || has_block( 'passpress/booking-calendar', $post ) ) {
			$features[] = 'booking-calendar';
		}

		if ( has_shortcode( $post->post_content, 'passpress_class_schedule' ) || has_block( 'passpress/class-schedule', $post ) ) {
			$features[] = 'class-schedule';
		}

		return $features;
	}

	public static function maybe_enqueue_for_current_page() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$features = self::detect_features( $post );

		if ( in_array( 'my-pass', $features, true ) ) {
			self::enqueue_my_pass_assets();
			self::enqueue_my_bookings_assets();
			self::enqueue_invite_guest_assets();
		}

		if ( in_array( 'plan-list', $features, true ) ) {
			self::enqueue_plan_list_assets();
		}

		if ( in_array( 'booking-calendar', $features, true ) ) {
			self::enqueue_booking_assets();
		}

		if ( in_array( 'class-schedule', $features, true ) ) {
			self::enqueue_class_schedule_assets();
		}
	}

	/**
	 * Adds a single `passpress-page` class so themes/CSS can target
	 * PassPress pages without relying on specific post IDs or slugs.
	 */
	public static function add_body_classes( $classes ) {
		if ( ! is_singular() ) {
			return $classes;
		}

		$post = get_post();
		if ( ! $post ) {
			return $classes;
		}

		if ( empty( self::detect_features( $post ) ) ) {
			return $classes;
		}

		$classes[] = 'passpress-page';

		return $classes;
	}

	public static function enqueue_my_pass_assets() {
		wp_enqueue_style( 'passpress-frontend' );
		wp_enqueue_script( 'passpress-my-pass' );
		wp_localize_script(
			'passpress-my-pass',
			'PassPressPass',
			array( 'qrSize' => (int) pp_get_setting( 'qr_size', 200 ) )
		);
	}

	public static function enqueue_plan_list_assets() {
		wp_enqueue_style( 'passpress-frontend' );

		$native_ok = PP_Billing::is_native_mode() && PP_Billing::is_billing_available();
		$wc_ok     = PP_Billing::is_woocommerce_mode() && class_exists( 'PP_Shop_WooCommerce' ) && PP_Shop_WooCommerce::is_available();

		if ( ! $native_ok && ! $wc_ok ) {
			return;
		}

		self::$checkout_modal_queued = true;

		$billing  = PP_Billing::get_settings();
		$my_pass  = pp_find_shortcode_page_url( 'passpress_my_pass' );
		$mode     = $wc_ok ? 'woocommerce' : 'native';

		wp_enqueue_script( 'passpress-checkout-modal' );
		wp_localize_script(
			'passpress-checkout-modal',
			'PassPressCheckout',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'paymentMode'         => $mode,
				'isLoggedIn'          => is_user_logged_in(),
				'loginUrl'            => wp_login_url( is_singular() ? get_permalink() : home_url( '/' ) ),
				'passUrl'             => $my_pass ? $my_pass : home_url( '/' ),
				'offlineInstructions' => ! empty( $billing['offline_instructions'] )
					? $billing['offline_instructions']
					: __( 'Please pay via bank transfer or at the front desk. We will confirm your membership once payment is received.', 'passpress' ),
				'i18n'                => array(
					'pass'                 => __( 'Pass', 'passpress' ),
					'payNow'               => __( 'Pay now', 'passpress' ),
					'completeRegistration' => __( 'Complete Registration', 'passpress' ),
					'processing'           => __( 'Processing…', 'passpress' ),
					'needGateway'          => __( 'Please choose a payment method.', 'passpress' ),
					'needMemberInfo'       => __( 'Please fill in all membership information fields.', 'passpress' ),
					'wcSuccess'            => __( 'Payment received! Your membership will activate shortly.', 'passpress' ),
					'error'                => __( 'Something went wrong. Please try again.', 'passpress' ),
				),
			)
		);
	}

	/**
	 * Prints the checkout modal once in the footer so Buy buttons can open it
	 * reliably regardless of shortcode/block render timing.
	 */
	public static function maybe_print_checkout_modal() {
		if ( ! self::$checkout_modal_queued ) {
			return;
		}

		$native_ok = PP_Billing::is_native_mode() && PP_Billing::is_billing_available();
		$wc_ok     = PP_Billing::is_woocommerce_mode() && class_exists( 'PP_Shop_WooCommerce' ) && PP_Shop_WooCommerce::is_available();

		if ( ! $native_ok && ! $wc_ok ) {
			return;
		}

		$payment_mode = $wc_ok ? 'woocommerce' : 'native';
		$gateways     = $native_ok ? PP_Billing::get_checkout_gateways() : array();
		$settings     = pp_get_settings();
		include PASSPRESS_PLUGIN_DIR . '/templates/checkout/checkout-modal.php';
	}

	public static function enqueue_my_bookings_assets() {
		wp_enqueue_script( 'passpress-my-bookings' );
		wp_localize_script(
			'passpress-my-bookings',
			'PassPressMyBookings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pp_booking' ),
				'i18n'    => array(
					'cancelled' => __( 'Cancelled', 'passpress' ),
				),
			)
		);
	}

	/**
	 * facilityId is intentionally NOT part of this localized data — the
	 * booking calendar JS reads it from each `.passpress-booking-calendar`
	 * element's own `data-facility-id` attribute instead, so multiple
	 * calendars (different facilities) on one page each work correctly
	 * rather than sharing one global facility id.
	 */
	public static function enqueue_booking_assets() {
		wp_enqueue_style( 'passpress-frontend' );
		wp_enqueue_script( 'passpress-booking' );
		wp_localize_script(
			'passpress-booking',
			'PassPressBooking',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'pp_booking' ),
				'loginUrl'   => wp_login_url( is_singular() ? get_permalink() : home_url( '/' ) ),
				'isLoggedIn' => is_user_logged_in(),
				'i18n'       => array(
					'loading'  => __( 'Loading…', 'passpress' ),
					'noSlots'  => __( 'No slots available on this date.', 'passpress' ),
					'book'     => __( 'Book', 'passpress' ),
					'waitlist' => __( 'Join Waitlist', 'passpress' ),
					'open'     => __( 'open', 'passpress' ),
					'error'    => __( 'Something went wrong. Please try again.', 'passpress' ),
				),
			)
		);
	}

	public static function enqueue_invite_guest_assets() {
		wp_enqueue_script( 'passpress-invite-guest' );
		wp_localize_script(
			'passpress-invite-guest',
			'PassPressInviteGuest',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pp_invite_guest' ),
				'i18n'    => array(
					'sending' => __( 'Sending…', 'passpress' ),
					'error'   => __( 'Something went wrong. Please try again.', 'passpress' ),
				),
			)
		);
	}

	public static function enqueue_class_schedule_assets() {
		wp_enqueue_style( 'passpress-frontend' );
		wp_enqueue_script( 'passpress-class-schedule' );
		wp_localize_script(
			'passpress-class-schedule',
			'PassPressClassSchedule',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'pp_booking' ),
				'loginUrl'   => wp_login_url( is_singular() ? get_permalink() : home_url( '/' ) ),
				'isLoggedIn' => is_user_logged_in(),
				'i18n'       => array(
					'book'       => __( 'Book', 'passpress' ),
					'booked'     => __( 'Booked', 'passpress' ),
					'waitlist'   => __( 'Join waitlist', 'passpress' ),
					'waitlisted' => __( 'Waitlisted', 'passpress' ),
					'processing' => __( '…', 'passpress' ),
					'full'       => __( 'Full', 'passpress' ),
					'error'      => __( 'Something went wrong. Please try again.', 'passpress' ),
				),
			)
		);
	}
}
