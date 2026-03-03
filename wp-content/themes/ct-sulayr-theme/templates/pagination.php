<?php

$args = [
    'mid_size' => 2,
    'prev_text' => __( '<', 'ct_sulayr_theme' ),
    'next_text' => __( '>', 'ct_sulayr_theme' ),
];

the_posts_pagination( $args );
