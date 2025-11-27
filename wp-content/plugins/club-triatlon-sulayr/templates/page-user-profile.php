<?php
/**
 * Template Name: Mi Perfil
 * Description: Página pública para que los deportistas editen su perfil con ACF.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
use ClubTriatlonSulayr\Services\UserService;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) exit;

acf_form_head();

if ( ! is_user_logged_in() ) {
    wp_redirect( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) );
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Obtener datos formateados del usuario usando helpers
$user_service = new UserService();
$user_data = $user_service->get_athlete_formatted_data( $current_user_id );

// Variables para compatibilidad con el template
$user_name = $current_user->display_name ?: $current_user->user_login;
$user_full_name = $user_data['name'] ?: $user_name;
$user_email = $current_user->user_email;
$user_profile_photo_url = $user_data['photo_url'];
$user_profile_phone = $user_data['phone'];
$user_profile_dni = $user_data['dni'];
$user_profile_birth_formatted = $user_data['birth_date_formatted'] ?: '-';
$user_profile_plan_label = $user_data['plan'] ?: '-';
$user_profile_plan_value = $user_data['plan_value'] ?: '';
$user_profile_training_type = $user_data['training_type'] ?: '';
$user_profile_swimming_label = $user_data['swimming_label'] ?: '-';
$user_profile_join_date_formatted = $user_data['join_date_formatted'] ?: '-';

$show_training_type = $user_profile_plan_value === 'premium';

// Determinar qué pestaña mostrar por defecto (si viene ?tab=edit o #edit, mostrar edición)
$active_tab = 'info';
if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'edit' ) {
    $active_tab = 'edit';
} elseif ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
    $active_tab = 'edit'; // Si se acaba de actualizar, mostrar la pestaña de edición
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
                <!-- Pestañas de navegación -->
                <ul class="nav nav-tabs mb-3" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'info' ? 'active' : ''; ?>" 
                                id="info-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#info" 
                                type="button" 
                                role="tab" 
                                aria-controls="info" 
                                aria-selected="<?php echo $active_tab === 'info' ? 'true' : 'false'; ?>">
                            <i class="bi bi-person-circle me-2"></i>
                            <?php _e( 'Información personal', SULAYR_TEXTDOMAIN ); ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'edit' ? 'active' : ''; ?>" 
                                id="edit-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#edit" 
                                type="button" 
                                role="tab" 
                                aria-controls="edit" 
                                aria-selected="<?php echo $active_tab === 'edit' ? 'true' : 'false'; ?>">
                            <i class="bi bi-pencil-square me-2"></i>
                            <?php _e( 'Editar información', SULAYR_TEXTDOMAIN ); ?>
                        </button>
                    </li>
                </ul>

                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="profileTabsContent">
                    <!-- Pestaña: Información personal -->
                    <div class="tab-pane fade <?php echo $active_tab === 'info' ? 'show active' : ''; ?>" 
                         id="info" 
                         role="tabpanel" 
                         aria-labelledby="info-tab">
                        <div class="card">
                            <div class="card-body">
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
                                        <h6 class="mb-0"><?php _e( 'Fecha de alta en el club', SULAYR_TEXTDOMAIN ); ?></h6>
                                    </div>
                                    <div class="col-sm-9 text-secondary">
                                        <?php echo esc_html( $user_profile_join_date_formatted ?: '-' ); ?>
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
                                <?php if ( $show_training_type ) : ?>
                                    <hr>
                                    <div class="row">
                                        <div class="col-sm-3">
                                            <h6 class="mb-0"><?php _e( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ); ?></h6>
                                        </div>
                                        <div class="col-sm-9 text-secondary">
                                            <?php echo esc_html( $user_profile_training_type ?: '-' ); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                    </div>

                    <!-- Pestaña: Editar información -->
                    <div class="tab-pane fade <?php echo $active_tab === 'edit' ? 'show active' : ''; ?>" 
                         id="edit" 
                         role="tabpanel" 
                         aria-labelledby="edit-tab">
                        <div class="card">
                            <div class="card-body">
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
                                    'return'          => add_query_arg( [ 'updated' => 'true', 'tab' => 'edit' ], get_permalink() ),
                                ] );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
