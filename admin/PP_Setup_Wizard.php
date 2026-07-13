<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business Type Selection + one-click Business Template import.
 * On activation (and whenever this screen loads), options open in a
 * modal so the admin can pick a business type before getting started.
 *
 * Form handling runs on admin_init (before any HTML) so redirects work.
 */
class PP_Setup_Wizard {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Process template import before admin output starts.
	 */
	public static function handle_import() {
		if ( ! isset( $_POST['pp_import_template'] ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'passpress-setup' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		check_admin_referer( 'pp_setup_wizard' );

		$slug   = isset( $_POST['business_type'] ) ? sanitize_key( $_POST['business_type'] ) : '';
		$result = PP_Business_Templates::import( $slug );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'passpress_setup_error',
				$result->get_error_message(),
				60
			);
			wp_safe_redirect( admin_url( 'admin.php?page=passpress-setup' ) );
			exit;
		}

		set_transient(
			'passpress_setup_notice',
			__( 'Business template imported! Sample plans, a facility, and pages were created.', 'passpress' ),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=passpress' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$error = get_transient( 'passpress_setup_error' );
		if ( $error ) {
			delete_transient( 'passpress_setup_error' );
			add_settings_error( 'passpress', 'pp_template_error', $error, 'error' );
		}

		$available    = PP_Business_Templates::get_available();
		$roadmap      = PP_Business_Templates::get_roadmap();
		$active_type  = get_option( 'passpress_active_business_type', '' );
		$is_welcome   = isset( $_GET['pp_welcome'] ) && '1' === $_GET['pp_welcome'];
		$force_modal  = $is_welcome || empty( $active_type );
		$active_label = ( $active_type && isset( $roadmap[ $active_type ] ) ) ? $roadmap[ $active_type ] : '';

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-setup-page">
			<h1><?php esc_html_e( 'PassPress Setup Wizard', 'passpress' ); ?></h1>
			<p class="passpress-setup-intro">
				<?php esc_html_e( 'Choose your business type to import sample membership plans, facilities, and pages. You can reopen this wizard anytime.', 'passpress' ); ?>
			</p>

			<?php if ( $active_label ) : ?>
				<p class="passpress-setup-active">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: business type label */
							__( 'Current business type: %s', 'passpress' ),
							'<strong>' . esc_html( $active_label ) . '</strong>'
						),
						array( 'strong' => array() )
					);
					?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="button button-primary button-hero" id="passpress-open-setup-modal">
					<?php echo $active_type ? esc_html__( 'Change Business Type', 'passpress' ) : esc_html__( 'Select Business Type', 'passpress' ); ?>
				</button>
				<?php if ( $active_type ) : ?>
					<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress' ) ); ?>">
						<?php esc_html_e( 'Go to Dashboard', 'passpress' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>

		<div
			id="passpress-setup-wizard-modal"
			class="passpress-modal-overlay passpress-setup-modal-overlay"
			style="<?php echo $force_modal ? '' : 'display:none;'; ?>"
			data-auto-open="<?php echo $force_modal ? '1' : '0'; ?>"
			data-welcome="<?php echo $is_welcome ? '1' : '0'; ?>"
			role="dialog"
			aria-modal="true"
			aria-labelledby="passpress-setup-modal-title"
		>
			<div class="passpress-modal passpress-setup-modal">
				<div class="pp-modal-header">
					<div>
						<h2 id="passpress-setup-modal-title"><?php esc_html_e( 'Welcome to PassPress', 'passpress' ); ?></h2>
						<p class="passpress-setup-modal-subtitle">
							<?php esc_html_e( 'Select your business type to get started with sample plans, facilities, and pages.', 'passpress' ); ?>
						</p>
					</div>
					<?php if ( ! $is_welcome ) : ?>
						<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=passpress-setup' ) ); ?>" id="passpress-setup-wizard-form">
					<?php wp_nonce_field( 'pp_setup_wizard' ); ?>
					<div class="passpress-template-grid passpress-setup-template-grid">
						<?php
						foreach ( $roadmap as $slug => $label ) :
							$enabled  = isset( $available[ $slug ] );
							$imported = PP_Business_Templates::is_imported( $slug );
							$checked  = ( $active_type && $active_type === $slug ) || ( ! $active_type && $enabled && 1 === count( $available ) );
							?>
							<label class="passpress-template-card <?php echo $enabled ? '' : 'passpress-template-disabled'; ?> <?php echo $checked ? 'is-selected' : ''; ?>">
								<input
									type="radio"
									name="business_type"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php disabled( ! $enabled ); ?>
									<?php checked( $checked ); ?>
								>
								<span class="passpress-template-label"><?php echo esc_html( $label ); ?></span>
								<?php if ( $imported ) : ?>
									<span class="passpress-template-badge passpress-template-badge-done"><?php esc_html_e( 'Imported', 'passpress' ); ?></span>
								<?php elseif ( ! $enabled ) : ?>
									<span class="passpress-template-badge"><?php esc_html_e( 'Coming soon', 'passpress' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="pp-modal-footer passpress-setup-modal-footer">
						<?php if ( $is_welcome ) : ?>
							<a class="pp-btn-outline" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress' ) ); ?>">
								<?php esc_html_e( 'Skip for now', 'passpress' ); ?>
							</a>
						<?php else : ?>
							<button type="button" class="pp-btn-outline passpress-modal-cancel">
								<?php esc_html_e( 'Cancel', 'passpress' ); ?>
							</button>
						<?php endif; ?>
						<?php submit_button( __( 'Import Template & Continue', 'passpress' ), 'primary pp-btn-solid', 'pp_import_template', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
