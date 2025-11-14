<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPEX_Font_Manager' ) ) {

	/**
	 * WPEX Font Manager.
	 */
	final class WPEX_Font_Manager {

		/**
		 * Post type used to store custom fonts.
		 */
		public const POST_TYPE = 'wpex_font';

		/**
		 * Transient name for registered fonts.
		 */
		private const TRANSIENT_NAME = 'wpex_font_manager_registered_fonts';

		/**
		 * Check if cache is enabled.
		 */
		private const ENABLE_CACHE = true;

		/**
		 * Check if the font post type is registered.
		 */
		protected $is_post_type_registered = false;

		/**
		 * Check if we have gotten registered fonts or not.
		 */
		protected $registered_fonts_check = false;

		/**
		 * Holds array of registered fonts.
		 */
		protected $registered_fonts = [];

		/**
		 * Check if the font styles are registered.
		 */
		private $is_styles_registered = false;

		/**
		 * Instance.
		 */
		private static $instance = null;

		/**
		 * Create or retrieve the instance of WPEX_Font_Manager.
		 */
		public static function instance() {
			if ( null === static::$instance ) {
				static::$instance = new self();
			}
			return static::$instance;
		}

		/**
		 * Private constructor.
		 */
		private function __construct() {
			$this->global_hooks();

			if ( is_admin() ) {
				$this->admin_hooks();
			}
		}

		/**
		 * Global hooks.
		 * 
		 * These functions run always, we don't add an !is_admin check incase a plugin
		 * needs to run do_action() to load scripts.
		 */
		public function global_hooks() {
		//	$this->delete_cache(); // for testing purposes only!
			add_action( 'init', [ $this, 'register_type' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'register_enqueue_styles' ], 5 ); // before stylesheets
			add_action( 'wp_enqueue_scripts', [ $this, 'register_enqueue_assignments' ], 999 ); // after stylesheets
			add_action( 'wpex_preload_links', [ $this, '_filter_preload_links' ] );
		}

		/**
		 * Get required user capability.
		 */
		private function get_user_cap(): string {
			return 'edit_theme_options';
		}

		/**
		 * Check user capabilities.
		 */
		private function check_user_cap(): bool {
			return (bool) current_user_can( $this->get_user_cap() );
		}

		/**
		 * Admin hooks.
		 */
		public function admin_hooks() {
			add_action( 'admin_action_depicter', function() {
				$this->register_enqueue_styles( true );
			}, 0 );

			if ( ! $this->check_user_cap() ) {
				return; // everything else is for the editor
			}

			add_action( 'edit_form_after_title', [ $this, '_on_edit_form_after_title' ] );
			add_action( 'admin_head', [ $this, '_on_admin_head' ] );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'set_admin_columns' ] );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'show_admin_columns' ], 10, 2 );
			add_action( 'save_post_' . self::POST_TYPE, [ $this, '_on_save_post' ] );

			add_action( 'admin_head-post.php', [ $this, 'add_back_button' ] );

			if ( class_exists( 'Vc_Manager' ) ) {
				add_filter( 'vc_is_valid_post_type_be', [ $this, 'disable_wpbakery' ], 10, 2 );
				add_filter( 'vc_show_button_fe', [ $this, 'remove_wpbakery_button_fe' ], 10, 3 );
			}

			if ( class_exists( 'WPEX_Meta_Factory' ) ) {
				add_action( 'admin_init', [ $this, 'register_metaboxes' ] );
			}

			add_filter( 'wp_handle_upload_prefilter', [ $this, '_filter_wp_handle_upload_prefilter' ] );
			add_filter( 'plupload_default_settings', [ $this, '_filter_plupload_default_settings' ] );
		}

		/**
		 * Hooks into the on_save action.
		 */
		public function _on_save_post( $post_id ) {
			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				|| wp_is_post_revision( $post_id )
				|| self::POST_TYPE !== get_post_type( $post_id )
			) {
				return;
			}

			// Clear transient
			$this->delete_cache();

			// Return early if doing a quick edit
			if ( ! empty( $_POST['_inline_edit'] ) ) {
				return;
			}

			// Delete font validation check
			if ( current_user_can( 'edit_post', $post_id ) ) {
				delete_post_meta( $post_id, '_font_validated' );
			}
		}

		/**
		 * Hooks into edit_form_after_title to validate the font.
		 */
		public function _on_edit_form_after_title( $post ) {
			if ( empty( $post->post_type ) || self::POST_TYPE !== $post->post_type ) {
				return;
			}

			$font = $this->generate_font_data( $post->ID )[1] ?? [];

			if ( empty( $font['type'] ) ) {
				return;
			}

			$font_type = $font['type'];

			// Remove Google Services is enabled
			if ( 'google' === $font_type
				&& function_exists( 'wpex_has_google_services_support' )
				&& ! wpex_has_google_services_support()
			) {
				echo '<div class="notice notice-error inline" style="margin-block-start:20px;">
					<p>' . sprintf(
						esc_html__( '%sWarning%s: The "Remove Google Fonts" option is enabled in the Theme Panel, so your Google Fonts will not be loaded.', 'total-theme-core' ),
						'<strong>',
						'</strong>'
					) . '</p>
				</div>';
			}

			if ( '1' === get_post_meta( $post->ID, '_font_validated', true ) ) {
				return;
			}

			$font_name = $font['name'] ?? '';

			if ( ! $font_name ) {
				return;
			}

			$notice = '';

			if ( 'adobe' === $font_type || 'google' === $font_type ) {
				if ( 'adobe' === $font_type && ! get_post_meta( $post->ID, 'adobe_project_id', true ) ) {
					$notice = sprintf(
						esc_html__( '%sWarning%s: You must enter your Adobe Project ID in the font settings.', 'total-theme-core' ),
						'<strong>',
						'</strong>'
					);
				}
				if ( ! $notice ) {
					$font_url = $this->get_font_url( $font );
					if ( $font_url ) {
						if ( 'google' === $font_type ) {
							if ( ! $this->validate_google_font( $font_url ) ) {
								$notice = sprintf(
									esc_html__( '%sWarning%s: The Google Font appears to be invalid, please double check your settings. Make sure the name is correct and only available font weights, subsets and italics are selected.', 'total-theme-core' ),
									'<strong>',
									'</strong>'
								);
							}
						}
						if ( 'adobe' === $font_type ) {
							$font_families = $this->get_adobe_font_families_from_url( $font_url );
							if ( is_wp_error( $font_families ) ) {
								$notice = $font_families->get_error_message();
							} elseif ( $font_families && ! in_array( $font_name, $font_families, true ) ) {
								$notice = sprintf(
									esc_html__( '%sWarning%s: "%s" doesn\'t appear to be a valid font name in this Adobe project. The following font families are available: %s', 'total-theme-core' ),
									'<strong>',
									'</strong>',
									$font_name,
									implode( ', ', array_map( 'esc_html', $font_families ) )
								);
							}
						}
					}
				}
			}

			if ( $notice ) {
				echo '<div class="notice notice-error inline" style="margin-block-start:20px;"><p>' . $notice . '</p></div>';
			} else {
				update_post_meta( $post->ID, '_font_validated', '1' );
			}
		}

		/**
		 * Remove the admin columns sort filter.
		 */
		public function _on_admin_head() {
			$screen = get_current_screen();

			if ( ! isset( $screen->post_type ) || self::POST_TYPE !== $screen->post_type ) {
				return;
			}

			// Remove admin columns sort filter
			add_filter( 'months_dropdown_results', '__return_empty_array' );

			// Good spot to register our styles
			$this->register_enqueue_styles( true );
		}

		/**
		 * Set admin columns.
		 */
		public function set_admin_columns( $columns ) {
			$columns['font']      = esc_html__( 'Font', 'total-theme-core' );
			$columns['type']      = esc_html__( 'Type', 'total-theme-core' );
			$columns['alias']     = esc_html__( 'Alias', 'total-theme-core' );
			$columns['assign_to'] = esc_html__( 'Assigned To', 'total-theme-core' );
			$columns['is_global'] = esc_html__( 'Global?', 'total-theme-core' );
			unset( $columns['date'] );
			return $columns;
		}

		/**
		 * Show admin columns.
		 */
		public function show_admin_columns( $column, $post_id ) {
			switch ( $column ) {
				case 'font':
					$type                = get_post_meta( $post_id, 'type', true );
					$font_name           = get_post_meta( $post_id, 'name', true );
					$font_name_sanitized = $font_name ? $this->sanitize_font_name( $font_name, $type ) : '-';
					$inline_style = 'font-size:24px;line-height:normal;';
					if ( $font_name_sanitized ) {
						if ( 'google' === $type
							&& function_exists( 'wpex_has_google_services_support' )
							&& ! wpex_has_google_services_support()
						) {
							echo '<div class="notice notice-error inline">
								<p>' . sprintf(
									esc_html__( '%sWarning%s: The "Remove Google Fonts" option is enabled in the Theme Panel, so your Google Fonts will not be loaded.', 'total-theme-core' ),
									'<strong>',
									'</strong>'
								) . '</p>
							</div>';
						}
						$font = $this->generate_font_data( $post_id )[1] ?? [];
						if ( isset( $font['stack'] ) && isset( $font['style_handle'] ) ) {
							wp_enqueue_style( $font['style_handle'] );
							$inline_style .= "font-family:{$font['stack']};";
						}
					}
					echo '<div style="' . esc_attr( $inline_style ) . '">' . esc_html( $font_name ) . '</div>';
					if ( $description = get_post_meta( $post_id, 'description', true ) ) {
						echo '<small class="description">' . esc_html( $description ) . '</small>';
					}
				break;
				case 'type':
					if ( $type = get_post_meta( $post_id, 'type', true ) ) {
						echo esc_html( self::choices_font_types()[ $type ] ?? $type );
					} else {
						echo '&#8212;';
					}
				break;
				case 'alias':
					if ( $alias = get_post_meta( $post_id, 'alias', true ) ) {
						echo esc_html( sanitize_text_field( $alias ) );
					} else {
						echo '&#8212;';
					}
				break;
				case 'assign_to':
					$assign_to = $this->parse_assign_to_list( sanitize_text_field( get_post_meta( $post_id, 'assign_to', true ) ) );
					if ( $assign_to ) {
						foreach ( $assign_to as $el ) {
							echo '<p><code>' . esc_html( $el ) . '</code></p>';
						}
					} else {
						echo '&#8212;';
					}
				break;
				case 'is_global':
					$type            = get_post_meta( $post_id, 'type', true );
					$is_global       = (bool) get_post_meta( $post_id, 'is_global', true );
					$has_assigned_to = (bool) get_post_meta( $post_id, 'assign_to', true );

					if ( $is_global || $has_assigned_to || 'custom' === $type ) {
						echo '<span class="dashicons dashicons-yes" aria-hidden="true" style="color:green;"><div class="screen-reader-text">' . esc_html__( 'Yes', 'total-theme-core' ) . '</div>';
					} else {
						echo '<span class="dashicons dashicons-no-alt" aria-hidden="true" style="color:red;"></span><div class="screen-reader-text">' . esc_html__( 'No', 'total-theme-core' ) . '</div>';
					}
				break;
			}
		}

		/**
		 * Add a back button to the Font Manager main page.
		 */
		public function add_back_button() {
			global $current_screen;

			if ( 'wpex_font' !== $current_screen->post_type ) {
				return;
			}

			wp_enqueue_script( 'jQuery' );

			?>

			<script>
				jQuery( function() {
					jQuery( 'body.post-type-wpex_font .wrap h1' ).append( '<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpex_font' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Font Manager', 'total-theme-core' ); ?></a>' );
				} );
			</script>
			<?php
		}

		/**
		 * Disable wpbakery builder from post type.
		 */
		public function disable_wpbakery( $check, $type ) {
			if ( self::POST_TYPE === $type ) {
				return false;
			}
			return $check;
		}

		/**
		 * Removes the edit with wpbakery button from the admin screen.
		 */
		public function remove_wpbakery_button_fe( $result, $post_id, $type ) {
			if ( self::POST_TYPE === $type ) {
				return false;
			}
			return $result;
		}

		/**
		 * Register metaboxes.
		 */
		public function register_metaboxes() {
			new WPEX_Meta_Factory( [
				'id'       => 'general',
				'title'    => esc_html__( 'Font Settings', 'total-theme-core' ),
				'screen'   => [ self::POST_TYPE ],
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => [ self::class, '_get_metabox_fields' ]
			] );
		}

		/**
		 * General metabox fields.
		 */
		public static function _get_metabox_fields(): array {
			return [
				[
					'name' => esc_html__( 'Type', 'total-theme-core' ),
					'id' => 'type',
					'type' => 'button_group',
					'required' => true,
					'desc' => esc_html__( 'Select a font type. Choose "Other" to register an existing font family for use with theme functions.', 'total-theme-core' ),
					'choices' => [ self::class, 'choices_font_types' ],
				],
				// Adobe Settings
				[
					'name' => esc_html__( 'Adobe Project ID', 'total-theme-core' ),
					'id' => 'adobe_project_id',
					'type' => 'text',
					'desc' => esc_html__( 'Enter your adobe project ID.', 'total-theme-core' ),
					'conditional_logic' => [ 'type', '=', 'adobe' ],
					'required' => true,
				],
				// General
				[
					'name' => esc_html__( 'Name', 'total-theme-core' ),
					'id' => 'name',
					'type' => 'text',
					'desc' => esc_html__( 'Your exact font name (case sensitive).', 'total-theme-core' ),
					'required' => true,
					'conditional_logic' => [ 'type', '!=', '' ],
				],
				[
					'name' => esc_html__( 'Alias', 'total-theme-core' ),
					'id' => 'alias',
					'type' => 'text',
					'desc' => esc_html__( 'Unique name that lets you change the font without having to reassign it. Optional but recommended.', 'total-theme-core' ),
					'hint' => esc_html__( 'Only lowercase letters, numbers and hyphens allowed (e.g., primary, secondary, fancy-heading). Maximum 20 characters.', 'total-theme-core' ),
					'immutable' => true,
					'conditional_logic' => [ 'type', '!=', '' ],
					'sanitize_callback' => [ self::class, '_sanitize_callback_alias' ],
					'input_attrs' => [ 'maxlength' => 20 ],
					'after_hook' => did_action( 'elementor/loaded' ) ? '<p class="wpex-mf-warning">' . esc_html__( 'Elementor only supports setting font families using the font name. If you want to use your alias in Elementor, you\'ll need to apply it using the CSS variable var(--wpex-{{alias}}-font).', 'total-theme-core' ) . '</p>' : '',
				],
				[
					'name' => esc_html__( 'Description', 'total-theme-core' ),
					'id' => 'description',
					'type' => 'text',
					'desc' => esc_html__( 'A helpful description for your own reference.', 'total-theme-core' ),
					'conditional_logic' => [ 'type', '!=', '' ],
				],
				[
					'name' => esc_html__( 'Display', 'total-theme-core' ),
					'id' => 'display',
					'type' => 'select',
					'desc' => esc_html__( 'Select your font-display value.', 'total-theme-core' ),
					'choices' => [ self::class, 'choices_font_display' ],
					'conditional_logic' => [ 'type', '=', [ 'google', 'custom' ] ],
				],
				[
					'name' => esc_html__( 'Fallback', 'total-theme-core' ),
					'id' => 'fallback',
					'type' => 'select',
					'desc' => esc_html__( 'Select your fallback font.', 'total-theme-core' ),
					'choices' => [ self::class, 'choices_fallback_fonts' ],
					'conditional_logic' => [ 'type', '!=', '' ],
				],
				[
					'name' => esc_html__( 'Custom Fallback', 'total-theme-core' ),
					'id'   => 'fallback_custom',
					'type' => 'text',
					'desc' => esc_html__( 'Enter a custom fallback font or stack.', 'total-theme-core' ),
					'conditional_logic' => [ 'fallback', '=', 'custom' ],
					'sanitize_callback' => [ self::class, '_sanitize_calback_fallback_custom' ],
				],
				[
					'name' => esc_html__( 'Load Globally', 'total-theme-core' ),
					'id' => 'is_global',
					'type' => 'checkbox',
					'desc' => esc_html__( 'Check the box to load this font on the entire site.', 'total-theme-core' ),
					'default' => false,
					'conditional_logic' => [ 'type', '=', [ 'google', 'adobe' ] ],
				],
				[
					'name' => esc_html__( 'Preload CSS File', 'total-theme-core' ),
					'id' => 'preload',
					'type' => 'checkbox',
					'default' => false,
					'desc' => esc_html__( 'You can preload web fonts that are required immediately to improve loading speed. This is recommended for fonts used "above the fold".', 'total-theme-core' ),
					'conditional_logic' => [ 'is_global', '=', 'true' ],
				],
				// Google Settings
				[
					'name' => esc_html__( 'Italics', 'total-theme-core' ),
					'id' => 'google_italic',
					'type' => 'checkbox',
					'desc' => esc_html__( 'Load italic styles for this font?', 'total-theme-core' ),
					'conditional_logic' => [ 'type', '=', 'google' ],
				],
				[
					'name' => esc_html__( 'Weights', 'total-theme-core' ),
					'id' => 'google_font_weights',
					'type' => 'multi_select',
					'desc' => esc_html__( 'Select the font weights to load. Make sure to only select font weights available for the desired font family.', 'total-theme-core' ),
					'choices' => [
						'100' => esc_html__( 'Thin: 100', 'total-theme-core' ),
						'200' => esc_html__( 'Extra Light: 200', 'total-theme-core' ),
						'300' => esc_html__( 'Light: 300', 'total-theme-core' ),
						'400' => esc_html__( 'Normal: 400', 'total-theme-core' ),
						'500' => esc_html__( 'Medium: 500', 'total-theme-core' ),
						'600' => esc_html__( 'Semibold: 600', 'total-theme-core' ),
						'700' => esc_html__( 'Bold: 700', 'total-theme-core' ),
						'800' => esc_html__( 'Extra Bold: 800', 'total-theme-core' ),
						'900' => esc_html__( 'Black: 900', 'total-theme-core' ),
					],
					'conditional_logic' => [ 'type', '=', 'google' ],
				],
				[
					'name' => esc_html__( 'Subsets', 'total-theme-core' ),
					'id' => 'google_subsets',
					'type' => 'multi_select',
					'desc' => esc_html__( 'Select the font subsets to load for browsers that do not suppot unicode-range.', 'total-theme-core' ),
					'choices' => [ self::class, 'choices_google_subsets' ],
					'conditional_logic' => [ 'type', '=', 'google' ],
				],
				// Custom Font
				[
					'id' => 'custom_fonts',
					'type' => 'group',
					'name' => esc_html__( 'Variants', 'total-theme-core' ),
					'desc' => esc_html__( 'Upload or select your custom font files from the Media Library.', 'total-theme-core' ),
					'group_title' => esc_html__( 'Variant', 'total-theme-core' ),
					'group_sort' => true,
					'conditional_logic' => [ 'type', '=', 'custom' ],
					'fields' => [
						[
							'name' => esc_html__( 'Preload', 'total-theme-core' ),
							'id' => 'preload',
							'type' => 'checkbox',
							'desc_tip' => true,
							'desc' => esc_html__( 'You can preload web fonts that are required immediately to improve loading speed. This is recommended for fonts used "above the fold".', 'total-theme-core' ),
						],
						[
							'name' => esc_html__( 'Weight', 'total-theme-core' ),
							'id' => 'weight',
							'type' => 'select',
							'default' => '400',
							'choices' => [
								'100' => esc_html__( 'Thin: 100', 'total-theme-core' ),
								'200' => esc_html__( 'Extra Light: 200', 'total-theme-core' ),
								'300' => esc_html__( 'Light: 300', 'total-theme-core' ),
								'400' => esc_html__( 'Normal: 400', 'total-theme-core' ),
								'500' => esc_html__( 'Medium: 500', 'total-theme-core' ),
								'600' => esc_html__( 'Semibold: 600', 'total-theme-core' ),
								'700' => esc_html__( 'Bold: 700', 'total-theme-core' ),
								'800' => esc_html__( 'Extra Bold: 800', 'total-theme-core' ),
								'900' => esc_html__( 'Black: 900', 'total-theme-core' ),
							],
						],
						[
							'name' => esc_html__( 'Style', 'total-theme-core' ),
							'id' => 'style',
							'type' => 'select',
							'choices' => array(
								'normal' => esc_html__( 'Normal', 'total-theme-core' ),
								'italic' => esc_html__( 'Italic', 'total-theme-core' ),
							),
						],
						[
							'name' => esc_html__( 'WOFF2 File', 'total-theme-core' ),
							'id' => 'woff2',
							'type' => 'upload',
							'media_type' => 'font',
							'preview' => true,
						],
						[
							'name' => esc_html__( 'WOFF File (optional)', 'total-theme-core' ),
							'id' => 'woff',
							'type' => 'upload',
							'media_type' => 'font',
							'preview' => true,
						],
					],
				],
				// Assign font
				[
					'name' => esc_html__( 'Assign to Elements', 'total-theme-core' ),
					'id' => 'assign_to',
					'type' => 'textarea',
					'desc' => esc_html__( 'Enter a list of ID\'s, classnames or element tags to target with this Font Family. Hit enter after each element or separate using commas.', 'total-theme-core' ),
					'conditional_logic' => [ 'type', '!=', '' ],
				],
			];
		}

		/**
		 * Return array of font types.
		 */
		public static function choices_font_types(): array {
			return [
				'google' => 'Google',
				'adobe'  => 'Adobe',
				'custom' => esc_html__( 'Self Hosted', 'total-theme-core' ),
				'other'  => esc_html__( 'Other', 'total-theme-core' ),
			];
		}

		/**
		 * Return fallback font choices.
		 */
		public static function choices_fallback_fonts(): array {
			$fallback_fonts = [
				''           => esc_html__( 'No Fallback', 'total-theme-core' ),
				'sans-serif' => 'Sans Serif',
				'serif'      => 'Serif',
				'monospace'  => 'Monospace',
				'cursive'    => 'Cursive',
				'custom'     => esc_html__( 'Other', 'total-theme-core' ),
			];
			return (array) apply_filters( 'wpex_font_manager_choices_fallback_fonts', $fallback_fonts );
		}

		/**
		 * Return font-display choices.
		 */
		public static function choices_font_display(): array {
			return [
				'swap'     => 'swap',
				'auto'     => 'auto',
				'block'    => 'block',
				'fallback' => 'fallback',
				'optional' => 'optional',
			];
		}

		/**
		 * Return Google font subsets.
		 * 
		 * @todo update to include all subset choices!
		 */
		public static function choices_google_subsets(): array {
			return [
				'latin'        => 'latin',
				'latin-ext'    => 'latin-ext',
				'cyrillic'     => 'cyrillic',
				'cyrillic-ext' => 'cyrillic-ext',
				'greek'        => 'greek',
				'greek-ext'    => 'greek-ext',
				'vietnamese'   => 'vietnamese',
			];
		}

		/**
		 * Register wpex_fonts type.
		 */
		public function register_type() {
			$user_cap = $this->get_user_cap();

			register_post_type( self::POST_TYPE, [
				'labels' => [
					'name'               => esc_html__( 'Font Manager', 'total-theme-core' ),
					'singular_name'      => esc_html__( 'Font', 'total-theme-core' ),
					'add_new'            => esc_html__( 'Add New Font' , 'total-theme-core' ),
					'add_new_item'       => esc_html__( 'Add New Font' , 'total-theme-core' ),
					'edit_item'          => esc_html__( 'Edit Font' , 'total-theme-core' ),
					'new_item'           => esc_html__( 'New Font' , 'total-theme-core' ),
					'view_item'          => esc_html__( 'View Font', 'total-theme-core' ),
					'search_items'       => esc_html__( 'Search Fonts', 'total-theme-core' ),
					'not_found'          => esc_html__( 'No Fonts found', 'total-theme-core' ),
					'not_found_in_trash' => esc_html__( 'No Fonts found in Trash', 'total-theme-core' ),
				],
				'public'          => false,
				'show_ui'         => $this->check_user_cap(),
				'_builtin'        => false,
				'capability_type' => 'page',
				'hierarchical'    => false,
				'rewrite'         => false,
				'supports'        => [ 'title' ],
				'show_in_menu'    => defined( 'WPEX_THEME_PANEL_SLUG' ) ? WPEX_THEME_PANEL_SLUG : 'themes.php',
				// Admins only
				'capabilities' => [
					'edit_post'          => $user_cap,
					'read_post'          => $user_cap,
					'delete_post'        => $user_cap,
					'edit_posts'         => $user_cap,
					'edit_others_posts'  => $user_cap,
					'delete_posts'       => $user_cap,
					'publish_posts'      => $user_cap,
					'read_private_posts' => $user_cap,
				],
			] );

			$this->is_post_type_registered = true;
		}

		/**
		 * Return all registered fonts.
		 */
		public function get_registered_fonts(): array {
			if ( self::ENABLE_CACHE ) {
				$fonts = get_transient( self::TRANSIENT_NAME );
				if ( ! empty( $fonts ) && is_array( $fonts ) ) {
					$this->registered_fonts = $fonts;
					$this->registered_fonts_check = true;
					return $this->registered_fonts;
				}
			}

			if ( $this->registered_fonts_check && $this->is_post_type_registered ) {
				return $this->registered_fonts;
			}

			$fonts = get_posts( [
				'numberposts' 	   => 50,
				'post_type' 	   => self::POST_TYPE,
				'post_status'      => 'publish',
				'suppress_filters' => false,
				'fields'           => 'ids',
			] );

			if ( ! empty( $fonts ) && is_array( $fonts ) ) {
				foreach ( $fonts as $font ) {
					$font_data = $this->generate_font_data( $font );
					if ( $font_data ) {
						$this->registered_fonts[ $font_data[0] ] = $font_data[1];
					}
				}
			}

			$this->registered_fonts_check = true;

			if ( self::ENABLE_CACHE ) {
				set_transient( self::TRANSIENT_NAME, $this->registered_fonts, MONTH_IN_SECONDS );
			}

			return $this->registered_fonts;
		}

		/**
		 * Generate font data for font by id.
		 */
		private function generate_font_data( $font ) {
			$type = sanitize_text_field( get_post_meta( $font, 'type', true ) );

			if ( ! $type ) {
				return;
			}

			if ( 'google' === $type && function_exists( 'wpex_has_google_services_support' ) && ! wpex_has_google_services_support() ) {
				return;
			}

			$name = $this->sanitize_font_name( get_post_meta( $font, 'name', true ), $type );

			if ( ! $name ) {
				return;
			}

			$data = [
				'id'   => $font, // Font post ID used for generating the font data on the fly
				'type' => $type, // Important for getting the type from a selected font
				'name' => $name, // Must alwas use the name for backwards compat and Elementor support
			];

			$skip = false;

			switch ( $type ) {
				case 'google';
					$data['preload']      = wp_validate_boolean( get_post_meta( $font, 'preload', true ) );
					$data['style_handle'] = "wpex-fm-{$font}";
				break;
				case 'adobe';
					$project_id = get_post_meta( $font, 'adobe_project_id', true );
					if ( $project_id ) {
						$data['preload']      = wp_validate_boolean( get_post_meta( $font, 'preload', true ) );
						$data['style_handle'] = 'wpex-fm-' . sanitize_title( $project_id ); // using adobe ID prevents multiple registrations
						$data['project_id']   = $project_id;
					} else {
						$skip = true;
					}
				break;
				case 'custom';
					$variants = get_post_meta( $font, 'custom_fonts', true );
					if ( $variants && is_array( $variants ) ) {
						$variants_parsed = [];
						foreach ( $variants as $variant ) {
							$variants_parsed[] = array_filter( [
								'preload' => isset( $variant['preload'] ) ? wp_validate_boolean( $variant['preload'] ) : '',
								'weight'  => isset( $variant['weight'] ) ? $this->sanitize_weight( $variant['weight'] ) : '',
								'style'   => isset( $variant['style'] ) ? $this->sanitize_style( $variant['style'] ) : '',
								'woff2'   => isset( $variant['woff2'] ) ? $this->sanitize_hosted_font_url( $variant['woff2'] ) : '',
								'woff'    => isset( $variant['woff'] ) ? $this->sanitize_hosted_font_url( $variant['woff'] ) : '',
							] );
						}
						$variants_parsed = array_filter( $variants_parsed );
						if ( $variants_parsed ) {
							$data['variants'] = $variants_parsed;
							$css = $this->generate_font_face_css(
								$name,
								$this->sanitize_font_display( get_post_meta( $font, 'display', true ) ),
								$variants_parsed
							);
							if ( $css ) {
								$data['style_handle'] = "wpex-fm-{$font}";
								$data['css']          = $css;
							} else {
								$skip = true;
							}
						}
					}
				break;
			}

			if ( $skip ) {
				return;
			}

			// Get alias
			$alias = get_post_meta( $font, 'alias', true );

			if ( $alias ) {
				$alias_safe = sanitize_title( $alias );
				$data['label'] = "{$name} ({$alias_safe})";
				$key = "ref-{$alias_safe}";
			} else {
				$key = $name;
				$data['label'] = $name;
			}

			// Define font stack
			$fallback = get_post_meta( $font, 'fallback', true );
			if ( 'custom' === $fallback ) {
				$fallback = (string) get_post_meta( $font, 'fallback_custom', true );
				if ( $fallback ) {
					$fallback = sanitize_text_field( $fallback );
				}
			}
			$data['stack'] = $this->get_font_stack( $name, $fallback, $type );

			// Check assignment
			$assign_to = $this->parse_assign_to_list( sanitize_text_field( get_post_meta( $font, 'assign_to', true ) ) );
			if ( $assign_to ) {
				$data['assign_to'] = array_map( 'sanitize_text_field', $assign_to );
			}

			// Add global check
			$data['is_global'] = $assign_to // assigned fonts are always global
				|| ( 'custom' === $type ) // self-hosted fonts are always global
				|| wp_validate_boolean( get_post_meta( $font, 'is_global', true ) ); // check if global is enabled

			// Define font URL - at the end once we have all the data
			if ( $url = $this->get_font_url( $data ) ) {
				$data['url'] = $url;
			}

			return [ $key, $data ];
		}

		/**
		 * Register font styles.
		 */
		public function register_enqueue_styles( $enqueue = false ) {
			if ( $this->is_styles_registered && ! $enqueue ) {
				return; // prevent duplicate registration
			}

			// Get registered fonts
			$registered_fonts = $this->get_registered_fonts();

			if ( ! $registered_fonts ) {
				return;
			}

			// Define main variables
			$registered_handles = [];
			$css_vars           = '';

			// Loop through registered fonts
			foreach ( $registered_fonts as $key => $font ) {
				if ( empty( $font['style_handle'] ) ) {
					continue; // other fonts
				}

				$handle = $font['style_handle'];

				// Register style if not already registered
				if ( ! $this->is_styles_registered ) {

					// Requirements for registration
					if ( ! in_array( $font['type'], [ 'google', 'adobe', 'custom' ], true ) ) {
						continue;
					}

					// If alias, add CSS var (other fonts can have aliases as well)
					if ( str_starts_with( $key, 'ref-' ) && ! empty( $font['stack'] ) ) {
						$alias = substr( $key, 4 );
						$css_vars .= "--wpex-{$alias}-font:{$font['stack']};";
					}

					if ( ! in_array( $font['style_handle'], $registered_handles, true ) ) {
						switch ( $font['type'] ) {
							case 'google':
							case 'adobe':
								wp_register_style(
									$handle,
									set_url_scheme( $font['url'] ?? $this->get_font_url( $font ) ),
									[],
									null
								);
								break;
							case 'custom':
								if ( ! empty( $font['css'] ) ) {
									wp_register_style( $handle, false );
									wp_add_inline_style( $handle, $font['css'] );
								}
								break;
						}
						$registered_handles[] = $handle;
					}
				}

				// Enqueue the style
				if ( $enqueue || ( isset( $font['is_global'] ) && wp_validate_boolean( $font['is_global'] ) ) ) {
					wp_enqueue_style( $handle, '', [], null );
				}
			}

			// Add CSS vars
			if ( $css_vars ) {
				if ( ! $this->is_styles_registered ) {
					wp_register_style( 'wpex-fm-vars', false );
					wp_add_inline_style( 'wpex-fm-vars', ":root{{$css_vars}}" );
				}
				wp_enqueue_style( 'wpex-fm-vars' );
			}

			$this->is_styles_registered = true;
		}

		/**
		 * Hooks into totaltheme/inline_css to add the font family CSS variables.
		 */
		public function register_enqueue_assignments() {
			$assigned_to_css = '';
			foreach ( $this->get_registered_fonts() as $key => $font ) {
				$assign_to = ( isset( $font['assign_to'] ) && is_array( $font['assign_to'] ) ) ? $font['assign_to'] : [];
				if ( ! empty( $font['assign_to'] ) && is_array( $font['assign_to'] ) ) {
					foreach ( $assign_to as $selector ) {
						if ( str_starts_with( $key, 'ref-' ) ) {
							$alias = substr( $key, 4 );
							$font_family_val = "var(--wpex-{$alias}-font)";
						} else {
							$font_family_val = $font['stack'];
						}
						$assigned_to_css .= "{$selector}{font-family:{$font_family_val};}";
					}
				}
			}
			if ( $assigned_to_css ) {
				wp_register_style( 'wpex-fm-assignments', false );
				wp_add_inline_style( 'wpex-fm-assignments', wp_strip_all_tags( $assigned_to_css ) );
				wp_enqueue_style( 'wpex-fm-assignments' );
			}
		}

		/**
		 * Hooks into wpex_preload_links.
		 */
		public function _filter_preload_links( array $links ): array {
			$stylesheets = [];
			foreach ( $this->get_registered_fonts() as $key => $font ) {
				if ( 'custom' === $font['type'] ) {
					if ( isset( $font['variants'] ) && is_array( $font['variants'] ) ) {
						foreach ( $font['variants'] as $variant ) {
							if ( isset( $variant['preload'] ) && true ===  $variant['preload'] ) {
								if ( ! empty( $variant['woff2'] ) ) {
									$links[] = [
										'href'        => $variant['woff2'],
										'type'        => 'font/woff2',
										'as'          => 'font',
										'crossorigin' => true,
									];
								} elseif ( ! empty( $variant['woff'] ) ) {
									$links[] = [
										'href'        => $variant['woff'],
										'type'        => 'font/woff',
										'as'          => 'font',
										'crossorigin' => true,
									];
								}
							}
						}
					}
				} elseif ( ! empty( $font['url'] ) ) {
					if ( isset( $font['preload'] )
						&& true === $font['preload']
						&& ! in_array( $font['url'], $stylesheets, true )
					) {
						$stylesheets[] = $font['url'];
					}
				}
			}
			foreach ( $stylesheets as $stylesheet ) {
				$links[] = [
					'href' => $stylesheet,
					'as'   => 'style',
				];
			}
			return $links;
		}

		/**
		 * Validate font-display.
		 */
		public function sanitize_font_display( $display = null, $fallback = 'swap' ) {
			if ( ! $display && $fallback ) {
				return $fallback;
			} elseif ( in_array( $display, self::choices_font_display(), true ) ) {
				return $display;
			}
		}

		/**
		 * Sanitize Font Name.
		 */
		public function sanitize_font_name( $name = '', $type = '' ) {
			switch ( $type ) {
				case 'adobe':
					$name = strtolower( str_replace( ' ', '-', $name ) );
					break;
			}
			return $name ? sanitize_text_field( $name ) : '';
		}

		/**
		 * Parse list.
		 */
		public function parse_assign_to_list( $list = [] ) {
			if ( is_string( $list ) && $list ) {
				if ( false !== strpos( $list, PHP_EOL ) ) {
					$list = explode( PHP_EOL, $list );
				} else {
					$list = explode( ',', $list );
				}
			}
			if ( is_array( $list ) ) {
				$list = array_map( 'trim', $list );
			}
			return $list;
		}

		/**
		 * Returns the font stack.
		 */
		private function get_font_stack( $name, $fallback = '', $type = '' ): string {
			$stack = $name;

			// Add quotes if font name contains spaces or special characters (excluding hyphens)
			if ( preg_match( '/[^a-zA-Z0-9\-]/', $stack )  ) {
				$stack = "'{$stack}'";
			}

			// Set some default fallbacks for popular Google fonts
			if ( 'google' === $type ) {
				if ( ! $fallback ) {
					$serif_fonts = [ 'Open Sans', 'Lato', 'Roboto', 'Source Sans Pro', 'Raleway', 'PT Sans', 'Noto Sans', 'Poppins', 'Inter' ];
					if ( in_array( $name, $serif_fonts, true ) ) {
						$fallback = 'sans-serif';
					}
				}
				$fallback = (string) apply_filters( 'wpex_get_font_family_stack', $fallback, $name );
			}

			// Add fallback
			if ( $fallback ) {
				$stack = "{$stack}, {$fallback}";
			}

			return $stack;
		}

		/**
		 * Returns the font URL.
		 */
		private function get_font_url( $font ) {
			switch ( $font['type'] ) {
				case 'google':
					return $this->get_google_font_url( $font['id'], $font['name'] );
					break;
				case 'adobe':
					if ( ! empty( $font['project_id'] ) ) {
						return $this->get_adobe_font_url( $font['project_id'] );
					}
					break;
			}
		}

		/**
		 * Returns the Google Font URL.
		 */
		private function get_google_font_url( $id, $name = '' ) {
			$italic  = wp_validate_boolean( get_post_meta( $id, 'google_italic', true ) );
			$weights = get_post_meta( $id, 'google_font_weights', true );
			$subsets = get_post_meta( $id, 'google_subsets', true );
			$display = get_post_meta( $id, 'display', true );

			// Define base url
			$url = $this->get_google_api_url() . '/css2?family=' . \str_replace( ' ', '+', \trim( $name ) );

			// Parse font weights
			if ( $weights ) {
				$weights = array_map( 'sanitize_text_field', wp_parse_list( $weights ) );
			} else {
				$weights = [ '100', '200', '300', '400', '500', '600', '700', '800', '900' ];
				$weights = apply_filters( 'wpex_google_font_enqueue_weights', $weights, $name );
				$weights = $weights ? wp_parse_list( $weights ) : [];
			}

			// Add weights & italics to the url
			if ( $weights ) {
				if ( 1 === count( $weights ) && 400 === (int) $weights[0] ) {
					if ( $italic ) {
						$url .= ':ital@0;1';
					}
				} else {
					$url .= $italic ? ':ital,wght@' : ':wght@';
					$weight_axes = [];
					if ( $italic ) {
						foreach ( $weights as $weight ) {
							$weight_axes[] = "0,{$weight}";
						}
						foreach ( $weights as $weight ) {
							$weight_axes[] = "1,{$weight}";
						}
					} else {
						foreach ( $weights as $weight ) {
							$weight_axes[] = $weight;
						}
					}
					$url .= \implode( ';', $weight_axes );
				}
			}

			// Add font display
			if ( $display && $display_safe = $this->sanitize_font_display( $display ) ) {
				$url .= "&display={$display_safe}";
			}

			// Parse subsets
			if ( $subsets ) {
				$subsets = array_map( 'sanitize_text_field', wp_parse_list( $subsets ) );
			} else {
				$subsets = get_theme_mod( 'google_font_subsets', [ 'latin' ] );
				$subsets = \apply_filters( 'wpex_google_font_enqueue_subsets', $subsets, $name );
				$subsets = wp_parse_list( $subsets );
			}

			// Add subsets to the url
			if ( $subsets ) {
				$safe_subsets = [];
				foreach ( $subsets as $subset ) {
					if ( in_array( $subset, self::choices_google_subsets(), true ) ) {
						$safe_subsets[] = $subset;
					}
				}
				$url .= '&subset=' . implode( ',', $safe_subsets );
			}

			return esc_url( $url );
		}

		/**
		 * Returns the Adobe Font URL.
		 */
		private function get_adobe_font_url( $project_id ) {
			$project_id_safe = sanitize_text_field( $project_id );
			if ( $project_id_safe ) {
				return esc_url( "https://use.typekit.net/{$project_id_safe}.css" );
			}
		}

		/**
		 * Hooks into "wp_handle_upload_prefilter".
		 */
		public function _filter_wp_handle_upload_prefilter( $file ) {
			$in_context = false;
			$post_id    = isset( $_REQUEST['post_id'] ) ? absint( wp_unslash( $_REQUEST['post_id'] ) ) : 0;
			if ( $post_id ) {
				$in_context = self::POST_TYPE === get_post_type( $post_id );
			} else {
				$in_context = isset( $_REQUEST['post_type'] )
					&& self::POST_TYPE === sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) );
			}
			if ( $in_context ) {
				add_filter( 'upload_mimes', [ $this, '_filter_upload_mimes' ] );
				add_filter( 'pre_move_uploaded_file', [ $this, '_filter_pre_move_uploaded_file' ] );
			}
			return $file;
		}

		/**
		 * Hooks into "upload_mimes".
		 */
		public function _filter_upload_mimes( $mimes ) {
			if ( $this->check_user_cap() ) {
				$mimes['woff']  = PHP_VERSION_ID >= 80112 ? 'font/woff' : 'application/font-woff';
				$mimes['woff2'] = PHP_VERSION_ID >= 80112 ? 'font/woff2' : 'application/font-woff2';
			}
			return $mimes;
		}

		/**
		 * Hooks into "pre_move_uploaded_file".
		 */
		public function _filter_pre_move_uploaded_file( $move_new_file ) {
			remove_filter( 'upload_mimes', [ $this, '_filter_upload_mimes' ] );
			return $move_new_file;
		}

		/**
		 * Filter the upload settings to allow font files.
		 */
		public function _filter_plupload_default_settings( $settings ) {
			if ( function_exists( 'get_current_screen' ) ) {
				$current_screen_type = get_current_screen()->post_type ?? '';
				if ( self::POST_TYPE !== $current_screen_type ) {
					return $settings;
				}
				if ( ! empty( $settings['filters']['mime_types'][0]['extensions'] ) ) {
					$extensions = $settings['filters']['mime_types'][0]['extensions'];
					if ( is_string( $extensions ) ) {
						if ( ! str_contains( $extensions, 'woff' ) ) {
							$settings['filters']['mime_types'][0]['extensions'] .= ',woff';
						}
						if ( ! str_contains( $extensions, 'woff2' ) ) {
							$settings['filters']['mime_types'][0]['extensions'] .= ',woff2';
						}
					}
				} else {
					$settings['filters']['mime_types'][0]['extensions'] = 'woff,woff2';
				}
			}
			return $settings;
		}

		/**
		 * Get remote font.
		 */
		private function get_remote_font( $url ) {
			return wp_safe_remote_get( $url, [
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
					'Accept' => 'text/css,*/*;q=0.1',
					'Accept-Language' => 'en-US,en;q=0.9',
				],
				'timeout' => 3,
				'redirection' => 0,
			] );
		}

		/**
		 * Validate Google Font URL.
		 */
		private function validate_google_font( $url ): bool {
			if ( ! str_starts_with( set_url_scheme( $url ), $this->get_google_api_url() ) ) {
				return true; // Not a Google Font URL
			}
			$response = $this->get_remote_font( $url );
			if ( is_wp_error( $response ) ) {
				return true;
			}
			return 400 !== (int) wp_remote_retrieve_response_code( $response );
		}

		/**
		 * Validate Adobe Font URL.
		 */
		private function get_adobe_font_families_from_url( $url ) {
			if ( ! str_starts_with( set_url_scheme( $url ), set_url_scheme( 'https://use.typekit.net/' ) ) ) {
				return false; // Not an Adobe Font URL
			}
			$response = $this->get_remote_font( $url );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $response_code ) {
				if ( 404 === $response_code ) {
					return new WP_Error( 'wpex_font_manager_adobe_request', esc_html__( 'The Adobe ID doesn\'t seem to be valid. Please double-check it. If it is correct, the validation may be blocked by the Adobe server, so you can safely ignore this message.', 'total-theme-core' ) );
				}
				return false;
			}
			$css = wp_remote_retrieve_body( $response );
			$font_families = [];
			if ( $css && str_contains( $css, '@font-face' ) ) {
				preg_match_all( '/@font-face\s*{[^}]*font-family\s*:\s*([\'"]?)([^;\'"}]+)\1\s*;/i', $css, $matches );
				if ( ! empty( $matches[2] ) ) {
					foreach ( $matches[2] as $family ) {
						$family = sanitize_text_field( $family );
						if ( $family && ! in_array( $family, $font_families, true ) ) {
							$font_families[] = $family;
						}
					}
				}
			}
			return $font_families;
		}

		/**
		 * Sanitize alias callback.
		 */
		public static function _sanitize_callback_alias( $value ) {
			// Clean user input
			$value = sanitize_text_field( wp_unslash( $value ) );

			// Convert to slug format
			$value = sanitize_title( $value );

			// Convert underscores to hyphens
			$value = str_replace( '_', '-', $value );

			// Collapse multiple hyphens
			$value = preg_replace( '/-+/', '-', $value );

			// Trim hyphens from start/end
			$value = trim( $value, '-' );

			// Limit length to 20 characters
			$value = substr( $value, 0, 20 );

			// Finally return the sanitized value
			return $value;
		}

		/**
		 * Sanitizes the custom fallback font.
		 */
		public static function _sanitize_calback_fallback_custom( $fallback ) {
			if ( ! $fallback ) {
				return;
			}
			$fallback = sanitize_text_field( wp_unslash( $fallback ) );
			$fallback = str_replace( [ '"', "'" ], '', $fallback );
			$fallback_array = explode( ',', $fallback ); // can't use wp_parse_list
			$fallback_array_new = [];
			foreach ( $fallback_array as $font ) {
				$font = preg_replace('/\s+/', ' ', trim( $font ) );
				// Add quotes if font name contains spaces or special characters (excluding hyphens)
				if ( preg_match( '/[^a-zA-Z0-9\-]/', $font ) && ! str_starts_with( $font, 'var(' ) ) {
					$fallback_array_new[] = "'{$font}'";
				} else {
					$fallback_array_new[] = $font;
				}
			}
			$fallback_array_new = array_filter( $fallback_array_new );
			return $fallback_array_new ? implode( ', ', $fallback_array_new ) : '';
		}

		/**
		 * Generate single font face rule.
		 */
		private function generate_single_font_face_css( $font ): string {
			$css = '';

			$urls = [];

			if ( ! empty( $font['woff2'] ) ) {
				$woff2_escaped = esc_url( set_url_scheme( $font['woff2'] ) );
				$urls[] = "url('{$woff2_escaped}') format('woff2')";
			}

			if ( ! empty( $font['woff'] ) ) {
				$woff_escaped = esc_url( set_url_scheme( $font['woff'] ) );
				$urls[] = "url('{$woff_escaped}') format('woff')";
			}

			if ( ! empty( $font['ttf'] ) ) {
				$ttf_escaped = esc_url( set_url_scheme( $font['ttf'] ) );
				$urls[] = "url('{$ttf_escaped}') format('truetype')";
			}

			if ( $urls ) {
				$css  .= '@font-face {';
					$css .= 'font-family:\'' . esc_attr( $font['name'] ) . '\';';
					$css .= 'src:' . implode( ', ', $urls ) . ';';
					if ( ! empty( $font['weight'] ) ) {
						$css .= 'font-weight:' . esc_attr( $font['weight'] ) . ';';
					}
					if ( ! empty( $font['style'] ) ) {
						$css .= 'font-style:' . esc_attr( $font['style'] ) . ';';
					}
					if ( ! empty( $font['display'] ) ) {
						$css .= 'font-display:' . esc_attr( $font['display'] ) . ';';
					}
				$css .= '}';
			}
			
			return $css;
		}

		/**
		 * Generate inline CSS for self hosted fonts.
		 */
		private function generate_font_face_css( $font_name, $font_display, $variants ): string {
			$css = '';
			foreach ( $variants as $variant ) {
				$variant['name']    = $font_name;
				$variant['display'] = $font_display;
				$css .= $this->generate_single_font_face_css( $variant );
			}
			return $css;
		}

		/**
		 * Sanitize font weight.
		 */
		private function sanitize_weight( $weight ): string {
			$allowed = [ '100', '200', '300', '400', '500', '600', '700', '800', '900' ];
			$weight = trim( (string) $weight );
			if ( in_array( $weight, $allowed, true ) ) {
				return $weight;
			}
			return '';
		}

		/**
		 * Sanitize font style.
		 */
		private function sanitize_style( $style ): string {
			$allowed = [ 'normal', 'italic', 'oblique' ];
			$style = strtolower( trim( (string) $style ) );
			if ( in_array( $style, $allowed, true ) ) {
				return $style;
			}
			return '';
		}

		/**
		 * Sanitizes a self-hosted font url.
		 */
		private function sanitize_hosted_font_url( $font ) {
			if ( is_numeric( $font ) ) {
				$font = wp_get_attachment_url( $font );
			}
			if ( ! is_string( $font ) || empty( $font ) ) {
				return;
			}
			$font       = sanitize_text_field( $font );
			$font_clean = strtok( $font, '?#' );
			$font_lower = strtolower( $font_clean );
			if ( str_ends_with( $font_lower, '.woff' )
				|| str_ends_with( $font_lower, '.woff2' )
				|| str_ends_with( $font_lower, '.ttf' )
			) {
				return $font_clean;
			}
		}

		/**
		 * Returns the Google api URL.
		 */
		private function get_google_api_url(): string {
			if ( function_exists( 'wpex_get_google_fonts_url' ) ) {
				return wpex_get_google_fonts_url();
			} else {
				return set_url_scheme( 'https://fonts.googleapis.com' );
			}
		}

		/**
		 * Delets the cache.
		 */
		private function delete_cache(): void {
			delete_transient( self::TRANSIENT_NAME );
		}

	}

	WPEX_Font_Manager::instance();

}
