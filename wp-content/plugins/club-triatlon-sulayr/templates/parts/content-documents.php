<?php
/**
 * Contenido del endpoint Documentos de Mi cuenta.
 * Requiere:
 *   $personal (array) documentos dirigidos al usuario,
 *   $groups   (array) de [ 'title' => string, 'documents' => array ] con los generales,
 *             por categoría; un único grupo con título vacío si ninguno tiene categoría,
 *   $total    (int)   número total de documentos.
 * Cada documento: id, title, description, type_label, ext, size, date, date_is_update, url, category.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sulayr_render_document = function ( array $doc ) {
    $date = '' !== $doc['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $doc['date'] ) ) : '';
    $date_text = '';
    if ( '' !== $date ) {
        $date_text = $doc['date_is_update']
            /* translators: %s: fecha */
            ? sprintf( __( 'Actualizado el %s', SULAYR_TEXTDOMAIN ), $date )
            /* translators: %s: fecha */
            : sprintf( __( 'Publicado el %s', SULAYR_TEXTDOMAIN ), $date );
    }
    $size = size_format( $doc['size'], $doc['size'] < KB_IN_BYTES ? 0 : 1 );
    $meta = array_filter( [ $doc['type_label'], $size, $date_text ] );
    ?>
    <li class="sulayr-document">
        <span class="sulayr-document-type sulayr-document-type--<?php echo esc_attr( $doc['ext'] ); ?>" aria-hidden="true"><?php echo esc_html( $doc['type_label'] ); ?></span>
        <div class="sulayr-document-body">
            <h4 class="sulayr-document-title"><?php echo esc_html( $doc['title'] ); ?></h4>
            <?php if ( '' !== $doc['description'] ) : ?>
                <p class="sulayr-document-description"><?php echo esc_html( $doc['description'] ); ?></p>
            <?php endif; ?>
            <p class="sulayr-document-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
        </div>
        <a class="sulayr-document-action button" href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener">
            <?php esc_html_e( 'Abrir', SULAYR_TEXTDOMAIN ); ?>
            <span class="screen-reader-text">
                <?php
                /* translators: %s: título del documento */
                echo esc_html( sprintf( __( '%s (se abre en una pestaña nueva)', SULAYR_TEXTDOMAIN ), $doc['title'] ) );
                ?>
            </span>
        </a>
    </li>
    <?php
};
?>
<div class="sulayr-documents">
    <?php if ( 0 === $total ) : ?>
        <p class="sulayr-documents-empty"><?php esc_html_e( 'Todavía no hay documentos disponibles.', SULAYR_TEXTDOMAIN ); ?></p>
    <?php else : ?>
        <?php if ( ! empty( $personal ) ) : ?>
            <section class="sulayr-documents-group sulayr-documents-group--personal" aria-labelledby="sulayr-documents-personal">
                <h3 class="sulayr-documents-group-title" id="sulayr-documents-personal"><?php esc_html_e( 'Para ti', SULAYR_TEXTDOMAIN ); ?></h3>
                <ul class="sulayr-documents-list">
                    <?php foreach ( $personal as $doc ) : ?>
                        <?php $sulayr_render_document( $doc ); ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php foreach ( $groups as $index => $group ) : ?>
            <?php
            $has_title = '' !== $group['title'];
            // Grupo sin título (ningún general tiene categoría): lista plana, salvo que
            // haya «Para ti»; entonces se titula «Documentos del club» para separarlos.
            $heading_id = 'sulayr-documents-group-' . (int) $index;
            ?>
            <section class="sulayr-documents-group"<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
                <?php if ( $has_title ) : ?>
                    <h3 class="sulayr-documents-group-title" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $group['title'] ); ?></h3>
                <?php elseif ( ! empty( $personal ) ) : ?>
                    <h3 class="sulayr-documents-group-title"><?php esc_html_e( 'Documentos del club', SULAYR_TEXTDOMAIN ); ?></h3>
                <?php endif; ?>
                <ul class="sulayr-documents-list">
                    <?php foreach ( $group['documents'] as $doc ) : ?>
                        <?php $sulayr_render_document( $doc ); ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
