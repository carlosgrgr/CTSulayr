<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package ct-sulayr-theme
 */

get_header();

$not_found_image       = '';
$not_found_title       = __( 'Page not found', CT_SULAYR_THEME_TEXTDOMAIN );
$not_found_description = __( 'The page you are looking for does not exist or has been moved.', CT_SULAYR_THEME_TEXTDOMAIN );
$not_found_link        = [];

?>
<div class="container">
    <div class="row block block-terms">
        <div class="col-12 px-xl-8 px-4 py-xl-8 py-4">
            <div class="c-noFound__wrapper">
                <?php if ( $not_found_image ) : ?><img class="c-noFound__image" src="<?php echo esc_url( $not_found_image ); ?>" alt=""><?php endif; ?>
                <h1 class="c-noFound__title"><?php echo $not_found_title ?></h1>
                <p  class="c-noFound__text"><?php echo $not_found_description ?></p>
                <?php if ( ! empty( $not_found_link ) ) : ?>
                <div class="c-noFound__links">
                    <?php foreach( $not_found_link as $link ) : ?>
                        <a href="<?php echo esc_url( is_object( $link ) ? $link->guid : $link['url'] ?? '#' ); ?>"><?php echo esc_html( is_object( $link ) ? $link->post_title : $link['title'] ?? '' ); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                    
            </div>
        </div>
    </div>
</div>

<?php 
get_footer();
