<?php
/**
 * Hero Block Template.
 */

use CTSulayrTheme\Infrastructure\CustomFields;
use CTSulayrTheme\Infrastructure\CustomBlocks;

$hero_display_block = get_field( CustomFields::HERO_DISPLAY ) ?: 'yes';
$is_block_inactive = 'no' === $hero_display_block && !is_admin();

if ( $is_block_inactive ) return;

$hero_title_line1   = get_field( CustomFields::HERO_TITLE ) ?: '';
$hero_heading       = get_field( CustomFields::HERO_HEADING ) ?: 'h1';
$hero_title_line2   = get_field( CustomFields::HERO_FEATURED_TEXT ) ?: '';
$hero_description   = get_field( CustomFields::HERO_DESCRIPTION ) ?: '';
$hero_image         = get_field( CustomFields::HERO_IMAGE ) ?: [];
$create_an_account = [];
$login = [];
$buttons_options = 'account';
$buttons_custom_link = [];

$block_inactive_class = 'no' === $hero_display_block ? 'block-inactive' : '';

if( have_rows( CustomFields::HERO_BUTTONS_GROUP ) ) {
    while( have_rows( CustomFields::HERO_BUTTONS_GROUP ) ) {
        the_row();
        $buttons_options     = get_sub_field( CustomFields::HERO_BUTTONS_OPTIONS ) ?: $buttons_options;
        $buttons_custom_link = get_sub_field( CustomFields::HERO_BUTTONS_CUSTOM_LINK ) ?: $buttons_custom_link;
	}
}

if ( !empty( $hero_title_line1 ) && !empty( $hero_image["url"] ) ) : ?>
<section class="c-hero <?php echo esc_attr( $block_inactive_class ) ?>" id="<?php echo esc_attr( $block['anchor'] ?? '' ) ?>">
    <div class="container">
        <div class="row">
			<div class="col-12 c-hero__wrapper">
                <?php if ( $hero_title_line1 ) : ?>
                    <<?php echo esc_html( $hero_heading ) ?> class="c-hero__title">
                        <?php echo esc_html( $hero_title_line1 ) ?> 
                        <?php if ( $hero_title_line2 ) : ?>
                            <span><?php echo esc_html( $hero_title_line2 ) ?></span>
                        <?php endif; ?>
                    </<?php echo esc_html( $hero_heading ) ?>>
                <?php endif; ?>

                <?php if ( $hero_description ) : ?>
                    <p><?php echo esc_html( $hero_description ) ?></p>
                <?php endif; ?>

                <div class="d-flex justify-content-center">
                    <?php if ( !empty( $create_an_account['url'] ) && $buttons_options === 'account' ) : ?>
                        <a href="<?php echo esc_url( $create_an_account['url'] ) ?>" class="c-primary-btn" title="<?php echo esc_attr( $create_an_account['title'] ) ?>" target="<?php echo esc_attr( $create_an_account['target'] ) ?>">
                            <?php echo esc_html( $create_an_account['title'] ) ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( !empty( $buttons_custom_link['url'] ) && $buttons_options === 'custom' ) : ?>
                        <a href="<?php echo esc_url( $buttons_custom_link['url'] ) ?>" class="c-primary-btn" title="<?php echo esc_attr( $buttons_custom_link['title'] ) ?>" target="<?php echo esc_attr( $buttons_custom_link['target'] ) ?>">
                            <?php echo esc_html( $buttons_custom_link['title'] ) ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ( $buttons_options === 'stores' ) : ?>
                    <form class="c-hero__apps">
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                if (typeof loadStoreButtons === "function") { 
                                    loadStoreButtons(".c-hero__apps");
                                }
                            });
                        </script>
                    </form>
                <?php endif; ?>
			</div>
        </div>
    </div>

    <div class="c-hero__image">
    <?php
    $is_svg = pathinfo( $hero_image["url"], PATHINFO_EXTENSION ) === 'svg';
    $show_svg = false;
    $svg_content = '';

    if ( $is_svg ) {
        $cache_key = 'hero_svg_' . md5( $hero_image["url"] );
        $svg_content = get_transient( $cache_key );

        if ( false === $svg_content ) {
            // Intentar cargar local
            $image_path = str_replace( home_url('/'), ABSPATH, $hero_image["url"] );
            if ( file_exists( $image_path ) ) {
                $svg_content = file_get_contents( $image_path );
            } else {
                // Intentar cargar desde CDN
                $response = wp_remote_get( $hero_image["url"], ['timeout'=>10] );
                if ( !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 ) {
                    $svg_content = wp_remote_retrieve_body($response);
                }
            }

            if ( $svg_content ) {
                $svg_content = preg_replace('/(<svg[^>]*)\s+width="[^"]*"/', '$1', $svg_content);
                $svg_content = preg_replace('/(<svg[^>]*)\s+height="[^"]*"/', '$1', $svg_content);
                if ( strpos($svg_content,'viewBox') === false ) {
                    $svg_content = str_replace('<svg','<svg viewBox="0 0 1920 530"', $svg_content);
                }
                $svg_attributes = 'class="skip-lazy" style="display:block; width:100%; height:auto;"';
                if ( !empty($hero_image["alt"]) ) {
                    $svg_attributes .= ' role="img" aria-label="' . esc_attr($hero_image["alt"]) . '"';
                }
                $svg_content = str_replace('<svg','<svg '.$svg_attributes,$svg_content);
                set_transient( $cache_key, $svg_content, DAY_IN_SECONDS );
            } else {
                // Fallback a <img> si falla
                $show_svg = false;
            }
        }

        if ( $svg_content ) {
            $show_svg = true;
        }
    }

    if ( $show_svg && $svg_content ) {
        echo $svg_content;
    } else {
        // Fallback <img>
        ?>
        <img class="skip-lazy" src="<?php echo esc_url( $hero_image["url"] ) ?>" alt="<?php echo esc_attr( $hero_image["alt"] ) ?>" fetchpriority="high" width="1920" height="530">
        <?php
    }

    // Scroll SVG
    $scroll_icon_path = get_template_directory() . '/assets/images/scroll-down.svg';
    if ( file_exists($scroll_icon_path) ) {
        $scroll_cache_key = 'scroll_svg_' . md5($scroll_icon_path . filemtime($scroll_icon_path));
        $scroll_svg = get_transient($scroll_cache_key);
        if ( false === $scroll_svg ) {
            $scroll_svg = file_get_contents($scroll_icon_path);
            $scroll_svg = str_replace('<svg','<svg class="u-scroll-down" role="img" aria-label="' . esc_attr__('You can scroll down', CT_SULAYR_THEME_TEXTDOMAIN) . '"', $scroll_svg);
            set_transient($scroll_cache_key,$scroll_svg,DAY_IN_SECONDS);
        }
        echo $scroll_svg;
    }
    ?>
    </div>
</section>
<?php else: ?>
<div><?php echo sprintf( __( '%s Block', CT_SULAYR_THEME_TEXTDOMAIN ), CustomBlocks::HERO_BLOCK_NAME ) ?></div>
<?php endif; ?>
