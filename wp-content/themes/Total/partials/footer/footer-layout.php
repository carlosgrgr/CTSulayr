<?php

use TotalTheme\Footer\Core as Footer;

/**
 * Footer Layout.
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

// Classic Footer Layout
if ( Footer::has_classic_layout() ) {
	get_template_part( 'partials/footer/footer-layout-classic' );
	return;
}

// Modern Footer Layout v6.4+
wpex_hook_footer_before();

// Capture custom footer
ob_start();
$has_custom_footer = wpex_theme_do_location( 'footer' );
$custom_footer_content = ob_get_clean();

// Begin footer output
ob_start();

	/**
	 * Hook: wpex_hook_footer_top.
	 *
	 * @hooked wpex_footer_callout - 10
	 */
	wpex_hook_footer_top();

	/**
	 * If we have a custom footer, such as Elementor or other we don't add the footer_inner hook
	 * as this hook is intended for the Footer Widgets.
	 */
	if ( $has_custom_footer ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $custom_footer_content;
	} else {

		/**
		 * Hook: wpex_hook_footer_inner.
		 *
		 * @hooked wpex_footer_widgets - 10
		 */
		ob_start();
			wpex_hook_footer_inner();
		$inner_content = ob_get_clean();

		// Wrap the footer inner content in it's own div
		if ( $inner_content ) {
			echo '<div id="footer-inner" ' . Footer::get_inner_class() . '>';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $inner_content;
			echo '</div>';
		}

	}

	/**
	 * Hook: wpex_hook_footer_bottom.
	 *
	 * @hooked wpex_footer_bottom - 10
	 */
	wpex_hook_footer_bottom();

$footer_content = ob_get_clean();

if ( $footer_content ) {
	if ( Footer::has_reveal() ) {
		echo '<div id="footer-reveal" class="footer-reveal-visible">';
			echo '<footer id="footer" ' . Footer::get_wrapper_class() . '>' . $footer_content . '</footer>';
		echo '</div>';
	} else {
		echo '<footer id="footer" ' . Footer::get_wrapper_class() . '>' . $footer_content . '</footer>';
	}
}

wpex_hook_footer_after();
