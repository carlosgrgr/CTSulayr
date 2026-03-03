<?php
/**
 * The template for displaying blog page
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

get_header();

use CTSulayrTheme\Infrastructure\CustomFields;

while ( have_rows( CustomFields::BLOG_SETTINGS_BLOG, 'options' ) ) {
    the_row();
    $blog_settings_blog_description = get_sub_field( CustomFields::BLOG_SETTINGS_DESCRIPTION, 'options' ) ?: '';
    $default_post_per_page          = get_option( 'posts_per_page' ) ?: 10;
    $blog_settings_post_per_page    = get_sub_field( CustomFields::BLOG_SETTINGS_POST_PER_PAGE, 'options' ) ?: $default_post_per_page;
}

while ( have_rows( CustomFields::BLOG_SETTINGS_PROMOTION, 'options' ) ) {
    the_row();
    $show_promotion = get_sub_field( CustomFields::BLOG_SETTINGS_PROMOTION_DISPLAY, 'options' ) === 'yes';
}

$blog_page_id = get_option( 'page_for_posts' );

get_template_part( 'templates/parts/header', 'blog', 
    [
        'title' => get_the_title( $blog_page_id ),
        'description' => $blog_settings_blog_description,
        'page_for_posts' => $blog_page_id,
        'blog_settings_post_per_page' => $blog_settings_post_per_page,
        'show_promotion' => $show_promotion,
        'cat_id' => null,
    ]
);

wp_reset_query();
get_footer();
