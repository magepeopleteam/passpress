<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the pp_membership_plan CPT and its plan-detail meta box: price,
 * plan type, duration, and the entry restriction applied at the door.
 */
class PP_Membership_Plan_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_pp_membership_plan', array( $this, 'save_meta' ) );
	}

	public function register() {
		register_post_type(
			'pp_membership_plan',
			array(
				'labels'       => array(
					'name'          => __( 'Membership Plans', 'passpress' ),
					'singular_name' => __( 'Membership Plan', 'passpress' ),
					'add_new_item'  => __( 'Add New Membership Plan', 'passpress' ),
					'edit_item'     => __( 'Edit Membership Plan', 'passpress' ),
					'all_items'     => __( 'Membership Plans', 'passpress' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false, // custom card-grid list page instead — see admin/PP_Plans_List.php
				'menu_icon'    => 'dashicons-id-alt',
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'membership-plan' ),
				'show_in_rest' => true,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'pp_plan_details', __( 'Plan Details', 'passpress' ), array( $this, 'render_meta_box' ), 'pp_membership_plan', 'normal', 'high' );
	}

	public static function plan_types() {
		return array(
			'monthly'   => __( 'Monthly Membership', 'passpress' ),
			'yearly'    => __( 'Yearly Membership', 'passpress' ),
			'weekly'    => __( 'Weekly Membership', 'passpress' ),
			'daily_pass'=> __( 'Daily Pass', 'passpress' ),
			'one_time'  => __( 'One-time Entry Pass', 'passpress' ),
			'family'    => __( 'Family Membership', 'passpress' ),
			'student'   => __( 'Student Membership', 'passpress' ),
			'vip'       => __( 'VIP Membership', 'passpress' ),
			'corporate' => __( 'Corporate Membership', 'passpress' ),
			'lifetime'  => __( 'Lifetime Membership', 'passpress' ),
		);
	}

	public static function duration_units() {
		return array(
			'day'      => __( 'Day(s)', 'passpress' ),
			'week'     => __( 'Week(s)', 'passpress' ),
			'month'    => __( 'Month(s)', 'passpress' ),
			'year'     => __( 'Year(s)', 'passpress' ),
			'lifetime' => __( 'Never expires', 'passpress' ),
		);
	}

	public static function entry_restrictions() {
		return array(
			'none'            => __( 'No restriction', 'passpress' ),
			'one_per_day'     => __( 'One entry per day', 'passpress' ),
			'weekday_only'    => __( 'Weekdays only', 'passpress' ),
			'weekend_only'    => __( 'Weekends only', 'passpress' ),
			'time_restricted' => __( 'Time restricted', 'passpress' ),
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'pp_save_plan_meta', 'pp_plan_meta_nonce' );

		$price          = get_post_meta( $post->ID, '_pp_price', true );
		$plan_type      = get_post_meta( $post->ID, '_pp_plan_type', true ) ?: 'monthly';
		$duration_value = get_post_meta( $post->ID, '_pp_duration_value', true );
		$duration_value = '' === $duration_value ? 1 : $duration_value;
		$duration_unit  = get_post_meta( $post->ID, '_pp_duration_unit', true ) ?: 'month';
		$restriction    = get_post_meta( $post->ID, '_pp_entry_restriction', true ) ?: 'none';
		$time_start     = get_post_meta( $post->ID, '_pp_time_restriction_start', true );
		$time_end       = get_post_meta( $post->ID, '_pp_time_restriction_end', true );
		$max_per_day    = get_post_meta( $post->ID, '_pp_max_entries_per_day', true );
		$features       = get_post_meta( $post->ID, '_pp_features', true );
		$most_popular   = get_post_meta( $post->ID, '_pp_most_popular', true );
		$settings       = pp_get_settings();
		?>
		<table class="form-table">
			<tr>
				<th><label for="pp_plan_type"><?php esc_html_e( 'Plan Type', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_plan_type" id="pp_plan_type">
						<?php foreach ( self::plan_types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $plan_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_price"><?php esc_html_e( 'Price', 'passpress' ); ?></label></th>
				<td>
					<?php echo esc_html( $settings['currency_symbol'] ); ?>
					<input type="number" step="0.01" min="0" name="_pp_price" id="pp_price" value="<?php echo esc_attr( $price ); ?>" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="pp_duration_value"><?php esc_html_e( 'Duration', 'passpress' ); ?></label></th>
				<td>
					<input type="number" min="0" name="_pp_duration_value" id="pp_duration_value" value="<?php echo esc_attr( $duration_value ); ?>" class="small-text">
					<select name="_pp_duration_unit">
						<?php foreach ( self::duration_units() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $duration_unit, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_entry_restriction"><?php esc_html_e( 'Entry Restriction', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_entry_restriction" id="pp_entry_restriction">
						<?php foreach ( self::entry_restrictions() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $restriction, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_time_restriction_start"><?php esc_html_e( 'Time Window (if time restricted)', 'passpress' ); ?></label></th>
				<td>
					<input type="time" name="_pp_time_restriction_start" id="pp_time_restriction_start" value="<?php echo esc_attr( $time_start ); ?>">
					&mdash;
					<input type="time" name="_pp_time_restriction_end" value="<?php echo esc_attr( $time_end ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="pp_max_entries_per_day"><?php esc_html_e( 'Max Entries / Day (0 = unlimited)', 'passpress' ); ?></label></th>
				<td><input type="number" min="0" name="_pp_max_entries_per_day" id="pp_max_entries_per_day" value="<?php echo esc_attr( $max_per_day ? $max_per_day : 0 ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="pp_features"><?php esc_html_e( 'Features', 'passpress' ); ?></label></th>
				<td>
					<textarea name="_pp_features" id="pp_features" rows="5" class="large-text" placeholder="<?php esc_attr_e( "One per line, e.g.\nFull facility access\nValid until midnight\nInstant QR by email", 'passpress' ); ?>"><?php echo esc_textarea( $features ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One feature per line — shown as a checklist on the plan list card.', 'passpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Most Popular', 'passpress' ); ?></th>
				<td><label><input type="checkbox" name="_pp_most_popular" value="1" <?php checked( ! empty( $most_popular ) ); ?>> <?php esc_html_e( 'Highlight this plan with a "Most Popular" badge on the plan list', 'passpress' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['pp_plan_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pp_plan_meta_nonce'], 'pp_save_plan_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_pp_price'] ) ) {
			update_post_meta( $post_id, '_pp_price', (float) wp_unslash( $_POST['_pp_price'] ) );
		}
		if ( isset( $_POST['_pp_plan_type'] ) ) {
			update_post_meta( $post_id, '_pp_plan_type', sanitize_key( $_POST['_pp_plan_type'] ) );
		}
		if ( isset( $_POST['_pp_duration_value'] ) ) {
			update_post_meta( $post_id, '_pp_duration_value', absint( $_POST['_pp_duration_value'] ) );
		}
		if ( isset( $_POST['_pp_duration_unit'] ) ) {
			update_post_meta( $post_id, '_pp_duration_unit', sanitize_key( $_POST['_pp_duration_unit'] ) );
		}
		if ( isset( $_POST['_pp_entry_restriction'] ) ) {
			update_post_meta( $post_id, '_pp_entry_restriction', sanitize_key( $_POST['_pp_entry_restriction'] ) );
		}
		if ( isset( $_POST['_pp_time_restriction_start'] ) ) {
			update_post_meta( $post_id, '_pp_time_restriction_start', sanitize_text_field( wp_unslash( $_POST['_pp_time_restriction_start'] ) ) );
		}
		if ( isset( $_POST['_pp_time_restriction_end'] ) ) {
			update_post_meta( $post_id, '_pp_time_restriction_end', sanitize_text_field( wp_unslash( $_POST['_pp_time_restriction_end'] ) ) );
		}
		if ( isset( $_POST['_pp_max_entries_per_day'] ) ) {
			update_post_meta( $post_id, '_pp_max_entries_per_day', absint( $_POST['_pp_max_entries_per_day'] ) );
		}
		if ( isset( $_POST['_pp_features'] ) ) {
			update_post_meta( $post_id, '_pp_features', sanitize_textarea_field( wp_unslash( $_POST['_pp_features'] ) ) );
		}
		update_post_meta( $post_id, '_pp_most_popular', ! empty( $_POST['_pp_most_popular'] ) ? 1 : 0 );
	}
}
