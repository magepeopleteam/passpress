<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the pp_facility CPT (Gym, Pool, Court, Ground, Library, ...)
 * with capacity meta, plus (Phase 2) booking configuration: whether the
 * facility requires a booking at all, its open hours/slot length/buffer,
 * which days it's open, cancellation lead time, and assigned staff.
 */
class PP_Facility_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_pp_facility', array( $this, 'save_meta' ) );
	}

	public function register() {
		register_post_type(
			'pp_facility',
			array(
				'labels'       => array(
					'name'          => __( 'Facilities', 'passpress' ),
					'singular_name' => __( 'Facility', 'passpress' ),
					'add_new_item'  => __( 'Add New Facility', 'passpress' ),
					'edit_item'     => __( 'Edit Facility', 'passpress' ),
					'all_items'     => __( 'Facilities', 'passpress' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => 'passpress',
				'menu_icon'    => 'dashicons-building',
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'facility' ),
				'show_in_rest' => true,
			)
		);
	}

	public static function facility_types() {
		return array(
			'gym'              => __( 'Gym', 'passpress' ),
			'swimming_pool'    => __( 'Swimming Pool', 'passpress' ),
			'tennis_court'     => __( 'Tennis Court', 'passpress' ),
			'football_ground'  => __( 'Football Ground', 'passpress' ),
			'cricket_ground'   => __( 'Cricket Ground', 'passpress' ),
			'basketball_court' => __( 'Basketball Court', 'passpress' ),
			'badminton_court'  => __( 'Badminton Court', 'passpress' ),
			'indoor_games'     => __( 'Indoor Games', 'passpress' ),
			'childrens_park'   => __( "Children's Park", 'passpress' ),
			'amusement_ride'   => __( 'Amusement Ride', 'passpress' ),
			'library'          => __( 'Library', 'passpress' ),
			'club_house'       => __( 'Club House', 'passpress' ),
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'pp_facility_details', __( 'Facility Details', 'passpress' ), array( $this, 'render_meta_box' ), 'pp_facility', 'normal', 'high' );
		add_meta_box( 'pp_facility_booking', __( 'Booking Settings', 'passpress' ), array( $this, 'render_booking_meta_box' ), 'pp_facility', 'normal', 'default' );
	}

	public static function weekdays() {
		return array(
			1 => __( 'Monday', 'passpress' ),
			2 => __( 'Tuesday', 'passpress' ),
			3 => __( 'Wednesday', 'passpress' ),
			4 => __( 'Thursday', 'passpress' ),
			5 => __( 'Friday', 'passpress' ),
			6 => __( 'Saturday', 'passpress' ),
			7 => __( 'Sunday', 'passpress' ),
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'pp_save_facility_meta', 'pp_facility_meta_nonce' );

		$type     = get_post_meta( $post->ID, '_pp_facility_type', true ) ?: 'gym';
		$capacity = get_post_meta( $post->ID, '_pp_capacity', true );
		?>
		<p>
			<label for="pp_facility_type"><?php esc_html_e( 'Facility Type', 'passpress' ); ?></label><br>
			<select name="_pp_facility_type" id="pp_facility_type">
				<?php foreach ( self::facility_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="pp_capacity"><?php esc_html_e( 'Capacity (concurrent bookings per slot)', 'passpress' ); ?></label><br>
			<input type="number" min="0" name="_pp_capacity" id="pp_capacity" value="<?php echo esc_attr( $capacity ); ?>">
		</p>
		<?php
	}

	public function render_booking_meta_box( $post ) {
		$booking_required   = get_post_meta( $post->ID, '_pp_booking_required', true );
		$slot_duration      = get_post_meta( $post->ID, '_pp_slot_duration', true );
		$slot_duration      = $slot_duration ? $slot_duration : 60;
		$buffer_minutes     = get_post_meta( $post->ID, '_pp_buffer_minutes', true );
		$open_time          = get_post_meta( $post->ID, '_pp_open_time', true );
		$open_time          = $open_time ? $open_time : '09:00';
		$close_time         = get_post_meta( $post->ID, '_pp_close_time', true );
		$close_time         = $close_time ? $close_time : '21:00';
		$days_open          = get_post_meta( $post->ID, '_pp_days_open', true );
		$days_open          = is_array( $days_open ) && $days_open ? $days_open : array( 1, 2, 3, 4, 5, 6, 7 );
		$cancellation_hours = get_post_meta( $post->ID, '_pp_cancellation_lead_hours', true );
		$cancellation_hours = '' === $cancellation_hours ? 2 : $cancellation_hours;
		$staff_ids          = get_post_meta( $post->ID, '_pp_staff_ids', true );
		$staff_ids          = is_array( $staff_ids ) ? $staff_ids : array();
		$staff_users        = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );
		?>
		<p>
			<label><input type="checkbox" name="_pp_booking_required" value="1" <?php checked( ! empty( $booking_required ) ); ?>> <?php esc_html_e( 'This facility requires a booking (shows a booking calendar on the front end)', 'passpress' ); ?></label>
		</p>
		<p>
			<label for="pp_slot_duration"><?php esc_html_e( 'Slot Duration (minutes)', 'passpress' ); ?></label><br>
			<input type="number" min="5" step="5" name="_pp_slot_duration" id="pp_slot_duration" value="<?php echo esc_attr( $slot_duration ); ?>" class="small-text">
		</p>
		<p>
			<label for="pp_buffer_minutes"><?php esc_html_e( 'Buffer Between Slots (minutes)', 'passpress' ); ?></label><br>
			<input type="number" min="0" step="5" name="_pp_buffer_minutes" id="pp_buffer_minutes" value="<?php echo esc_attr( $buffer_minutes ); ?>" class="small-text">
		</p>
		<p>
			<label for="pp_open_time"><?php esc_html_e( 'Open Time', 'passpress' ); ?></label>
			<input type="time" name="_pp_open_time" id="pp_open_time" value="<?php echo esc_attr( $open_time ); ?>">
			<label for="pp_close_time"><?php esc_html_e( 'Close Time', 'passpress' ); ?></label>
			<input type="time" name="_pp_close_time" id="pp_close_time" value="<?php echo esc_attr( $close_time ); ?>">
		</p>
		<p>
			<?php esc_html_e( 'Open Days', 'passpress' ); ?><br>
			<?php foreach ( self::weekdays() as $day_num => $day_label ) : ?>
				<label style="margin-right:10px;"><input type="checkbox" name="_pp_days_open[]" value="<?php echo esc_attr( $day_num ); ?>" <?php checked( in_array( $day_num, array_map( 'intval', $days_open ), true ) ); ?>> <?php echo esc_html( $day_label ); ?></label>
			<?php endforeach; ?>
		</p>
		<p>
			<label for="pp_cancellation_lead_hours"><?php esc_html_e( 'Cancellation Lead Time (hours before start)', 'passpress' ); ?></label><br>
			<input type="number" min="0" name="_pp_cancellation_lead_hours" id="pp_cancellation_lead_hours" value="<?php echo esc_attr( $cancellation_hours ); ?>" class="small-text">
		</p>
		<p>
			<?php esc_html_e( 'Assigned Staff', 'passpress' ); ?><br>
			<?php if ( ! $staff_users ) : ?>
				<em><?php esc_html_e( 'No staff/trainer users found.', 'passpress' ); ?></em>
			<?php endif; ?>
			<?php foreach ( $staff_users as $staff_user ) : ?>
				<label style="display:block;"><input type="checkbox" name="_pp_staff_ids[]" value="<?php echo esc_attr( $staff_user->ID ); ?>" <?php checked( in_array( (int) $staff_user->ID, array_map( 'intval', $staff_ids ), true ) ); ?>> <?php echo esc_html( $staff_user->display_name ); ?></label>
			<?php endforeach; ?>
		</p>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['pp_facility_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pp_facility_meta_nonce'], 'pp_save_facility_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_pp_facility_type'] ) ) {
			update_post_meta( $post_id, '_pp_facility_type', sanitize_key( $_POST['_pp_facility_type'] ) );
		}
		if ( isset( $_POST['_pp_capacity'] ) ) {
			update_post_meta( $post_id, '_pp_capacity', absint( $_POST['_pp_capacity'] ) );
		}

		update_post_meta( $post_id, '_pp_booking_required', ! empty( $_POST['_pp_booking_required'] ) ? 1 : 0 );

		if ( isset( $_POST['_pp_slot_duration'] ) ) {
			update_post_meta( $post_id, '_pp_slot_duration', max( 5, absint( $_POST['_pp_slot_duration'] ) ) );
		}
		if ( isset( $_POST['_pp_buffer_minutes'] ) ) {
			update_post_meta( $post_id, '_pp_buffer_minutes', absint( $_POST['_pp_buffer_minutes'] ) );
		}
		if ( isset( $_POST['_pp_open_time'] ) ) {
			update_post_meta( $post_id, '_pp_open_time', sanitize_text_field( wp_unslash( $_POST['_pp_open_time'] ) ) );
		}
		if ( isset( $_POST['_pp_close_time'] ) ) {
			update_post_meta( $post_id, '_pp_close_time', sanitize_text_field( wp_unslash( $_POST['_pp_close_time'] ) ) );
		}

		$days_open = isset( $_POST['_pp_days_open'] ) && is_array( $_POST['_pp_days_open'] ) ? array_map( 'absint', $_POST['_pp_days_open'] ) : array();
		update_post_meta( $post_id, '_pp_days_open', $days_open );

		if ( isset( $_POST['_pp_cancellation_lead_hours'] ) ) {
			update_post_meta( $post_id, '_pp_cancellation_lead_hours', absint( $_POST['_pp_cancellation_lead_hours'] ) );
		}

		$staff_ids = isset( $_POST['_pp_staff_ids'] ) && is_array( $_POST['_pp_staff_ids'] ) ? array_map( 'absint', $_POST['_pp_staff_ids'] ) : array();
		update_post_meta( $post_id, '_pp_staff_ids', $staff_ids );
	}
}
