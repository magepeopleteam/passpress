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
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs() {
		return array(
			'general'       => array(
				'label' => __( 'General', 'passpress' ),
				'icon'  => 'dashicons-admin-generic',
			),
			'billing'       => array(
				'label' => __( 'Payment Method', 'passpress' ),
				'icon'  => 'dashicons-money-alt',
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'passpress' ),
				'icon'  => 'dashicons-email-alt',
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
		?>
		<div class="wrap passpress-wrap passpress-settings-page">
			<h1><?php esc_html_e( 'PassPress Settings', 'passpress' ); ?></h1>

			<div class="passpress-settings-layout">
				<nav class="passpress-settings-sidebar" aria-label="<?php esc_attr_e( 'Settings sections', 'passpress' ); ?>">
					<ul class="passpress-settings-nav">
						<?php foreach ( $tabs as $slug => $tab ) : ?>
							<li>
								<a
									href="<?php echo esc_url( self::url( $slug ) ); ?>"
									class="passpress-settings-nav-link <?php echo $slug === $current_tab ? 'is-active' : ''; ?>"
									data-tab="<?php echo esc_attr( $slug ); ?>"
								>
									<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
									<span class="passpress-settings-nav-label"><?php echo esc_html( $tab['label'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<div class="passpress-settings-content">
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
