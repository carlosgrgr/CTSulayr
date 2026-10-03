<?php
/**
 * Template: Fila de tabla de nadadores (partial)
 *
 * @var array $swimmer Datos del nadador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<tr>
    <td><?php echo esc_html( $swimmer['ID'] ?? '' ); ?></td>
    <td>
        <strong><?php echo esc_html( $swimmer['name'] ?? '' ); ?></strong>
    </td>
    <td><?php echo esc_html( $swimmer['email'] ?? '' ); ?></td>
    <td><?php echo esc_html( ! empty( $swimmer['phone'] ) ? $swimmer['phone'] : '-' ); ?></td>
    <td><?php echo esc_html( ! empty( $swimmer['dni'] ) ? $swimmer['dni'] : '-' ); ?></td>
    <td><?php echo esc_html( ! empty( $swimmer['plan'] ) ? $swimmer['plan'] : '-' ); ?></td>
    <td><?php echo esc_html( ! empty( $swimmer['training_type'] ) ? $swimmer['training_type'] : '-' ); ?></td>
</tr>

