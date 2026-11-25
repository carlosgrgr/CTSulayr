<?php
/**
 * Template: Listado de atletas por tarifa
 *
 * @var array  $grouped Atletas agrupados por categoría
 * @var string $current_month Nombre del mes actual
 * @var int    $total_count Total de atletas
 * @var bool   $next Previsión del mes siguiente
 * @var int    $changed_count Deportistas del listado con algún cambio programado
 * @var array  $leaving Deportistas activos hoy que causan baja o se congelan
 */

$next = $next ?? false;
$changed_count = $changed_count ?? 0;
$leaving = $leaving ?? [];

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card sulayr-athletes-list-card">
    <div class="card-body">
        <h2 class="sulayr-athletes-title">
            *<?php echo esc_html( sprintf( __( 'DEPORTISTAS %s', SULAYR_TEXTDOMAIN ), $current_month ) ); ?>* (<?php echo esc_html( $total_count ); ?>)
        </h2>
        <?php if ( $next ) : ?>
            <p class="sulayr-athletes-changes-note">
                <?php if ( $changed_count > 0 ) : ?>
                    <span class="sulayr-athlete-change-badge"><?php esc_html_e( 'Cambio', SULAYR_TEXTDOMAIN ); ?></span>
                    <?php echo esc_html( sprintf( _n( '%d deportista con cambios para este mes.', '%d deportistas con cambios para este mes.', $changed_count, SULAYR_TEXTDOMAIN ), $changed_count ) ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Ningún deportista del listado tiene cambios para este mes.', SULAYR_TEXTDOMAIN ); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        
        <?php
        // Renderizar cada grupo usando el partial
        if ( ! empty( $grouped['basic'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'basic',
                'athletes' => $grouped['basic'],
                'title' => __( 'Básica', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['training_sprint'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'training_sprint',
                'athletes' => $grouped['training_sprint'],
                'title' => __( 'Entrenamientos sprint', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['training_olimpic'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'training_olimpic',
                'athletes' => $grouped['training_olimpic'],
                'title' => __( 'Entrenamientos olímpico', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['other'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'other',
                'athletes' => $grouped['other'],
                'title' => __( 'Otros', SULAYR_TEXTDOMAIN ),
                'bold' => false,
            ] );
        }
        ?>

        <?php if ( ! empty( $leaving ) ) : ?>
            <h4 class="sulayr-athletes-group-title sulayr-athletes-group-title-other">
                <?php echo esc_html( sprintf( __( 'Salen del listado: bajas y congelaciones (%d)', SULAYR_TEXTDOMAIN ), count( $leaving ) ) ); ?>
            </h4>
            <div class="sulayr-athletes-list">
                <?php foreach ( $leaving as $index => $athlete ) : ?>
                    <span class="sulayr-athletes-list-item sulayr-athletes-list-item-changed">
                        <?php echo esc_html( ( $index + 1 ) . '. ' . $athlete['name'] . ' ' . $athlete['email'] ); ?>
                        <span class="sulayr-athlete-change"><?php echo esc_html( implode( ' · ', $athlete['changes'] ) ); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

