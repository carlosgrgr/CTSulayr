<?php
/**
 * Ranking: clasificación de la temporada.
 *
 * Se puede sobrescribir copiándola en el tema: sulayr/ranking/standings.php.
 *
 * @var array  $filters      Filtros (ver filters.php).
 * @var string $title        Clasificación que se ve.
 * @var int    $season       Año.
 * @var int    $best_n       Pruebas que cuentan.
 * @var array  $rows         rank, name, url, photo, initials, group, finished, counted, results, total, is_me.
 * @var array  $competitions name, date, type, url.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$with_group = 'absoluta' === $filters['view'];
?>
<header class="sulayr-ranking-header">
    <p class="sulayr-ranking-eyebrow"><?php echo esc_html( sprintf( __( 'Temporada %d', SULAYR_TEXTDOMAIN ), $season ) ); ?></p>
    <h2 class="sulayr-ranking-title"><?php echo esc_html( $title ); ?></h2>
    <?php /* translators: %d: pruebas que cuentan. */ ?>
    <p class="sulayr-ranking-note"><?php echo esc_html( sprintf( __( 'Cuentan las %d mejores pruebas de cada socio.', SULAYR_TEXTDOMAIN ), $best_n ) ); ?></p>
</header>

<?php sulayr_load_template( 'ranking/filters', [ 'filters' => $filters ] ); ?>

<?php if ( empty( $rows ) ) : ?>
    <p class="sulayr-ranking-empty"><?php esc_html_e( 'Todavía no hay nadie en esta clasificación.', SULAYR_TEXTDOMAIN ); ?></p>
<?php else : ?>
    <div class="sulayr-ranking-scroll">
    <table class="sulayr-ranking-table">
        <caption class="screen-reader-text"><?php echo esc_html( $title ); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Puesto', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Deportista', SULAYR_TEXTDOMAIN ); ?></th>
                <?php if ( $with_group ) : ?>
                    <th scope="col"><?php esc_html_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?></th>
                <?php endif; ?>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Terminadas', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Puntos', SULAYR_TEXTDOMAIN ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) : ?>
                <tr class="<?php echo esc_attr( trim( ( $row['is_me'] ? 'is-me ' : '' ) . ( $row['rank'] <= 3 ? 'is-podium' : '' ) ) ); ?>">
                    <td class="sulayr-ranking-rank" data-label="<?php esc_attr_e( 'Puesto', SULAYR_TEXTDOMAIN ); ?>"><?php echo (int) $row['rank']; ?></td>
                    <td class="sulayr-ranking-name" data-label="<?php esc_attr_e( 'Deportista', SULAYR_TEXTDOMAIN ); ?>">
                        <a href="<?php echo esc_url( $row['url'] ); ?>">
                            <?php sulayr_load_template( 'ranking/parts/avatar', [ 'photo' => $row['photo'], 'initials' => $row['initials'], 'name' => $row['name'], 'size' => 'sm' ] ); ?>
                            <span><?php echo esc_html( $row['name'] ); ?></span>
                        </a>
                        <?php if ( $row['is_me'] ) : ?>
                            <span class="sulayr-ranking-me"><?php esc_html_e( 'Tú', SULAYR_TEXTDOMAIN ); ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ( $with_group ) : ?>
                        <td data-label="<?php esc_attr_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $row['group'] ); ?></td>
                    <?php endif; ?>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Terminadas', SULAYR_TEXTDOMAIN ); ?>"><?php echo (int) $row['finished']; ?></td>
                    <td class="sulayr-num sulayr-ranking-total" data-label="<?php esc_attr_e( 'Puntos', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $row['total'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php if ( ! empty( $competitions ) ) : ?>
    <section class="sulayr-ranking-competitions">
        <h3 class="sulayr-ranking-subtitle"><?php esc_html_e( 'Competiciones de la temporada', SULAYR_TEXTDOMAIN ); ?></h3>
        <ul>
            <?php foreach ( $competitions as $c ) : ?>
                <li>
                    <span class="sulayr-ranking-date"><?php echo esc_html( $c['date'] ); ?></span>
                    <a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['name'] ); ?></a>
                    <span class="sulayr-ranking-type"><?php echo esc_html( $c['type'] ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
