<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [passpress_my_pass] — member-facing pass (QR, status, expiry, PIN).
 * [passpress_membership_plans] — public plan list.
 */
class PP_Shortcodes {

	public static function init() {
		add_shortcode( 'passpress_my_pass', array( __CLASS__, 'render_my_pass' ) );
		add_shortcode( 'passpress_membership_plans', array( __CLASS__, 'render_plans' ) );
	}

	public static function render_my_pass( $atts ) {
		ob_start();

		if ( ! is_user_logged_in() ) {
			?>
			<p class="passpress-my-pass-guest">
				<?php esc_html_e( 'Please log in to view your pass.', 'passpress' ); ?>
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'passpress' ); ?></a>
			</p>
			<?php
			return ob_get_clean();
		}

		PP_Frontend::enqueue_my_pass_assets();
		PP_Frontend::enqueue_my_bookings_assets();

		$birthdate_saved = PP_Notifications::maybe_save_birthdate_from_post();

		$memberships = array_map( array( 'PP_Membership_Status', 'maybe_expire' ), PP_Membership::get_active_for_user( get_current_user_id() ) );
		$settings    = pp_get_settings();
		$bookings    = PP_Booking::get_for_user( get_current_user_id() );
		$birthdate   = get_user_meta( get_current_user_id(), 'pp_birthdate', true );

		include PASSPRESS_PLUGIN_DIR . '/templates/my-pass/my-pass.php';

		return ob_get_clean();
	}

	public static function render_plans( $atts ) {
		PP_Frontend::enqueue_plan_list_assets();

		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		$settings = pp_get_settings();

		ob_start();
		include PASSPRESS_PLUGIN_DIR . '/templates/layout/plan-list.php';
		return ob_get_clean();
	}
}
