<?php
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

get_header(); 

switch ( $post->post_type ) :
    case 'faq':
        get_template_part( 'templates/FAQ/archive', $post->post_type );
        break;
endswitch;

get_footer();
