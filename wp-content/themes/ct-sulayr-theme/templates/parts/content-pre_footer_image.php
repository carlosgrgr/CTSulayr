<?php
$show_general_settings_pre_footer_image = false !== isset( $args['pre_footer_image']['desktop_image']['url'] )
                                       && false === empty( $args['pre_footer_image']['desktop_image']['url'] )
                                       && false !== isset( $args['pre_footer_image']['mobile_image']['url'] )
                                       && false === empty( $args['pre_footer_image']['mobile_image']['url'] );
?>
<?php if ( true === $show_general_settings_pre_footer_image ): ?>
    <section>
        <img class="d-desktop background-image-section" src="<?php echo esc_url( $args['pre_footer_image']['desktop_image']['url'] ) ?>" alt="<?php echo esc_html( $args['pre_footer_image']['desktop_image']['alt'] ) ?>">
        <img class="d-flex-mobile background-image-section-mobile" src="<?php echo esc_url( $args['pre_footer_image']['mobile_image']['url'] ) ?>" alt="<?php echo esc_html( $args['pre_footer_image']['mobile_image']['alt'] ) ?>">
    </section>
<?php endif; ?>