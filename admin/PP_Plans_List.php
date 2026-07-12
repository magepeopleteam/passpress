<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card-grid replacement for the native pp_membership_plan list table
 * (`edit.php?post_type=pp_membership_plan` — the CPT is registered with
 * `show_in_menu => false` specifically so this custom page owns that slot
 * instead, see class-pp-membership-cpt.php). Editing/trashing a plan still
 * goes through the native post editor (post.php/post-new.php) — only the
 * list view (and now, plan creation) is redesigned here.
 *
 * "+ New Plan" opens an in-page modal instead of navigating to post-new.php;
 * submitting it creates the plan over AJAX (ajax_create_plan()) using the
 * exact same meta fields/sanitization as PP_Membership_Plan_CPT::save_meta(),
 * then reloads the page so the grid re-renders server-side — no client-side
 * duplication of render_card()'s markup.
 */
class PP_Plans_List {

	public static function init() {
		add_action( 'wp_ajax_pp_create_plan', array( __CLASS__, 'ajax_create_plan' ) );
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$plans    = self::get_plans();
		$sold     = self::get_sold_counts();
		$settings = pp_get_settings();
		?>
		<div class="wrap passpress-wrap">
			<div class="passpress-plans-header">
				<h1><?php esc_html_e( 'Membership Plans', 'passpress' ); ?></h1>
				<button type="button" id="passpress-new-plan-trigger" class="button button-primary passpress-plans-new-btn">
					<?php esc_html_e( '+ New Plan', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( ! $plans ) : ?>
				<p><?php esc_html_e( 'No membership plans yet.', 'passpress' ); ?></p>
			<?php else : ?>
				<div class="passpress-plans-grid">
					<?php foreach ( $plans as $plan ) : ?>
						<?php self::render_card( $plan, $settings, isset( $sold[ $plan->ID ] ) ? $sold[ $plan->ID ] : 0 ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php self::render_new_plan_modal( $settings ); ?>
		</div>
		<?php
	}

	private static function render_card( $plan, $settings, $sold_count ) {
		$price             = (float) get_post_meta( $plan->ID, '_pp_price', true );
		$plan_type         = get_post_meta( $plan->ID, '_pp_plan_type', true );
		$duration_value    = (int) get_post_meta( $plan->ID, '_pp_duration_value', true );
		$duration_unit     = get_post_meta( $plan->ID, '_pp_duration_unit', true );
		$entry_restriction = get_post_meta( $plan->ID, '_pp_entry_restriction', true );
		$max_per_day       = (int) get_post_meta( $plan->ID, '_pp_max_entries_per_day', true );

		$plan_types         = PP_Membership_Plan_CPT::plan_types();
		$entry_restrictions = PP_Membership_Plan_CPT::entry_restrictions();

		$duration_label = ( 'lifetime' === $duration_unit || ! $duration_value )
			? __( 'Lifetime', 'passpress' )
			: $duration_value . ' ' . $duration_unit . ( $duration_value > 1 ? 's' : '' );
		?>
		<a class="passpress-plan-admin-card" href="<?php echo esc_url( get_edit_post_link( $plan->ID ) ); ?>">
			<div class="passpress-plan-admin-card-top">
				<h3><?php echo esc_html( $plan->post_title ); ?></h3>
				<span class="passpress-plan-admin-price"><?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $price, 2 ) ); ?></span>
			</div>

			<div class="passpress-plan-admin-details">
				<?php if ( isset( $plan_types[ $plan_type ] ) ) : ?>
					<p><span><?php esc_html_e( 'Type', 'passpress' ); ?></span><strong><?php echo esc_html( $plan_types[ $plan_type ] ); ?></strong></p>
				<?php endif; ?>
				<p><span><?php esc_html_e( 'Duration', 'passpress' ); ?></span><strong><?php echo esc_html( $duration_label ); ?></strong></p>
				<?php if ( isset( $entry_restrictions[ $entry_restriction ] ) ) : ?>
					<p><span><?php esc_html_e( 'Entry Restriction', 'passpress' ); ?></span><strong><?php echo esc_html( $entry_restrictions[ $entry_restriction ] ); ?></strong></p>
				<?php endif; ?>
				<?php if ( $max_per_day > 0 ) : ?>
					<p><span><?php esc_html_e( 'Max Entries/Day', 'passpress' ); ?></span><strong><?php echo esc_html( $max_per_day ); ?></strong></p>
				<?php endif; ?>
			</div>

			<div class="passpress-plan-admin-footer">
				<?php
				printf(
					/* translators: 1: number sold, 2: Live on site/Draft */
					esc_html__( '%1$d sold · %2$s', 'passpress' ),
					(int) $sold_count,
					'publish' === $plan->post_status ? esc_html__( 'Live on site', 'passpress' ) : esc_html__( 'Draft', 'passpress' )
				);
				?>
			</div>
		</a>
		<?php
	}

	private static function render_new_plan_modal( $settings ) {
		?>
		<div id="passpress-new-plan-modal" class="passpress-modal-overlay" style="display:none;">
			<div class="passpress-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-new-plan-title">
				<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				<h2 id="passpress-new-plan-title"><?php esc_html_e( 'New Membership Plan', 'passpress' ); ?></h2>

				<div class="passpress-modal-notice" style="display:none;"></div>

				<form id="passpress-new-plan-form">
					<?php wp_nonce_field( 'pp_create_plan', 'pp_create_plan_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="pp_new_plan_title"><?php esc_html_e( 'Plan Name', 'passpress' ); ?></label></th>
							<td><input type="text" id="pp_new_plan_title" name="title" class="regular-text" required></td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_type"><?php esc_html_e( 'Plan Type', 'passpress' ); ?></label></th>
							<td>
								<select id="pp_new_plan_type" name="_pp_plan_type">
									<?php foreach ( PP_Membership_Plan_CPT::plan_types() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_price"><?php esc_html_e( 'Price', 'passpress' ); ?></label></th>
							<td>
								<?php echo esc_html( $settings['currency_symbol'] ); ?>
								<input type="number" step="0.01" min="0" id="pp_new_plan_price" name="_pp_price" class="small-text" value="0">
							</td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_duration_value"><?php esc_html_e( 'Duration', 'passpress' ); ?></label></th>
							<td>
								<input type="number" min="0" id="pp_new_plan_duration_value" name="_pp_duration_value" class="small-text" value="1">
								<select name="_pp_duration_unit">
									<?php foreach ( PP_Membership_Plan_CPT::duration_units() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'month', $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_restriction"><?php esc_html_e( 'Entry Restriction', 'passpress' ); ?></label></th>
							<td>
								<select id="pp_new_plan_restriction" name="_pp_entry_restriction">
									<?php foreach ( PP_Membership_Plan_CPT::entry_restrictions() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_time_start"><?php esc_html_e( 'Time Window (if time restricted)', 'passpress' ); ?></label></th>
							<td>
								<input type="time" id="pp_new_plan_time_start" name="_pp_time_restriction_start">
								&mdash;
								<input type="time" name="_pp_time_restriction_end">
							</td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_max_per_day"><?php esc_html_e( 'Max Entries / Day (0 = unlimited)', 'passpress' ); ?></label></th>
							<td><input type="number" min="0" id="pp_new_plan_max_per_day" name="_pp_max_entries_per_day" class="small-text" value="0"></td>
						</tr>
						<tr>
							<th><label for="pp_new_plan_features"><?php esc_html_e( 'Features', 'passpress' ); ?></label></th>
							<td>
								<textarea id="pp_new_plan_features" name="_pp_features" rows="4" class="large-text" placeholder="<?php esc_attr_e( "One per line, e.g.\nFull facility access\nValid until midnight\nInstant QR by email", 'passpress' ); ?>"></textarea>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Most Popular', 'passpress' ); ?></th>
							<td><label><input type="checkbox" name="_pp_most_popular" value="1"> <?php esc_html_e( 'Highlight with a "Most Popular" badge', 'passpress' ); ?></label></td>
						</tr>
					</table>
					<p class="passpress-modal-actions">
						<button type="submit" class="button button-primary" id="passpress-new-plan-submit"><?php esc_html_e( 'Create Plan', 'passpress' ); ?></button>
						<button type="button" class="button passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	public static function ajax_create_plan() {
		check_ajax_referer( 'pp_create_plan', 'pp_create_plan_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a plan name.', 'passpress' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_membership_plan',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		// Same fields/sanitization as PP_Membership_Plan_CPT::save_meta() —
		// that method's own nonce check (a different nonce than this AJAX
		// action's) harmlessly no-ops when save_post fires from the
		// wp_insert_post() call above, so setting meta explicitly here is
		// the one source of truth for this creation path.
		update_post_meta( $post_id, '_pp_price', isset( $_POST['_pp_price'] ) ? (float) wp_unslash( $_POST['_pp_price'] ) : 0 );
		update_post_meta( $post_id, '_pp_plan_type', isset( $_POST['_pp_plan_type'] ) ? sanitize_key( $_POST['_pp_plan_type'] ) : 'monthly' );
		update_post_meta( $post_id, '_pp_duration_value', isset( $_POST['_pp_duration_value'] ) ? absint( $_POST['_pp_duration_value'] ) : 1 );
		update_post_meta( $post_id, '_pp_duration_unit', isset( $_POST['_pp_duration_unit'] ) ? sanitize_key( $_POST['_pp_duration_unit'] ) : 'month' );
		update_post_meta( $post_id, '_pp_entry_restriction', isset( $_POST['_pp_entry_restriction'] ) ? sanitize_key( $_POST['_pp_entry_restriction'] ) : 'none' );
		update_post_meta( $post_id, '_pp_time_restriction_start', isset( $_POST['_pp_time_restriction_start'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_time_restriction_start'] ) ) : '' );
		update_post_meta( $post_id, '_pp_time_restriction_end', isset( $_POST['_pp_time_restriction_end'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_time_restriction_end'] ) ) : '' );
		update_post_meta( $post_id, '_pp_max_entries_per_day', isset( $_POST['_pp_max_entries_per_day'] ) ? absint( $_POST['_pp_max_entries_per_day'] ) : 0 );
		update_post_meta( $post_id, '_pp_features', isset( $_POST['_pp_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_pp_features'] ) ) : '' );
		update_post_meta( $post_id, '_pp_most_popular', ! empty( $_POST['_pp_most_popular'] ) ? 1 : 0 );

		if ( class_exists( 'PP_Shop_WooCommerce' ) && PP_Shop_WooCommerce::is_available() ) {
			PP_Shop_WooCommerce::sync_product_for_plan( $post_id );
		}

		PP_Activity_Logger::log( 'membership_plan_created', 'plan', $post_id, sprintf( 'Plan "%s" created.', $title ) );

		wp_send_json_success(
			array(
				'message'   => __( 'Plan created!', 'passpress' ),
				'plan_id'   => $post_id,
				'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
				'reload_url' => admin_url( 'admin.php?page=passpress-plans' ),
			)
		);
	}

	private static function get_plans() {
		return get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * @return array plan_id => issued-membership count (member_type='member'
	 *               only — same query PP_Reports::get_popular_plans() uses,
	 *               so "sold" here matches the Reports page's definition and
	 *               covers every issuance channel: admin manual issue, native
	 *               checkout, and WooCommerce Shop orders alike).
	 */
	private static function get_sold_counts() {
		$counts = array();
		foreach ( PP_Reports::get_popular_plans() as $row ) {
			$counts[ $row['plan_id'] ] = $row['count'];
		}
		return $counts;
	}
}
