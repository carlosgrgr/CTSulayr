<?php
/**
 * Contenido del perfil del deportista (para usar dentro de Mi cuenta WC o página standalone).
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
    <div class="sulayr-profile-layout">
        <aside class="sulayr-profile-sidebar">
            <div class="sulayr-profile-card">
                <div class="sulayr-profile-avatar-wrap">
                    <img src="<?php echo esc_url( $user_profile_photo_url ); ?>"
                        alt="<?php echo esc_attr( $user_name ); ?>"
                        class="sulayr-profile-avatar"
                        width="140" height="140" loading="lazy">
                </div>
                <h2 class="sulayr-profile-name"><?php echo esc_html( $user_name ); ?></h2>
                <?php
                /**
                 * Enlace a reservas. Lo pinta sulayr-reservas si el deportista es elegible.
                 */
                do_action( 'sulayr_athlete_profile_card' );
                ?>
            </div>
        </aside>

        <div class="sulayr-profile-main">
            <div class="sulayr-profile-tabs" role="tablist" id="profileTabs">
                <button class="sulayr-profile-tab <?php echo $active_tab === 'info' ? 'active' : ''; ?>"
                    id="info-tab"
                    type="button"
                    role="tab"
                    aria-selected="<?php echo $active_tab === 'info' ? 'true' : 'false'; ?>"
                    aria-controls="info"
                    data-bs-toggle="tab"
                    data-bs-target="#info"
                    aria-expanded="<?php echo $active_tab === 'info' ? 'true' : 'false'; ?>">
                    <span class="sulayr-profile-tab-icon" aria-hidden="true"><i class="bi bi-person-circle"></i></span>
                    <span><?php _e( 'Información personal', SULAYR_TEXTDOMAIN ); ?></span>
                </button>
                <button class="sulayr-profile-tab <?php echo $active_tab === 'edit' ? 'active' : ''; ?>"
                    id="edit-tab"
                    type="button"
                    role="tab"
                    aria-selected="<?php echo $active_tab === 'edit' ? 'true' : 'false'; ?>"
                    aria-controls="edit"
                    data-bs-toggle="tab"
                    data-bs-target="#edit"
                    aria-expanded="<?php echo $active_tab === 'edit' ? 'true' : 'false'; ?>">
                    <span class="sulayr-profile-tab-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
                    <span><?php _e( 'Editar información', SULAYR_TEXTDOMAIN ); ?></span>
                </button>
            </div>

            <div class="sulayr-profile-panels tab-content">
                <section class="sulayr-profile-panel tab-pane fade <?php echo $active_tab === 'info' ? 'show active' : ''; ?>"
                    id="info"
                    role="tabpanel"
                    aria-labelledby="info-tab">
                    <div class="sulayr-info-block">
                        <h3 class="sulayr-info-block-title"><?php _e( 'Datos personales', SULAYR_TEXTDOMAIN ); ?></h3>
                        <dl class="sulayr-info-list">
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Nombre completo', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_full_name ); ?></dd>
                            </div>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'DNI', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_dni ?: '—' ); ?></dd>
                            </div>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_birth_formatted ?: '—' ); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="sulayr-info-block">
                        <h3 class="sulayr-info-block-title"><?php _e( 'Contacto', SULAYR_TEXTDOMAIN ); ?></h3>
                        <dl class="sulayr-info-list">
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Email', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><a href="mailto:<?php echo esc_attr( $user_email ); ?>"><?php echo esc_html( $user_email ); ?></a></dd>
                            </div>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_phone ?: '—' ); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="sulayr-info-block">
                        <h3 class="sulayr-info-block-title"><?php _e( 'Datos del club', SULAYR_TEXTDOMAIN ); ?></h3>
                        <dl class="sulayr-info-list">
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Fecha de alta', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_join_date_formatted ?: '—' ); ?></dd>
                            </div>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_plan_label ); ?></dd>
                            </div>
                            <?php if ( $show_training_type ) : ?>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_training_type ?: '—' ); ?></dd>
                            </div>
                            <?php endif; ?>
                            <div class="sulayr-info-row">
                                <dt><?php _e( 'Suplemento natación', SULAYR_TEXTDOMAIN ); ?></dt>
                                <dd><?php echo esc_html( $user_profile_swimming_label ); ?></dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="sulayr-profile-panel tab-pane fade <?php echo $active_tab === 'edit' ? 'show active' : ''; ?>"
                    id="edit"
                    role="tabpanel"
                    aria-labelledby="edit-tab">
                    <div class="sulayr-edit-block">
                        <?php
                        if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
                            echo '<div class="sulayr-alert sulayr-alert-success" role="status">' . esc_html__( 'Perfil actualizado correctamente.', SULAYR_TEXTDOMAIN ) . '</div>';
                        }
                        if ( function_exists( 'acf_form' ) && isset( $return_url ) ) {
                            acf_form( [
                                'id'              => 'acf-user-profile-form',
                                'post_id'         => 'user_' . $current_user_id,
                                'field_groups'    => [ 'group_' . md5( \ClubTriatlonSulayr\Infrastructure\CustomFields::USER_PROFILE_FIELDS_GROUP ) ],
                                'uploader'        => 'basic',
                                'form_attributes' => [ 'enctype' => 'multipart/form-data', 'class' => 'sulayr-profile-form' ],
                                'submit_value'    => __( 'Guardar cambios', SULAYR_TEXTDOMAIN ),
                                'return'          => $return_url,
                            ] );
                        }

                        /**
                         * Bloques extra de la edición (consentimiento de imagen, spec altas D10).
                         */
                        do_action( 'sulayr_athlete_profile_edit_after', $current_user_id );
                        ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
