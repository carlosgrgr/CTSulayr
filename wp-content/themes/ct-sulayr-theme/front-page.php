<?php
/**
 * The template for displaying the front page
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ct-sulayr-theme
 */

get_header(); ?>

<?php if ( false !== have_posts() ): 
    while ( false !== have_posts() ): the_post(); ?>
        <?php the_content();
    endwhile;
endif;

get_footer();