<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General + QR Code settings panel (rendered inside PP_Settings_Page).
 */
class PP_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_settings_group', 'passpress_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		return array(
			'currency_symbol'  => isset( $input['currency_symbol'] ) ? sanitize_text_field( $input['currency_symbol'] ) : '$',
			'currency_code'    => isset( $input['currency_code'] ) ? strtolower( sanitize_text_field( $input['currency_code'] ) ) : 'usd',
			'date_format'      => isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : 'F j, Y',
			'qr_size'          => isset( $input['qr_size'] ) ? max( 100, min( 400, absint( $input['qr_size'] ) ) ) : 200,
			'show_pin_on_pass' => ! empty( $input['show_pin_on_pass'] ) ? 1 : 0,
		);
	}

	/**
	 * @deprecated Use PP_Settings_Page::render() — kept as alias for old callbacks.
	 */
	public static function render() {
		PP_Settings_Page::render();
	}

	public static function render_panel() {
		$settings = pp_get_settings();
		?>
		<div class="passpress-settings-panel" id="passpress-panel-general">
			<header class="passpress-settings-panel-header">
				<p class="passpress-settings-panel-eyebrow"><?php esc_html_e( 'Basics', 'passpress' ); ?></p>
				<h2><?php esc_html_e( 'General', 'passpress' ); ?></h2>
				<p><?php esc_html_e( 'Currency, dates, and how passes appear to members.', 'passpress' ); ?></p>
			</header>

			<form method="post" action="options.php" class="passpress-settings-form">
				<?php settings_fields( 'passpress_settings_group' ); ?>

				<section class="passpress-settings-card">
					<div class="passpress-settings-card-head">
						<h3><?php esc_html_e( 'Currency & dates', 'passpress' ); ?></h3>
						<p><?php esc_html_e( 'Used on plan cards, checkout, and membership lists.', 'passpress' ); ?></p>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_currency_symbol"><?php esc_html_e( 'Currency symbol', 'passpress' ); ?></label>
							<input type="text" id="pp_currency_symbol" name="passpress_settings[currency_symbol]" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" class="pp-input pp-input-narrow" maxlength="8">
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_currency_code"><?php esc_html_e( 'Currency code', 'passpress' ); ?></label>
							<input type="text" id="pp_currency_code" name="passpress_settings[currency_code]" value="<?php echo esc_attr( $settings['currency_code'] ); ?>" class="pp-input pp-input-narrow" maxlength="3">
							<p class="pp-field-hint"><?php esc_html_e( 'ISO 4217, e.g. usd, eur, gbp — required by Stripe/PayPal.', 'passpress' ); ?></p>
						</div>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_date_format"><?php esc_html_e( 'Date format', 'passpress' ); ?></label>
						<input type="text" id="pp_date_format" name="passpress_settings[date_format]" value="<?php echo esc_attr( $settings['date_format'] ); ?>" class="pp-input">
						<p class="pp-field-hint"><?php esc_html_e( 'PHP date() format for expiry dates on My Pass and in admin lists.', 'passpress' ); ?></p>
					</div>
				</section>

				<section class="passpress-settings-card">
					<div class="passpress-settings-card-head">
						<h3><?php esc_html_e( 'My Pass display', 'passpress' ); ?></h3>
						<p><?php esc_html_e( 'How the digital pass looks when members open it.', 'passpress' ); ?></p>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_qr_size"><?php esc_html_e( 'QR code size (px)', 'passpress' ); ?></label>
						<input type="number" id="pp_qr_size" name="passpress_settings[qr_size]" value="<?php echo esc_attr( $settings['qr_size'] ); ?>" min="100" max="400" step="10" class="pp-input pp-input-narrow">
						<p class="pp-field-hint"><?php esc_html_e( 'Between 100 and 400 pixels.', 'passpress' ); ?></p>
					</div>

					<label class="pp-checkbox-box">
						<input type="checkbox" name="passpress_settings[show_pin_on_pass]" value="1" <?php checked( ! empty( $settings['show_pin_on_pass'] ) ); ?>>
						<span><?php esc_html_e( 'Show PIN code alongside the QR on My Pass', 'passpress' ); ?></span>
					</label>
				</section>

				<div class="passpress-settings-actions">
					<button type="submit" class="pp-btn-solid"><?php esc_html_e( 'Save general settings', 'passpress' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}
}
