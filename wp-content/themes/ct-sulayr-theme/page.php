<?php
/**
 * The template for displaying all pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */
 use CTSulayrTheme\Infrastructure\CustomActions;

 do_action( CustomActions::CT_SULAYR_THEME_PAGE_PRE_HEADER );
 get_header(); 
 do_action( CustomActions::CT_SULAYR_THEME_PAGE_AFTER_HEADER );
 ?>


<?php if ( false !== have_posts() ): 
    while ( false !== have_posts() ): the_post(); ?>
        <?php the_content();
    endwhile;
endif;

do_action( CustomActions::CT_SULAYR_THEME_PAGE_PRE_FOOTER );
get_footer();
