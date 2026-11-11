<?php
/**
 * Template: Listado de atletas por estado
 *
 * @var array  $grouped Atletas agrupados por estado
 * @var int    $total_count Total de atletas
 */

use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card sulayr-athletes-list-card">
    <div class="card-body">
        <h2 class="sulayr-athletes-title">
            <?php echo esc_html__( 'DEPORTISTAS POR ESTADO', SULAYR_TEXTDOMAIN ); ?> (<?php echo esc_html( $total_count ); ?>)
        </h2>
        
        <?php
        // Renderizar cada grupo usando el partial
        if ( ! empty( $grouped['active'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'active',
                'athletes' => $grouped['active'],
                'title' => __( 'Activos', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['congelado'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'congelado',
                'athletes' => $grouped['congelado'],
                'title' => __( 'Congelados', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['baja'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'baja',
                'athletes' => $grouped['baja'],
                'title' => __( 'Baja', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        
        if ( ! empty( $grouped['pendiente'] ) ) {
            sulayr_load_template( 'admin/athletes-group', [
                'group_key' => 'pendiente',
                'athletes' => $grouped['pendiente'],
                'title' => __( 'Pendientes', SULAYR_TEXTDOMAIN ),
                'bold' => true,
            ] );
        }
        ?>
    </div>
</div>
