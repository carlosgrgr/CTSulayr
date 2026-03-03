<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ct-sulayr-theme
 */

$network_buttons = [];
$contat_data_title = '';
$contact_data_text = '';
$contact_data_email = [];
$contact_data_featured_text = '';
?>  
        </main>
        <footer class="c-footer background-dark-blue">
            <div class="container">
                <div class="row">
                    <div class="col-12 px-3 px-md-4 px-xl-8">
                        <div class="row row-container-cards">
                            <div class="col-12 col-md-6 col-lg-3">
                                <?php wp_nav_menu( [ 
                                    'theme_location' => 'footer_column_1',
                                    'container' => 'div',
                                    'container_class' => 'c-footer__col',
                                    'depth' => '2',
                                    'fallback_cb' => false,
                                    'menu_class'=> 'menu',
                                    'add_li_class'  => 'menu-item',
                                ] );
                                ?>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <?php wp_nav_menu( [ 
                                    'theme_location' => 'footer_column_2',
                                    'container' => 'div',
                                    'container_class' => 'c-footer__col',
                                    'depth' => '2',
                                    'fallback_cb' => false,
                                    'menu_class'=> 'menu',
                                    'add_li_class'  => 'menu-item',
                                ] );
                                ?>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <div class="c-footer__col">
                                    <?php if ( !empty( $contat_data_title ) ) : ?>
                                        <p class="c-footer__title"><?php echo $contat_data_title ?></p>
                                    <?php endif; ?>
                                    <div id="contact-email"></div>
                                    <?php if ( !empty( $contact_data_text ) ) : ?>
                                        <p class="c-footer__text"><?php echo $contact_data_text ?></p>
                                    <?php endif; ?>

                                    <?php if ( !empty( $contact_data_email['url'] ) ) : ?>
                                        <a href="<?php echo $contact_data_email['url'] ?>" class="c-link" title="<?php echo $contact_data_email['title'] ?>" target="<?php echo $contact_data_email['target'] ?>">
                                            <?php echo $contact_data_email['title'] ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-3 mt-3 mt-md-0 mt-lg-0">
                                <div class="c-footer__col">
                                    <?php
                                        get_template_part('templates/parts/newsletter', 'footer');
                                    ?>
                                    <?php if ( !empty ( $contact_data_featured_text ) ) : ?>
                                        <p class="c-footer__title"><?php echo $contact_data_featured_text ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="c-footer__bottom">
                                <div class="c-footer__bottom-col">
                                    <p>© <?php echo date( "Y" ) ?> <?php echo get_bloginfo( 'name' ) ?></p>
                                </div>
                                <div class="c-footer__bottom-col">
                                    <nav class="c-footer__rrss" aria-label="<?php _e( 'Social Network Menu', CT_SULAYR_THEME_TEXTDOMAIN ) ?>">
                                        <ul role="menu">
                                            <li role="menuitem" aria-label="<?php _e( 'Social Network Menu Item', CT_SULAYR_THEME_TEXTDOMAIN ) ?>">
                                                <?php if( !empty ( $network_buttons ) ) : ?>
                                                    <?php foreach ($network_buttons as $button) {
                                                        echo $button;
                                                    } ?>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php get_template_part('templates/parts/newsletter', 'modal') ?>
        </footer>
        <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                            <span class="d-none d-md-inline"><?php _e( 'Close', CT_SULAYR_THEME_TEXTDOMAIN ); ?></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <section class="c-search" style="padding-top: 2rem">
                            <div class="container px-3 px-md-4 px-xl-8 d-flex flex-column align-items-center">
                                <?php get_template_part( 'templates/parts/content', 'search_form', ['is_modal' => true] ); ?>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <?php wp_footer(); ?>
    </body>
</html>