<?php

use  TotalTheme\Topbar\Menu as Topbar_Menu;

/**
 * Topbar menu displays inside the topbar "content" area
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

$menu = Topbar_Menu::get_menu();

if ( ! $menu ) {
	return;
}

?>

<nav <?php Topbar_Menu::wrapper_class(); ?><?php wpex_aria_label( 'topbar_menu' ); ?>><?php
	echo $menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></nav>
