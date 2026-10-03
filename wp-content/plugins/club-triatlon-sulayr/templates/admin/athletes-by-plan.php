<?php
/**
 * Template: Listado de atletas por tarifa
 *
 * @var array  $grouped Atletas agrupados por categoría
 * @var string $current_month Nombre del mes actual
 * @var int    $total_count Total de atletas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card sulayr-athletes-list-card">
    <div class="card-body">
        <h2 class="sulayr-athletes-title">
            *<?php echo esc_html( sprintf( __( 'DEPORTISTAS %s', SULAYR_TEXTDOMAIN ), $current_month ) ); ?>* (<?php echo esc_html( $total_count ); ?>)
        </h2>
        
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
    </div>
</div>

