<?php
/**
 * Ranking: ficha del deportista.
 *
 * Se puede sobrescribir copiándola en el tema: sulayr/ranking/athlete.php.
 *
 * @var string $back_url Volver a la clasificación.
 * @var int    $season   Año.
 * @var array  $seasons  Año => URL de las temporadas con resultados.
 * @var int    $best_n   Pruebas que cuentan.
 * @var array  $athlete  name, photo, initials, sex, group, absolute_rank, absolute_total, group_rank, group_total, counted, is_me.
 * @var array  $results  Resultados de la temporada.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<p><a class="sulayr-ranking-link" href="<?php echo esc_url( $back_url ); ?>">← <?php esc_html_e( 'Volver a la clasificación', SULAYR_TEXTDOMAIN ); ?></a></p>

<header class="sulayr-ranking-profile">
    <?php sulayr_load_template( 'ranking/parts/avatar', [ 'photo' => $athlete['photo'], 'initials' => $athlete['initials'], 'name' => $athlete['name'], 'size' => 'lg' ] ); ?>
    <div>
        <p class="sulayr-ranking-eyebrow"><?php echo esc_html( $athlete['sex'] . ' · ' . $athlete['group'] ); ?></p>
        <h2 class="sulayr-ranking-title"><?php echo esc_html( $athlete['name'] ); ?><?php if ( $athlete['is_me'] ) : ?> <span class="sulayr-ranking-me"><?php esc_html_e( 'Tú', SULAYR_TEXTDOMAIN ); ?></span><?php endif; ?></h2>
    </div>
</header>

<dl class="sulayr-ranking-stats">
    <div><dt><?php esc_html_e( 'Absoluta', SULAYR_TEXTDOMAIN ); ?></dt><dd><?php echo esc_html( $athlete['absolute_rank'] ); ?> <small><?php echo esc_html( $athlete['absolute_total'] ); ?></small></dd></div>
    <div><dt><?php esc_html_e( 'Grupo de edad', SULAYR_TEXTDOMAIN ); ?></dt><dd><?php echo esc_html( $athlete['group_rank'] ); ?> <small><?php echo esc_html( $athlete['group_total'] ); ?></small></dd></div>
    <?php /* translators: %d: pruebas que cuentan. */ ?>
    <div><dt><?php echo esc_html( sprintf( __( 'Cuentan (máx. %d)', SULAYR_TEXTDOMAIN ), $best_n ) ); ?></dt><dd><?php echo esc_html( $athlete['counted'] ); ?></dd></div>
</dl>

<?php if ( count( $seasons ) > 1 ) : ?>
    <nav class="sulayr-ranking-seasons" aria-label="<?php esc_attr_e( 'Temporadas', SULAYR_TEXTDOMAIN ); ?>">
        <?php foreach ( $seasons as $year => $url ) : ?>
            <a href="<?php echo esc_url( $url ); ?>"<?php echo (int) $year === (int) $season ? ' aria-current="page"' : ''; ?>><?php echo (int) $year; ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ( empty( $results ) ) : ?>
    <p class="sulayr-ranking-empty"><?php echo esc_html( sprintf( __( 'Sin resultados en %d.', SULAYR_TEXTDOMAIN ), $season ) ); ?></p>
<?php else : ?>
    <div class="sulayr-ranking-scroll">
    <table class="sulayr-ranking-table">
        <caption class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Resultados de %1$s en %2$d', SULAYR_TEXTDOMAIN ), $athlete['name'], $season ) ); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Fecha', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Competición', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Tiempo', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Pos', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Cat', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Pos Cat', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Absoluta', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $r ) : ?>
                <tr>
                    <td class="sulayr-ranking-date" data-label="<?php esc_attr_e( 'Fecha', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['date'] ); ?></td>
                    <td data-label="<?php esc_attr_e( 'Competición', SULAYR_TEXTDOMAIN ); ?>">
                        <a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['competition'] ); ?></a>
                        <span class="sulayr-ranking-type"><?php echo esc_html( $r['type'] ); ?></span>
                    </td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Tiempo', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['finished'] ? $r['time'] : $r['status'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Pos', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['position_sex'] ); ?></td>
                    <td data-label="<?php esc_attr_e( 'Cat', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['race_category'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Pos Cat', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['position_age_group'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Absoluta', SULAYR_TEXTDOMAIN ); ?>"><?php sulayr_load_template( 'ranking/parts/points', [ 'points' => $r['points_absolute'], 'breakdown' => $r['breakdown_absolute'], 'counts' => $r['counts_absolute'] ] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?>"><?php sulayr_load_template( 'ranking/parts/points', [ 'points' => $r['points_age_group'], 'breakdown' => $r['breakdown_age_group'], 'counts' => $r['counts_age_group'] ] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
