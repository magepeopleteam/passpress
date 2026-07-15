<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card-grid replacement for edit.php?post_type=pp_coupon / post-new.php.
 * Create and edit both open the same in-page modal; meta matches
 * PP_Coupon_CPT::save_meta().
 */
class PP_Coupons_List {

	public static function init() {
		add_action( 'wp_ajax_pp_create_coupon', array( __CLASS__, 'ajax_create_coupon' ) );
		add_action( 'wp_ajax_pp_get_coupon', array( __CLASS__, 'ajax_get_coupon' ) );
		add_action( 'wp_ajax_pp_update_coupon', array( __CLASS__, 'ajax_update_coupon' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy' ) );
	}

	/**
	 * Send anyone hitting the native CPT screens to the redesigned page.
	 */
	public static function maybe_redirect_legacy() {
		global $pagenow;

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		$target = admin_url( 'admin.php?page=passpress-coupons' );

		if ( 'edit.php' === $pagenow && ! empty( $_GET['post_type'] ) && 'pp_coupon' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( $target );
			exit;
		}

		if ( 'post-new.php' === $pagenow && ! empty( $_GET['post_type'] ) && 'pp_coupon' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( $target );
			exit;
		}

		if ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post    = get_post( $post_id );
			if ( $post && 'pp_coupon' === $post->post_type ) {
				wp_safe_redirect( add_query_arg( 'edit', $post_id, $target ) );
				exit;
			}
		}
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$coupons  = self::get_coupons();
		$usage    = self::get_usage_counts();
		$settings = pp_get_settings();
		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap passpress-wrap passpress-coupons-page"<?php echo $edit_id ? ' data-auto-edit="' . esc_attr( (string) $edit_id ) . '"' : ''; ?>>
			<div class="passpress-coupons-page-header">
				<div class="passpress-coupons-page-copy">
					<p class="passpress-coupons-page-eyebrow"><?php esc_html_e( 'Marketing', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Coupons', 'passpress' ); ?></h1>
					<p class="passpress-coupons-page-desc">
						<?php
						printf(
							/* translators: %d: number of coupons */
							esc_html( _n( '%d promo code in your catalog', '%d promo codes in your catalog', count( $coupons ), 'passpress' ) ),
							count( $coupons )
						);
						?>
					</p>
				</div>
				<button type="button" id="passpress-new-coupon-trigger" class="passpress-coupons-new-btn">
					<?php esc_html_e( 'New coupon', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( ! $coupons ) : ?>
				<div class="passpress-coupons-empty">
					<p class="passpress-coupons-empty-eyebrow"><?php esc_html_e( 'Get started', 'passpress' ); ?></p>
					<h2 class="passpress-coupons-empty-title"><?php esc_html_e( 'No coupons yet', 'passpress' ); ?></h2>
					<p class="passpress-coupons-empty-desc"><?php esc_html_e( 'Create a promo code members can enter at checkout for a percentage or fixed discount.', 'passpress' ); ?></p>
					<button type="button" class="passpress-coupons-new-btn passpress-coupons-empty-cta" data-open-new-coupon>
						<?php esc_html_e( 'Create a coupon', 'passpress' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="passpress-coupons-grid">
					<?php foreach ( $coupons as $coupon ) : ?>
						<?php self::render_card( $coupon, $settings, isset( $usage[ $coupon->post_title ] ) ? $usage[ $coupon->post_title ] : 0 ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php self::render_modal( $settings ); ?>
		</div>
		<?php
	}

	private static function render_card( $coupon, $settings, $used_count ) {
		$discount_type   = get_post_meta( $coupon->ID, '_pp_discount_type', true ) ?: 'percent';
		$discount_amount = (float) get_post_meta( $coupon->ID, '_pp_discount_amount', true );
		$active          = get_post_meta( $coupon->ID, '_pp_active', true );
		$active          = '' === $active ? 1 : (int) $active;
		$expiry_date     = get_post_meta( $coupon->ID, '_pp_expiry_date', true );
		$limit_total     = (int) get_post_meta( $coupon->ID, '_pp_usage_limit_total', true );
		$limit_per_user  = get_post_meta( $coupon->ID, '_pp_usage_limit_per_user', true );
		$limit_per_user  = '' === $limit_per_user ? 1 : (int) $limit_per_user;
		$plans           = get_post_meta( $coupon->ID, '_pp_applicable_plans', true );
		$plans           = is_array( $plans ) ? $plans : array();
		$is_live         = ( 'publish' === $coupon->post_status );
		$is_expired      = $expiry_date && strtotime( $expiry_date ) < strtotime( current_time( 'Y-m-d' ) );

		if ( 'fixed' === $discount_type ) {
			$discount_label = $settings['currency_symbol'] . number_format_i18n( $discount_amount, 2 );
		} else {
			$discount_label = rtrim( rtrim( number_format_i18n( $discount_amount, 2 ), '0' ), '.' ) . '%';
		}

		$plans_label = $plans
			? sprintf(
				/* translators: %d: number of plans */
				_n( '%d plan', '%d plans', count( $plans ), 'passpress' ),
				count( $plans )
			)
			: __( 'All plans', 'passpress' );

		$limit_label = $limit_total > 0
			? sprintf(
				/* translators: 1: used count, 2: total limit */
				__( '%1$d / %2$d used', 'passpress' ),
				(int) $used_count,
				$limit_total
			)
			: sprintf(
				/* translators: %d: redemption count */
				_n( '%d use', '%d uses', (int) $used_count, 'passpress' ),
				(int) $used_count
			);
		?>
		<button type="button" class="passpress-coupon-admin-card<?php echo $active && ! $is_expired ? ' is-active' : ' is-inactive'; ?><?php echo $is_live ? ' is-live' : ' is-draft'; ?>" data-edit-coupon="<?php echo esc_attr( (string) $coupon->ID ); ?>">
			<div class="passpress-coupon-admin-card-top">
				<div class="passpress-coupon-admin-card-badges">
					<span class="passpress-coupon-admin-badge <?php echo ( $active && ! $is_expired ) ? 'is-active' : 'is-inactive'; ?>">
						<?php
						if ( $is_expired ) {
							esc_html_e( 'Expired', 'passpress' );
						} elseif ( $active ) {
							esc_html_e( 'Active', 'passpress' );
						} else {
							esc_html_e( 'Off', 'passpress' );
						}
						?>
					</span>
					<span class="passpress-coupon-admin-badge <?php echo $is_live ? 'is-live' : 'is-draft'; ?>">
						<?php echo $is_live ? esc_html__( 'Live', 'passpress' ) : esc_html__( 'Draft', 'passpress' ); ?>
					</span>
					<span class="passpress-coupon-admin-badge is-type">
						<?php echo 'fixed' === $discount_type ? esc_html__( 'Fixed', 'passpress' ) : esc_html__( 'Percent', 'passpress' ); ?>
					</span>
				</div>
				<span class="passpress-coupon-admin-discount"><?php echo esc_html( $discount_label ); ?></span>
			</div>

			<h3 class="passpress-coupon-admin-title"><?php echo esc_html( $coupon->post_title ); ?></h3>

			<dl class="passpress-coupon-admin-details">
				<div>
					<dt><?php esc_html_e( 'Applies to', 'passpress' ); ?></dt>
					<dd><?php echo esc_html( $plans_label ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Per member', 'passpress' ); ?></dt>
					<dd>
						<?php
						echo $limit_per_user > 0
							? esc_html( (string) $limit_per_user )
							: esc_html__( 'Unlimited', 'passpress' );
						?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Expires', 'passpress' ); ?></dt>
					<dd><?php echo $expiry_date ? esc_html( $expiry_date ) : esc_html__( 'Never', 'passpress' ); ?></dd>
				</div>
			</dl>

			<div class="passpress-coupon-admin-footer">
				<span class="passpress-coupon-admin-usage"><?php echo esc_html( $limit_label ); ?></span>
				<span class="passpress-coupon-admin-edit"><?php esc_html_e( 'Edit coupon', 'passpress' ); ?></span>
			</div>
		</button>
		<?php
	}

	private static function render_modal( $settings ) {
		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div id="passpress-coupon-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-coupon-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-coupon-modal-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow" data-label-create="<?php esc_attr_e( 'Create', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit', 'passpress' ); ?>"><?php esc_html_e( 'Create', 'passpress' ); ?></p>
						<h2 id="passpress-coupon-modal-title" data-label-create="<?php esc_attr_e( 'New coupon', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Edit coupon', 'passpress' ); ?>"><?php esc_html_e( 'New coupon', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>

				<div class="passpress-modal-notice" hidden></div>

				<form id="passpress-coupon-form" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_coupon_modal', 'pp_coupon_modal_nonce' ); ?>
					<input type="hidden" name="coupon_id" id="pp_coupon_id" value="0">

					<div class="pp-field">
						<label class="pp-label" for="pp_coupon_code"><?php esc_html_e( 'Coupon code', 'passpress' ); ?></label>
						<input type="text" id="pp_coupon_code" name="title" class="pp-input" placeholder="<?php esc_attr_e( 'e.g. SUMMER20', 'passpress' ); ?>" required autocomplete="off" style="text-transform:uppercase;">
						<p class="pp-field-hint"><?php esc_html_e( 'Members enter this at checkout. Not case-sensitive; stored uppercase.', 'passpress' ); ?></p>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_coupon_discount_type"><?php esc_html_e( 'Discount type', 'passpress' ); ?></label>
							<select id="pp_coupon_discount_type" name="_pp_discount_type" class="pp-input pp-input-select">
								<option value="percent"><?php esc_html_e( 'Percentage', 'passpress' ); ?></option>
								<option value="fixed"><?php esc_html_e( 'Fixed amount', 'passpress' ); ?></option>
							</select>
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_coupon_discount_amount"><?php esc_html_e( 'Amount', 'passpress' ); ?></label>
							<div class="pp-input-prefix-wrap">
								<span class="pp-input-prefix" id="pp_coupon_amount_prefix" data-currency="<?php echo esc_attr( $settings['currency_symbol'] ); ?>">%</span>
								<input type="number" step="0.01" min="0" id="pp_coupon_discount_amount" name="_pp_discount_amount" class="pp-input" value="10">
							</div>
							<p class="pp-field-hint"><?php esc_html_e( 'Percentage (e.g. 20) or fixed currency amount, matching the type above.', 'passpress' ); ?></p>
						</div>
					</div>

					<hr class="pp-divider">

					<div class="pp-field">
						<span class="pp-label"><?php esc_html_e( 'Applicable plans', 'passpress' ); ?></span>
						<p class="pp-field-hint"><?php esc_html_e( 'Leave all unchecked to allow any plan.', 'passpress' ); ?></p>
						<?php if ( ! $plans ) : ?>
							<p class="pp-field-hint"><?php esc_html_e( 'No membership plans exist yet.', 'passpress' ); ?></p>
						<?php else : ?>
							<div class="pp-checkbox-grid">
								<?php foreach ( $plans as $plan ) : ?>
									<label class="pp-checkbox-box pp-checkbox-compact">
										<input type="checkbox" name="_pp_applicable_plans[]" value="<?php echo esc_attr( (string) $plan->ID ); ?>" class="pp-coupon-plan-check">
										<span><?php echo esc_html( $plan->post_title ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="pp-field-row">
						<div class="pp-field">
							<div class="pp-label-row">
								<label class="pp-label" for="pp_coupon_usage_total"><?php esc_html_e( 'Total usage limit', 'passpress' ); ?></label>
								<span class="pp-label-hint"><?php esc_html_e( '0 = unlimited', 'passpress' ); ?></span>
							</div>
							<input type="number" min="0" id="pp_coupon_usage_total" name="_pp_usage_limit_total" class="pp-input" value="0">
						</div>
						<div class="pp-field">
							<div class="pp-label-row">
								<label class="pp-label" for="pp_coupon_usage_per_user"><?php esc_html_e( 'Per member limit', 'passpress' ); ?></label>
								<span class="pp-label-hint"><?php esc_html_e( '0 = unlimited', 'passpress' ); ?></span>
							</div>
							<input type="number" min="0" id="pp_coupon_usage_per_user" name="_pp_usage_limit_per_user" class="pp-input" value="1">
						</div>
					</div>

					<div class="pp-field">
						<label class="pp-label" for="pp_coupon_expiry"><?php esc_html_e( 'Expiry date', 'passpress' ); ?></label>
						<input type="date" id="pp_coupon_expiry" name="_pp_expiry_date" class="pp-input">
						<p class="pp-field-hint"><?php esc_html_e( 'Optional. Leave blank for no expiry.', 'passpress' ); ?></p>
					</div>

					<label class="pp-checkbox-box">
						<input type="checkbox" id="pp_coupon_active" name="_pp_active" value="1" checked>
						<span><?php esc_html_e( 'Coupon can be used (active)', 'passpress' ); ?></span>
					</label>

					<label class="pp-checkbox-box pp-coupon-status-box" hidden>
						<input type="checkbox" id="pp_coupon_live" name="is_live" value="1" checked>
						<span><?php esc_html_e( 'Live (published)', 'passpress' ); ?></span>
					</label>

					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" class="pp-btn-solid" id="passpress-coupon-submit" data-label-create="<?php esc_attr_e( 'Create coupon', 'passpress' ); ?>" data-label-edit="<?php esc_attr_e( 'Save changes', 'passpress' ); ?>"><?php esc_html_e( 'Create coupon', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int $post_id Coupon post ID.
	 */
	private static function save_coupon_meta_from_request( $post_id ) {
		update_post_meta( $post_id, '_pp_active', ! empty( $_POST['_pp_active'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_pp_discount_type', ( isset( $_POST['_pp_discount_type'] ) && 'fixed' === $_POST['_pp_discount_type'] ) ? 'fixed' : 'percent' );
		update_post_meta( $post_id, '_pp_discount_amount', isset( $_POST['_pp_discount_amount'] ) ? (float) wp_unslash( $_POST['_pp_discount_amount'] ) : 0 );

		$applicable_plans = isset( $_POST['_pp_applicable_plans'] ) && is_array( $_POST['_pp_applicable_plans'] )
			? array_map( 'absint', $_POST['_pp_applicable_plans'] )
			: array();
		update_post_meta( $post_id, '_pp_applicable_plans', $applicable_plans );

		update_post_meta( $post_id, '_pp_usage_limit_total', isset( $_POST['_pp_usage_limit_total'] ) ? absint( $_POST['_pp_usage_limit_total'] ) : 0 );
		update_post_meta( $post_id, '_pp_usage_limit_per_user', isset( $_POST['_pp_usage_limit_per_user'] ) ? absint( $_POST['_pp_usage_limit_per_user'] ) : 1 );
		update_post_meta( $post_id, '_pp_expiry_date', isset( $_POST['_pp_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_pp_expiry_date'] ) ) : '' );
	}

	/**
	 * @param int $coupon_id
	 * @return array|WP_Error
	 */
	private static function get_coupon_payload( $coupon_id ) {
		$coupon = get_post( $coupon_id );
		if ( ! $coupon || 'pp_coupon' !== $coupon->post_type ) {
			return new WP_Error( 'not_found', __( 'Coupon not found.', 'passpress' ) );
		}

		$active = get_post_meta( $coupon->ID, '_pp_active', true );
		$active = '' === $active ? 1 : (int) $active;

		$per_user = get_post_meta( $coupon->ID, '_pp_usage_limit_per_user', true );
		$per_user = '' === $per_user ? 1 : (int) $per_user;

		$plans = get_post_meta( $coupon->ID, '_pp_applicable_plans', true );
		$plans = is_array( $plans ) ? array_map( 'intval', $plans ) : array();

		return array(
			'coupon_id'               => (int) $coupon->ID,
			'title'                   => $coupon->post_title,
			'status'                  => $coupon->post_status,
			'is_live'                 => ( 'publish' === $coupon->post_status ) ? 1 : 0,
			'_pp_active'              => $active,
			'_pp_discount_type'       => (string) ( get_post_meta( $coupon->ID, '_pp_discount_type', true ) ?: 'percent' ),
			'_pp_discount_amount'     => (float) get_post_meta( $coupon->ID, '_pp_discount_amount', true ),
			'_pp_applicable_plans'    => $plans,
			'_pp_usage_limit_total'   => (int) get_post_meta( $coupon->ID, '_pp_usage_limit_total', true ),
			'_pp_usage_limit_per_user'=> $per_user,
			'_pp_expiry_date'         => (string) get_post_meta( $coupon->ID, '_pp_expiry_date', true ),
		);
	}

	/**
	 * @param string $code
	 * @param int    $exclude_id
	 * @return bool
	 */
	private static function code_exists( $code, $exclude_id = 0 ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pp_coupon' AND post_status IN ('publish','draft') AND UPPER(post_title) = %s",
			$code
		);
		if ( $exclude_id ) {
			$sql .= $wpdb->prepare( ' AND ID != %d', $exclude_id );
		}
		$sql .= ' LIMIT 1';
		return (bool) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function ajax_get_coupon() {
		check_ajax_referer( 'pp_coupon_modal', 'pp_coupon_modal_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		$payload   = self::get_coupon_payload( $coupon_id );
		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
		}

		wp_send_json_success( $payload );
	}

	public static function ajax_create_coupon() {
		check_ajax_referer( 'pp_coupon_modal', 'pp_coupon_modal_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'passpress' ) ) );
		}
		if ( self::code_exists( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'That coupon code already exists.', 'passpress' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_coupon',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		self::save_coupon_meta_from_request( $post_id );

		PP_Activity_Logger::log( 'coupon_created', 'coupon', $post_id, sprintf( 'Coupon "%s" created.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Coupon created!', 'passpress' ),
				'coupon_id'  => $post_id,
				'reload_url' => admin_url( 'admin.php?page=passpress-coupons' ),
			)
		);
	}

	public static function ajax_update_coupon() {
		check_ajax_referer( 'pp_coupon_modal', 'pp_coupon_modal_nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		$coupon    = get_post( $coupon_id );
		if ( ! $coupon || 'pp_coupon' !== $coupon->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Coupon not found.', 'passpress' ) ) );
		}

		$title = isset( $_POST['title'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) : '';
		if ( ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'passpress' ) ) );
		}
		if ( self::code_exists( $title, $coupon_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That coupon code already exists.', 'passpress' ) ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $coupon_id,
				'post_title'  => $title,
				'post_status' => ! empty( $_POST['is_live'] ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
		}

		self::save_coupon_meta_from_request( $coupon_id );

		PP_Activity_Logger::log( 'coupon_updated', 'coupon', $coupon_id, sprintf( 'Coupon "%s" updated.', $title ) );

		wp_send_json_success(
			array(
				'message'    => __( 'Coupon saved!', 'passpress' ),
				'coupon_id'  => $coupon_id,
				'reload_url' => admin_url( 'admin.php?page=passpress-coupons' ),
			)
		);
	}

	private static function get_coupons() {
		return get_posts(
			array(
				'post_type'      => 'pp_coupon',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * @return array code => paid redemption count
	 */
	private static function get_usage_counts() {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_billing_history';
		$rows  = $wpdb->get_results( "SELECT coupon_code, COUNT(*) AS cnt FROM {$table} WHERE coupon_code != '' AND status = 'paid' GROUP BY coupon_code" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$counts[ $row->coupon_code ] = (int) $row->cnt;
			}
		}
		return $counts;
	}
}
