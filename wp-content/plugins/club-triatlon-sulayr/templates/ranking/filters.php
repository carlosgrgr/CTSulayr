<?php
/**
 * Ranking: filtros de la clasificación (formulario GET; funciona sin JavaScript).
 *
 * @var array $filters action, hidden, season, seasons, view, views, sex, sexes, group, groups.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$select = function ( string $name, string $label, array $options, $current ) {
    printf( '<div class="sulayr-ranking-filter"><label for="sulayr-ranking-%1$s">%2$s</label><select id="sulayr-ranking-%1$s" name="%1$s" data-sulayr-autosubmit>', esc_attr( $name ), esc_html( $label ) );
    foreach ( $options as $value => $text ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( (string) $value ), selected( (string) $current, (string) $value, false ), esc_html( $text ) );
    }
    echo '</select></div>';
};
?>
<form class="sulayr-ranking-filters" method="get" action="<?php echo esc_url( $filters['action'] ); ?>">
    <?php foreach ( $filters['hidden'] as $name => $value ) : ?>
        <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
    <?php endforeach; ?>
    <?php
    $select( 'temporada', __( 'Temporada', SULAYR_TEXTDOMAIN ), $filters['seasons'], $filters['season'] );
    $select( 'vista', __( 'Clasificación', SULAYR_TEXTDOMAIN ), $filters['views'], $filters['view'] );
    $select( 'sexo', __( 'Sexo', SULAYR_TEXTDOMAIN ), $filters['sexes'], $filters['sex'] );
    if ( 'grupo' === $filters['view'] ) {
        $select( 'grupo', __( 'Grupo de edad', SULAYR_TEXTDOMAIN ), $filters['groups'], $filters['group'] );
    }
    ?>
    <div class="sulayr-ranking-filter sulayr-ranking-filter-submit">
        <button type="submit" class="sulayr-ranking-button"><?php esc_html_e( 'Ver', SULAYR_TEXTDOMAIN ); ?></button>
    </div>
</form>
<script>
( function () {
    var form = document.currentScript && document.currentScript.previousElementSibling;
    if ( ! form ) { return; }
    form.querySelectorAll( '[data-sulayr-autosubmit]' ).forEach( function ( el ) {
        el.addEventListener( 'change', function () { form.submit(); } );
    } );
    var submit = form.querySelector( '.sulayr-ranking-filter-submit' );
    if ( submit ) { submit.hidden = true; }
}() );
</script>
