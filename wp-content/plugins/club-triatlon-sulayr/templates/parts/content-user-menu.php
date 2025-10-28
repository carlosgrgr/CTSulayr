<?php

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
/**
 * Template part user profile menu
 */

 $my_profile = CustomTemplates::TEMPLATE_MY_PROFILE;
 $unsusbscribe = CustomTemplates::TEMPLATE_UNSUBSCRIBE;
?>

<nav class="user-profile-menu">
    <ul>
        <li>
            <a href="<?php echo esc_url( site_url( $my_profile['url'] ) ); ?>" class="<?php echo is_page( 'mi-perfil' ) ? 'active' : ''; ?>">
                <?php echo $my_profile['name']; ?>
            </a>
        </li>
        <!-- <li><a href="<?php echo esc_url( site_url( '/mis-pagos' ) ); ?>"><?php _e('Mis pagos', 'club-triatlon-sulayr'); ?></a></li> -->
        <li>
            <a href="<?php echo esc_url( site_url( $unsusbscribe['url'] ) ); ?>" class="<?php echo is_page( 'darse-de-baja' ) ? 'active' : ''; ?>">
                <?php echo $unsusbscribe['name']; ?>
            </a>
        </li>
    </ul>
</nav>