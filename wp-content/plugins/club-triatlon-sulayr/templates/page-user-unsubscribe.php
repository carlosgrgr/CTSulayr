<?php
/**
 * Template Name: Unsubscribe
 * Description: Página pública para que los deportistas puedan darse de baja.
 */

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) );
    exit;
}

$current_user = wp_get_current_user();
$message = '';

if ( isset($_POST['sulayr_delete_user']) && check_admin_referer('sulayr_delete_user_action', 'sulayr_delete_user_nonce') ) {
    require_once(ABSPATH.'wp-admin/includes/user.php');
    
    do_action( 'sulayr_before_athlete_unsubscription', $current_user );

    $user_id   = $current_user->ID;
    wp_delete_user( $user_id );
    
    $message = '<div class="sulayr-baja-completa">Tu cuenta ha sido eliminada. </div>';
}

get_header();
?>

<main class="user-profile-page">
    
    <?php include SULAYR_TEMPLATE_DIR . '/parts/content-user-menu.php'; ?>
    
    <h1><?php _e( 'Darse de baja', SULAYR_TEXTDOMAIN ); ?></h1>
    
    <?php if( $message ): ?>
        <?php echo $message; ?>
    <?php else: ?>
        <form method="post">
            <p>¿Estás seguro de que te quieres dar de baja del club? Esto <strong>borrará tu usuario y todos sus datos</strong> de forma permanente.</p>
            <?php wp_nonce_field('sulayr_delete_user_action', 'sulayr_delete_user_nonce'); ?>
            <button type="submit" name="sulayr_delete_user" class="sulayr-danger-btn">Sí, borrar mi cuenta</button>
        </form>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
