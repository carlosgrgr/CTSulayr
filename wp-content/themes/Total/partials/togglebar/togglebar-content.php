<?php

use TotalTheme\ToggleBar;

/**
 * Togglebar content.
 *
 * @package Total WordPress theme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

$content = ToggleBar::get_content();

if ( ! $content ) {
	return;
}

$template_id = ToggleBar::get_template_id();

if ( $template_id
	&& WPEX_VC_ACTIVE
	&& $wpb_style = totaltheme_get_instance_of( 'TotalTheme\Integration\WPBakery\Shortcode_Inline_Style' )
) {
	$wpb_style->render_style( $template_id );
}
?>

<div class="toggle-bar-content entry wpex-clr"><?php
	echo do_shortcode( wp_kses_post( $content ) );
?></div>
