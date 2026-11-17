<?php
/**
 * Ranking: ficha de la competición.
 *
 * Se puede sobrescribir copiándola en el tema: sulayr/ranking/competition.php.
 *
 * @var string $back_url    Volver a la clasificación.
 * @var array  $competition name, date, type, interest, location, format, url.
 * @var array  $references  label, time, participants.
 * @var array  $sections    Categoría de equipo ('' en individual) => filas de socios.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<p><a class="sulayr-ranking-link" href="<?php echo esc_url( $back_url ); ?>">← <?php esc_html_e( 'Volver a la clasificación', SULAYR_TEXTDOMAIN ); ?></a></p>

<header class="sulayr-ranking-header">
    <p class="sulayr-ranking-eyebrow"><?php echo esc_html( $competition['date'] . ( '' !== $competition['location'] ? ' · ' . $competition['location'] : '' ) ); ?></p>
    <h2 class="sulayr-ranking-title"><?php echo esc_html( $competition['name'] ); ?></h2>
    <p class="sulayr-ranking-note"><?php echo esc_html( $competition['type'] . ' · ' . $competition['format'] ); ?></p>
    <?php if ( '' !== $competition['interest'] ) : ?>
        <?php /* translators: %s: «+100 · Circuito Granadino». */ ?>
        <p><span class="sulayr-ranking-interest"><?php echo esc_html( sprintf( __( 'Bonus por interés del club %s', SULAYR_TEXTDOMAIN ), $competition['interest'] ) ); ?></span></p>
    <?php endif; ?>
</header>

<?php if ( ! empty( $references ) ) : ?>
    <dl class="sulayr-ranking-stats">
        <?php foreach ( $references as $ref ) : ?>
            <div>
                <dt><?php echo esc_html( $ref['label'] ); ?></dt>
                <dd><?php echo esc_html( $ref['time'] ); ?><?php if ( '' !== $ref['participants'] ) : ?> <small><?php echo esc_html( sprintf( __( '%s finalizados', SULAYR_TEXTDOMAIN ), $ref['participants'] ) ); ?></small><?php endif; ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>

<?php if ( empty( $sections ) ) : ?>
    <p class="sulayr-ranking-empty"><?php esc_html_e( 'No hay resultados de socios en esta competición.', SULAYR_TEXTDOMAIN ); ?></p>
<?php endif; ?>

<?php foreach ( $sections as $section => $rows ) : ?>
    <?php if ( '' !== $section ) : ?>
        <h3 class="sulayr-ranking-subtitle"><?php echo esc_html( $section ); ?></h3>
    <?php endif; ?>
    <div class="sulayr-ranking-scroll">
    <table class="sulayr-ranking-table">
        <caption class="screen-reader-text"><?php echo esc_html( $competition['name'] . ( '' !== $section ? ' · ' . $section : '' ) ); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Deportista', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Cat', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col"><?php esc_html_e( 'Tiempo', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Pos', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Pos Cat', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Absoluta', SULAYR_TEXTDOMAIN ); ?></th>
                <th scope="col" class="sulayr-num"><?php esc_html_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $r ) : ?>
                <tr class="<?php echo $r['is_me'] ? 'is-me' : ''; ?>">
                    <td class="sulayr-ranking-name" data-label="<?php esc_attr_e( 'Deportista', SULAYR_TEXTDOMAIN ); ?>">
                        <a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['name'] ); ?></a>
                        <span class="sulayr-ranking-type"><?php echo esc_html( $r['sex'] . ' · ' . $r['group'] ); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Cat', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['race_category'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Tiempo', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['finished'] ? $r['time'] : $r['status'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Pos', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['position_sex'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Pos Cat', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['position_age_group'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Absoluta', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['points_absolute'] ); ?></td>
                    <td class="sulayr-num" data-label="<?php esc_attr_e( 'Grupo', SULAYR_TEXTDOMAIN ); ?>"><?php echo esc_html( $r['points_age_group'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endforeach; ?>
