<?php

namespace TotalThemeCore\Vcex;

use WPBMap;
use TotalTheme\Pagination\Load_More;

defined( 'ABSPATH' ) || exit;

/**
 * Render shortcodes with AJAX.
 */
final class Ajax {

	/**
	 * AJAX Action.
	 */
	const ACTION = 'vcex_ajax_action';

	/**
	 * Security Nonce Action.
	 */
	const NONCE = 'vcex-ajax-functions';

	/**
	 * Instance.
	 */
	private static $instance = null;

	/**
	 * Create or retrieve the instance of Scripts.
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new self();
		}
		return static::$instance;
	}

	/**
	 * Class Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, '_callback' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, '_callback' ] );
	}

	/**
	 * Checks if we are currently making an ajax request.
	 */
	public function is_doing_ajax(): bool {
		return ! empty( $_REQUEST['action'] ) && self::ACTION === $_REQUEST['action'];
	}

	/**
	 * Register scripts.
	 */
	public function register_scripts(): void {
		$dependencies = [
		//	'imagesloaded' // @deprecated in 5.10 - this makes the load more seem slower and it's only needed for isotope.
		];

		/**
		 * Filters the ajax script dependencies.
		 */
		$dependencies = (array) apply_filters( 'vcex_ajax_script_dependencies', $dependencies );

		wp_register_script(
			'vcex-ajax',
			vcex_get_js_file( 'frontend/ajax' ),
			$dependencies,
			TTC_VERSION,
			[
				'strategy' => 'defer',
			]
		);

		$ajaxl10n = [
			'ajax_url'        => esc_url( set_url_scheme( admin_url( 'admin-ajax.php' ) ) ),
			'nonce'           => wp_create_nonce( self::NONCE ),
			// @note in 2.0 we switched to using JS memory instead to prevent cross page issues and dynamic queries.
			'session_storage' => (int) apply_filters( 'vcex_ajax_use_session_storage', ! vcex_vc_is_inline() ),
		];

		if ( in_array( 'imagesloaded', $dependencies, true ) ) {
			$ajaxl10n['wait_for_images'] = '1';
		}

		wp_localize_script( 'vcex-ajax', 'vcex_ajax_params', $ajaxl10n );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts( $shortcode_class = '', $shortcode_atts = [] ): void {
		do_action( 'vcex_ajax_enqueue_scripts', [
			'shortcode_class' => $shortcode_class,
			'shortcode_atts'  => $shortcode_atts,
		] );

		/**
		 * Filters whether the wp media scripts should load when using ajax.
		 *
		 * @param bool $maybe_enqueue_media_elements
		 */
		$maybe_enqueue_media_elements = (bool) apply_filters( 'vcex_loadmore_enqueue_mediaelement', false );

		if ( $maybe_enqueue_media_elements ) {
			wp_enqueue_style( 'wp-mediaelement' );
			wp_enqueue_script( 'wp-mediaelement' );
		}

		wp_enqueue_script( 'vcex-ajax' );
	}

	/**
	 * The ajax action.
	 */
	public function _callback(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security nonce check failed' ] );
		}

		if ( empty( $_POST['shortcodeAtts'] ) || empty( $_POST['shortcodeClass'] ) ) {
			wp_send_json_error( [ 'message' => 'ShortcodeAtts and/or shortcodeClass not defined' ] );
		}

		$data            = [];
		$shortcode_class = sanitize_text_field( wp_unslash( $_POST['shortcodeClass'] ) );
		$shortcode_atts  = wp_strip_all_tags( wp_unslash( $_POST['shortcodeAtts'] ) );

		$allowed_classes = [
			'Wpex_Post_Cards_Shortcode',
		];

		if ( ! in_array( $shortcode_class, $allowed_classes, true ) ) {
			wp_send_json_error( [ 'message' => 'Shortcode class not allowed' ] );
		}
		
		if ( ! $shortcode_atts ) {
			wp_send_json_error( [ 'message' => 'empty atts' ] );
		}

		$shortcode_atts = json_decode( $shortcode_atts, true );

		if ( empty( $shortcode_atts['nonce'] ) ) {
			wp_send_json_error( [ 'message' => 'Atts nonce not found' ] );
		}

		$atts_nonce = sanitize_text_field( wp_unslash( $shortcode_atts['nonce'] ) );
		unset( $shortcode_atts['nonce'] );
		ksort( $shortcode_atts );

		$expected_nonce = 'wpex_post_cards_' . md5( wp_json_encode( $shortcode_atts, false ) );

		if ( ! wp_verify_nonce( $atts_nonce, $expected_nonce ) ) {
			wp_send_json_error( [ 'message' => 'Atts nonce check failed' ] );
		}

		if ( ! empty( $shortcode_atts['query_vars'] ) ) {
			$query_vars_safe = self::decrypt( sanitize_text_field( wp_unslash( $shortcode_atts['query_vars'] ) ) );
			if ( $query_vars_safe ) {
				if ( is_string( $query_vars_safe ) ) {
					// we need to convert it into an array when sending it to the Post Cards
					// since the Post Cards will then conert it back to JSON
					$query_vars_safe = json_decode( $query_vars_safe, true );
				}
				$shortcode_atts['query_vars'] = $query_vars_safe;
			} else {
				wp_send_json_error( [ 'message' => 'Could not decrypt query_vars' ] );
			}
		}

		if ( isset( $_POST['paged'] ) ) {
			$shortcode_atts['paged'] = intval( wp_unslash( $_POST['paged'] ) );
		}

		if ( isset( $_POST['actionType'] ) ) {
			$shortcode_atts['ajax_action'] = sanitize_text_field( wp_unslash( $_POST['actionType'] ) );
		}

		if ( isset( $_POST['filterData'] ) ) {
			$shortcode_atts['ajax_filter'] = sanitize_text_field( wp_unslash( $_POST['filterData'] ) );
		}

		if ( 'Wpex_Post_Cards_Shortcode' === $shortcode_class ) {
			$data = [];
			$return = '';

			if ( is_callable( [ 'WPBMap', 'addAllMappedShortcodes' ] ) ) {
				WPBMap::addAllMappedShortcodes(); // Fix for WPBakery not working in ajax
			}

			if ( ! empty( $_POST['data'] ) ) {
				$extra_data = json_decode( sanitize_text_field( wp_unslash( $_POST['data'] ) ), true );
				if ( isset( $extra_data['return'] ) ) {
					$return = sanitize_text_field( $extra_data['return'] );
				}
			}

			if ( isset( $extra_data['ignore_tax_query'] ) ) {
				$shortcode_atts['ignore_tax_query'] = sanitize_text_field( $extra_data['ignore_tax_query'] );
			}

			if ( ! $return || 'filter_counts' !== $return ) {
				$post_cards = new Post_Cards( $shortcode_atts );
				$data['html'] = $post_cards->get_output();
			}

			// Get counters
			if ( ! empty( $extra_data['filter'] ) && isset( $extra_data['update_counts'] ) && '1' === $extra_data['update_counts'] ) {
				if ( isset( $post_cards ) ) {
					$shortcode_atts = $post_cards->get_atts();
				} else {
					$shortcode_atts = vcex_shortcode_atts( 'wpex_post_cards', $shortcode_atts, 'Wpex_Post_Cards_Shortcode' );
				}
				$data['counts'] = wp_json_encode( $this->get_term_counts( $extra_data['filter'], $shortcode_atts ) );
			}

		} else {
			$data = [
				'html' => $shortcode_class::output( $shortcode_atts ),
			];
		}

		wp_send_json_success( wp_send_json_success( $data ) );
	}

	/**
	 * Returns the ajax loader element.
	 */
	public function get_ajax_loader(): string {
		$html = '<div class="vcex-ajax-loader wpex-hidden wpex-absolute wpex-inset-0 wpex-items-center wpex-justify-center">';
			$html .= '<span class="vcex-ajax-loader__overlay wpex-absolute wpex-inset-0 wpex-surface-1 wpex-opacity-60 wpex-z-10"></span>';
			if ( $svg = $this->get_ajax_loader_svg() ) {
				$html .= '<div class="vcex-ajax-loader__icon wpex-relative wpex-z-20">' . $svg . '</div>';
			}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Returns the ajax loader svg.
	 */
	public function get_ajax_loader_svg( int $size = 30 ): string {
		$svg = '';
		if ( is_callable( '\TotalTheme\Pagination\Load_More::get_loader_svg_html' ) ) {
			$icon_size = get_theme_mod( 'ajax_loader_svg_size' ) ?: $size;
			$svg_icon  = Load_More::get_loader_svg_html( '', (int) $icon_size );
			$svg       = apply_filters( 'vcex_ajax_loader_svg_icon', $svg_icon );
		}
		return $svg;
	}

	/**
	 * Returns the load more button.
	 */
	public function get_loadmore_button( $shortcode_tag, $shortcode_atts = [], $query = '', $infinite_scroll = false ) {
		if ( is_array( $shortcode_tag ) ) {
			extract( $shortcode_tag );
		}

		$page         = get_query_var( 'paged' ) ?: 1;
		$max_pages    = $query->max_num_pages;
		$vc_is_inline = vcex_vc_is_inline();

		// No need for load more if we already reached the last page
		if ( $page >= $max_pages || ( $infinite_scroll && $vc_is_inline ) ) {
			return;
		}

		// Remove useless attributes
		unset( $shortcode_atts['wrap_css'] );
		unset( $shortcode_atts['show_categories_tax'] );

		if ( ! in_array( $shortcode_tag, [ 'vcex_post_type_archive', 'vcex_post_type_grid', 'vcex_recent_news' ], true ) ) {
			unset( $shortcode_atts['post_type'] );
			unset( $shortcode_atts['taxonomy'] );
		}

		// Define load more text
		if ( is_callable( [ 'TotalTheme\Pagination\Load_More', 'get_loading_text' ] ) ) {
			$loading_text = Load_More::get_loading_text();
			$failed_text  = Load_More::get_failed_text();
			if ( empty( $loadmore_text ) ) {
				$loadmore_text = Load_More::get_more_text();
			}
		} else {
			$loading_text = esc_html__( 'Loading&hellip;', 'total-theme-core' );
			$failed_text  = esc_html__( 'Failed to load posts.', 'total-theme-core' );
			if ( empty( $loadmore_text ) ) {
				$loadmore_text = esc_html__( 'Load More', 'total-theme-core' );
			}
		}

		// Create array of load more settings to be added to the button data
		$settings = [
			'class'           => 'vcex-loadmore-button theme-button',
			'text'            => $loadmore_text,
			'loading_text'    => $loading_text,
			'failed_text'     => $failed_text,
			'infinite_scroll' => $infinite_scroll,
		];

		if ( is_callable( 'TotalTheme\Pagination\Load_More::get_loader_svg_html' ) ) {
			$settings['svg'] = Load_More::get_loader_svg_html();
		} else {
			$settings['gif'] = includes_url( 'images/spinner-2x.gif' );
		}

		$settings = apply_filters( 'vcex_get_loadmore_button_settings', $settings, $shortcode_tag, $shortcode_atts ); // @deprecated
		$settings = (array) apply_filters( 'vcex_loadmore_button_settings', $settings, $shortcode_tag, $shortcode_atts );

		// Check if infinite scroll is enabled
		$has_infinite_scroll = wp_validate_boolean( $settings['infinite_scroll'] ?? false );

		if ( $vc_is_inline && $has_infinite_scroll  ) {
			return;
		}

		// Load more classes
		$loadmore_classes = [
			'vcex-loadmore',
			'wpex-clear',
			'wpex-text-center',
		];

		if ( 'wpex_post_cards' === $shortcode_tag || ! vcex_has_classic_styles() ) {
			$loadmore_classes[] = 'wpex-mt-30';
		} else {
			$loadmore_classes[] = 'wpex-mt-10';
		}

		if ( $has_infinite_scroll ) {
			$loadmore_classes[] = 'vcex-loadmore--infinite-scroll wpex-invisible';
		}

		$loadmore_classes = (array) apply_filters( 'vcex_loadmore_class', $loadmore_classes, $shortcode_tag, $shortcode_atts );

		// Return load more button
		$button = '<div class="' . esc_attr( implode( ' ', $loadmore_classes ) ) . '">';

			$button_class = $settings['class'] ?? '';

			if ( $has_infinite_scroll ) {
				if ( is_string( $button_class ) ) {
					$button_class .= ' wpex-h-1px';
				} else {
					$button_class[] = 'wpex-h-1px';
				}
			}

			$btn_attr = [
				'href'                  => '#',
				'class'                 => esc_attr( $button_class ),
				'data-infinite-scroll'  => (int) $has_infinite_scroll,
				'data-text'             => esc_attr( $settings['text'] ),
				'data-loading-text'     => esc_attr( $settings['loading_text'] ),
				'data-failed-text'      => esc_attr( $settings['failed_text'] ),
			];

			// Add extra data for elements (not needed for Post Cards)
			if ( 'wpex_post_cards' !== $shortcode_tag ) {

				// Eencrypt query_vars as an added precaution
				if ( $this->is_auto_query( $shortcode_atts ) ) {
					$shortcode_atts['query_vars'] = self::encrypt( wp_json_encode( $query->query_vars ) );
				}

				// Standardize shortcode atts order
				ksort( $shortcode_atts );

				// Create a secure nonce based on the atts array
				$shortcode_atts['nonce'] = wp_create_nonce( 'vcex_load_more_button_' . md5( wp_json_encode( $shortcode_atts ) ) );

				// Define button data
				$btn_attr['data-page']             = esc_attr( $page );
				$btn_attr['data-max-pages']        = esc_attr( $max_pages );
				$btn_attr['data-shortcode-tag']    = esc_attr( $shortcode_tag );
				$btn_attr['data-shortcode-params'] = esc_attr( wp_json_encode( $shortcode_atts ) );
			}

			$button .= '<a';
				foreach ( $btn_attr as $name => $value_escaped ) {
		            $button .= ' ' . $name . '="' .  $value_escaped . '"';
		        }
			$button .= '>';

				$button_text_allowed_tags = [
					'img' => [
						'src' => [],
						'alt' => [],
					],
					'span' => [
						'class' => [],
					],
				];

				$button_text_allowed_tags = apply_filters( 'vcex_loadmore_button_text_allowed_tags', $button_text_allowed_tags );

				$button .= '<span class="vcex-txt">' . wp_kses( $settings['text'], $button_text_allowed_tags ) . '</span>';

			$button .= '</a>';

			// Spinner
			if ( $has_infinite_scroll ) {
				$spinner_class = 'vcex-loadmore-spinner vcex-spinner wpex-invisible wpex-opacity-0';
			} else {
				$spinner_class = 'vcex-loadmore-spinner vcex-spinner wpex-hidden';
			}
			if ( ! empty( $settings['gif'] ) ) {
				$button .= '<img src="' . esc_url( $settings['gif'] ) . '" class="' . esc_attr( $spinner_class ) . ' wpex-opacity-40" alt="' . esc_attr( $settings['loading_text'] ) . '" height="20" width="20">';
			} elseif ( ! empty( $settings['svg'] ) ) {
				$button .= '<div class="' . esc_attr( $spinner_class ) . '">' . $settings['svg'] . '</div>';
			}

		$button .= '</div>';

		return $button;
	}

	/**
	 * Check if we are showing an auto query.
	 */
	protected function is_auto_query( $atts = [] ): bool {
		if ( isset( $atts['query_type'] ) ) {
			return 'auto' === $atts['query_type'];
		} else {
			return vcex_validate_att_boolean( 'auto_query', $atts );
		}
	}

	/**
	 * Returns updated taxonomy counters based on ajax request.
	 */
	protected function get_term_counts( $filter_items = '', $shortcode_atts = '' ) {
		if ( ! is_array( $filter_items ) || empty( $filter_items ) ) {
			return;
		}

		$counts = [];
		$sanitized_filter_items = [];

		foreach ( $filter_items as $taxonomy => $terms ) {
			$taxonomy_safe = sanitize_text_field( $taxonomy );
			if ( $taxonomy_safe && taxonomy_exists( $taxonomy_safe ) ) {
				foreach ( $terms as $term_id ) {
					$term_id_safe = absint( $term_id );
					if ( $term_id_safe ) {
						$counts[ "{$taxonomy_safe}|{$term_id_safe}" ] = 0;
						$sanitized_filter_items[ $taxonomy_safe ][] = $term_id_safe;
					}
				}
			}
		}

		if ( empty( $counts ) ) {
			return;
		}

		$posts_per_page = ( isset( $shortcode_atts['query_type'] ) && 'auto' === $shortcode_atts['query_type'] ) ? 9999 : -1;

		$shortcode_atts['unfiltered_query_args'] = [
			'posts_per_page' => $posts_per_page, // @note using -1 in auto queries causes an error in the WP query.
		];

		$get_posts = vcex_build_wp_query( $shortcode_atts, 'wpex_post_cards', 'ids' );

		if ( empty( $get_posts->posts ) ) {
			return 'empty';
		}

		if ( empty( $shortcode_atts['ajax_filter'] ) ) {
			$counts['all'] = count( $get_posts->posts );
		}

		$include_children = wp_validate_boolean( $shortcode_atts['ajax_filter']['include_children'] ?? true );
		foreach ( $get_posts->posts as $post_id ) {
			foreach ( $sanitized_filter_items as $taxonomy => $term_ids ) {
				foreach ( $term_ids as $term_id ) {
					$term_id_array = [ $term_id ];
					if ( $include_children ) {
						$children = get_term_children( $term_id, $taxonomy );
						if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
							$term_id_array = array_merge( $term_id_array, $children );
						}
					}
					if ( has_term( $term_id_array, $taxonomy, $post_id ) ) {
						$counts[ "{$taxonomy}|{$term_id}" ]++;
					}
				}
			}
		}

		return $counts;
	}

	/**
	 * Encrypt data.
	 */
	public static function encrypt( $data ) {
		if ( is_array( $data ) ) {
			$data = wp_json_encode( $data, false );
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return base64_encode( $data );
		}

		// Generate a key based on a salt or secret string
		$key = wp_hash( 'vcex_ajax' );

		// Generate a random initialization vector (IV) to ensure unique encryption each time
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );

		// Encrypt the data using AES-256-CBC and the generated key + IV
		$encrypted_data = openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv );

		// Encryption failed, return the original data base64-encoded as a fallback
		if ( $encrypted_data === false ) {
			return base64_encode( $data );
		}

		// Base64 encode the encrypted data and the IV, and concatenate them with a separator (::)
		$encrypted_output = base64_encode( $encrypted_data . '::' . $iv );

		// Return the encrypted, base64-encoded string
		return $encrypted_output;
	}

	/**
	 * Decrypt data.
	 */
	public static function decrypt( $data ) {
		$data = base64_decode( $data );

		// Check if the data was properly base64 decoded
		if ( false === $data ) {
			return false;
		}

		// Split the encrypted data and the IV
		$parts = explode( '::', $data );

		// Ensure the data contains both encrypted data and IV
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$encrypted_data = $parts[0];
		$iv = $parts[1];

		// Generate the same key as used in the encryption function
		$key = wp_hash( 'vcex_ajax' );

		// Decrypt the data using AES-256-CBC and the generated key + IV
		$decrypted_data = openssl_decrypt( $encrypted_data, 'aes-256-cbc', $key, 0, $iv );

		// Return decrypted data
		return $decrypted_data;
	}

	/**
	 * Parses a multi attribute returned by the AJAX filter.
	 *
	 * @deprecated 1.6.1
	 */
	public function parse_ajax_filter_multi_attribute_value( $value ): array {
		$result = [];
		$params_pairs = explode( '|', $value );
		if ( ! empty( $params_pairs ) ) {
			foreach ( $params_pairs as $pair ) {
				$param = preg_split( '/\:/', $pair );
				if ( ! empty( $param[0] ) && isset( $param[1] ) ) {
					$result[ $param[0] ] = $param[1];
				}
			}
		}
		return $result;
	}

	/**
	 * The ajax action.
	 * 
	 * @deprecated 2.4
	 */
	public function action(): void {
		_deprecated_function( __METHOD__, 'Total Theme Core 2.4' );
	}

}
