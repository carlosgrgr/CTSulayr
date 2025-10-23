<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) exit;

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( site_url( '/inicio-de-sesion' ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

$user_name = $current_user->display_name ?: $current_user->user_login;
$user_profile_photo = get_user_meta( $current_user_id, 'sulayr_user_photo', true );

if ( $user_profile_photo ) {
    $user_profile_photo_url = wp_get_attachment_url( $user_profile_photo );
} else {
    $user_profile_photo_url = SULAYR_PUBLIC_ASSETS . '/images/default-user.png';
}

// TODO: CSS for the profile page
?>

<main class="user-profile-page">
    <h1><?php _e( 'My profile', SULAYR_TEXTDOMAIN ); ?></h1>
    <div class="user-profile-photo">
        <img src="<?php echo esc_url( $user_profile_photo_url ); ?>" alt="<?php echo esc_attr( $user_name ) ?? __( 'Profile photo', SULAYR_TEXTDOMAIN ); ?>">
    </div>

    <?php
        acf_form( [
            'id'              => 'acf-user-profile-form',
            'post_id'         => 'user_' . $current_user_id,
            'field_groups'    => [ 'group_' . md5( CustomFields::USER_PROFILE_FIELDS_GROUP ) ],
            'uploader'        => 'basic',
            'form_attributes' => [ 'enctype' => 'multipart/form-data' ],
            'submit_value'    => __( 'Save changes', SULAYR_TEXTDOMAIN ),
        ] );
    ?>
    <div class="logout-button-wrapper">
        <a class="button logout-button" href="<?php echo esc_url( wp_logout_url( site_url('/inicio-de-sesion') ) ); ?>">
            <?php _e( 'Logout', SULAYR_TEXTDOMAIN ); ?>
        </a>
    </div>
    </p>
</main>

<?php get_footer(); ?>
