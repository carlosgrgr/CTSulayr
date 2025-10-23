<?php
/**
 * Template Name: Inicio de sesión
 * Description: Página personalizada de login para usuarios del club.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( is_user_logged_in() ) {
    // Redirecciona al perfil si el usuario ya ha iniciado sesión
    wp_redirect( site_url('/mi-perfil') );
    exit;
}

// Procesamiento del formulario de login
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
    $signon = wp_signon($creds, false);

    if ( is_wp_error($signon) ) {
        $login_error = $signon->get_error_message();
    } else {
        wp_redirect(site_url('/mi-perfil'));
        exit;
    }
}

get_header();
?>

<div class="user-login-page">
    <h1><?php _e( 'Login', SULAYR_TEXTDOMAIN ); ?></h1>
    <?php if ( $login_error ) : ?>
        <div class="login-error"><?php echo esc_html( $login_error ); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'sulayr_login_nonce' ); ?>
        <p>
            <label for="sulayr_user_login"><?php _e( 'Username or Email', SULAYR_TEXTDOMAIN ); ?></label>
            <input type="text" name="user_login" id="sulayr_user_login" required>
        </p>
        <p>
            <label for="sulayr_user_pass"><?php _e( 'Password', SULAYR_TEXTDOMAIN ); ?></label>
            <input type="password" name="user_pass" id="sulayr_user_pass" required>
        </p>
        <p>
            <label>
                <input type="checkbox" name="rememberme"> <?php _e( 'Remember me', SULAYR_TEXTDOMAIN ); ?>
            </label>
        </p>
        <p>
            <button type="submit"><?php _e( 'Login', SULAYR_TEXTDOMAIN ); ?></button>
            <input type="hidden" name="sulayr_user_login" value="1">
        </p>
    </form>
    <p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php _e( 'Forgot your password?', SULAYR_TEXTDOMAIN ); ?></a></p>
</div>

<?php get_footer(); ?>
