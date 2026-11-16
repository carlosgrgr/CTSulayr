<?php
/**
 * Ranking: aviso (sin acceso, nada que mostrar, no encontrado).
 *
 * Se puede sobrescribir copiándola en el tema: sulayr/ranking/notice.php.
 *
 * @var string $text  Texto.
 * @var string $url   Enlace opcional.
 * @var string $label Texto del enlace.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sulayr-ranking-notice">
    <p><?php echo esc_html( $text ); ?></p>
    <?php if ( '' !== $url ) : ?>
        <p><a class="sulayr-ranking-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></p>
    <?php endif; ?>
</div>
