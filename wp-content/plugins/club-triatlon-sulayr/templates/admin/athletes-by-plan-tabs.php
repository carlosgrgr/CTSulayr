<?php
/**
 * Template: Selector mes actual / mes siguiente del listado por tarifa
 *
 * @var bool   $next        True si se ve el mes siguiente
 * @var string $current_url URL del mes actual
 * @var string $next_url    URL del mes siguiente
 * @var string $date        Y-m-d del día 1 del mes siguiente ('' en el mes actual)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<ul class="subsubsub sulayr-athletes-month-tabs">
    <li><a href="<?php echo esc_url( $current_url ); ?>"<?php echo $next ? '' : ' class="current" aria-current="page"'; ?>><?php esc_html_e( 'Mes actual', SULAYR_TEXTDOMAIN ); ?></a> |</li>
    <li><a href="<?php echo esc_url( $next_url ); ?>"<?php echo $next ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Mes siguiente', SULAYR_TEXTDOMAIN ); ?></a></li>
</ul>
<br class="clear" />
<?php if ( $next ) : ?>
    <p class="description">
        <?php echo esc_html( sprintf( __( 'Previsión a %s: servicios de cada deportista con los cambios, bajas y congelaciones aprobados que todavía no se han aplicado. Los deportistas con algún cambio van marcados.', SULAYR_TEXTDOMAIN ), \ClubTriatlonSulayr\Modules\Members\MemberDates::show( $date ) ) ); ?>
    </p>
<?php endif; ?>
