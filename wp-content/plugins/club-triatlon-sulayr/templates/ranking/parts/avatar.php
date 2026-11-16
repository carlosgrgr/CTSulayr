<?php
/**
 * Ranking: foto del socio o sus iniciales.
 *
 * @var string $photo    URL de la foto ('' si no tiene).
 * @var string $initials Iniciales.
 * @var string $name     Nombre (texto alternativo).
 * @var string $size     sm o lg.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$size = isset( $size ) ? $size : 'sm';
?>
<?php if ( '' !== $photo ) : ?>
    <img class="sulayr-ranking-avatar sulayr-ranking-avatar-<?php echo esc_attr( $size ); ?>" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
<?php else : ?>
    <span class="sulayr-ranking-avatar sulayr-ranking-avatar-<?php echo esc_attr( $size ); ?>" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
<?php endif; ?>
