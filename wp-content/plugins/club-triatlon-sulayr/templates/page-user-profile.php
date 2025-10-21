<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) exit;

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$post_id = 'user_' . $current_user_id;

$foto_perfil_id = get_user_meta($current_user_id, 'sulayr_user_photo', true);

if ($foto_perfil_id) {
    $foto_perfil_url = wp_get_attachment_url($foto_perfil_id);
} else {
    $foto_perfil_url = SULAYR_PUBLIC_ASSETS . '/images/default-user.png';
}

// TODO: Add constant from CustomFields for all form fields
// TODO: CSS for the profile page
?>

<div class="user-profile-page">
    <h1>Mi Perfil</h1>

    <div class="perfil-foto">
        <img src="<?php echo esc_url($foto_perfil_url); ?>" alt="Foto de perfil" width="120" height="120">
    </div>

    <?php
        acf_form([
            'id'              => 'acf-user-profile-form',
            'post_id'         => 'user_' . $current_user_id,
            'field_groups'    => ['group_' . md5(CustomFields::USER_PROFILE_FIELDS_GROUP)],
            'uploader'        => 'basic',
            'form_attributes' => ['enctype' => 'multipart/form-data'],
            'submit_value'    => __('Guardar cambios', 'club-triatlon-sulayr'),
        ]);
    ?>
</div>

<?php get_footer(); ?>
