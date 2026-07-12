<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the pp_class_session CPT (Yoga, Zumba, Fitness, Swimming,
 * Karate, Dance, Football Training, Cricket Coaching). A class meets once a
 * week at a fixed day/time — a class meeting multiple days/week is modeled
 * as separate posts (e.g. "Morning Yoga (Mon)", "Morning Yoga (Wed)"), a
 * deliberate simplification documented in CLAUDE.md.
 *
 * No separate "Instructor" data structure/CPT: the instructor is just a
 * pp_trainer/pp_staff/administrator WP user, picked via a meta field —
 * consistent with how Facility already assigns staff.
 */
class PP_Class_Session_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_pp_class_session', array( $this, 'save_meta' ) );
	}

	public function register() {
		register_post_type(
			'pp_class_session',
			array(
				'labels'       => array(
					'name'          => __( 'Class Sessions', 'passpress' ),
					'singular_name' => __( 'Class Session', 'passpress' ),
					'add_new_item'  => __( 'Add New Class Session', 'passpress' ),
					'edit_item'     => __( 'Edit Class Session', 'passpress' ),
					'all_items'     => __( 'Class Sessions', 'passpress' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => 'passpress',
				'menu_icon'    => 'dashicons-universal-access-alt',
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'class-session' ),
				'show_in_rest' => true,
			)
		);
	}

	public static function class_types() {
		return array(
			'yoga'              => __( 'Yoga', 'passpress' ),
			'zumba'             => __( 'Zumba', 'passpress' ),
			'fitness'           => __( 'Fitness', 'passpress' ),
			'swimming'          => __( 'Swimming', 'passpress' ),
			'karate'            => __( 'Karate', 'passpress' ),
			'dance'             => __( 'Dance', 'passpress' ),
			'football_training' => __( 'Football Training', 'passpress' ),
			'cricket_coaching'  => __( 'Cricket Coaching', 'passpress' ),
		);
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

	public function add_meta_boxes() {
		add_meta_box( 'pp_class_session_details', __( 'Class Details', 'passpress' ), array( $this, 'render_meta_box' ), 'pp_class_session', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'pp_save_class_session_meta', 'pp_class_session_meta_nonce' );

		$class_type   = get_post_meta( $post->ID, '_pp_class_type', true ) ?: 'yoga';
		$instructor   = (int) get_post_meta( $post->ID, '_pp_instructor_id', true );
		$facility_id  = (int) get_post_meta( $post->ID, '_pp_facility_id', true );
		$capacity     = get_post_meta( $post->ID, '_pp_capacity', true );
		$capacity     = $capacity ? $capacity : 10;
		$day_of_week  = (int) get_post_meta( $post->ID, '_pp_day_of_week', true );
		$day_of_week  = $day_of_week ? $day_of_week : 1;
		$start_time   = get_post_meta( $post->ID, '_pp_start_time', true ) ?: '09:00';
		$end_time     = get_post_meta( $post->ID, '_pp_end_time', true ) ?: '10:00';

		$instructors = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );
		$facilities  = get_posts( array( 'post_type' => 'pp_facility', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		?>
		<table class="form-table">
			<tr>
				<th><label for="pp_class_type"><?php esc_html_e( 'Class Type', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_class_type" id="pp_class_type">
						<?php foreach ( self::class_types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $class_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_instructor_id"><?php esc_html_e( 'Instructor', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_instructor_id" id="pp_instructor_id">
						<option value="0"><?php esc_html_e( '— None —', 'passpress' ); ?></option>
						<?php foreach ( $instructors as $user ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $instructor, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_class_facility_id"><?php esc_html_e( 'Facility / Room', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_facility_id" id="pp_class_facility_id">
						<option value="0"><?php esc_html_e( '— None —', 'passpress' ); ?></option>
						<?php foreach ( $facilities as $facility ) : ?>
							<option value="<?php echo esc_attr( $facility->ID ); ?>" <?php selected( $facility_id, $facility->ID ); ?>><?php echo esc_html( $facility->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_class_capacity"><?php esc_html_e( 'Capacity', 'passpress' ); ?></label></th>
				<td><input type="number" min="1" name="_pp_capacity" id="pp_class_capacity" value="<?php echo esc_attr( $capacity ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="pp_day_of_week"><?php esc_html_e( 'Day of Week', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_day_of_week" id="pp_day_of_week">
						<?php foreach ( self::weekdays() as $day_num => $day_label ) : ?>
							<option value="<?php echo esc_attr( $day_num ); ?>" <?php selected( $day_of_week, $day_num ); ?>><?php echo esc_html( $day_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'A class meeting multiple days a week? Create one Class Session per day.', 'passpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pp_class_start_time"><?php esc_html_e( 'Time', 'passpress' ); ?></label></th>
				<td>
					<input type="time" name="_pp_start_time" id="pp_class_start_time" value="<?php echo esc_attr( $start_time ); ?>">
					&mdash;
					<input type="time" name="_pp_end_time" value="<?php echo esc_attr( $end_time ); ?>">
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['pp_class_session_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pp_class_session_meta_nonce'], 'pp_save_class_session_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_pp_class_type'] ) ) {
			update_post_meta( $post_id, '_pp_class_type', sanitize_key( $_POST['_pp_class_type'] ) );
		}
		if ( isset( $_POST['_pp_instructor_id'] ) ) {
			update_post_meta( $post_id, '_pp_instructor_id', absint( $_POST['_pp_instructor_id'] ) );
		}
		if ( isset( $_POST['_pp_facility_id'] ) ) {
			update_post_meta( $post_id, '_pp_facility_id', absint( $_POST['_pp_facility_id'] ) );
		}
		if ( isset( $_POST['_pp_capacity'] ) ) {
			update_post_meta( $post_id, '_pp_capacity', max( 1, absint( $_POST['_pp_capacity'] ) ) );
		}
		if ( isset( $_POST['_pp_day_of_week'] ) ) {
			update_post_meta( $post_id, '_pp_day_of_week', absint( $_POST['_pp_day_of_week'] ) );
		}
		if ( isset( $_POST['_pp_start_time'] ) ) {
			update_post_meta( $post_id, '_pp_start_time', sanitize_text_field( wp_unslash( $_POST['_pp_start_time'] ) ) );
		}
		if ( isset( $_POST['_pp_end_time'] ) ) {
			update_post_meta( $post_id, '_pp_end_time', sanitize_text_field( wp_unslash( $_POST['_pp_end_time'] ) ) );
		}
	}
}
