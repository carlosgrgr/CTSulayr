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
        <span class="sulayr-athletes-list-item">
            <?php echo esc_html( ( $index + 1 ) . '. ' . $athlete['name'] . ' ' . $athlete['email'] ); ?>
        </span>
    <?php endforeach; ?>
</div>
<?php if ( $group_key !== 'other' ) : ?>
    <br />
<?php endif; ?>

