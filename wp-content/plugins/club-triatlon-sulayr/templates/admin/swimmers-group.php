<?php
/**
 * Template: Grupo de nadadores (partial)
 *
 * @var array $swimmers Lista de nadadores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $swimmers ) ) {
    return;
}

$count = count( $swimmers );
?>
<div class="sulayr-athletes-list">
    <?php foreach ( $swimmers as $index => $swimmer ) : ?>
        <span class="sulayr-athletes-list-item">
            <?php 
            $name = esc_html( $swimmer['name'] ?? '' );
            $dni = esc_html( $swimmer['dni'] ?? '-' );
            $email = esc_html( $swimmer['email'] ?? '-' );
            
            echo esc_html( ( $index + 1 ) . '. ' . $name . ' ' . $dni . ' ' . $email );
            ?>
        </span>
    <?php endforeach; ?>
</div>

