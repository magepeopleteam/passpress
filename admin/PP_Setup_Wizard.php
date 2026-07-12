<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business Type Selection + one-click Business Template import. Every
 * business type in the product roadmap now has real seed data (see
 * PP_Business_Templates::get_available()); the enabled/disabled/"Coming
 * soon" branching below is kept as-is in case a future template is added
 * before its data file is ready.
 */
class PP_Setup_Wizard {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		if ( isset( $_POST['pp_import_template'] ) && check_admin_referer( 'pp_setup_wizard' ) ) {
			$slug   = isset( $_POST['business_type'] ) ? sanitize_key( $_POST['business_type'] ) : '';
			$result = PP_Business_Templates::import( $slug );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'passpress', 'pp_template_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'passpress', 'pp_template_success', __( 'Business template imported! Sample plans, a facility, and pages were created.', 'passpress' ), 'success' );
			}
		}

		settings_errors( 'passpress' );

		$available = PP_Business_Templates::get_available();
		$roadmap   = PP_Business_Templates::get_roadmap();
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'PassPress Setup Wizard', 'passpress' ); ?></h1>
			<p><?php esc_html_e( 'Choose a business type to import sample membership plans, facilities, and pages.', 'passpress' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'pp_setup_wizard' ); ?>
				<div class="passpress-template-grid">
					<?php foreach ( $roadmap as $slug => $label ) :
						$enabled  = isset( $available[ $slug ] );
						$imported = PP_Business_Templates::is_imported( $slug );
						?>
						<label class="passpress-template-card <?php echo $enabled ? '' : 'passpress-template-disabled'; ?>">
							<input type="radio" name="business_type" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( ! $enabled ); ?> <?php checked( $enabled && 1 === count( $available ) ); ?>>
							<span class="passpress-template-label"><?php echo esc_html( $label ); ?></span>
							<?php if ( $imported ) : ?>
								<span class="passpress-template-badge passpress-template-badge-done"><?php esc_html_e( 'Imported', 'passpress' ); ?></span>
							<?php elseif ( ! $enabled ) : ?>
								<span class="passpress-template-badge"><?php esc_html_e( 'Coming soon', 'passpress' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php submit_button( __( 'Import Template', 'passpress' ), 'primary', 'pp_import_template' ); ?>
			</form>
		</div>
		<?php
	}
}
