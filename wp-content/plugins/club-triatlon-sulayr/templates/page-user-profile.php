<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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
    // Configuración del formulario ACF
    acf_form([
        'id'                   => 'acf-user-profile-form',
        'post_id'              => 'user_' . $current_user_id, // indicamos que son campos de usuario
        'field_groups'         => ['group_' . md5( 'user_profile_fields' )], // el mismo ID que definiste en UserFieldsGroup
        'submit_value'         => __('Guardar cambios', 'club-triatlon-sulayr'),
        'updated_message'      => __('Perfil actualizado correctamente.', 'club-triatlon-sulayr'),
        'return'               => add_query_arg('updated', 'true', get_permalink()),
        'uploader'             => 'wp', // para usar la librería de medios
    ]);
    ?>
</div>

<?php
get_footer();
