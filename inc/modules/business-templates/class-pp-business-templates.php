<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-click Business Template importer, modeled on mage-eventpress's
 * admin/mep_dummy_import.php. Each template is a data file under ./data/
 * returning plans/facilities/pages — every business type in the product
 * plan now has a real data file (get_available() === get_roadmap()).
 */
class PP_Business_Templates {

	public static function get_available() {
		return self::get_roadmap();
	}

	/**
	 * Every business type named in the product plan. Also doubles as the
	 * available-templates list now that all 26 have real data files — kept
	 * as a separate method since the Setup Wizard historically called it to
	 * show the roadmap while only "gym" was actually wired up.
	 */
	public static function get_roadmap() {
		return array(
			'gym'                   => __( 'Gym', 'passpress' ),
			'fitness_center'        => __( 'Fitness Center', 'passpress' ),
			'health_club'           => __( 'Health Club', 'passpress' ),
			'swimming_pool'         => __( 'Swimming Pool', 'passpress' ),
			'sports_club'           => __( 'Sports Club', 'passpress' ),
			'football_academy'      => __( 'Football Academy', 'passpress' ),
			'cricket_academy'       => __( 'Cricket Academy', 'passpress' ),
			'tennis_club'           => __( 'Tennis Club', 'passpress' ),
			'badminton_club'        => __( 'Badminton Club', 'passpress' ),
			'basketball_club'       => __( 'Basketball Club', 'passpress' ),
			'golf_club'             => __( 'Golf Club', 'passpress' ),
			'community_club'        => __( 'Community Club', 'passpress' ),
			'kids_play_zone'        => __( 'Kids Play Zone', 'passpress' ),
			'theme_park'            => __( 'Theme Park', 'passpress' ),
			'water_park'            => __( 'Water Park', 'passpress' ),
			'public_park'           => __( 'Public Park', 'passpress' ),
			'recreation_center'     => __( 'Recreation Center', 'passpress' ),
			'library_membership'    => __( 'Library Membership', 'passpress' ),
			'museum_pass'           => __( 'Museum Pass', 'passpress' ),
			'zoo_pass'              => __( 'Zoo Pass', 'passpress' ),
			'adventure_park'        => __( 'Adventure Park', 'passpress' ),
			'ski_resort'            => __( 'Ski Resort', 'passpress' ),
			'cycling_club'          => __( 'Cycling Club', 'passpress' ),
			'martial_arts_academy'  => __( 'Martial Arts Academy', 'passpress' ),
			'yoga_studio'           => __( 'Yoga Studio', 'passpress' ),
			'dance_academy'         => __( 'Dance Academy', 'passpress' ),
		);
	}

	public static function get_template_data( $slug ) {
		$file = PASSPRESS_PLUGIN_DIR . '/inc/modules/business-templates/data/' . sanitize_file_name( $slug ) . '.php';
		if ( ! file_exists( $file ) ) {
			return null;
		}
		return include $file;
	}

	public static function is_imported( $slug ) {
		return (bool) get_option( 'passpress_template_imported_' . sanitize_key( $slug ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function import( $slug ) {
		$slug = sanitize_key( $slug );
		$data = self::get_template_data( $slug );

		if ( ! $data ) {
			return new WP_Error( 'pp_template_not_found', __( 'That business template is not available yet.', 'passpress' ) );
		}

		if ( self::is_imported( $slug ) ) {
			return new WP_Error( 'pp_template_already_imported', __( 'This template has already been imported.', 'passpress' ) );
		}

		foreach ( $data['plans'] as $plan ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'pp_membership_plan',
					'post_title'  => $plan['name'],
					'post_status' => 'publish',
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_pp_price', floatval( $plan['price'] ) );
				update_post_meta( $post_id, '_pp_plan_type', $plan['plan_type'] );
				update_post_meta( $post_id, '_pp_duration_value', absint( $plan['duration_value'] ) );
				update_post_meta( $post_id, '_pp_duration_unit', $plan['duration_unit'] );
				update_post_meta( $post_id, '_pp_entry_restriction', isset( $plan['entry_restriction'] ) ? $plan['entry_restriction'] : 'none' );

				// wp_insert_post() above already fired save_post_pp_membership_plan
				// (and with it, PP_Shop_WooCommerce::sync_product_for_plan()) before
				// _pp_price existed — that call was a guarded no-op. Call it again
				// now that the real price is set, so imported plans still get a
				// synced WooCommerce product when the Shop module is active.
				if ( class_exists( 'PP_Shop_WooCommerce' ) && PP_Shop_WooCommerce::is_available() ) {
					PP_Shop_WooCommerce::sync_product_for_plan( $post_id );
				}
			}
		}

		$facility_ids_by_name = array();

		foreach ( $data['facilities'] as $facility ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'pp_facility',
					'post_title'  => $facility['name'],
					'post_status' => 'publish',
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_pp_facility_type', $facility['facility_type'] );
				update_post_meta( $post_id, '_pp_capacity', absint( $facility['capacity'] ) );
				$facility_ids_by_name[ $facility['name'] ] = $post_id;
			}
		}

		foreach ( (array) ( $data['class_sessions'] ?? array() ) as $class ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'pp_class_session',
					'post_title'  => $class['name'],
					'post_status' => 'publish',
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_pp_class_type', $class['class_type'] );
				update_post_meta( $post_id, '_pp_facility_id', isset( $facility_ids_by_name[ $class['facility_name'] ] ) ? $facility_ids_by_name[ $class['facility_name'] ] : 0 );
				update_post_meta( $post_id, '_pp_capacity', absint( $class['capacity'] ) );
				update_post_meta( $post_id, '_pp_day_of_week', absint( $class['day_of_week'] ) );
				update_post_meta( $post_id, '_pp_start_time', $class['start_time'] );
				update_post_meta( $post_id, '_pp_end_time', $class['end_time'] );
			}
		}

		foreach ( $data['pages'] as $page ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $page['title'],
					'post_content' => '[' . $page['shortcode'] . ']',
					'post_status'  => 'publish',
				)
			);
		}

		update_option( 'passpress_template_imported_' . $slug, 1 );
		update_option( 'passpress_active_business_type', $slug );

		PP_Activity_Logger::log( 'business_template_imported', 'template', 0, sprintf( 'Imported the "%s" business template.', $data['label'] ) );

		return true;
	}
}
