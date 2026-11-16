<?php
/**
 * Ranking: puntos con su desglose y la marca de «cuenta».
 *
 * @var string $points    Puntos ya formateados.
 * @var string $breakdown Desglose («1000 × 0,8333 × 1,10 + 50»).
 * @var bool   $counts    Cuenta para el total.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<span class="sulayr-ranking-points<?php echo $counts ? ' is-counted' : ''; ?>" title="<?php echo esc_attr( $breakdown ); ?>">
    <?php echo esc_html( $points ); ?>
    <?php if ( $counts ) : ?>
        <span class="sulayr-ranking-counts"><?php esc_html_e( 'cuenta', SULAYR_TEXTDOMAIN ); ?></span>
    <?php endif; ?>
    <small class="sulayr-ranking-breakdown"><?php echo esc_html( $breakdown ); ?></small>
</span>
