<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check if we are currently loading new posts.
 */
function vcex_doing_loadmore(): bool {
	return ! empty( $_REQUEST['action'] ) && 'vcex_loadmore_ajax_render' === $_REQUEST['action'];
}

/**
 * Load More Scripts.
 */
function vcex_loadmore_scripts() {
	$dependencies = [
		'jquery',
		'imagesloaded',
	];

	if ( apply_filters( 'vcex_loadmore_enqueue_mediaelement', false ) ) {
		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );
	}

	// Enqueue load more script.
	wp_enqueue_script(
		'vcex-loadmore',
		vcex_get_js_file( 'frontend/loadmore' ),
		$dependencies,
		TTC_VERSION,
		true
	);

	// Localize load more script.
	wp_localize_script(
		'vcex-loadmore',
		'vcex_loadmore_params',
		[
			'ajax_url' => esc_url( set_url_scheme( admin_url( 'admin-ajax.php' ) ) ),
			'nonce'    => wp_create_nonce( 'vcex-ajax-pagination-nonce' )
		]
	);
}

/**
 * Load More Button.
 */
function vcex_get_loadmore_button( $shortcode_tag, $raw_atts, $query, $infinite_scroll = false ) {
	return totalthemecore_call_non_static( 'Vcex\Ajax', 'get_loadmore_button', $shortcode_tag, $raw_atts, $query, $infinite_scroll );
}

/**
 *  Load More AJAX.
 */
function vcex_loadmore_ajax_render() {
	check_ajax_referer( 'vcex-ajax-pagination-nonce', 'nonce' );

	if ( empty( $_POST['shortcodeParams'] ) || empty( $_POST[ 'shortcodeTag' ] ) ) {
		wp_send_json_error( 'vcex loadmore: shortcodeParams and/or shortcodeTag not defined');
	}

	$tag = sanitize_text_field( wp_unslash( $_POST['shortcodeTag'] ) );

	$allowed_tags = [
		'vcex_blog_grid',
		'vcex_image_grid',
		'vcex_portfolio_grid',
		'vcex_post_type_archive',
		'vcex_post_type_grid',
		'vcex_recent_news',
		'vcex_staff_grid',
		'vcex_testimonials_grid',
	];

	if ( ! in_array( $tag, $allowed_tags, true ) ) {
		wp_send_json_error( 'vcex: Shortcode not allowed' );
	}

	if ( class_exists( 'WPBMap' ) ) {
		WPBMap::addAllMappedShortcodes(); // fix for WPBakery not working in ajax
	}

	$params = sanitize_text_field( wp_unslash( $_POST['shortcodeParams'] ) );
		
	if ( ! $params ) {
		wp_send_json_error( [ 'message' => 'vcex_ajax: empty atts' ] );
	}

	$params = json_decode( $params, true );
	
	if ( empty( $params['nonce'] ) ) {
		wp_send_json_error( [ 'message' => 'vcex_ajax: atts nonce not found' ] );
	}

	$atts_nonce = $params['nonce'];
	unset( $params['nonce'] );
	ksort( $params );

	$expected_nonce = 'vcex_load_more_button_' . md5( wp_json_encode( $params ) );

	if ( ! wp_verify_nonce( $atts_nonce, $expected_nonce ) ) {
		wp_send_json_error( [ 'message' => 'vcex_ajax: atts nonce did not verify' ] );
	}

	if ( ! empty( $params['query_vars'] ) ) {
		$query_vars_safe = totalthemecore_call_static( 'Vcex\Ajax', 'decrypt', sanitize_text_field( wp_unslash( $params['query_vars'] ) ) );
		if ( $query_vars_safe ) {
			$params['query_vars'] = $query_vars_safe;
		} else {
			wp_send_json_error( [ 'message' => 'vcex_ajax: could not decrypt query_vars' ] );
		}
	}

	if ( isset( $_POST['paged'] ) ) {
		$params['paged'] = floatval( wp_unslash( $_POST['paged'] ) );
	}

	wp_send_json_success( vcex_do_shortcode_function( $tag, $params ) );

	wp_die();
}
add_action( 'wp_ajax_vcex_loadmore_ajax_render', 'vcex_loadmore_ajax_render' );
add_action( 'wp_ajax_nopriv_vcex_loadmore_ajax_render', 'vcex_loadmore_ajax_render' );
