<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers pp_coupon — a real CPT, not a table, since a coupon is a
 * defined rule/entity (like a plan or facility), not transactional data.
 * The post *title* is the coupon code itself (uppercased on save).
 * Usage/redemption tracking lives on pp_billing_history (coupon_code +
 * discount_amount columns added in Phase 4), not a separate table — this
 * plugin's own coupon engine, not WC_Coupon, per CLAUDE.md's Marketing note.
 */
class PP_Coupon_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_pp_coupon', array( $this, 'save_meta' ) );
	}

	public function register() {
		register_post_type(
			'pp_coupon',
			array(
				'labels'            => array(
					'name'              => __( 'Coupons', 'passpress' ),
					'singular_name'     => __( 'Coupon', 'passpress' ),
					'add_new_item'      => __( 'Add New Coupon', 'passpress' ),
					'edit_item'         => __( 'Edit Coupon', 'passpress' ),
					'all_items'         => __( 'Coupons', 'passpress' ),
					'title_placeholder' => __( 'Coupon code, e.g. SUMMER20', 'passpress' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false, // custom card-grid list page instead — see admin/PP_Coupons_List.php
				'menu_icon'    => 'dashicons-tag',
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'show_in_rest' => false,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'pp_coupon_details', __( 'Coupon Rules', 'passpress' ), array( $this, 'render_meta_box' ), 'pp_coupon', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'pp_save_coupon_meta', 'pp_coupon_meta_nonce' );

		$discount_type       = get_post_meta( $post->ID, '_pp_discount_type', true ) ?: 'percent';
		$discount_amount     = get_post_meta( $post->ID, '_pp_discount_amount', true );
		$applicable_plans    = get_post_meta( $post->ID, '_pp_applicable_plans', true );
		$applicable_plans    = is_array( $applicable_plans ) ? $applicable_plans : array();
		$usage_limit_total   = get_post_meta( $post->ID, '_pp_usage_limit_total', true );
		$usage_limit_peruser = get_post_meta( $post->ID, '_pp_usage_limit_per_user', true );
		$usage_limit_peruser = '' === $usage_limit_peruser ? 1 : $usage_limit_peruser;
		$expiry_date         = get_post_meta( $post->ID, '_pp_expiry_date', true );
		$active              = get_post_meta( $post->ID, '_pp_active', true );
		$active              = '' === $active ? 1 : $active;

		$plans = get_posts( array( 'post_type' => 'pp_membership_plan', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		?>
		<p class="description"><?php esc_html_e( 'The Title field above is the coupon code members enter at checkout (not case-sensitive).', 'passpress' ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active', 'passpress' ); ?></th>
				<td><label><input type="checkbox" name="_pp_active" value="1" <?php checked( $active, 1 ); ?>> <?php esc_html_e( 'Coupon can be used', 'passpress' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="pp_discount_type"><?php esc_html_e( 'Discount Type', 'passpress' ); ?></label></th>
				<td>
					<select name="_pp_discount_type" id="pp_discount_type">
						<option value="percent" <?php selected( $discount_type, 'percent' ); ?>><?php esc_html_e( 'Percentage', 'passpress' ); ?></option>
						<option value="fixed" <?php selected( $discount_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'passpress' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pp_discount_amount"><?php esc_html_e( 'Discount Amount', 'passpress' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" name="_pp_discount_amount" id="pp_discount_amount" value="<?php echo esc_attr( $discount_amount ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'A percentage (e.g. 20 for 20%) or a fixed currency amount, depending on Discount Type above.', 'passpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Applicable Plans', 'passpress' ); ?></th>
				<td>
					<?php if ( ! $plans ) : ?>
						<em><?php esc_html_e( 'No membership plans exist yet.', 'passpress' ); ?></em>
					<?php endif; ?>
					<?php foreach ( $plans as $plan ) : ?>
						<label style="display:block;"><input type="checkbox" name="_pp_applicable_plans[]" value="<?php echo esc_attr( $plan->ID ); ?>" <?php checked( in_array( (int) $plan->ID, array_map( 'intval', $applicable_plans ), true ) ); ?>> <?php echo esc_html( $plan->post_title ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Leave all unchecked to allow this coupon on any plan.', 'passpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pp_usage_limit_total"><?php esc_html_e( 'Total Usage Limit (0 = unlimited)', 'passpress' ); ?></label></th>
				<td><input type="number" min="0" name="_pp_usage_limit_total" id="pp_usage_limit_total" value="<?php echo esc_attr( $usage_limit_total ? $usage_limit_total : 0 ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="pp_usage_limit_per_user"><?php esc_html_e( 'Usage Limit Per Member (0 = unlimited)', 'passpress' ); ?></label></th>
				<td><input type="number" min="0" name="_pp_usage_limit_per_user" id="pp_usage_limit_per_user" value="<?php echo esc_attr( $usage_limit_peruser ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="pp_expiry_date"><?php esc_html_e( 'Expiry Date (optional)', 'passpress' ); ?></label></th>
				<td><input type="date" name="_pp_expiry_date" id="pp_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>"></td>
			</tr>
		</table>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['pp_coupon_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pp_coupon_meta_nonce'], 'pp_save_coupon_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_pp_active', ! empty( $_POST['_pp_active'] ) ? 1 : 0 );

		if ( isset( $_POST['_pp_discount_type'] ) ) {
			update_post_meta( $post_id, '_pp_discount_type', 'fixed' === $_POST['_pp_discount_type'] ? 'fixed' : 'percent' );
		}
		if ( isset( $_POST['_pp_discount_amount'] ) ) {
			update_post_meta( $post_id, '_pp_discount_amount', (float) wp_unslash( $_POST['_pp_discount_amount'] ) );
		}

		$applicable_plans = isset( $_POST['_pp_applicable_plans'] ) && is_array( $_POST['_pp_applicable_plans'] ) ? array_map( 'absint', $_POST['_pp_applicable_plans'] ) : array();
		update_post_meta( $post_id, '_pp_applicable_plans', $applicable_plans );

		if ( isset( $_POST['_pp_usage_limit_total'] ) ) {
			update_post_meta( $post_id, '_pp_usage_limit_total', absint( $_POST['_pp_usage_limit_total'] ) );
		}
		if ( isset( $_POST['_pp_usage_limit_per_user'] ) ) {
			update_post_meta( $post_id, '_pp_usage_limit_per_user', absint( $_POST['_pp_usage_limit_per_user'] ) );
		}
		if ( isset( $_POST['_pp_expiry_date'] ) ) {
			update_post_meta( $post_id, '_pp_expiry_date', sanitize_text_field( wp_unslash( $_POST['_pp_expiry_date'] ) ) );
		}

		// Coupon codes are looked up case-insensitively but stored uppercased
		// for a consistent, scannable admin list.
		$post = get_post( $post_id );
		if ( $post && strtoupper( $post->post_title ) !== $post->post_title ) {
			remove_action( 'save_post_pp_coupon', array( $this, 'save_meta' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => strtoupper( $post->post_title ) ) );
			add_action( 'save_post_pp_coupon', array( $this, 'save_meta' ) );
		}
	}
}
