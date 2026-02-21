<?php
/**
 * Contenido del perfil del deportista (para usar dentro de Mi cuenta WC o página Mi perfil).
 * Requiere variables: $current_user_id, $current_user, $user_name, $user_full_name, $user_email,
 * $user_profile_photo_url, $user_profile_phone, $user_profile_dni, $user_profile_birth_formatted,
 * $user_profile_plan_label, $user_profile_plan_value, $user_profile_training_type, $user_profile_swimming_label,
 * $user_profile_join_date_formatted, $show_training_type, $active_tab, $return_url.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sulayr-athlete-profile-content">
    <div class="row gutters-sm">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column align-items-center text-center">
                        <img src="<?php echo esc_url( $user_profile_photo_url ); ?>"
                            alt="<?php echo esc_attr( $user_name ); ?>"
                            class="img-profile-circle"
                            width="150" height="150">
                        <div class="mt-3">
                            <h4><?php echo esc_html( $user_name ); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
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

            <div class="tab-content" id="profileTabsContent">
                <div class="tab-pane fade <?php echo $active_tab === 'info' ? 'show active' : ''; ?>"
                     id="info"
                     role="tabpanel"
                     aria-labelledby="info-tab">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Nombre completo', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_full_name ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Email', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_email ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_phone ?: '-' ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'DNI', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_dni ?: '-' ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_birth_formatted ?: '-' ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Fecha de alta en el club', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_join_date_formatted ?: '-' ); ?></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_plan_label ); ?></div>
                            </div>
                            <?php if ( $show_training_type ) : ?>
                                <hr>
                                <div class="row">
                                    <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                    <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_training_type ?: '-' ); ?></div>
                                </div>
                            <?php endif; ?>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3"><h6 class="mb-0"><?php _e( 'Suplemento natación', SULAYR_TEXTDOMAIN ); ?></h6></div>
                                <div class="col-sm-9 text-secondary"><?php echo esc_html( $user_profile_swimming_label ); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

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
                            if ( function_exists( 'acf_form' ) && isset( $return_url ) ) {
                                acf_form( [
                                    'id'              => 'acf-user-profile-form',
                                    'post_id'         => 'user_' . $current_user_id,
                                    'field_groups'    => [ 'group_' . md5( \ClubTriatlonSulayr\Infrastructure\CustomFields::USER_PROFILE_FIELDS_GROUP ) ],
                                    'uploader'        => 'basic',
                                    'form_attributes' => [ 'enctype' => 'multipart/form-data' ],
                                    'submit_value'    => __( 'Guardar cambios', SULAYR_TEXTDOMAIN ),
                                    'return'          => $return_url,
                                ] );
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
