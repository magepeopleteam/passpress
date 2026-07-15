<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification toggles panel (rendered inside PP_Settings_Page).
 * Renewal-reminder timing stays on the Billing panel (renewal_reminder_days).
 */
class PP_Notification_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_notification_settings_group', 'passpress_notification_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = PP_Notifications::default_settings();
		$sanitized = array();

		$sanitized['welcome_enabled']          = ! empty( $input['welcome_enabled'] ) ? 1 : 0;
		$sanitized['booking_reminder_enabled'] = ! empty( $input['booking_reminder_enabled'] ) ? 1 : 0;
		$sanitized['booking_reminder_days']    = isset( $input['booking_reminder_days'] ) ? max( 1, absint( $input['booking_reminder_days'] ) ) : $defaults['booking_reminder_days'];
		$sanitized['payment_failed_enabled']   = ! empty( $input['payment_failed_enabled'] ) ? 1 : 0;
		$sanitized['birthday_enabled']         = ! empty( $input['birthday_enabled'] ) ? 1 : 0;

		return $sanitized;
	}

	/**
	 * @deprecated Use PP_Settings_Page::render() with tab=notifications.
	 */
	public static function render() {
		$_GET['tab'] = 'notifications';
		PP_Settings_Page::render();
	}

	public static function render_panel() {
		$settings = PP_Notifications::get_settings();
		?>
		<div class="passpress-settings-panel" id="passpress-panel-notifications">
			<header class="passpress-settings-panel-header">
				<p class="passpress-settings-panel-eyebrow"><?php esc_html_e( 'Email', 'passpress' ); ?></p>
				<h2><?php esc_html_e( 'Notifications', 'passpress' ); ?></h2>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: link to Billing settings tab */
							__( 'All notifications are sent by email only. Renewal reminder timing is under %s.', 'passpress' ),
							'<a href="' . esc_url( PP_Settings_Page::url( 'billing' ) ) . '">' . esc_html__( 'Payment Method', 'passpress' ) . '</a>'
						),
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</p>
			</header>

			<form method="post" action="options.php" class="passpress-settings-form">
				<?php settings_fields( 'passpress_notification_settings_group' ); ?>

				<section class="passpress-settings-card">
					<div class="passpress-settings-card-head">
						<h3><?php esc_html_e( 'Member emails', 'passpress' ); ?></h3>
						<p><?php esc_html_e( 'Turn each message on or off. Off emails are never sent.', 'passpress' ); ?></p>
					</div>

					<label class="passpress-settings-toggle-row">
						<span class="passpress-settings-toggle-copy">
							<span class="passpress-settings-toggle-title"><?php esc_html_e( 'Welcome email', 'passpress' ); ?></span>
							<span class="passpress-settings-toggle-desc"><?php esc_html_e( 'Sent when a new membership or visitor pass is issued.', 'passpress' ); ?></span>
						</span>
						<span class="passpress-pm-switch">
							<input type="checkbox" name="passpress_notification_settings[welcome_enabled]" value="1" <?php checked( ! empty( $settings['welcome_enabled'] ) ); ?>>
							<span class="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<label class="passpress-settings-toggle-row">
						<span class="passpress-settings-toggle-copy">
							<span class="passpress-settings-toggle-title"><?php esc_html_e( 'Booking reminder', 'passpress' ); ?></span>
							<span class="passpress-settings-toggle-desc"><?php esc_html_e( 'Reminder before an upcoming facility or class booking.', 'passpress' ); ?></span>
						</span>
						<span class="passpress-pm-switch">
							<input type="checkbox" name="passpress_notification_settings[booking_reminder_enabled]" value="1" <?php checked( ! empty( $settings['booking_reminder_enabled'] ) ); ?>>
							<span class="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<div class="pp-field passpress-settings-nested-field">
						<div class="pp-label-row">
							<label class="pp-label" for="pp_booking_reminder_days"><?php esc_html_e( 'Days before the booking', 'passpress' ); ?></label>
						</div>
						<input type="number" id="pp_booking_reminder_days" name="passpress_notification_settings[booking_reminder_days]" value="<?php echo esc_attr( $settings['booking_reminder_days'] ); ?>" min="1" max="14" class="pp-input pp-input-narrow">
					</div>

					<label class="passpress-settings-toggle-row">
						<span class="passpress-settings-toggle-copy">
							<span class="passpress-settings-toggle-title"><?php esc_html_e( 'Payment failed', 'passpress' ); ?></span>
							<span class="passpress-settings-toggle-desc"><?php esc_html_e( 'Sent when a checkout payment attempt fails.', 'passpress' ); ?></span>
						</span>
						<span class="passpress-pm-switch">
							<input type="checkbox" name="passpress_notification_settings[payment_failed_enabled]" value="1" <?php checked( ! empty( $settings['payment_failed_enabled'] ) ); ?>>
							<span class="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<label class="passpress-settings-toggle-row">
						<span class="passpress-settings-toggle-copy">
							<span class="passpress-settings-toggle-title"><?php esc_html_e( 'Birthday greeting', 'passpress' ); ?></span>
							<span class="passpress-settings-toggle-desc"><?php esc_html_e( 'Sent on a member\'s birthday from the date saved on My Pass.', 'passpress' ); ?></span>
						</span>
						<span class="passpress-pm-switch">
							<input type="checkbox" name="passpress_notification_settings[birthday_enabled]" value="1" <?php checked( ! empty( $settings['birthday_enabled'] ) ); ?>>
							<span class="passpress-pm-switch-slider"></span>
						</span>
					</label>
				</section>

				<div class="passpress-settings-actions">
					<button type="submit" class="pp-btn-solid"><?php esc_html_e( 'Save notification settings', 'passpress' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}
}
