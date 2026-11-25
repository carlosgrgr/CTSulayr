<?php
/**
 * Template: Grupo de atletas (partial)
 *
 * @var string $group_key Clave del grupo
 * @var array  $athletes  Lista de atletas
 * @var string $title     Título del grupo
 * @var bool   $bold      Si el título debe estar en negrita
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $athletes ) ) {
    return;
}

$count = count( $athletes );
$title_prefix = $bold ? '*' : '';
$title_suffix = $bold ? '*' : '';
$css_class = $group_key === 'other' ? 'sulayr-athletes-group-title sulayr-athletes-group-title-other' : 'sulayr-athletes-group-title';
?>
<h4 class="<?php echo esc_attr( $css_class ); ?>">
    <?php echo esc_html( $title_prefix . $title . $title_suffix ); ?> (<?php echo esc_html( $count ); ?>)
</h4>
<div class="sulayr-athletes-list">
    <?php foreach ( $athletes as $index => $athlete ) : ?>
        <?php $changes = $athlete['changes'] ?? []; ?>
        <span class="sulayr-athletes-list-item<?php echo empty( $changes ) ? '' : ' sulayr-athletes-list-item-changed'; ?>">
            <?php echo esc_html( ( $index + 1 ) . '. ' . $athlete['name'] . ' ' . $athlete['email'] ); ?>
            <?php if ( ! empty( $changes ) ) : ?>
                <span class="sulayr-athlete-change-badge"><?php esc_html_e( 'Cambio', SULAYR_TEXTDOMAIN ); ?></span>
                <span class="sulayr-athlete-change">
                    <?php
                    $text = implode( ' · ', $changes );
                    if ( ( $athlete['previous_plan'] ?? '' ) !== ( $athlete['plan'] ?? '' ) ) {
                        $text .= ' · ' . sprintf( __( 'antes: %s', SULAYR_TEXTDOMAIN ), '' !== $athlete['previous_plan'] ? $athlete['previous_plan'] : '—' );
                    }
                    echo esc_html( $text );
                    ?>
                </span>
            <?php endif; ?>
        </span>
    <?php endforeach; ?>
</div>
<?php if ( $group_key !== 'other' ) : ?>
    <br />
<?php endif; ?>

