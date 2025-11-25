<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Infrastructure\CustomTemplates;

if ( ! defined( 'ABSPATH' ) ) exit;

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Obtener datos del usuario
$user_name = $current_user->display_name ?: $current_user->user_login;
$user_first_name = get_user_meta( $current_user_id, 'first_name', true );
$user_last_name = get_user_meta( $current_user_id, 'last_name', true );
$user_full_name = trim( $user_first_name . ' ' . $user_last_name ) ?: $user_name;
$user_email = $current_user->user_email;

// Obtener campos ACF usando get_field() para asegurar compatibilidad
// Si get_field() no funciona, intentamos con get_user_meta() como fallback
$user_profile_photo = get_field( CustomFields::USER_PROFILE_PHOTO, 'user_' . $current_user_id );
if ( ! $user_profile_photo ) {
    $user_profile_photo = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_PHOTO, true );
}

$user_profile_phone = get_field( CustomFields::USER_PROFILE_PHONE, 'user_' . $current_user_id );
if ( $user_profile_phone === false || $user_profile_phone === null ) {
    $user_profile_phone = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_PHONE, true );
}

$user_profile_dni = get_field( CustomFields::USER_PROFILE_DNI, 'user_' . $current_user_id );
if ( $user_profile_dni === false || $user_profile_dni === null ) {
    $user_profile_dni = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_DNI, true );
}

$user_profile_birth = get_field( CustomFields::USER_PROFILE_BIRTH, 'user_' . $current_user_id );
if ( $user_profile_birth === false || $user_profile_birth === null ) {
    $user_profile_birth = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_BIRTH, true );
}

$user_profile_plan = get_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, 'user_' . $current_user_id );
if ( $user_profile_plan === false || $user_profile_plan === null || $user_profile_plan === '' ) {
    $user_profile_plan = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, true );
}

$user_profile_swimming = get_field( CustomFields::USER_PROFILE_SWIMMING, 'user_' . $current_user_id );
if ( $user_profile_swimming === false || $user_profile_swimming === null || $user_profile_swimming === '' ) {
    $user_profile_swimming = get_user_meta( $current_user_id, CustomFields::USER_PROFILE_SWIMMING, true );
}

// Debug temporal - descomentar para ver los valores obtenidos
echo '<!-- DEBUG: Plan = ' . var_export( $user_profile_plan, true ) . ' -->';
echo '<!-- DEBUG: Swimming = ' . var_export( $user_profile_swimming, true ) . ' -->';

// Procesar foto de perfil
if ( $user_profile_photo ) {
    if ( is_numeric( $user_profile_photo ) ) {
        $user_profile_photo_url = wp_get_attachment_image_url( $user_profile_photo, 'medium' );
    } else {
        $user_profile_photo_url = $user_profile_photo;
    }
} else {
    $user_profile_photo_url = SULAYR_PUBLIC_ASSETS . '/images/default-user.png';
}

// Formatear fecha de nacimiento
$user_profile_birth_formatted = '';
if ( $user_profile_birth ) {
    $birth_date = \DateTime::createFromFormat( 'Y-m-d', $user_profile_birth );
    if ( $birth_date ) {
        $user_profile_birth_formatted = $birth_date->format( 'd/m/Y' );
    } else {
        $user_profile_birth_formatted = $user_profile_birth;
    }
}

// Formatear tarifa
$user_profile_plan_label = '-';
if ( ! empty( $user_profile_plan ) || $user_profile_plan === '0' || $user_profile_plan === 0 ) {
    // Normalizar el valor (puede venir como string o número)
    $plan_value = is_string( $user_profile_plan ) ? strtolower( trim( $user_profile_plan ) ) : (string) $user_profile_plan;
    
    // Comparaciones más flexibles
    if ( $plan_value === 'basic' || $plan_value === 'básica' || stripos( $plan_value, 'basic' ) !== false ) {
        $user_profile_plan_label = __( 'Básica', SULAYR_TEXTDOMAIN );
    } elseif ( $plan_value === 'premium' || $plan_value === 'entrenamiento' || stripos( $plan_value, 'premium' ) !== false || stripos( $plan_value, 'entrenamiento' ) !== false ) {
        $user_profile_plan_label = __( 'Entrenamiento', SULAYR_TEXTDOMAIN );
    } else {
        // Si no coincide, mostrar el valor original (solo si no está vacío)
        $user_profile_plan_label = ! empty( $user_profile_plan ) ? esc_html( $user_profile_plan ) : '-';
    }
}

// Formatear suplemento natación
$user_profile_swimming_label = '-';
// Aceptar cualquier valor, incluyendo '0' que es válido
if ( $user_profile_swimming !== false && $user_profile_swimming !== null ) {
    // Normalizar el valor (puede venir como '1', '0', true, false, 1, 0)
    $swimming_value = is_string( $user_profile_swimming ) ? trim( $user_profile_swimming ) : $user_profile_swimming;
    
    // Convertir a string para comparaciones más flexibles
    $swimming_str = (string) $swimming_value;
    
    if ( $swimming_value === '1' || $swimming_value === 1 || $swimming_value === true || 
         $swimming_str === '1' || strtolower( $swimming_str ) === 'yes' || strtolower( $swimming_str ) === 'sí' || 
         strtolower( $swimming_str ) === 'si' || $swimming_value === 'true' ) {
        $user_profile_swimming_label = __( 'Sí', SULAYR_TEXTDOMAIN );
    } elseif ( $swimming_value === '0' || $swimming_value === 0 || $swimming_value === false || 
               $swimming_str === '0' || strtolower( $swimming_str ) === 'no' || $swimming_value === 'false' || 
               $swimming_value === '' ) {
        $user_profile_swimming_label = __( 'No', SULAYR_TEXTDOMAIN );
    } else {
        // Si no coincide, mostrar el valor original o "No" por defecto
        $user_profile_swimming_label = ! empty( $swimming_value ) ? esc_html( $swimming_value ) : __( 'No', SULAYR_TEXTDOMAIN );
    }
}
?>

<div class="container">
    <div class="main-body">
        
        <?php include SULAYR_TEMPLATE_DIR . '/parts/content-user-menu.php'; ?>
        

        <div class="row gutters-sm">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center text-center">
                        <img src="<?php echo esc_url( $user_profile_photo_url ); ?>"
                            alt="<?php echo esc_attr( $user_name ) ?? __( 'Foto de perfil', SULAYR_TEXTDOMAIN ); ?>"
                            class="img-profile-circle"
                            width="150" height="150">
                            <div class="mt-3">
                                <h4><?php echo $user_name; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><?php _e( 'Información personal', SULAYR_TEXTDOMAIN ); ?></h5>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Nombre completo', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_full_name ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Email', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_email ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_profile_phone ?: '-' ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'DNI', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_profile_dni ?: '-' ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_profile_birth_formatted ?: '-' ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_profile_plan_label ); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0"><?php _e( 'Suplemento natación', SULAYR_TEXTDOMAIN ); ?></h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                <?php echo esc_html( $user_profile_swimming_label ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de edición con ACF -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><?php _e( 'Editar información', SULAYR_TEXTDOMAIN ); ?></h5>
                        <?php
                        if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
                            echo '<div class="alert alert-success mb-3">' . esc_html__( 'Perfil actualizado correctamente.', SULAYR_TEXTDOMAIN ) . '</div>';
                        }
                        acf_form( [
                            'id'              => 'acf-user-profile-form',
                            'post_id'         => 'user_' . $current_user_id,
                            'field_groups'    => [ 'group_' . md5( CustomFields::USER_PROFILE_FIELDS_GROUP ) ],
                            'uploader'        => 'basic',
                            'form_attributes' => [ 'enctype' => 'multipart/form-data' ],
                            'submit_value'    => __( 'Guardar cambios', SULAYR_TEXTDOMAIN ),
                            'return'          => add_query_arg( 'updated', 'true', get_permalink() ),
                        ] );
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
