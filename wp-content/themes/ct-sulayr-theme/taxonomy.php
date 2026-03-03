<?php
/**
 * The template for displaying taxonomy pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

get_header(); 

$taxonomy = get_queried_object()->taxonomy;

switch ( $taxonomy ) :
    case 'faq_category':
        get_template_part( 'templates/FAQ/taxonomy', $taxonomy );
        break;
endswitch;

get_footer();
