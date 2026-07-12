<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Facility {

	public static function get_all() {
		return get_posts(
			array(
				'post_type'      => 'pp_facility',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
}
