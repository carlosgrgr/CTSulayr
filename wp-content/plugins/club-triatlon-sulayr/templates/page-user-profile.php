<?php
/**
 * Template Name: Perfil de deportista
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 * Con WooCommerce activo, los athletes son redirigidos a Mi cuenta; esta página queda para compatibilidad.
 */

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
use ClubTriatlonSulayr\Services\UserService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$current_user    = wp_get_current_user();
$user_service    = new UserService();
$user_data       = $user_service->get_athlete_formatted_data( $current_user_id );

$user_name                   = $current_user->display_name ?: $current_user->user_login;
$user_full_name              = $user_data['name'] ?: $user_name;
$user_email                  = $current_user->user_email;
$user_profile_photo_url      = $user_data['photo_url'];
$user_profile_phone          = $user_data['phone'];
$user_profile_dni            = $user_data['dni'];
$user_profile_birth_formatted = $user_data['birth_date_formatted'] ?: '-';
$user_profile_plan_label     = $user_data['plan'] ?: '-';
$user_profile_plan_value     = $user_data['plan_value'] ?: '';
$user_profile_training_type  = $user_data['training_type'] ?: '';
$user_profile_swimming_label = $user_data['swimming_label'] ?: '-';
$user_profile_join_date_formatted = $user_data['join_date_formatted'] ?: '-';
$show_training_type          = $user_profile_plan_value === 'premium';

$active_tab = 'info';
if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'edit' ) {
    $active_tab = 'edit';
} elseif ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
    $active_tab = 'edit';
}

$return_url = add_query_arg( [ 'updated' => 'true', 'tab' => 'edit' ], get_permalink() );
?>

<div class="container">
    <div class="main-body">
        <?php include SULAYR_TEMPLATE_DIR . '/parts/content-user-menu.php'; ?>
        <?php include SULAYR_TEMPLATE_DIR . '/parts/content-athlete-profile.php'; ?>
    </div>
</div>

<?php get_footer(); ?>
