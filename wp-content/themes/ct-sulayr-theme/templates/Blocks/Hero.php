<?php
/**
 * Hero Block Template.
 * Usa el repeater "Slider" (hero_slider): imagen, título, descripción y botón por slide.
 */

use CTSulayrTheme\Infrastructure\CustomFields;
use CTSulayrTheme\Infrastructure\CustomBlocks;

$block_anchor = $block['anchor'] ?? '';
$hero_display_block = get_field( CustomFields::HERO_DISPLAY ) ?: 'yes';
$is_block_inactive = 'no' === $hero_display_block && ! is_admin();

if ( $is_block_inactive ) {
	return;
}

$block_inactive_class = 'no' === $hero_display_block ? 'block-inactive' : '';
$has_slides = have_rows( CustomFields::HERO_SLIDER );

if ( $has_slides ) : ?>
<div class="swiper-container swiper-slider <?php echo esc_attr( $block_inactive_class ); ?>" <?php if ( $block_anchor ) : ?>id="<?php echo esc_attr( $block_anchor ); ?>"<?php endif; ?> data-loop="false" data-autoplay="5500" data-dragable="false">
	<div class="swiper-wrapper text-center">
		<?php
		while ( have_rows( CustomFields::HERO_SLIDER ) ) {
			the_row();
			$slide_image   = get_sub_field( CustomFields::HERO_SLIDER_IMAGE );
			$slide_title   = get_sub_field( CustomFields::HERO_SLIDER_TITLE ) ?: '';
			$slide_desc    = get_sub_field( CustomFields::HERO_SLIDER_DESCRIPTION ) ?: '';
			$slide_button  = get_sub_field( CustomFields::HERO_SLIDER_BUTTON );
			$slide_bg = is_array( $slide_image ) && ! empty( $slide_image['url'] ) ? $slide_image['url'] : '';
			?>
		<div class="swiper-slide" <?php if ( $slide_bg ) : ?>data-slide-bg="<?php echo esc_url( $slide_bg ); ?>" data-preview-bg="<?php echo esc_url( $slide_bg ); ?>"<?php endif; ?>>
			<div class="swiper-caption swiper-parallax" data-speed="0.5" data-fade="true">
				<div class="swiper-slide-caption">
					<div class="container">
						<div class="row justify-content-xl-center">
							<div class="col-xl-12">
								<?php if ( $slide_title ) : ?>
									<div class="text-extra-big font-weight-bold font-italic text-uppercase" data-caption-animate="fadeInUp" data-caption-delay="300"><?php echo esc_html( $slide_title ); ?></div>
								<?php endif; ?>
							</div>
							<div class="col-xl-8 offset-top-10">
								<?php if ( $slide_desc ) : ?>
									<h5 class="hidden d-sm-block text-light" data-caption-animate="fadeInUp" data-caption-delay="500"><?php echo esc_html( $slide_desc ); ?></h5>
								<?php endif; ?>
								<?php
								if ( is_array( $slide_button ) && ! empty( $slide_button['url'] ) ) :
									$btn_url    = $slide_button['url'];
									$btn_title  = $slide_button['title'] ?: $slide_button['url'];
									$btn_target = ! empty( $slide_button['target'] ) ? $slide_button['target'] : '_self';
									?>
									<div class="offset-top-20 offset-sm-top-50">
										<a class="btn btn-danger btn-anis-effect" href="<?php echo esc_url( $btn_url ); ?>" title="<?php echo esc_attr( $btn_title ); ?>" target="<?php echo esc_attr( $btn_target ); ?>" data-caption-animate="fadeInUp" data-caption-delay="800">
											<span class="btn-text"><?php echo esc_html( $btn_title ); ?></span>
										</a>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>
	<div class="swiper-button swiper-button-prev swiper-parallax mdi mdi-chevron-left"></div>
	<div class="swiper-button swiper-button-next swiper-parallax mdi mdi-chevron-right"></div>
	<div class="swiper-pagination"></div>
</div>
<?php else : ?>
	<div class="hero-block-placeholder"><?php echo esc_html( sprintf( __( 'Añade al menos una fila en el campo «Slider» del bloque %s.', CT_SULAYR_THEME_TEXTDOMAIN ), CustomBlocks::HERO_BLOCK_NAME ) ); ?></div>
<?php endif; ?>
