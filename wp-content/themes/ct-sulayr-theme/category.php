<?php

/**
 * The template for displaying category page
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

get_header();

use CTSulayrTheme\Infrastructure\CustomFields;

while (have_rows(CustomFields::BLOG_SETTINGS_BLOG, 'options')) {
    the_row();
    $default_post_per_page          = get_option('posts_per_page') ?: 10;
    $blog_settings_post_per_page    = get_sub_field(CustomFields::BLOG_SETTINGS_POST_PER_PAGE, 'options') ?: $default_post_per_page;
}

$category = get_queried_object();

if ( null === $category ) {
    return;
}

$show_promotion = get_field( CustomFields::CATEGORY_SHOW_PROMOTION, CustomFields::CATEGORY_SLUG . '_' . $category->term_id );
$blog_page_id = get_option( 'page_for_posts' );

get_template_part( 'templates/parts/header', 'blog', 
    [
        'title' => $category->name,
        'description' => $category->description,
        'page_for_posts' => $blog_page_id,
        'blog_settings_post_per_page' => $blog_settings_post_per_page,
        'show_promotion' => $show_promotion,
        'cat_id' => $category->term_id,
    ]
);

get_footer();
