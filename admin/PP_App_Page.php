<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mounts the React admin app (admin-app/, built to
 * assets/admin/build/admin-app.js). Every passpress-* submenu slug that has
 * been ported renders this same shell — the React router (admin-app/src/router.jsx)
 * reads ?page= from the URL to decide which screen to show, so WP's own
 * submenu links/capability gating/bookmarks keep working unchanged.
 */
class PP_App_Page {

	public static function render() {
		?>
		<div class="wrap">
			<div id="passpress-root">
				<noscript>
					<p><?php esc_html_e( 'PassPress requires JavaScript to be enabled in your browser.', 'passpress' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Consolidates the legacy-CPT-screen redirects that used to live on each
	 * retired admin/PP_*_List.php class (maybe_redirect_legacy_list() /
	 * maybe_redirect_legacy()) — anyone hitting the native
	 * edit.php?post_type=pp_facility / pp_class_session / pp_coupon screens,
	 * or a pp_coupon post.php?action=edit deep link, still lands on the
	 * React card-grid page instead of a confusing default CPT list/editor.
	 */
	public static function maybe_redirect_legacy_cpt_screens() {
		global $pagenow;

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		$redirects = array(
			'pp_facility'      => 'passpress-facilities',
			'pp_class_session' => 'passpress-class-sessions',
			'pp_coupon'        => 'passpress-coupons',
		);

		if ( 'edit.php' === $pagenow && ! empty( $_GET['post_type'] ) && isset( $redirects[ $_GET['post_type'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=' . $redirects[ $_GET['post_type'] ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			exit;
		}

		if ( 'post-new.php' === $pagenow && ! empty( $_GET['post_type'] ) && 'pp_coupon' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=passpress-coupons' ) );
			exit;
		}

		if ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post    = get_post( $post_id );
			if ( $post && isset( $redirects[ $post->post_type ] ) ) {
				wp_safe_redirect( add_query_arg( 'edit', $post_id, admin_url( 'admin.php?page=' . $redirects[ $post->post_type ] ) ) );
				exit;
			}
		}
	}

	/**
	 * Sends the two hidden legacy settings slugs (passpress-billing-settings,
	 * passpress-notification-settings — bookmarks from before the unified
	 * Settings page existed) to their matching tab, same target
	 * PP_Settings_Page::render_legacy_billing()/render_legacy_notifications()
	 * used to redirect to before that class was retired.
	 */
	public static function maybe_redirect_legacy_settings_slugs() {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map  = array(
			'passpress-billing-settings'      => 'billing',
			'passpress-notification-settings' => 'notifications',
		);

		if ( ! isset( $map[ $page ] ) || ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=passpress-settings&tab=' . $map[ $page ] ) );
		exit;
	}
}
