<?php

use TotalTheme\Footer\Core as Footer;
use TotalTheme\Footer\Widgets as Footer_Widgets;

/**
 * Footer Widgets.
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

$modern_layout = ! Footer::has_classic_layout();
$columns       = (int) get_theme_mod( 'footer_widgets_columns', 4 );
$grid_class    = wpex_row_column_width_class( $columns );
$grid_class    = (array) apply_filters( 'wpex_footer_widget_col_classes', array( $grid_class ) );

if ( ! empty( $grid_class ) && is_array( $grid_class ) ) {
	$grid_class = array_map( 'esc_attr', $grid_class );
	$grid_class = implode( ' ', $grid_class );
}

?>

<div id="footer-widgets" <?php Footer_Widgets::wrapper_class(); ?>><?php

	do_action( 'wpex_hook_footer_widgets_top' );

	if ( $modern_layout ) {
		echo '<div id="footer-widgets-inner" ' . Footer_Widgets::get_row_class() . '>';
	}

	if ( is_active_sidebar( 'footer_one' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-1"><?php dynamic_sidebar( 'footer_one' ); ?></div>

	<?php }

	if ( $columns > 1 && is_active_sidebar( 'footer_two' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-2"><?php dynamic_sidebar( 'footer_two' ); ?></div>

	<?php }

	if ( $columns > 2 && is_active_sidebar( 'footer_three' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-3"><?php dynamic_sidebar( 'footer_three' ); ?></div>

	<?php }

	if ( $columns > 3 && is_active_sidebar( 'footer_four' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-4"><?php dynamic_sidebar( 'footer_four' ); ?></div>

	<?php }

	if ( $columns > 4 && is_active_sidebar( 'footer_five' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-5"><?php dynamic_sidebar( 'footer_five' ); ?></div>

	<?php }

	if ( $columns > 5 && is_active_sidebar( 'footer_six' ) ) { ?>

		<div class="footer-box <?php echo esc_attr( $grid_class ); ?> col col-6"><?php dynamic_sidebar( 'footer_six' ); ?></div>

	<?php }

	if ( $modern_layout ) {
		echo '</div>';
	}

	do_action( 'wpex_hook_footer_widgets_bottom' );

?></div>
