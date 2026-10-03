<?php
/**
 * Template: Vista de importación de CSV
 *
 * @var array|null $import_result Resultado de la importación
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Importar deportistas desde CSV', SULAYR_TEXTDOMAIN ); ?></h1>
    
    <div class="sulayr-import-container">
        <?php if ( $import_result ) : ?>
            <div class="notice notice-<?php echo $import_result['errors'] > 0 ? 'warning' : 'success'; ?> is-dismissible">
                <h2><?php esc_html_e( 'Resultado de la importación', SULAYR_TEXTDOMAIN ); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e( 'Importados correctamente:', SULAYR_TEXTDOMAIN ); ?></strong> <?php echo esc_html( $import_result['success'] ); ?></li>
                    <li><strong><?php esc_html_e( 'Omitidos:', SULAYR_TEXTDOMAIN ); ?></strong> <?php echo esc_html( $import_result['skipped'] ); ?></li>
                    <li><strong><?php esc_html_e( 'Errores:', SULAYR_TEXTDOMAIN ); ?></strong> <?php echo esc_html( $import_result['errors'] ); ?></li>
                </ul>
                
                <?php if ( ! empty( $import_result['messages'] ) ) : ?>
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: bold;"><?php esc_html_e( 'Ver detalles', SULAYR_TEXTDOMAIN ); ?></summary>
                        <ul style="margin-top: 10px; max-height: 400px; overflow-y: auto;">
                            <?php foreach ( $import_result['messages'] as $message ) : ?>
                                <li><?php echo esc_html( $message ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <h2><?php esc_html_e( 'Formato del archivo CSV', SULAYR_TEXTDOMAIN ); ?></h2>
                <p><?php esc_html_e( 'El archivo CSV debe tener las siguientes columnas (separadas por punto y coma ;):', SULAYR_TEXTDOMAIN ); ?></p>
                <ul>
                    <li><strong><?php esc_html_e( 'Nombre', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'Nombre del deportista', SULAYR_TEXTDOMAIN ); ?></li>
                    <li><strong><?php esc_html_e( 'Apellidos', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'Apellidos del deportista', SULAYR_TEXTDOMAIN ); ?></li>
                    <li><strong><?php esc_html_e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'Teléfono (opcional)', SULAYR_TEXTDOMAIN ); ?></li>
                    <li><strong><?php esc_html_e( 'Email', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'Email del deportista (requerido, debe ser único)', SULAYR_TEXTDOMAIN ); ?></li>
                    <li><strong><?php esc_html_e( 'DNI', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'DNI del deportista (opcional)', SULAYR_TEXTDOMAIN ); ?></li>
                    <li><strong><?php esc_html_e( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ); ?></strong> - <?php esc_html_e( 'Fecha en formato DD/MM/YYYY (opcional)', SULAYR_TEXTDOMAIN ); ?></li>
                </ul>
                
                <p><strong><?php esc_html_e( 'Nota:', SULAYR_TEXTDOMAIN ); ?></strong> <?php esc_html_e( 'Los usuarios con el mismo email serán omitidos. Se generará automáticamente un nombre de usuario y una contraseña aleatoria para cada nuevo usuario.', SULAYR_TEXTDOMAIN ); ?></p>
                
                <hr>
                
                <h2><?php esc_html_e( 'Subir archivo CSV', SULAYR_TEXTDOMAIN ); ?></h2>
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field( 'sulayr_import_csv' ); ?>
                    <input type="hidden" name="sulayr_import_csv" value="1">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file"><?php esc_html_e( 'Archivo CSV', SULAYR_TEXTDOMAIN ); ?></label>
                            </th>
                            <td>
                                <input type="file" 
                                       name="csv_file" 
                                       id="csv_file" 
                                       accept=".csv" 
                                       required>
                                <p class="description">
                                    <?php esc_html_e( 'Selecciona el archivo CSV con los datos de los deportistas.', SULAYR_TEXTDOMAIN ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="submit" 
                               id="submit" 
                               class="button button-primary" 
                               value="<?php esc_attr_e( 'Importar deportistas', SULAYR_TEXTDOMAIN ); ?>">
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

