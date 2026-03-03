<?php
/**
 * The template for displaying post tags page
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

get_header(); ?>

<h1><?php esc_html_e( 'You are in a tag page', 'ct_sulayr_theme' ) ?></h1>

<?php if ( false !== have_posts() ): 
    while ( false !== have_posts() ): the_post(); ?>
        <h2><?php the_title() ?></h2>
        <?php the_content();
    endwhile;
endif;

get_footer();
