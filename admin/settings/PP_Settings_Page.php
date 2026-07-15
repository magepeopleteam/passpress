<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-page Settings hub: left sidebar navigation, right-side panels for
 * General, Billing, and Notifications. Replaces the three separate submenu
 * items; old URLs redirect here with the matching tab.
 */
class PP_Settings_Page {

	const PAGE_SLUG = 'passpress-settings';

	/**
	 * @return array<string, array{label: string, icon: string, desc: string}>
	 */
	public static function tabs() {
		return array(
			'general'       => array(
				'label' => __( 'General', 'passpress' ),
				'icon'  => 'dashicons-admin-generic',
				'desc'  => __( 'Currency, dates & pass display', 'passpress' ),
			),
			'billing'       => array(
				'label' => __( 'Payment Method', 'passpress' ),
				'icon'  => 'dashicons-money-alt',
				'desc'  => __( 'Checkout gateways & renewals', 'passpress' ),
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'passpress' ),
				'icon'  => 'dashicons-email-alt',
				'desc'  => __( 'Email alerts for members', 'passpress' ),
			),
		);
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy' ) );
	}

	/**
	 * Send bookmarks for the old Billing / Notification settings screens
	 * to the unified page with the right tab (before any admin HTML).
	 */
	public static function maybe_redirect_legacy() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		$map  = array(
			'passpress-billing-settings'      => 'billing',
			'passpress-notification-settings' => 'notifications',
		);

		if ( ! isset( $map[ $page ] ) || ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		wp_safe_redirect( self::url( $map[ $page ] ) );
		exit;
	}

	/** Fallback if admin_init redirect was skipped. */
	public static function render_legacy_billing() {
		wp_safe_redirect( self::url( 'billing' ) );
		exit;
	}

	/** Fallback if admin_init redirect was skipped. */
	public static function render_legacy_notifications() {
		wp_safe_redirect( self::url( 'notifications' ) );
		exit;
	}

	public static function current_tab() {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	public static function url( $tab = 'general' ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$tabs        = self::tabs();
		$current_tab = self::current_tab();

		// options.php stores the success notice in a transient; plugin pages must print it.
		if ( ! empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$stored = get_transient( 'settings_errors' );
			if ( empty( $stored ) ) {
				add_settings_error(
					'passpress',
					'settings_updated',
					__( 'Settings saved.', 'passpress' ),
					'success'
				);
			}
		}
		?>
		<div class="wrap passpress-wrap passpress-settings-page">
			<div class="passpress-settings-page-header">
				<div class="passpress-settings-page-copy">
					<p class="passpress-settings-page-eyebrow"><?php esc_html_e( 'Configuration', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Settings', 'passpress' ); ?></h1>
					<p class="passpress-settings-page-desc">
						<?php esc_html_e( 'Tune currency, checkout, and the emails members receive.', 'passpress' ); ?>
					</p>
				</div>
			</div>

			<div class="passpress-settings-notices">
				<?php settings_errors(); ?>
			</div>

			<div class="passpress-settings-layout">
				<nav class="passpress-settings-sidebar" aria-label="<?php esc_attr_e( 'Settings sections', 'passpress' ); ?>">
					<p class="passpress-settings-sidebar-label"><?php esc_html_e( 'Sections', 'passpress' ); ?></p>
					<ul class="passpress-settings-nav">
						<?php foreach ( $tabs as $slug => $tab ) : ?>
							<li>
								<a
									href="<?php echo esc_url( self::url( $slug ) ); ?>"
									class="passpress-settings-nav-link <?php echo $slug === $current_tab ? 'is-active' : ''; ?>"
									data-tab="<?php echo esc_attr( $slug ); ?>"
								>
									<span class="passpress-settings-nav-icon">
										<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
									</span>
									<span class="passpress-settings-nav-text">
										<span class="passpress-settings-nav-label"><?php echo esc_html( $tab['label'] ); ?></span>
										<span class="passpress-settings-nav-desc"><?php echo esc_html( $tab['desc'] ); ?></span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<div class="passpress-settings-content" data-active-tab="<?php echo esc_attr( $current_tab ); ?>">
					<?php
					switch ( $current_tab ) {
						case 'billing':
							PP_Billing_Settings::render_panel();
							break;
						case 'notifications':
							PP_Notification_Settings::render_panel();
							break;
						case 'general':
						default:
							PP_Settings::render_panel();
							break;
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
