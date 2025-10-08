<?php

namespace TotalTheme\Pagination;

use WP_Query;

\defined( 'ABSPATH' ) || exit;

/**
 * Load more pagination.
 */
class Load_More {

	/**
	 * Query var used to store loadmore POST data.
	 */
	public const QUERY_VAR = 'wpex_loadmore_data';

	/**
	 * AJAX data key name.
	 */
	private const POST_KEY = 'loadMore';

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Checks if we are currently fetching posts.
	 */
	public static function is_doing_ajax(): bool {
		return isset( $_POST[ self::POST_KEY ] ) && isset( $_REQUEST['action'] ) && 'wpex_ajax_load_more' === $_REQUEST['action'];
	}

	/**
	 * Returns a query var value.
	 */
	public static function get_query_var( string $key ) {
		$data = \get_query_var( self::QUERY_VAR );
		if ( isset( $data['query_vars'] ) && isset( $data['query_vars'][ $key ] ) ) {
			return \sanitize_text_field( \wp_unslash( $data['query_vars'][ $key ] ) );
		}
		return null;
	}

	/**
	 * Returns load more data.
	 */
	public static function get_data( $key = '' ) {
		$data = \get_query_var( self::QUERY_VAR );
		if ( $key ) {
			return $data[ $key ] ?? '';
		}
		return $data;
	}

	/**
	 * Enqueues load more scripts.
	 */
	public static function enqueue_scripts(): void {
		// Make sure possibly needed scripts are loaded @todo can this be optimized somehow?
		\wpex_enqueue_slider_pro_scripts();
		\wpex_enqueue_lightbox_scripts();

		// WP Media
		if ( \apply_filters( 'wpex_loadmore_enqueue_mediaelement', false ) ) {
			\wp_enqueue_style( 'wp-mediaelement' );
			\wp_enqueue_script( 'wp-mediaelement' );
		}

		\wp_enqueue_script(
			'wpex-loadmore',
			\totaltheme_get_js_file( 'frontend/loadmore' ),
			[ 'jquery', 'imagesloaded' ],
			\WPEX_THEME_VERSION,
			[
				'strategy' => 'defer',
			]
		);

		\wp_localize_script(
			'wpex-loadmore',
			'wpex_loadmore_params',
			[
				'ajax_url' => \set_url_scheme( \admin_url( 'admin-ajax.php' ) ),
				'nonce'    => \wp_create_nonce( 'wpex_load_more_button' ),
				'i18n'     => [
					'text'        => \esc_js( self::get_more_text() ),
					'loadingText' => \esc_js( self::get_loading_text() ),
					'failedText'  => \esc_js( self::get_failed_text() ),
				],
			]
		);
	}

	/**
	 * Returns an array of loader options.
	 */
	public static function get_loader_svg_options(): array {
		return [
			''                 => \esc_html__( 'Default', 'total' ),
			'ball-triangle'    => \esc_html__( 'Ball Triangle', 'total' ),
			'bars'             => \esc_html__( 'Bars', 'total' ),
			'circles'          => \esc_html__( 'Circles', 'total' ),
			'grid'             => \esc_html__( 'Grid', 'total' ),
			'oval'             => \esc_html__( 'Oval', 'total' ),
			'puff'             => \esc_html__( 'Puff', 'total' ),
			'rings'            => \esc_html__( 'Rings', 'total' ),
			'spinning-circles' => \esc_html__( 'Spinning Circles', 'total' ),
			'tail-spin'        => \esc_html__( 'Tail Spin', 'total' ),
			'three-dots'       => \esc_html__( 'Three Dots', 'total' ),
			'wordpress'        => 'WordPress',
		];
	}

	/**
	 * Returns loader gif.
	 */
	public static function get_loader_gif(): string {
		return (string) \apply_filters( 'wpex_loadmore_gif', null );
	}

	/**
	 * Returns loader svg.
	 */
	public static function get_loader_svg(): string {
		$svg = ( $svg = \get_theme_mod( 'loadmore_svg' ) ) ? \sanitize_text_field( $svg ) : '';
		if ( $svg && ! \array_key_exists( $svg, self::get_loader_svg_options() ) ) {
			$svg = 'default';
		}
		return $svg;
	}

	/**
	 * Returns loader svg html.
	 */
	public static function get_loader_svg_html( string $svg = '', int $size = 0 ): string {
		if ( ! $svg ) {
			$svg = self::get_loader_svg();
		}
		if ( ! $size ) {
			$size = ( $custom_size = \get_theme_mod( 'loadmore_svg_size' ) ) ? \absint( $custom_size ) : '';
			if ( ! $size ) {
				$size = totaltheme_has_classic_styles() ? 20 : 24;
			}
		}
		return (string) \totaltheme_get_loading_icon( $svg, $size );
	}

	/**
	 * Returns the more text.
	 */
	public static function get_more_text(): string {
		$text = ( $text = \wpex_get_translated_theme_mod( 'loadmore_text' ) ) ? \sanitize_text_field( $text ) : \esc_html__( 'Load More', 'total' );
		$text = \apply_filters( 'wpex_loadmore_text', $text ); // @deprecated
		return (string) \apply_filters( 'totaltheme/pagination/load_more/button_text', $text );
	}

	/**
	 * Returns the loading text.
	 */
	public static function get_loading_text(): string {
		$text = ( $text = \wpex_get_translated_theme_mod( 'loadmore_loading_text' ) ) ? \sanitize_text_field( $text ) : \esc_html__( 'Loading...', 'total' );
		$text = \apply_filters( 'wpex_loadmore_loading_text', $text ); // @deprecated
		return (string) \apply_filters( 'totaltheme/pagination/load_more/loading_text', $text );
	}

	/**
	 * Returns the failed text.
	 */
	public static function get_failed_text(): string {
		$text = ( $text = \wpex_get_translated_theme_mod( 'loadmore_failed_text' ) ) ? \sanitize_text_field( $text ) : \esc_html__( 'Failed to load posts.', 'total' );
		$text = \apply_filters( 'wpex_loadmore_failed_text', $text ); // @deprecated
		return (string) \apply_filters( 'totaltheme/pagination/load_more/failed_text', $text );
	}

	/**
	 * Render button.
	 */
	public static function render_button( $args = [] ): void {
		global $wp_query;

		if ( empty( $wp_query->query_vars ) ) {
			return;
		}

		$max_pages = \absint( $wp_query->max_num_pages ?? 1 );
		$page      = \get_query_var( 'paged' ) ?: 1;

		$defaults = [ 
			'grid'            => '#blog-entries',
			'loop_type'       => 'blog',
			'infinite_scroll' => false,
			'page'            => \intval( $page ),
			'maxPages'        => $max_pages,
			'count'           => \wpex_get_loop_counter(),
			'query_vars'      => self::encrypt( \wp_json_encode( $wp_query->query_vars ) ),
		];

		// Add current term_id to args for meta field checks (custom term settings).
		if ( \is_category() || \is_tag() || \is_tax() ) {
			$defaults['term_id'] = \get_queried_object_id();
		}

		$args                = \wp_parse_args( $args, $defaults );
		$args['nonce']       = \wp_create_nonce( 'wpex_load_more_' . \md5( \wp_json_encode( $args ) ) );
		$has_infinite_scroll = \wp_validate_boolean( $args['infinite_scroll'] );

		if ( ! \wp_doing_ajax() && ( ! $max_pages || 1 === $max_pages || ( $max_pages == $page ) ) ) {
			return;
		}

		self::enqueue_scripts();

		$output = '<div class="wpex-load-more-wrap wpex-mt-30 wpex-text-center">';

			$button_class = 'wpex-load-more';

			if ( $has_infinite_scroll ) {
				$button_class .= ' wpex-load-more--infinite-scroll wpex-h-1px wpex-invisible';
			}

			if ( \get_theme_mod( 'loadmore_btn_expanded', true ) ) {
				$button_class .= ' wpex-load-more--expanded wpex-w-100';
			}

			$button_class .= ' theme-button';

			if ( \totaltheme_has_classic_styles() ) {
				$button_class .= ' wpex-py-0';
			}

			$output .= '<a href="#" class="' . \esc_attr( $button_class ) . '" data-loadmore="' . \esc_attr( \wp_json_encode( $args ) ) . '">';
				$output .= '<span class="theme-button-inner">' . \esc_html( self::get_more_text() ) . '</span>';
			$output .= '</a>';

			$gif = self::get_loader_gif();
			$spinner_class = 'wpex-load-more-spinner wpex-spinner wpex-hidden';

			if ( $gif ) {
				$output .= '<img src="' . \esc_url( $gif ) . '" class="' . \esc_attr( $spinner_class ) . '" alt="' . \esc_attr( self::get_loading_text() ) . '" height="20" width="20">';
			} elseif ( $svg = self::get_loader_svg_html() ) {
				$output .= '<div class="' . \esc_attr( $spinner_class ) . '">' . $svg . '</div>';
			}

			$output .= '</div>';

		echo $output; // @codingStandardsIgnoreLine
	}

	/**
	 * Get posts via AJAX.
	 */
	public static function get_posts() {
		\check_ajax_referer( 'wpex_load_more_button', 'nonce' );

		if ( empty( $_POST[ self::POST_KEY ] ) ) {
			\wp_send_json_error( [ 'message' => 'wpex_load_more: missing data' ] );
		}

		$loadmore = \json_decode( \sanitize_text_field( \wp_unslash( $_POST[ self::POST_KEY ] ) ), true );

		if ( ! $loadmore ) {
			\wp_send_json_error( [ 'message' => 'wpex_load_more: missing data' ] );
		}

		if ( empty( $loadmore['query_vars'] ) ) {
			\wp_send_json_error( [ 'message' => 'wpex_load_more: no query vars' ] );
		}

		$query_vars_safe = self::decrypt( \sanitize_text_field( \wp_unslash( $loadmore['query_vars'] ) ) );

		if ( ! $query_vars_safe ) {
			\wp_send_json_error( [ 'message' => 'wpex_load_more: could not decrypt query_vars' ] );
		}

		$query_args = \json_decode( $query_vars_safe, true );

		if ( ! $query_args ) {
			\wp_send_json_error( [ 'message' => 'wpex_load_more: query args array empty' ] );
		}

		// Update query vars with the decrypted version
		$loadmore['query_vars'] = $query_args;

		// Make sure we are only querying published posts since WP doesn't know we are on the front-end
		$query_args['post_status'] = 'publish';

		// Updated query paged argument
		$query_args['paged'] = isset( $_POST['page'] ) ? \absint( \wp_unslash( $_POST['page'] ) ) : 2;

		// Update loop counter
		$loop_count = isset( $_POST['loopCount'] ) ? \absint( \wp_unslash( $_POST['loopCount'] ) ) : 0;
		\wpex_set_loop_counter( $loop_count );

		// Update query var with loadmore data
		\set_query_var( self::QUERY_VAR, $loadmore );

		// Get loop type
		$loop_type = ! empty( $loadmore['loop_type'] ) ? \sanitize_text_field( \wp_unslash( $loadmore['loop_type'] ) ) : 'blog';

		// Query posts
		$posts = new WP_Query( $query_args );

		// Get new posts html
		\ob_start();
			if ( $posts->have_posts() ) :
				while ( $posts->have_posts() ): $posts->the_post();
					\get_template_part( 'partials/loop/loop', $loop_type );
				endwhile;
			endif;
			$loop_count = \wpex_get_loop_counter();
			\wp_reset_postdata();
			\set_query_var( self::QUERY_VAR, null );
		$new_posts = \ob_get_clean();

		// Send response
		\wp_send_json_success( [
			'html'      => $new_posts,
			'loopCount' => \absint( $loop_count ),
		] );

		\wp_die();
	}

	/**
	 * Encrypt data.
	 */
	private static function encrypt( $data ) {
		if ( \is_array( $data ) ) {
			$data = \wp_json_encode( $data, false );
		}

		if ( ! \function_exists( '\openssl_encrypt' ) || ! \function_exists( '\openssl_random_pseudo_bytes' ) ) {
			return \base64_encode( $data );
		}

		// Generate a key based on a salt or secret string
		$key = \wp_hash( 'wpex_load_more' );

		// Generate a random initialization vector (IV) to ensure unique encryption each time
		$iv = \openssl_random_pseudo_bytes( \openssl_cipher_iv_length( 'aes-256-cbc' ) );

		// Encrypt the data using AES-256-CBC and the generated key + IV
		$encrypted_data = \openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv );

		// Encryption failed, return the original data base64-encoded as a fallback
		if ( $encrypted_data === false ) {
			return \base64_encode( $data );
		}

		// Base64 encode the encrypted data and the IV, and concatenate them with a separator (::)
		$encrypted_output = \base64_encode( $encrypted_data . '::' . $iv );

		// Return the encrypted, base64-encoded string
		return $encrypted_output;
	}

	/**
	 * Decrypt data.
	 */
	private static function decrypt( $data ) {
		$data = \base64_decode( $data );

		// Check if the data was properly base64 decoded
		if ( false === $data ) {
			return false;
		}

		// Split the encrypted data and the IV
		$parts = \explode( '::', $data );

		// Ensure the data contains both encrypted data and IV
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$encrypted_data = $parts[0];
		$iv = $parts[1];

		// Generate the same key as used in the encryption function
		$key = \wp_hash( 'wpex_load_more' );

		// Decrypt the data using AES-256-CBC and the generated key + IV
		$decrypted_data = \openssl_decrypt( $encrypted_data, 'aes-256-cbc', $key, 0, $iv );

		// Return decrypted data
		return $decrypted_data;
	}

}
