<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/setup — direct REST port of admin/PP_Setup_Wizard.php. The
 * categories/blurbs/icons static maps and template_summaries() logic move
 * here verbatim since that PHP class is retired in favor of
 * admin-app/src/components/SetupWizardModal.jsx.
 */
class PP_REST_Setup extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/setup/summaries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summaries' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/setup/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function get_summaries( $request ) {
		return rest_ensure_response(
			array(
				'categories'   => self::categories(),
				'summaries'    => self::template_summaries(),
				'active_type'  => get_option( 'passpress_active_business_type', '' ),
			)
		);
	}

	public function import( $request ) {
		$slug = sanitize_key( (string) $request->get_param( 'business_type' ) );
		if ( ! $slug ) {
			return new WP_Error( 'pp_missing_type', __( 'Please select a business template to import.', 'passpress' ), array( 'status' => 400 ) );
		}

		$result = PP_Business_Templates::import( $slug );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		// Say what was actually created — templates differ (a museum has no
		// facilities, a golf club has no classes), so a fixed sentence would be
		// wrong for most of them. Same wording as the old page-POST handler.
		$data  = PP_Business_Templates::get_template_data( $slug );
		$count = function ( $key ) use ( $data ) {
			return ( $data && isset( $data[ $key ] ) ) ? count( (array) $data[ $key ] ) : 0;
		};
		$parts = array();

		$plans = $count( 'plans' );
		if ( $plans ) {
			/* translators: %d: number of membership plans created */
			$parts[] = sprintf( _n( '%d membership plan', '%d membership plans', $plans, 'passpress' ), $plans );
		}
		$facilities = $count( 'facilities' );
		if ( $facilities ) {
			/* translators: %d: number of facilities created */
			$parts[] = sprintf( _n( '%d facility', '%d facilities', $facilities, 'passpress' ), $facilities );
		}
		$classes = $count( 'class_sessions' );
		if ( $classes ) {
			/* translators: %d: number of classes created */
			$parts[] = sprintf( _n( '%d class', '%d classes', $classes, 'passpress' ), $classes );
		}
		$pages = $count( 'pages' );
		if ( $pages ) {
			/* translators: %d: number of pages created */
			$parts[] = sprintf( _n( '%d page', '%d pages', $pages, 'passpress' ), $pages );
		}

		$message = $parts
			/* translators: %s: list of what was created, e.g. "4 membership plans, 1 facility, 3 pages" */
			? sprintf( __( 'Your starter setup is ready — %s created. Edit prices and delete anything you don\'t need.', 'passpress' ), implode( ', ', $parts ) )
			: __( 'Business template imported.', 'passpress' );

		// The Dashboard's REST endpoint (class-pp-rest-dashboard.php) reads and
		// consumes this same transient — set it here so navigating to the
		// Dashboard after a successful import still shows the success flash,
		// exactly as the old full-page-POST-then-redirect flow did.
		set_transient( 'passpress_setup_notice', $message, 60 );

		return rest_ensure_response( array( 'message' => $message ) );
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

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function template_summaries() {
		$roadmap    = PP_Business_Templates::get_roadmap();
		$available  = PP_Business_Templates::get_available();
		$icons      = self::type_icons();
		$blurbs     = self::type_blurbs();
		$categories = self::categories();

		$category_of = array();
		foreach ( $categories as $cat_slug => $category ) {
			foreach ( $category['types'] as $type_slug ) {
				$category_of[ $type_slug ] = $cat_slug;
			}
		}

		$summaries = array();
		foreach ( $roadmap as $slug => $label ) {
			$data = PP_Business_Templates::get_template_data( $slug );

			$summaries[ $slug ] = array(
				'slug'       => $slug,
				'label'      => $label,
				'blurb'      => isset( $blurbs[ $slug ] ) ? $blurbs[ $slug ] : '',
				'icon'       => isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'dashicons-tag',
				'category'   => isset( $category_of[ $slug ] ) ? $category_of[ $slug ] : '',
				'available'  => isset( $available[ $slug ] ),
				'imported'   => PP_Business_Templates::is_imported( $slug ),
				'plans'      => $data ? count( (array) ( isset( $data['plans'] ) ? $data['plans'] : array() ) ) : 0,
				'facilities' => $data ? count( (array) ( isset( $data['facilities'] ) ? $data['facilities'] : array() ) ) : 0,
				'classes'    => $data ? count( (array) ( isset( $data['class_sessions'] ) ? $data['class_sessions'] : array() ) ) : 0,
				'pages'      => $data ? count( (array) ( isset( $data['pages'] ) ? $data['pages'] : array() ) ) : 0,
			);
		}

		return $summaries;
	}
}
