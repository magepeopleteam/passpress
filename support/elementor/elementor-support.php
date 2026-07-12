<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor bridge — deliberately a thin detection stub, same shape and
 * same reasoning as PP_Gateway_Woo_Subscriptions (see that class's
 * docblock): Elementor is not installed on this site, so there is no way
 * to verify real widget registration (Elementor\Widget_Base subclasses,
 * `elementor/widgets/register` hook name/signature, controls API) even at
 * a basic "loads without a fatal error" level against the actual installed
 * version. Writing speculative widget code against an unverifiable API
 * would look done without being done — see CLAUDE.md's Phase 2 notes on
 * the WC Subscriptions bridge for the same call.
 *
 * What this class actually does: detects whether Elementor is active and,
 * if so, shows a plain admin notice saying the widgets aren't built yet.
 * Real implementation (one Elementor\Widget_Base subclass per shortcode —
 * Membership Plans, My Pass, Booking Calendar, Class Schedule — each
 * wrapping the same canonical shortcode render function used by
 * inc/PP_Shortcodes.php and inc/PP_Blocks.php, per CLAUDE.md's "one
 * canonical render function per feature" rule) should happen in an
 * environment where Elementor can actually be installed and tested against.
 */
class PP_Elementor_Support {

	public static function init() {
		if ( ! pp_is_elementor_active() ) {
			return;
		}

		add_action( 'admin_notices', array( __CLASS__, 'show_not_implemented_notice' ) );
	}

	public static function show_not_implemented_notice() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php esc_html_e( 'PassPress: Elementor is active, but dedicated PassPress widgets are not built yet. Use the [passpress_my_pass], [passpress_membership_plans], [passpress_booking_calendar], and [passpress_class_schedule] shortcodes (or the matching Gutenberg blocks) inside an Elementor Shortcode/HTML widget instead.', 'passpress' ); ?></p>
		</div>
		<?php
	}
}
