<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card-grid replacement for the native pp_membership_plan list table.
 * Create and edit both open the same in-page modal; saves go through AJAX
 * using the same meta fields/sanitization as PP_Membership_Plan_CPT::save_meta().
 */
class PP_Plans_List {

	public static function init() {
		add_action( 'wp_ajax_pp_create_plan', array( __CLASS__, 'ajax_create_plan' ) );
		add_action( 'wp_ajax_pp_get_plan', array( __CLASS__, 'ajax_get_plan' ) );
		add_action( 'wp_ajax_pp_update_plan', array( __CLASS__, 'ajax_update_plan' ) );
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$plans    = self::get_plans();
		$sold     = self::get_sold_counts();
		$settings = pp_get_settings();
		?>
		<div class="wrap passpress-wrap passpress-plans-page">
			<div class="passpress-plans-page-header">
				<div class="passpress-plans-page-copy">
					<p class="passpress-plans-page-eyebrow"><?php esc_html_e( 'Catalog', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Membership Plans', 'passpress' ); ?></h1>
					<p class="passpress-plans-page-desc">
						<?php
						printf(
							/* translators: %d: number of plans */
							esc_html( _n( '%d plan in your catalog', '%d plans in your catalog', count( $plans ), 'passpress' ) ),
							count( $plans )
						);
						?>
					</p>
				</div>
				<button type="button" id="passpress-new-plan-trigger" class="passpress-plans-new-btn">
					<?php esc_html_e( 'New plan', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( ! $plans ) : ?>
				<div class="passpress-plans-empty">
					<p class="passpress-plans-empty-eyebrow"><?php esc_html_e( 'Get started', 'passpress' ); ?></p>
					<h2 class="passpress-plans-empty-title"><?php esc_html_e( 'No membership plans yet', 'passpress' ); ?></h2>
					<p class="passpress-plans-empty-desc"><?php esc_html_e( 'Create your first plan to sell passes on the site and at the front desk.', 'passpress' ); ?></p>
					<button type="button" class="passpress-plans-new-btn passpress-plans-empty-cta" data-open-new-plan>
						<?php esc_html_e( 'Create a plan', 'passpress' ); ?>
					</button>
				</div>
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
		$most_popular      = (int) get_post_meta( $plan->ID, '_pp_most_popular', true );
		$is_live           = ( 'publish' === $plan->post_status );

		$plan_types         = PP_Membership_Plan_CPT::plan_types();
		$entry_restrictions = PP_Membership_Plan_CPT::entry_restrictions();
		$duration_units     = PP_Membership_Plan_CPT::duration_units();

		if ( 'lifetime' === $duration_unit || ! $duration_value ) {
			$duration_label = __( 'Lifetime', 'passpress' );
		} elseif ( isset( $duration_units[ $duration_unit ] ) ) {
			$duration_label = $duration_value . ' ' . $duration_units[ $duration_unit ];
		} else {
			$duration_label = $duration_value . ' ' . $duration_unit . ( $duration_value > 1 ? 's' : '' );
		}
		?>
		<button type="button" class="passpress-plan-admin-card<?php echo $most_popular ? ' is-popular' : ''; ?><?php echo $is_live ? ' is-live' : ' is-draft'; ?>" data-edit-plan="<?php echo esc_attr( (string) $plan->ID ); ?>">
			<div class="passpress-plan-admin-card-top">
				<div class="passpress-plan-admin-card-badges">
					<?php if ( $most_popular ) : ?>
						<span class="passpress-plan-admin-badge is-popular"><?php esc_html_e( 'Popular', 'passpress' ); ?></span>
					<?php endif; ?>
					<span class="passpress-plan-admin-badge <?php echo $is_live ? 'is-live' : 'is-draft'; ?>">
						<?php echo $is_live ? esc_html__( 'Live', 'passpress' ) : esc_html__( 'Draft', 'passpress' ); ?>
					</span>
				</div>
				<span class="passpress-plan-admin-price"><?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $price, 2 ) ); ?></span>
			</div>

			<h3 class="passpress-plan-admin-title"><?php echo esc_html( $plan->post_title ); ?></h3>

			<dl class="passpress-plan-admin-details">
				<?php if ( isset( $plan_types[ $plan_type ] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Type', 'passpress' ); ?></dt>
						<dd><?php echo esc_html( $plan_types[ $plan_type ] ); ?></dd>
					</div>
				<?php endif; ?>
				<div>
					<dt><?php esc_html_e( 'Duration', 'passpress' ); ?></dt>
					<dd><?php echo esc_html( $duration_label ); ?></dd>
				</div>
				<?php if ( isset( $entry_restrictions[ $entry_restriction ] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Entry', 'passpress' ); ?></dt>
						<dd><?php echo esc_html( $entry_restrictions[ $entry_restriction ] ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( $max_per_day > 0 ) : ?>
					<div>
						<dt><?php esc_html_e( 'Max / day', 'passpress' ); ?></dt>
						<dd><?php echo esc_html( (string) $max_per_day ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>

			<div class="passpress-plan-admin-footer">
				<span class="passpress-plan-admin-sold">
					<?php
					printf(
						/* translators: %d: number of memberships sold */
						esc_html( _n( '%d sold', '%d sold', (int) $sold_count, 'passpress' ) ),
						(int) $sold_count
					);
					?>
				</span>
				<span class="passpress-plan-admin-edit"><?php esc_html_e( 'Edit plan', 'passpress' ); ?></span>
			</div>
		</button>
		<?php
	}

	private static function render_new_plan_modal( $settings ) {
		?>
		<div id="passpress-new-plan-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-plan-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-new-plan-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow" data-label-create="<?php esc_attr_e( 'Create', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit', 'passpress' ); ?>"><?php esc_html_e( 'Create', 'passpress' ); ?></p>
						<h2 id="passpress-new-plan-title" data-label-create="<?php esc_attr_e( 'New membership plan', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit membership plan', 'passpress' ); ?>"><?php esc_html_e( 'New membership plan', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>

				<div class="passpress-modal-notice" hidden></div>

				<form id="passpress-new-plan-form" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_create_plan', 'pp_create_plan_nonce' ); ?>
					<input type="hidden" name="plan_id" id="pp_plan_id" value="0">

					<div class="pp-field">
						<label class="pp-label" for="pp_new_plan_title"><?php esc_html_e( 'Plan name', 'passpress' ); ?></label>
						<input type="text" id="pp_new_plan_title" name="title" class="pp-input" placeholder="<?php esc_attr_e( 'e.g. Gold Annual Membership', 'passpress' ); ?>" required>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_new_plan_type"><?php esc_html_e( 'Plan type', 'passpress' ); ?></label>
						<select id="pp_new_plan_type" name="_pp_plan_type" class="pp-input pp-input-select">
							<?php foreach ( PP_Membership_Plan_CPT::plan_types() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_new_plan_price"><?php esc_html_e( 'Price', 'passpress' ); ?></label>
							<div class="pp-input-prefix-wrap">
								<span class="pp-input-prefix"><?php echo esc_html( $settings['currency_symbol'] ); ?></span>
								<input type="number" step="0.01" min="0" id="pp_new_plan_price" name="_pp_price" class="pp-input" value="0">
							</div>
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_new_plan_duration_value"><?php esc_html_e( 'Duration', 'passpress' ); ?></label>
							<div class="pp-input-group">
								<input type="number" min="0" id="pp_new_plan_duration_value" name="_pp_duration_value" class="pp-input pp-input-narrow" value="1">
								<select id="pp_new_plan_duration_unit" name="_pp_duration_unit" class="pp-input pp-input-select">
									<?php foreach ( PP_Membership_Plan_CPT::duration_units() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'month', $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>

					<hr class="pp-divider">

					<div class="pp-field">
						<label class="pp-label" for="pp_new_plan_restriction"><?php esc_html_e( 'Entry restriction', 'passpress' ); ?></label>
						<select id="pp_new_plan_restriction" name="_pp_entry_restriction" class="pp-input pp-input-select">
							<?php foreach ( PP_Membership_Plan_CPT::entry_restrictions() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_new_plan_time_start"><?php esc_html_e( 'Time window', 'passpress' ); ?></label>
						<p class="pp-field-hint"><?php esc_html_e( 'Used when entry is time restricted.', 'passpress' ); ?></p>
						<div class="pp-input-group pp-input-group-time">
							<input type="time" id="pp_new_plan_time_start" name="_pp_time_restriction_start" class="pp-input">
							<span class="pp-input-group-sep">&mdash;</span>
							<input type="time" id="pp_new_plan_time_end" name="_pp_time_restriction_end" class="pp-input">
						</div>
					</div>

					<div class="pp-field">
						<div class="pp-label-row">
							<label class="pp-label" for="pp_new_plan_max_per_day"><?php esc_html_e( 'Max entries / day', 'passpress' ); ?></label>
							<span class="pp-label-hint"><?php esc_html_e( '0 = unlimited', 'passpress' ); ?></span>
						</div>
						<input type="number" min="0" id="pp_new_plan_max_per_day" name="_pp_max_entries_per_day" class="pp-input pp-input-narrow" value="0">
					</div>

					<hr class="pp-divider">

					<div class="pp-field">
						<label class="pp-label" for="pp_new_plan_features"><?php esc_html_e( 'Features', 'passpress' ); ?></label>
						<textarea id="pp_new_plan_features" name="_pp_features" rows="4" class="pp-input" placeholder="<?php esc_attr_e( "One per line, e.g.\nFull facility access\nValid until midnight\nInstant QR by email", 'passpress' ); ?>"></textarea>
					</div>

					<label class="pp-checkbox-box">
						<input type="checkbox" id="pp_new_plan_most_popular" name="_pp_most_popular" value="1">
						<span><?php esc_html_e( 'Highlight with a "Most Popular" badge', 'passpress' ); ?></span>
					</label>

					<label class="pp-checkbox-box pp-plan-status-box" hidden>
						<input type="checkbox" id="pp_new_plan_live" name="is_live" value="1" checked>
						<span><?php esc_html_e( 'Live on site (published)', 'passpress' ); ?></span>
					</label>

					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" class="pp-btn-solid" id="passpress-new-plan-submit" data-label-create="<?php esc_attr_e( 'Create plan', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Save changes', 'passpress' ); ?>"><?php esc_html_e( 'Create plan', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Shared meta write used by create + update AJAX handlers.
	 *
	 * @param int $post_id Plan post ID.
	 */
	private static function save_plan_meta_from_request( $post_id ) {
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
	}

	/**
	 * @param int $plan_id
	 * @return array|WP_Error
	 */
	private static function get_plan_payload( $plan_id ) {
		$plan = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type ) {
			return new WP_Error( 'not_found', __( 'Plan not found.', 'passpress' ) );
		}

		return array(
			'plan_id'                   => (int) $plan->ID,
			'title'                     => $plan->post_title,
			'status'                    => $plan->post_status,
			'is_live'                   => ( 'publish' === $plan->post_status ) ? 1 : 0,
			'_pp_price'                 => (float) get_post_meta( $plan->ID, '_pp_price', true ),
			'_pp_plan_type'             => (string) get_post_meta( $plan->ID, '_pp_plan_type', true ),
			'_pp_duration_value'        => (int) get_post_meta( $plan->ID, '_pp_duration_value', true ),
			'_pp_duration_unit'         => (string) get_post_meta( $plan->ID, '_pp_duration_unit', true ),
			'_pp_entry_restriction'     => (string) get_post_meta( $plan->ID, '_pp_entry_restriction', true ),
			'_pp_time_restriction_start'=> (string) get_post_meta( $plan->ID, '_pp_time_restriction_start', true ),
			'_pp_time_restriction_end'  => (string) get_post_meta( $plan->ID, '_pp_time_restriction_end', true ),
			'_pp_max_entries_per_day'   => (int) get_post_meta( $plan->ID, '_pp_max_entries_per_day', true ),
			'_pp_features'              => (string) get_post_meta( $plan->ID, '_pp_features', true ),
			'_pp_most_popular'          => (int) get_post_meta( $plan->ID, '_pp_most_popular', true ),
		);
	}

	public static function ajax_get_plan() {
		check_ajax_referer( 'pp_create_plan', 'pp_create_plan_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$payload = self::get_plan_payload( $plan_id );
		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
		}

		wp_send_json_success( $payload );
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

		self::save_plan_meta_from_request( $post_id );

		PP_Activity_Logger::log( 'membership_plan_created', 'plan', $post_id, sprintf( 'Plan "%s" created.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Plan created!', 'passpress' ),
				'plan_id'    => $post_id,
				'reload_url' => admin_url( 'admin.php?page=passpress-plans' ),
			)
		);
	}

	public static function ajax_update_plan() {
		check_ajax_referer( 'pp_create_plan', 'pp_create_plan_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$plan    = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Plan not found.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a plan name.', 'passpress' ) ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $plan_id,
				'post_title'  => $title,
				'post_status' => ! empty( $_POST['is_live'] ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
		}

		self::save_plan_meta_from_request( $plan_id );

		PP_Activity_Logger::log( 'membership_plan_updated', 'plan', $plan_id, sprintf( 'Plan "%s" updated.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Plan saved!', 'passpress' ),
				'plan_id'    => $plan_id,
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
