<?php
/**
 * Template Name: Inicio de sesión
 * Description: Página personalizada de login para usuarios del club.
 */

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    if ( in_array( 'athlete', (array) $user->roles, true ) && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
        wp_redirect( wc_get_page_permalink( 'myaccount' ) );
    } else {
        wp_redirect( site_url( CustomTemplates::TEMPLATE_MY_PROFILE['url'] ) );
    }
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sulayr_user_login'])) {
    check_admin_referer('sulayr_login_nonce');
    
    $user = sanitize_user($_POST['user_login']);
    $pass = $_POST['user_pass'];
    
    $creds = [
        'user_login'    => $user,
        'user_password' => $pass,
        'remember'      => !empty($_POST['rememberme']),
    ];
    $signon = wp_signon( $creds, false );

    if ( is_wp_error( $signon ) ) {
        $login_error = $signon->get_error_message();
    } else {
        if ( in_array( 'athlete', (array) $signon->roles, true ) && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
            wp_redirect( wc_get_page_permalink( 'myaccount' ) );
        } else {
            wp_redirect( site_url( CustomTemplates::TEMPLATE_MY_PROFILE['url'] ) );
        }
        exit;
    }
}

get_header();
?>

<div class="user-login-page">
    <h1><?php _e( 'Iniciar sesión', SULAYR_TEXTDOMAIN ); ?></h1>
    <?php if ( $login_error ) : ?>
        <div class="login-error"><?php echo esc_html( $login_error ); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'sulayr_login_nonce' ); ?>
        <p>
            <label for="sulayr_user_login"><?php _e( 'Nombre de usuario o Email', SULAYR_TEXTDOMAIN ); ?></label>
            <input type="text" name="user_login" id="sulayr_user_login" required>
        </p>
        <p>
            <label for="sulayr_user_pass"><?php _e( 'Contraseña', SULAYR_TEXTDOMAIN ); ?></label>
            <input type="password" name="user_pass" id="sulayr_user_pass" required>
        </p>
        <p>
            <label>
                <input type="checkbox" name="rememberme"> <?php _e( 'Recuérdame', SULAYR_TEXTDOMAIN ); ?>
            </label>
        </p>
        <p>
            <button type="submit"><?php _e( 'Iniciar sesión', SULAYR_TEXTDOMAIN ); ?></button>
            <input type="hidden" name="sulayr_user_login" value="1">
        </p>
    </form>
    <p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php _e( '¿Has olvidado tu contraseña?', SULAYR_TEXTDOMAIN ); ?></a></p>
</div>

<?php get_footer(); ?>
