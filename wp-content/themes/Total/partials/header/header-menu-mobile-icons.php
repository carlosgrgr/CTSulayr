<?php

use TotalTheme\Header\Core as Header;
use TotalTheme\Mobile\Menu as Mobile_Menu;

/**
 * Mobile Icons Header Menu
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TotalTheme\Header\Core' ) || ! class_exists( 'TotalTheme\Mobile\Menu' ) ) {
	return;
}

$icons_menu_placement = Mobile_Menu::icons_menu_placement();

?>

<div id="mobile-menu" <?php wpex_mobile_menu_toggle_class(); ?>>
	<div class="wpex-inline-flex wpex-items-center"><?php
		wpex_hook_mobile_menu_toggle_top();
		if ( 'before_toggle' === $icons_menu_placement ) {
			wpex_mobile_menu_toggle_extra_icons();
		}
		echo wpex_get_mobile_menu_toggle_icon();
		wpex_hook_mobile_menu_toggle_bottom();
	?></div>
</div>

<?php
if ( 'separate' === $icons_menu_placement ) {
	$extra_icons = wpex_mobile_menu_toggle_extra_icons( false );
	if ( $extra_icons ) {
		echo '<div id="mobile-icons-menu" class="show-at-mm-breakpoint wpex-flex wpex-items-center wpex-justify-end wpex-gap-20">' . $extra_icons . '</div>';
	}
}
