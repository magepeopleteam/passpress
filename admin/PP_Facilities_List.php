<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card-grid replacement for edit.php?post_type=pp_facility.
 * Create/edit open the same modal; meta matches PP_Facility_CPT::save_meta().
 */
class PP_Facilities_List {

	public static function init() {
		add_action( 'wp_ajax_pp_create_facility', array( __CLASS__, 'ajax_create_facility' ) );
		add_action( 'wp_ajax_pp_get_facility', array( __CLASS__, 'ajax_get_facility' ) );
		add_action( 'wp_ajax_pp_update_facility', array( __CLASS__, 'ajax_update_facility' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_list' ) );
	}

	/**
	 * Send anyone hitting the native CPT list to the redesigned page.
	 */
	public static function maybe_redirect_legacy_list() {
		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}
		if ( empty( $_GET['post_type'] ) || 'pp_facility' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=passpress-facilities' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$facilities = self::get_facilities();
		?>
		<div class="wrap passpress-wrap passpress-facilities-page">
			<div class="passpress-facilities-page-header">
				<div class="passpress-facilities-page-copy">
					<p class="passpress-facilities-page-eyebrow"><?php esc_html_e( 'Spaces', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Facilities', 'passpress' ); ?></h1>
					<p class="passpress-facilities-page-desc">
						<?php
						printf(
							/* translators: %d: number of facilities */
							esc_html( _n( '%d facility configured', '%d facilities configured', count( $facilities ), 'passpress' ) ),
							count( $facilities )
						);
						?>
					</p>
				</div>
				<button type="button" id="passpress-new-facility-trigger" class="passpress-facilities-new-btn">
					<?php esc_html_e( 'New facility', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( ! $facilities ) : ?>
				<div class="passpress-facilities-empty">
					<p class="passpress-facilities-empty-eyebrow"><?php esc_html_e( 'Get started', 'passpress' ); ?></p>
					<h2 class="passpress-facilities-empty-title"><?php esc_html_e( 'No facilities yet', 'passpress' ); ?></h2>
					<p class="passpress-facilities-empty-desc"><?php esc_html_e( 'Add a gym, court, pool, or room so members can book and check in.', 'passpress' ); ?></p>
					<button type="button" class="passpress-facilities-new-btn" data-open-new-facility>
						<?php esc_html_e( 'Create a facility', 'passpress' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="passpress-facilities-grid">
					<?php foreach ( $facilities as $facility ) : ?>
						<?php self::render_card( $facility ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php self::render_modal(); ?>
		</div>
		<?php
	}

	private static function render_card( $facility ) {
		$type              = get_post_meta( $facility->ID, '_pp_facility_type', true );
		$capacity          = get_post_meta( $facility->ID, '_pp_capacity', true );
		$booking_required  = (int) get_post_meta( $facility->ID, '_pp_booking_required', true );
		$slot_duration     = (int) get_post_meta( $facility->ID, '_pp_slot_duration', true );
		$open_time         = get_post_meta( $facility->ID, '_pp_open_time', true );
		$close_time        = get_post_meta( $facility->ID, '_pp_close_time', true );
		$days_open         = get_post_meta( $facility->ID, '_pp_days_open', true );
		$days_open         = is_array( $days_open ) ? array_map( 'intval', $days_open ) : array();
		$types             = PP_Facility_CPT::facility_types();
		$type_label        = isset( $types[ $type ] ) ? $types[ $type ] : __( 'Facility', 'passpress' );
		$is_live           = ( 'publish' === $facility->post_status );
		$hours_label       = ( $open_time && $close_time ) ? $open_time . '–' . $close_time : '—';
		$days_label        = self::format_days_label( $days_open );
		?>
		<button type="button" class="passpress-facility-admin-card<?php echo $is_live ? ' is-live' : ' is-draft'; ?><?php echo $booking_required ? ' is-bookable' : ''; ?>" data-edit-facility="<?php echo esc_attr( (string) $facility->ID ); ?>">
			<div class="passpress-facility-admin-card-top">
				<div class="passpress-facility-admin-card-badges">
					<span class="passpress-facility-admin-badge is-type"><?php echo esc_html( $type_label ); ?></span>
					<span class="passpress-facility-admin-badge <?php echo $is_live ? 'is-live' : 'is-draft'; ?>">
						<?php echo $is_live ? esc_html__( 'Live', 'passpress' ) : esc_html__( 'Draft', 'passpress' ); ?>
					</span>
					<?php if ( $booking_required ) : ?>
						<span class="passpress-facility-admin-badge is-bookable"><?php esc_html_e( 'Bookable', 'passpress' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<h3 class="passpress-facility-admin-title"><?php echo esc_html( $facility->post_title ); ?></h3>

			<dl class="passpress-facility-admin-details">
				<div>
					<dt><?php esc_html_e( 'Capacity', 'passpress' ); ?></dt>
					<dd><?php echo '' !== $capacity ? esc_html( (string) (int) $capacity ) : '—'; ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Hours', 'passpress' ); ?></dt>
					<dd><?php echo esc_html( $hours_label ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Days', 'passpress' ); ?></dt>
					<dd><?php echo esc_html( $days_label ); ?></dd>
				</div>
				<?php if ( $slot_duration ) : ?>
					<div>
						<dt><?php esc_html_e( 'Slot', 'passpress' ); ?></dt>
						<dd>
							<?php
							/* translators: %d: minutes */
							echo esc_html( sprintf( __( '%d min', 'passpress' ), $slot_duration ) );
							?>
						</dd>
					</div>
				<?php endif; ?>
			</dl>

			<div class="passpress-facility-admin-footer">
				<span class="passpress-facility-admin-hint">
					<?php echo $booking_required ? esc_html__( 'Shows on booking calendar', 'passpress' ) : esc_html__( 'Walk-in / open access', 'passpress' ); ?>
				</span>
				<span class="passpress-facility-admin-edit"><?php esc_html_e( 'Edit facility', 'passpress' ); ?></span>
			</div>
		</button>
		<?php
	}

	/**
	 * @param int[] $days
	 */
	private static function format_days_label( $days ) {
		$days = array_values( array_unique( array_filter( array_map( 'intval', (array) $days ) ) ) );
		if ( 7 === count( $days ) ) {
			return __( 'Daily', 'passpress' );
		}
		if ( ! $days ) {
			return '—';
		}
		$weekdays = PP_Facility_CPT::weekdays();
		sort( $days );
		$labels = array();
		foreach ( $days as $day_num ) {
			if ( isset( $weekdays[ $day_num ] ) ) {
				$labels[] = mb_substr( $weekdays[ $day_num ], 0, 3 );
			}
		}
		return implode( ', ', $labels );
	}

	private static function render_modal() {
		$staff_users = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );
		?>
		<div id="passpress-facility-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-facility-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-facility-modal-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow" data-label-create="<?php esc_attr_e( 'Create', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit', 'passpress' ); ?>"><?php esc_html_e( 'Create', 'passpress' ); ?></p>
						<h2 id="passpress-facility-modal-title" data-label-create="<?php esc_attr_e( 'New facility', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit facility', 'passpress' ); ?>"><?php esc_html_e( 'New facility', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>

				<div class="passpress-modal-notice" hidden></div>

				<form id="passpress-facility-form" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_facility_modal', 'pp_facility_modal_nonce' ); ?>
					<input type="hidden" name="facility_id" id="pp_facility_id" value="0">

					<div class="pp-field">
						<label class="pp-label" for="pp_facility_title"><?php esc_html_e( 'Facility name', 'passpress' ); ?></label>
						<input type="text" id="pp_facility_title" name="title" class="pp-input" placeholder="<?php esc_attr_e( 'e.g. Main Gym Floor', 'passpress' ); ?>" required>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_facility_type_field"><?php esc_html_e( 'Type', 'passpress' ); ?></label>
							<select id="pp_facility_type_field" name="_pp_facility_type" class="pp-input pp-input-select">
								<?php foreach ( PP_Facility_CPT::facility_types() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_facility_capacity"><?php esc_html_e( 'Capacity', 'passpress' ); ?></label>
							<input type="number" min="0" id="pp_facility_capacity" name="_pp_capacity" class="pp-input" value="10">
						</div>
					</div>

					<label class="pp-checkbox-box">
						<input type="checkbox" id="pp_facility_booking_required" name="_pp_booking_required" value="1">
						<span><?php esc_html_e( 'Requires booking (show calendar on the front end)', 'passpress' ); ?></span>
					</label>

					<hr class="pp-divider">

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_facility_slot_duration"><?php esc_html_e( 'Slot duration (min)', 'passpress' ); ?></label>
							<input type="number" min="5" step="5" id="pp_facility_slot_duration" name="_pp_slot_duration" class="pp-input" value="60">
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_facility_buffer"><?php esc_html_e( 'Buffer (min)', 'passpress' ); ?></label>
							<input type="number" min="0" step="5" id="pp_facility_buffer" name="_pp_buffer_minutes" class="pp-input" value="0">
						</div>
					</div>

					<div class="pp-field">
						<label class="pp-label"><?php esc_html_e( 'Open hours', 'passpress' ); ?></label>
						<div class="pp-input-group pp-input-group-time">
							<input type="time" id="pp_facility_open_time" name="_pp_open_time" class="pp-input" value="09:00">
							<span class="pp-input-group-sep">&mdash;</span>
							<input type="time" id="pp_facility_close_time" name="_pp_close_time" class="pp-input" value="21:00">
						</div>
					</div>

					<div class="pp-field">
						<span class="pp-label"><?php esc_html_e( 'Open days', 'passpress' ); ?></span>
						<div class="pp-days-grid" id="pp_facility_days_open">
							<?php foreach ( PP_Facility_CPT::weekdays() as $day_num => $day_label ) : ?>
								<label class="pp-day-chip">
									<input type="checkbox" name="_pp_days_open[]" value="<?php echo esc_attr( (string) $day_num ); ?>" checked>
									<span><?php echo esc_html( mb_substr( $day_label, 0, 3 ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_facility_cancel_hours"><?php esc_html_e( 'Cancellation lead (hours)', 'passpress' ); ?></label>
						<input type="number" min="0" id="pp_facility_cancel_hours" name="_pp_cancellation_lead_hours" class="pp-input pp-input-narrow" value="2">
					</div>

					<?php if ( $staff_users ) : ?>
						<div class="pp-field">
							<span class="pp-label"><?php esc_html_e( 'Assigned staff', 'passpress' ); ?></span>
							<div class="pp-staff-grid" id="pp_facility_staff_ids">
								<?php foreach ( $staff_users as $staff_user ) : ?>
									<label class="pp-staff-chip">
										<input type="checkbox" name="_pp_staff_ids[]" value="<?php echo esc_attr( (string) $staff_user->ID ); ?>">
										<span><?php echo esc_html( $staff_user->display_name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<label class="pp-checkbox-box pp-facility-status-box" hidden>
						<input type="checkbox" id="pp_facility_live" name="is_live" value="1" checked>
						<span><?php esc_html_e( 'Live on site (published)', 'passpress' ); ?></span>
					</label>

					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" class="pp-btn-solid" id="passpress-facility-submit" data-label-create="<?php esc_attr_e( 'Create facility', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Save changes', 'passpress' ); ?>"><?php esc_html_e( 'Create facility', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private static function save_meta_from_request( $post_id ) {
		update_post_meta( $post_id, '_pp_facility_type', isset( $_POST['_pp_facility_type'] ) ? sanitize_key( $_POST['_pp_facility_type'] ) : 'gym' );
		update_post_meta( $post_id, '_pp_capacity', isset( $_POST['_pp_capacity'] ) ? absint( $_POST['_pp_capacity'] ) : 0 );
		update_post_meta( $post_id, '_pp_booking_required', ! empty( $_POST['_pp_booking_required'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_pp_slot_duration', isset( $_POST['_pp_slot_duration'] ) ? max( 5, absint( $_POST['_pp_slot_duration'] ) ) : 60 );
		update_post_meta( $post_id, '_pp_buffer_minutes', isset( $_POST['_pp_buffer_minutes'] ) ? absint( $_POST['_pp_buffer_minutes'] ) : 0 );
		update_post_meta( $post_id, '_pp_open_time', isset( $_POST['_pp_open_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_open_time'] ) ) : '09:00' );
		update_post_meta( $post_id, '_pp_close_time', isset( $_POST['_pp_close_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_close_time'] ) ) : '21:00' );

		$days_open = isset( $_POST['_pp_days_open'] ) && is_array( $_POST['_pp_days_open'] ) ? array_map( 'absint', wp_unslash( $_POST['_pp_days_open'] ) ) : array();
		update_post_meta( $post_id, '_pp_days_open', $days_open );

		update_post_meta( $post_id, '_pp_cancellation_lead_hours', isset( $_POST['_pp_cancellation_lead_hours'] ) ? absint( $_POST['_pp_cancellation_lead_hours'] ) : 2 );

		$staff_ids = isset( $_POST['_pp_staff_ids'] ) && is_array( $_POST['_pp_staff_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['_pp_staff_ids'] ) ) : array();
		update_post_meta( $post_id, '_pp_staff_ids', $staff_ids );
	}

	/**
	 * @param int $facility_id
	 * @return array|WP_Error
	 */
	private static function get_facility_payload( $facility_id ) {
		$facility = get_post( $facility_id );
		if ( ! $facility || 'pp_facility' !== $facility->post_type ) {
			return new WP_Error( 'not_found', __( 'Facility not found.', 'passpress' ) );
		}

		$days_open = get_post_meta( $facility->ID, '_pp_days_open', true );
		$days_open = is_array( $days_open ) ? array_map( 'intval', $days_open ) : array();
		$staff_ids = get_post_meta( $facility->ID, '_pp_staff_ids', true );
		$staff_ids = is_array( $staff_ids ) ? array_map( 'intval', $staff_ids ) : array();

		return array(
			'facility_id'                 => (int) $facility->ID,
			'title'                       => $facility->post_title,
			'is_live'                     => ( 'publish' === $facility->post_status ) ? 1 : 0,
			'_pp_facility_type'           => (string) get_post_meta( $facility->ID, '_pp_facility_type', true ),
			'_pp_capacity'                => (int) get_post_meta( $facility->ID, '_pp_capacity', true ),
			'_pp_booking_required'        => (int) get_post_meta( $facility->ID, '_pp_booking_required', true ),
			'_pp_slot_duration'           => (int) get_post_meta( $facility->ID, '_pp_slot_duration', true ),
			'_pp_buffer_minutes'          => (int) get_post_meta( $facility->ID, '_pp_buffer_minutes', true ),
			'_pp_open_time'               => (string) get_post_meta( $facility->ID, '_pp_open_time', true ),
			'_pp_close_time'              => (string) get_post_meta( $facility->ID, '_pp_close_time', true ),
			'_pp_days_open'               => $days_open,
			'_pp_cancellation_lead_hours' => (int) get_post_meta( $facility->ID, '_pp_cancellation_lead_hours', true ),
			'_pp_staff_ids'               => $staff_ids,
		);
	}

	public static function ajax_get_facility() {
		check_ajax_referer( 'pp_facility_modal', 'pp_facility_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$payload     = self::get_facility_payload( $facility_id );
		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
		}

		wp_send_json_success( $payload );
	}

	public static function ajax_create_facility() {
		check_ajax_referer( 'pp_facility_modal', 'pp_facility_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a facility name.', 'passpress' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_facility',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		self::save_meta_from_request( $post_id );
		PP_Activity_Logger::log( 'facility_created', 'facility', $post_id, sprintf( 'Facility "%s" created.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Facility created!', 'passpress' ),
				'reload_url' => admin_url( 'admin.php?page=passpress-facilities' ),
			)
		);
	}

	public static function ajax_update_facility() {
		check_ajax_referer( 'pp_facility_modal', 'pp_facility_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$facility    = get_post( $facility_id );
		if ( ! $facility || 'pp_facility' !== $facility->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Facility not found.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a facility name.', 'passpress' ) ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $facility_id,
				'post_title'  => $title,
				'post_status' => ! empty( $_POST['is_live'] ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
		}

		self::save_meta_from_request( $facility_id );
		PP_Activity_Logger::log( 'facility_updated', 'facility', $facility_id, sprintf( 'Facility "%s" updated.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Facility saved!', 'passpress' ),
				'reload_url' => admin_url( 'admin.php?page=passpress-facilities' ),
			)
		);
	}

	private static function get_facilities() {
		return get_posts(
			array(
				'post_type'      => 'pp_facility',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
	}
}
