<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
?>

<div class="user-profile-page">
    <h1>Mi Perfil</h1>

    <?php
        acf_form([
            'id'              => 'acf-user-profile-form',
            'post_id'         => 'user_' . $current_user_id,
            'field_groups'    => ['group_' . md5('user_profile_fields')],
            'uploader'        => 'basic',
            'form_attributes' => ['enctype' => 'multipart/form-data'],
            'submit_value'    => __('Guardar cambios', 'club-triatlon-sulayr'),
        ]);
    ?>
</div>

<?php get_footer(); ?>
