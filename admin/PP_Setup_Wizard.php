<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business Type Selection + one-click Business Template import.
 * Full-page picker with categorized templates; welcome flow uses the
 * same page with a Skip action rather than a separate sparse modal.
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

		if ( ! $slug ) {
			set_transient(
				'passpress_setup_error',
				__( 'Please select a business template to import.', 'passpress' ),
				60
			);
			wp_safe_redirect( admin_url( 'admin.php?page=passpress-setup' ) );
			exit;
		}

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

	/**
	 * @return array<string, array{label: string, icon: string, types: string[]}>
	 */
	private static function categories() {
		return array(
			'fitness'     => array(
				'label' => __( 'Fitness & health', 'passpress' ),
				'icon'  => 'dashicons-heart',
				'types' => array( 'gym', 'fitness_center', 'health_club', 'swimming_pool', 'recreation_center' ),
			),
			'sports'      => array(
				'label' => __( 'Sports & academies', 'passpress' ),
				'icon'  => 'dashicons-awards',
				'types' => array( 'sports_club', 'football_academy', 'cricket_academy', 'tennis_club', 'badminton_club', 'basketball_club', 'golf_club', 'cycling_club' ),
			),
			'attractions' => array(
				'label' => __( 'Parks & attractions', 'passpress' ),
				'icon'  => 'dashicons-palmtree',
				'types' => array( 'theme_park', 'water_park', 'adventure_park', 'kids_play_zone', 'zoo_pass', 'ski_resort', 'public_park' ),
			),
			'culture'     => array(
				'label' => __( 'Community & culture', 'passpress' ),
				'icon'  => 'dashicons-building',
				'types' => array( 'community_club', 'library_membership', 'museum_pass' ),
			),
			'wellness'    => array(
				'label' => __( 'Mind & movement', 'passpress' ),
				'icon'  => 'dashicons-universal-access-alt',
				'types' => array( 'martial_arts_academy', 'yoga_studio', 'dance_academy' ),
			),
		);
	}

	/**
	 * Short blurbs so each card explains what you'll get.
	 *
	 * @return array<string, string>
	 */
	private static function type_blurbs() {
		return array(
			'gym'                  => __( 'Floor access, day passes, and a sample yoga class.', 'passpress' ),
			'fitness_center'       => __( 'Membership tiers for a full-service fitness hub.', 'passpress' ),
			'health_club'          => __( 'Club memberships with facilities and guest options.', 'passpress' ),
			'swimming_pool'        => __( 'Lane access, swim passes, and pool hours.', 'passpress' ),
			'sports_club'          => __( 'Multi-sport club plans and bookable courts.', 'passpress' ),
			'football_academy'     => __( 'Academy memberships and training sessions.', 'passpress' ),
			'cricket_academy'      => __( 'Coaching plans and ground bookings.', 'passpress' ),
			'tennis_club'          => __( 'Court memberships and timed bookings.', 'passpress' ),
			'badminton_club'       => __( 'Court passes and session schedules.', 'passpress' ),
			'basketball_club'      => __( 'Court access and youth/adult memberships.', 'passpress' ),
			'golf_club'            => __( 'Club memberships without class schedules.', 'passpress' ),
			'community_club'       => __( 'Neighborhood club plans and shared spaces.', 'passpress' ),
			'kids_play_zone'       => __( 'Family day passes and play-area access.', 'passpress' ),
			'theme_park'           => __( 'Ticket-style passes for parks and rides.', 'passpress' ),
			'water_park'           => __( 'Day tickets and seasonal water-park passes.', 'passpress' ),
			'public_park'          => __( 'Simple park access memberships.', 'passpress' ),
			'recreation_center'    => __( 'Rec center plans across shared facilities.', 'passpress' ),
			'library_membership'   => __( 'Library cards without facility booking.', 'passpress' ),
			'museum_pass'          => __( 'Exhibit passes focused on membership, not rooms.', 'passpress' ),
			'zoo_pass'             => __( 'Annual and day zoo admission plans.', 'passpress' ),
			'adventure_park'       => __( 'Activity passes for adventure grounds.', 'passpress' ),
			'ski_resort'           => __( 'Season and day resort passes.', 'passpress' ),
			'cycling_club'         => __( 'Club memberships and group ride sessions.', 'passpress' ),
			'martial_arts_academy' => __( 'Belt-program memberships and class packs.', 'passpress' ),
			'yoga_studio'          => __( 'Studio memberships and weekly class schedules.', 'passpress' ),
			'dance_academy'        => __( 'Dance school memberships and studio sessions.', 'passpress' ),
		);
	}

	/**
	 * Dashicons keyed by business type.
	 *
	 * @return array<string, string>
	 */
	private static function type_icons() {
		return array(
			'gym'                  => 'dashicons-heart',
			'fitness_center'       => 'dashicons-heart',
			'health_club'          => 'dashicons-groups',
			'swimming_pool'        => 'dashicons-admin-site-alt3',
			'sports_club'          => 'dashicons-awards',
			'football_academy'     => 'dashicons-awards',
			'cricket_academy'      => 'dashicons-awards',
			'tennis_club'          => 'dashicons-awards',
			'badminton_club'       => 'dashicons-awards',
			'basketball_club'      => 'dashicons-awards',
			'golf_club'            => 'dashicons-flag',
			'community_club'       => 'dashicons-groups',
			'kids_play_zone'       => 'dashicons-smiley',
			'theme_park'           => 'dashicons-tickets-alt',
			'water_park'           => 'dashicons-admin-site-alt3',
			'public_park'          => 'dashicons-palmtree',
			'recreation_center'    => 'dashicons-building',
			'library_membership'   => 'dashicons-book',
			'museum_pass'          => 'dashicons-building',
			'zoo_pass'             => 'dashicons-carrot',
			'adventure_park'       => 'dashicons-palmtree',
			'ski_resort'           => 'dashicons-location-alt',
			'cycling_club'         => 'dashicons-performance',
			'martial_arts_academy' => 'dashicons-universal-access-alt',
			'yoga_studio'          => 'dashicons-universal-access-alt',
			'dance_academy'        => 'dashicons-format-audio',
		);
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
		$categories   = self::categories();
		$blurbs       = self::type_blurbs();
		$icons        = self::type_icons();
		$active_type  = get_option( 'passpress_active_business_type', '' );
		$is_welcome   = isset( $_GET['pp_welcome'] ) && '1' === $_GET['pp_welcome']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_label = ( $active_type && isset( $roadmap[ $active_type ] ) ) ? $roadmap[ $active_type ] : '';
		$template_count = count( $roadmap );

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-setup-page<?php echo $is_welcome ? ' is-welcome' : ''; ?>">
			<?php if ( $is_welcome ) : ?>
				<div class="passpress-setup-welcome-banner">
					<div class="passpress-setup-welcome-copy">
						<p class="passpress-setup-welcome-eyebrow"><?php esc_html_e( 'Welcome', 'passpress' ); ?></p>
						<strong><?php esc_html_e( 'Let’s set up PassPress for your business', 'passpress' ); ?></strong>
						<span><?php esc_html_e( 'Pick a template below to seed sample plans, facilities, and pages. You can change things later.', 'passpress' ); ?></span>
					</div>
					<a class="pp-btn-outline" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress' ) ); ?>">
						<?php esc_html_e( 'Skip for now', 'passpress' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="passpress-setup-page-header">
				<div class="passpress-setup-page-copy">
					<p class="passpress-setup-page-eyebrow"><?php esc_html_e( 'Getting started', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Setup Wizard', 'passpress' ); ?></h1>
					<p class="passpress-setup-page-desc">
						<?php
						printf(
							/* translators: %d: number of business templates */
							esc_html__( 'Choose from %d business templates. Importing creates starter plans, facilities, classes (when relevant), and pages — then take you to the dashboard.', 'passpress' ),
							(int) $template_count
						);
						?>
					</p>
				</div>
				<?php if ( $active_type ) : ?>
					<a class="passpress-setup-dashboard-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress' ) ); ?>">
						<?php esc_html_e( 'Go to Dashboard', 'passpress' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $active_label ) : ?>
				<div class="passpress-setup-status">
					<div class="passpress-setup-status-icon">
						<span class="dashicons <?php echo esc_attr( isset( $icons[ $active_type ] ) ? $icons[ $active_type ] : 'dashicons-yes-alt' ); ?>" aria-hidden="true"></span>
					</div>
					<div class="passpress-setup-status-copy">
						<p class="passpress-setup-status-label"><?php esc_html_e( 'Active business type', 'passpress' ); ?></p>
						<strong><?php echo esc_html( $active_label ); ?></strong>
						<span><?php esc_html_e( 'You can import a different template only if it hasn’t been imported yet. Already-imported types stay locked.', 'passpress' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<ol class="passpress-setup-steps">
				<li class="is-active">
					<span class="passpress-setup-step-num">1</span>
					<span class="passpress-setup-step-text">
						<strong><?php esc_html_e( 'Pick a type', 'passpress' ); ?></strong>
						<em><?php esc_html_e( 'Match your venue or program', 'passpress' ); ?></em>
					</span>
				</li>
				<li>
					<span class="passpress-setup-step-num">2</span>
					<span class="passpress-setup-step-text">
						<strong><?php esc_html_e( 'Import sample data', 'passpress' ); ?></strong>
						<em><?php esc_html_e( 'Plans, spaces, and pages', 'passpress' ); ?></em>
					</span>
				</li>
				<li>
					<span class="passpress-setup-step-num">3</span>
					<span class="passpress-setup-step-text">
						<strong><?php esc_html_e( 'Customize & sell', 'passpress' ); ?></strong>
						<em><?php esc_html_e( 'Edit prices, then open checkout', 'passpress' ); ?></em>
					</span>
				</li>
			</ol>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=passpress-setup' . ( $is_welcome ? '&pp_welcome=1' : '' ) ) ); ?>" id="passpress-setup-wizard-form" class="passpress-setup-form">
				<?php wp_nonce_field( 'pp_setup_wizard' ); ?>
				<input type="hidden" name="pp_import_template" value="1">

				<div class="passpress-setup-toolbar">
					<label class="passpress-setup-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Search business types', 'passpress' ); ?></span>
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" id="passpress-setup-search" placeholder="<?php esc_attr_e( 'Search templates…', 'passpress' ); ?>" autocomplete="off">
					</label>
					<div class="passpress-setup-filters" role="tablist" aria-label="<?php esc_attr_e( 'Template categories', 'passpress' ); ?>">
						<button type="button" class="passpress-setup-filter is-active" data-filter="all"><?php esc_html_e( 'All', 'passpress' ); ?></button>
						<?php foreach ( $categories as $cat_slug => $category ) : ?>
							<button type="button" class="passpress-setup-filter" data-filter="<?php echo esc_attr( $cat_slug ); ?>">
								<?php echo esc_html( $category['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="passpress-setup-catalog">
					<?php foreach ( $categories as $cat_slug => $category ) : ?>
						<section class="passpress-setup-category" data-category="<?php echo esc_attr( $cat_slug ); ?>">
							<header class="passpress-setup-category-head">
								<span class="dashicons <?php echo esc_attr( $category['icon'] ); ?>" aria-hidden="true"></span>
								<h2><?php echo esc_html( $category['label'] ); ?></h2>
							</header>
							<div class="passpress-setup-template-grid">
								<?php
								foreach ( $category['types'] as $slug ) :
									if ( ! isset( $roadmap[ $slug ] ) ) {
										continue;
									}
									$label    = $roadmap[ $slug ];
									$enabled  = isset( $available[ $slug ] );
									$imported = PP_Business_Templates::is_imported( $slug );
									$checked  = ( $active_type && $active_type === $slug ) || ( ! $active_type && 'gym' === $slug );
									$blurb    = isset( $blurbs[ $slug ] ) ? $blurbs[ $slug ] : '';
									$icon     = isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'dashicons-tag';
									$disabled = ! $enabled || $imported;
									?>
									<label
										class="passpress-template-card<?php echo $disabled ? ' passpress-template-disabled' : ''; ?><?php echo $checked && ! $imported ? ' is-selected' : ''; ?>"
										data-label="<?php echo esc_attr( strtolower( $label ) ); ?>"
										data-category="<?php echo esc_attr( $cat_slug ); ?>"
									>
										<input
											type="radio"
											name="business_type"
											value="<?php echo esc_attr( $slug ); ?>"
											<?php disabled( $disabled ); ?>
											<?php checked( $checked && ! $imported ); ?>
										>
										<span class="passpress-template-card-top">
											<span class="passpress-template-icon">
												<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
											</span>
											<?php if ( $imported ) : ?>
												<span class="passpress-template-badge passpress-template-badge-done"><?php esc_html_e( 'Imported', 'passpress' ); ?></span>
											<?php elseif ( ! $enabled ) : ?>
												<span class="passpress-template-badge"><?php esc_html_e( 'Coming soon', 'passpress' ); ?></span>
											<?php elseif ( $active_type === $slug ) : ?>
												<span class="passpress-template-badge passpress-template-badge-active"><?php esc_html_e( 'Active', 'passpress' ); ?></span>
											<?php endif; ?>
										</span>
										<span class="passpress-template-label"><?php echo esc_html( $label ); ?></span>
										<?php if ( $blurb ) : ?>
											<span class="passpress-template-blurb"><?php echo esc_html( $blurb ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
				</div>

				<p class="passpress-setup-empty" hidden><?php esc_html_e( 'No templates match your search.', 'passpress' ); ?></p>

				<div class="passpress-setup-footer">
					<div class="passpress-setup-footer-note">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Import once per template. You can edit or delete sample content afterward.', 'passpress' ); ?></span>
					</div>
					<div class="passpress-setup-footer-actions">
						<?php if ( $is_welcome ) : ?>
							<a class="pp-btn-outline" href="<?php echo esc_url( admin_url( 'admin.php?page=passpress' ) ); ?>">
								<?php esc_html_e( 'Skip for now', 'passpress' ); ?>
							</a>
						<?php endif; ?>
						<button type="submit" class="pp-btn-solid" id="passpress-setup-submit">
							<?php esc_html_e( 'Import template & continue', 'passpress' ); ?>
						</button>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
