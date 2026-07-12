<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Four dynamic Gutenberg blocks, one per existing shortcode surface. Each
 * render_callback wraps the same canonical shortcode via do_shortcode()
 * rather than re-implementing the markup — see CLAUDE.md's "one canonical
 * render function per feature" rule. Only the JS editor side (block.js) has
 * anything block-specific: a ServerSideRender preview so the editor shows
 * the exact same output the frontend will.
 */
class PP_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	public static function register_blocks() {
		wp_register_script(
			'passpress-blocks-editor',
			PASSPRESS_PLUGIN_URL . '/assets/blocks/passpress-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			PASSPRESS_PLUGIN_VERSION,
			true
		);

		register_block_type(
			'passpress/plan-list',
			array(
				'editor_script'   => 'passpress-blocks-editor',
				'render_callback' => array( __CLASS__, 'render_plan_list' ),
			)
		);

		register_block_type(
			'passpress/my-pass',
			array(
				'editor_script'   => 'passpress-blocks-editor',
				'render_callback' => array( __CLASS__, 'render_my_pass' ),
			)
		);

		register_block_type(
			'passpress/booking-calendar',
			array(
				'editor_script'   => 'passpress-blocks-editor',
				'attributes'      => array(
					'facilityId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( __CLASS__, 'render_booking_calendar' ),
			)
		);

		register_block_type(
			'passpress/class-schedule',
			array(
				'editor_script'   => 'passpress-blocks-editor',
				'render_callback' => array( __CLASS__, 'render_class_schedule' ),
			)
		);
	}

	public static function render_plan_list( $attributes ) {
		return do_shortcode( '[passpress_membership_plans]' );
	}

	public static function render_my_pass( $attributes ) {
		return do_shortcode( '[passpress_my_pass]' );
	}

	public static function render_booking_calendar( $attributes ) {
		$facility_id = isset( $attributes['facilityId'] ) ? absint( $attributes['facilityId'] ) : 0;

		if ( ! $facility_id ) {
			return '<p class="passpress-checkout-notice">' . esc_html__( 'Choose a facility for this Booking Calendar block in the editor sidebar.', 'passpress' ) . '</p>';
		}

		return do_shortcode( '[passpress_booking_calendar facility_id="' . $facility_id . '"]' );
	}

	public static function render_class_schedule( $attributes ) {
		return do_shortcode( '[passpress_class_schedule]' );
	}
}
