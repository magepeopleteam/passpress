<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card-grid replacement for edit.php?post_type=pp_class_session.
 * Create/edit open the same modal; meta matches PP_Class_Session_CPT::save_meta().
 */
class PP_Class_Sessions_List {

	public static function init() {
		add_action( 'wp_ajax_pp_create_class_session', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_pp_get_class_session', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_pp_update_class_session', array( __CLASS__, 'ajax_update' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_list' ) );
	}

	public static function maybe_redirect_legacy_list() {
		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}
		if ( empty( $_GET['post_type'] ) || 'pp_class_session' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=passpress-class-sessions' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$classes = self::get_classes();
		?>
		<div class="wrap passpress-wrap passpress-classes-page">
			<div class="passpress-classes-page-header">
				<div class="passpress-classes-page-copy">
					<p class="passpress-classes-page-eyebrow"><?php esc_html_e( 'Schedule', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Class Sessions', 'passpress' ); ?></h1>
					<p class="passpress-classes-page-desc">
						<?php
						printf(
							/* translators: %d: number of class sessions */
							esc_html( _n( '%d weekly class', '%d weekly classes', count( $classes ), 'passpress' ) ),
							count( $classes )
						);
						?>
					</p>
				</div>
				<button type="button" id="passpress-new-class-trigger" class="passpress-classes-new-btn">
					<?php esc_html_e( 'New class', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( ! $classes ) : ?>
				<div class="passpress-classes-empty">
					<p class="passpress-classes-empty-eyebrow"><?php esc_html_e( 'Get started', 'passpress' ); ?></p>
					<h2 class="passpress-classes-empty-title"><?php esc_html_e( 'No class sessions yet', 'passpress' ); ?></h2>
					<p class="passpress-classes-empty-desc"><?php esc_html_e( 'Add yoga, fitness, or training sessions so members can book upcoming dates.', 'passpress' ); ?></p>
					<button type="button" class="passpress-classes-new-btn" data-open-new-class>
						<?php esc_html_e( 'Create a class', 'passpress' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="passpress-classes-grid">
					<?php foreach ( $classes as $class ) : ?>
						<?php self::render_card( $class ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php self::render_modal(); ?>
		</div>
		<?php
	}

	private static function render_card( $class ) {
		$class_type    = get_post_meta( $class->ID, '_pp_class_type', true );
		$instructor_id = (int) get_post_meta( $class->ID, '_pp_instructor_id', true );
		$facility_id   = (int) get_post_meta( $class->ID, '_pp_facility_id', true );
		$capacity      = (int) get_post_meta( $class->ID, '_pp_capacity', true );
		$day_of_week   = (int) get_post_meta( $class->ID, '_pp_day_of_week', true );
		$start_time    = get_post_meta( $class->ID, '_pp_start_time', true );
		$end_time      = get_post_meta( $class->ID, '_pp_end_time', true );
		$types         = PP_Class_Session_CPT::class_types();
		$weekdays      = PP_Class_Session_CPT::weekdays();
		$type_label    = isset( $types[ $class_type ] ) ? $types[ $class_type ] : __( 'Class', 'passpress' );
		$day_label     = isset( $weekdays[ $day_of_week ] ) ? $weekdays[ $day_of_week ] : '—';
		$instructor    = $instructor_id ? get_userdata( $instructor_id ) : null;
		$is_live       = ( 'publish' === $class->post_status );
		$time_label    = ( $start_time && $end_time ) ? $start_time . '–' . $end_time : '—';
		?>
		<button type="button" class="passpress-class-admin-card<?php echo $is_live ? ' is-live' : ' is-draft'; ?>" data-edit-class="<?php echo esc_attr( (string) $class->ID ); ?>">
			<div class="passpress-class-admin-card-top">
				<div class="passpress-class-admin-card-badges">
					<span class="passpress-class-admin-badge is-type"><?php echo esc_html( $type_label ); ?></span>
					<span class="passpress-class-admin-badge <?php echo $is_live ? 'is-live' : 'is-draft'; ?>">
						<?php echo $is_live ? esc_html__( 'Live', 'passpress' ) : esc_html__( 'Draft', 'passpress' ); ?>
					</span>
				</div>
				<span class="passpress-class-admin-day"><?php echo esc_html( $day_label ); ?></span>
			</div>

			<h3 class="passpress-class-admin-title"><?php echo esc_html( $class->post_title ); ?></h3>
			<p class="passpress-class-admin-time"><?php echo esc_html( $time_label ); ?></p>

			<dl class="passpress-class-admin-details">
				<div>
					<dt><?php esc_html_e( 'Instructor', 'passpress' ); ?></dt>
					<dd><?php echo $instructor ? esc_html( $instructor->display_name ) : '—'; ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Facility', 'passpress' ); ?></dt>
					<dd><?php echo $facility_id ? esc_html( get_the_title( $facility_id ) ) : '—'; ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Capacity', 'passpress' ); ?></dt>
					<dd><?php echo $capacity ? esc_html( (string) $capacity ) : '—'; ?></dd>
				</div>
			</dl>

			<div class="passpress-class-admin-footer">
				<span class="passpress-class-admin-hint"><?php esc_html_e( 'Weekly session', 'passpress' ); ?></span>
				<span class="passpress-class-admin-edit"><?php esc_html_e( 'Edit class', 'passpress' ); ?></span>
			</div>
		</button>
		<?php
	}

	private static function render_modal() {
		$instructors = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );
		$facilities  = get_posts(
			array(
				'post_type'      => 'pp_facility',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div id="passpress-class-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-class-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-class-modal-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow" data-label-create="<?php esc_attr_e( 'Create', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit', 'passpress' ); ?>"><?php esc_html_e( 'Create', 'passpress' ); ?></p>
						<h2 id="passpress-class-modal-title" data-label-create="<?php esc_attr_e( 'New class session', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit class session', 'passpress' ); ?>"><?php esc_html_e( 'New class session', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>

				<div class="passpress-modal-notice" hidden></div>

				<form id="passpress-class-form" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_class_modal', 'pp_class_modal_nonce' ); ?>
					<input type="hidden" name="class_id" id="pp_class_id" value="0">

					<div class="pp-field">
						<label class="pp-label" for="pp_class_title"><?php esc_html_e( 'Class name', 'passpress' ); ?></label>
						<input type="text" id="pp_class_title" name="title" class="pp-input" placeholder="<?php esc_attr_e( 'e.g. Morning Yoga', 'passpress' ); ?>" required>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_class_type_field"><?php esc_html_e( 'Class type', 'passpress' ); ?></label>
							<select id="pp_class_type_field" name="_pp_class_type" class="pp-input pp-input-select">
								<?php foreach ( PP_Class_Session_CPT::class_types() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_class_capacity_field"><?php esc_html_e( 'Capacity', 'passpress' ); ?></label>
							<input type="number" min="1" id="pp_class_capacity_field" name="_pp_capacity" class="pp-input" value="10">
						</div>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_class_instructor_field"><?php esc_html_e( 'Instructor', 'passpress' ); ?></label>
							<select id="pp_class_instructor_field" name="_pp_instructor_id" class="pp-input pp-input-select">
								<option value="0"><?php esc_html_e( '— None —', 'passpress' ); ?></option>
								<?php foreach ( $instructors as $user ) : ?>
									<option value="<?php echo esc_attr( (string) $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_class_facility_field"><?php esc_html_e( 'Facility / room', 'passpress' ); ?></label>
							<select id="pp_class_facility_field" name="_pp_facility_id" class="pp-input pp-input-select">
								<option value="0"><?php esc_html_e( '— None —', 'passpress' ); ?></option>
								<?php foreach ( $facilities as $facility ) : ?>
									<option value="<?php echo esc_attr( (string) $facility->ID ); ?>"><?php echo esc_html( $facility->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<hr class="pp-divider">

					<div class="pp-field">
						<label class="pp-label" for="pp_class_day_field"><?php esc_html_e( 'Day of week', 'passpress' ); ?></label>
						<p class="pp-field-hint"><?php esc_html_e( 'For multiple weekdays, create one class session per day.', 'passpress' ); ?></p>
						<select id="pp_class_day_field" name="_pp_day_of_week" class="pp-input pp-input-select">
							<?php foreach ( PP_Class_Session_CPT::weekdays() as $day_num => $day_label ) : ?>
								<option value="<?php echo esc_attr( (string) $day_num ); ?>" <?php selected( 1, $day_num ); ?>><?php echo esc_html( $day_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="pp-field">
						<label class="pp-label"><?php esc_html_e( 'Time', 'passpress' ); ?></label>
						<div class="pp-input-group pp-input-group-time">
							<input type="time" id="pp_class_start_field" name="_pp_start_time" class="pp-input" value="09:00">
							<span class="pp-input-group-sep">&mdash;</span>
							<input type="time" id="pp_class_end_field" name="_pp_end_time" class="pp-input" value="10:00">
						</div>
					</div>

					<label class="pp-checkbox-box pp-class-status-box" hidden>
						<input type="checkbox" id="pp_class_live" name="is_live" value="1" checked>
						<span><?php esc_html_e( 'Live on site (published)', 'passpress' ); ?></span>
					</label>

					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" class="pp-btn-solid" id="passpress-class-submit" data-label-create="<?php esc_attr_e( 'Create class', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Save changes', 'passpress' ); ?>"><?php esc_html_e( 'Create class', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private static function save_meta_from_request( $post_id ) {
		update_post_meta( $post_id, '_pp_class_type', isset( $_POST['_pp_class_type'] ) ? sanitize_key( $_POST['_pp_class_type'] ) : 'yoga' );
		update_post_meta( $post_id, '_pp_instructor_id', isset( $_POST['_pp_instructor_id'] ) ? absint( $_POST['_pp_instructor_id'] ) : 0 );
		update_post_meta( $post_id, '_pp_facility_id', isset( $_POST['_pp_facility_id'] ) ? absint( $_POST['_pp_facility_id'] ) : 0 );
		update_post_meta( $post_id, '_pp_capacity', isset( $_POST['_pp_capacity'] ) ? max( 1, absint( $_POST['_pp_capacity'] ) ) : 10 );
		update_post_meta( $post_id, '_pp_day_of_week', isset( $_POST['_pp_day_of_week'] ) ? absint( $_POST['_pp_day_of_week'] ) : 1 );
		update_post_meta( $post_id, '_pp_start_time', isset( $_POST['_pp_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_start_time'] ) ) : '09:00' );
		update_post_meta( $post_id, '_pp_end_time', isset( $_POST['_pp_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_end_time'] ) ) : '10:00' );
	}

	/**
	 * @param int $class_id
	 * @return array|WP_Error
	 */
	private static function get_payload( $class_id ) {
		$class = get_post( $class_id );
		if ( ! $class || 'pp_class_session' !== $class->post_type ) {
			return new WP_Error( 'not_found', __( 'Class session not found.', 'passpress' ) );
		}

		return array(
			'class_id'          => (int) $class->ID,
			'title'             => $class->post_title,
			'is_live'           => ( 'publish' === $class->post_status ) ? 1 : 0,
			'_pp_class_type'    => (string) get_post_meta( $class->ID, '_pp_class_type', true ),
			'_pp_instructor_id' => (int) get_post_meta( $class->ID, '_pp_instructor_id', true ),
			'_pp_facility_id'   => (int) get_post_meta( $class->ID, '_pp_facility_id', true ),
			'_pp_capacity'      => (int) get_post_meta( $class->ID, '_pp_capacity', true ),
			'_pp_day_of_week'   => (int) get_post_meta( $class->ID, '_pp_day_of_week', true ),
			'_pp_start_time'    => (string) get_post_meta( $class->ID, '_pp_start_time', true ),
			'_pp_end_time'      => (string) get_post_meta( $class->ID, '_pp_end_time', true ),
		);
	}

	public static function ajax_get() {
		check_ajax_referer( 'pp_class_modal', 'pp_class_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$payload  = self::get_payload( $class_id );
		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
		}

		wp_send_json_success( $payload );
	}

	public static function ajax_create() {
		check_ajax_referer( 'pp_class_modal', 'pp_class_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a class name.', 'passpress' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_class_session',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		self::save_meta_from_request( $post_id );
		PP_Activity_Logger::log( 'class_session_created', 'class', $post_id, sprintf( 'Class "%s" created.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Class created!', 'passpress' ),
				'reload_url' => admin_url( 'admin.php?page=passpress-class-sessions' ),
			)
		);
	}

	public static function ajax_update() {
		check_ajax_referer( 'pp_class_modal', 'pp_class_modal_nonce' );
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$class    = get_post( $class_id );
		if ( ! $class || 'pp_class_session' !== $class->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Class session not found.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a class name.', 'passpress' ) ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $class_id,
				'post_title'  => $title,
				'post_status' => ! empty( $_POST['is_live'] ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
		}

		self::save_meta_from_request( $class_id );
		PP_Activity_Logger::log( 'class_session_updated', 'class', $class_id, sprintf( 'Class "%s" updated.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Class saved!', 'passpress' ),
				'reload_url' => admin_url( 'admin.php?page=passpress-class-sessions' ),
			)
		);
	}

	private static function get_classes() {
		return get_posts(
			array(
				'post_type'      => 'pp_class_session',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
	}
}
