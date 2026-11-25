<?php
namespace ClubTriatlonSulayr\Services;

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Services\HelperService;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserService {

    public function __construct() {
        static $hooks_registered = false;
        if ( $hooks_registered ) {
            return;
        }
        $hooks_registered = true;

        // Hooks de usuario
        add_action('user_register', [$this, 'assign_athlete_role'], 10, 1);
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
        add_filter('acf/pre_save_post', [$this, 'save_user_data_security']);
        add_filter( 'show_admin_bar', [$this, 'hide_wpadminbar_to_athletes'] );
        add_filter('acf/prepare_field/name=sulayr_user_photo', [$this, 'render_user_photo_preview']);
        // Campo "Miembro directiva": solo visible para admin o para el usuario que lo tiene activo; solo editable por admin.
        add_filter('acf/prepare_field/name=' . CustomFields::USER_PROFILE_IS_DIRECTIVE, [$this, 'prepare_field_directive_visibility']);
        add_filter('acf/update_value/name=' . CustomFields::USER_PROFILE_IS_DIRECTIVE, [$this, 'preserve_directive_field_value'], 10, 3);

        if ( ! is_admin() ) {
            // D33 (spec de altas): en Mi cuenta el deportista solo cambia teléfono, foto, email
            // e imagen. Estado, DNI y nacimiento los cambia un gestor; servicios y baja, por solicitud.
            $locked_fields = [
                CustomFields::USER_PROFILE_MEMBERSHIP_PLAN,
                CustomFields::USER_PROFILE_TRAINING_TYPE,
                CustomFields::USER_PROFILE_SWIMMING,
                CustomFields::USER_PROFILE_ATHLETE_STATUS,
                CustomFields::USER_PROFILE_DNI,
                CustomFields::USER_PROFILE_BIRTH,
            ];
            foreach ( $locked_fields as $field_name ) {
                add_filter( 'acf/prepare_field/name=' . $field_name, [ $this, 'lock_public_plan_field' ] );
                add_action( 'acf/render_field/name=' . $field_name, [ $this, 'render_locked_plan_value' ], 20 );
                add_filter( 'acf/update_value/name=' . $field_name, [ $this, 'preserve_locked_field_value' ], 10, 3 );
            }
            add_action( 'acf/render_field/name=' . CustomFields::USER_PROFILE_SWIMMING, [ $this, 'render_plan_change_notice' ], 21 );
            add_filter('acf/load_field/name=' . CustomFields::USER_PROFILE_JOIN_DATE, [$this, 'lock_field']);
        }
        add_action( 'login_footer', [$this, 'reset_page_login_link'] );
        add_action( 'admin_init', [$this, 'redirect_athlete_to_profile'] );
        add_filter( 'login_display_language_dropdown', '__return_false' );

    }

    /**
     * Asignar rol "athlete" a nuevos usuarios
     *
     * @param int $user_id ID del usuario
     * @return void
     */
    public function assign_athlete_role(int $user_id): void {
        if ( ! $this->is_valid_user_id( $user_id ) ) {
            return;
        }

        $user = get_userdata($user_id);
        if ( ! $user ) {
            return;
        }

        if (empty($user->roles) || in_array('subscriber', $user->roles, true)) {
            $user->set_role('athlete');
        }
    }

    /**
     * Redirigir usuarios "athlete" a su perfil tras login
     *
     * @param string $redirect_to URL de redirección por defecto
     * @param string $requested_redirect_to URL de redirección solicitada
     * @param \WP_User|\WP_Error $user Usuario o error
     * @return string URL de redirección
     */
    public function redirect_after_login($redirect_to, $requested_redirect_to, $user) {
        if ( ! $user instanceof \WP_User ) {
            return $redirect_to;
        }

        if ( isset( $user->roles ) && in_array( 'athlete', $user->roles, true ) ) {
            if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
                return wc_get_page_permalink( 'myaccount' );
            }
            return home_url( CustomTemplates::TEMPLATE_MY_PROFILE['url'] );
        }
        return $redirect_to;
    }

    /**
     * Obtener perfil completo de un usuario (nativo + ACF)
     *
     * @param int $user_id ID del usuario
     * @return array Datos del perfil del usuario
     */
    public function get_user_profile(int $user_id): array {
        if ( ! $this->is_valid_user_id( $user_id ) ) {
            return [];
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        return [
            'ID'         => $user->ID,
            'username'   => $user->user_login,
            'email'      => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name'  => get_user_meta($user->ID, 'last_name', true),

            // Campos ACF - Usando HelperService para consistencia
            'phone'       => HelperService::get_user_field(CustomFields::USER_PROFILE_PHONE, $user->ID),
            'birth_date'  => HelperService::get_user_field(CustomFields::USER_PROFILE_BIRTH, $user->ID),
            'dni'         => HelperService::get_user_field(CustomFields::USER_PROFILE_DNI, $user->ID),
            'tarifa'      => HelperService::get_user_field(CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user->ID),
            'suplemento'  => (bool) HelperService::get_user_field(CustomFields::USER_PROFILE_SWIMMING, $user->ID),
            'foto'        => HelperService::get_user_field(CustomFields::USER_PROFILE_PHOTO, $user->ID), // ID de imagen
        ];
    }

    /**
     * Actualizar perfil de usuario con datos pasados
     *
     * @param int   $user_id ID del usuario
     * @param array $data    Array con los datos a actualizar
     * @return bool true si se actualizó correctamente, false en caso contrario
     */
    public function update_user_profile(int $user_id, array $data): bool {
        // Validar user_id
        if ( ! $this->is_valid_user_id( $user_id ) ) {
            return false;
        }

        $user = get_userdata($user_id);
        if ( ! $user ) {
            return false;
        }

        // Validar que el array de datos no esté vacío
        if ( empty( $data ) || ! is_array( $data ) ) {
            return false;
        }

        // Validar y actualizar campos nativos
        if ( isset( $data['email'] ) && ! empty( $data['email'] ) ) {
            if ( ! $this->is_valid_email( $data['email'] ) ) {
                return false;
            }
            $result = wp_update_user(['ID' => $user_id, 'user_email' => sanitize_email($data['email'])]);
            if ( is_wp_error( $result ) ) {
                return false;
            }
        }

        if ( isset( $data['first_name'] ) && ! empty( $data['first_name'] ) ) {
            if ( ! is_string( $data['first_name'] ) ) {
                return false;
            }
            update_user_meta($user_id, 'first_name', sanitize_text_field($data['first_name']));
        }

        if ( isset( $data['last_name'] ) && ! empty( $data['last_name'] ) ) {
            if ( ! is_string( $data['last_name'] ) ) {
                return false;
            }
            update_user_meta($user_id, 'last_name', sanitize_text_field($data['last_name']));
        }

        // Validar y actualizar campos ACF
        $acf_fields = [
            'phone' => 'validate_phone',
            'birth_date' => 'validate_birth_date',
            'dni' => 'validate_dni',
            'tarifa' => 'validate_membership_plan',
            'suplemento' => 'validate_swimming_supplement',
            'foto' => 'validate_photo',
        ];

        foreach ($acf_fields as $field => $validator_method) {
            if ( isset( $data[$field] ) ) {
                $validated_value = $this->$validator_method( $data[$field] );
                if ( $validated_value !== false ) {
                    update_field($field, $validated_value, 'user_' . $user_id);
                }
            }
        }

        return true;
    }

    /**
     * Validar seguridad al guardar datos de usuario
     *
     * @param int|string $post_id ID del post o usuario
     * @return int|string ID del post o usuario
     */
    public function save_user_data_security($post_id) {
        if ( ! is_string( $post_id ) && ! is_int( $post_id ) ) {
            return $post_id;
        }

        $post_id_str = (string) $post_id;
        if (strpos($post_id_str, 'user_') === 0) {
            $user_id = (int) str_replace('user_', '', $post_id_str);
            if ( ! $this->is_valid_user_id( $user_id ) ) {
                wp_die( esc_html__( 'ID de usuario no válido.', SULAYR_TEXTDOMAIN ) );
            }
            if ($user_id !== get_current_user_id()) {
                wp_die( esc_html__( 'No tienes permiso para editar este perfil.', SULAYR_TEXTDOMAIN ) );
            }
        }

        return $post_id;
    }

    /**
     * Renderizar vista previa de foto de usuario en campo ACF
     * 
     * Nota: ACF ya muestra automáticamente la vista previa de la imagen cuando hay un valor,
     * tanto con el uploader 'wp' como con 'basic'. Por lo tanto, no es necesario añadir
     * una vista previa manual, ya que causaría duplicación.
     * 
     * Este método se mantiene por si en el futuro se necesita añadir funcionalidad adicional
     * sin duplicar la imagen.
     *
     * @param array $field Campo ACF
     * @return array Campo ACF modificado
     */
    public function render_user_photo_preview($field)
    {
        if ( ! is_array( $field ) ) {
            return $field;
        }

        // ACF ya muestra automáticamente la imagen cuando hay un valor guardado,
        // por lo que no necesitamos añadir una vista previa manual.
        // Esto evita que la imagen aparezca duplicada.

        return $field;
    }

    /**
     * Tarifa, tipo de entrenamiento y suplemento de natación no se editan en la ficha pública.
     *
     * @param array $field Campo ACF
     * @return array Campo ACF modificado
     */
    public function lock_public_plan_field( $field ) {
        if ( ! is_array( $field ) ) {
            return $field;
        }

        $field['readonly'] = 1;
        $field['disabled'] = 1;

        if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
            $field['wrapper'] = [];
        }
        $class = isset( $field['wrapper']['class'] ) ? (string) $field['wrapper']['class'] : '';
        if ( false === strpos( $class, 'sulayr-field-locked' ) ) {
            $field['wrapper']['class'] = trim( $class . ' sulayr-field-locked' );
        }

        return $field;
    }

    /**
     * Reenvía el valor guardado. El select queda deshabilitado y no viaja en el formulario.
     *
     * @param array $field Campo ACF ya preparado.
     * @return void
     */
    public function render_locked_plan_value( $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            return;
        }

        $value = $field['value'] ?? '';
        if ( is_array( $value ) ) {
            $value = reset( $value );
        }

        echo '<input type="hidden" name="' . esc_attr( (string) $field['name'] ) . '" value="' . esc_attr( (string) $value ) . '" />';
    }

    /**
     * Aviso bajo el suplemento de natación, el último de los campos de tarifa.
     *
     * @param array $field Campo ACF.
     * @return void
     */
    public function render_plan_change_notice( $field ) {
        unset( $field );

        echo '<p class="sulayr-plan-change-notice">' . esc_html__( 'Para cambiar la tarifa y el suplemento de natación hay que avisar al administrador antes de que empiece el mes en el que se quiere efectuar el cambio.', SULAYR_TEXTDOMAIN ) . '</p>';
    }

    /**
     * Bloquear campo a final de mes
     *
     * @param array $field Campo ACF
     * @return array Campo ACF modificado
     */
    public function lock_field($field) {
        if ( ! is_user_logged_in() ) {
            return $field;
        }

        $user = wp_get_current_user();
        if ( ! $user instanceof \WP_User ) {
            return $field;
        }

        if ( in_array( 'athlete', (array) $user->roles, true ) ) {
            $field['disabled'] = true;
            $field['readonly'] = 1;
        }

        return $field;
    }

    /**
     * Visibilidad del campo "Miembro de la directiva": solo para administradores o para el usuario que lo tiene activo.
     * Si no es admin, el campo se muestra en solo lectura.
     *
     * @param array $field Campo ACF.
     * @return array
     */
    public function prepare_field_directive_visibility( $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            return $field;
        }
        $profile_user_id = is_admin() && isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : get_current_user_id();
        if ( ! $profile_user_id ) {
            $field['type'] = 'hidden';
            return $field;
        }
        $is_directive = (bool) \ClubTriatlonSulayr\Services\HelperService::get_user_field( CustomFields::USER_PROFILE_IS_DIRECTIVE, $profile_user_id );
        $can_edit = current_user_can( 'manage_options' );
        $is_own_profile = (int) get_current_user_id() === (int) $profile_user_id;
        $show = $can_edit || ( $is_own_profile && $is_directive );
        if ( ! $show ) {
            $field['type'] = 'hidden';
            return $field;
        }
        if ( ! $can_edit ) {
            $field['readonly'] = 1;
            $field['disabled'] = true;
        }
        return $field;
    }

    /**
     * Solo los administradores pueden cambiar el valor de "Miembro de la directiva".
     *
     * @param mixed  $value   Valor que se intenta guardar.
     * @param string $post_id user_123.
     * @param array  $field   Campo ACF.
     * @return mixed
     */
    public function preserve_directive_field_value( $value, $post_id, $field ) {
        if ( current_user_can( 'manage_options' ) ) {
            return $value;
        }
        if ( ! is_string( $post_id ) || strpos( $post_id, 'user_' ) !== 0 ) {
            return $value;
        }
        $user_id = (int) str_replace( 'user_', '', $post_id );
        $current = \ClubTriatlonSulayr\Services\HelperService::get_user_field( CustomFields::USER_PROFILE_IS_DIRECTIVE, $user_id );
        return $current ? '1' : '0';
    }

    /**
     * Impide cambiar tarifa, tipo de entrenamiento y suplemento desde la ficha pública.
     *
     * @param mixed  $value   Nuevo valor que se intenta guardar
     * @param int|string $post_id ID del post o usuario
     * @param array  $field   Array del campo ACF
     * @return mixed Valor ya guardado
     */
    public function preserve_locked_field_value( $value, $post_id, $field ) {
        if ( ! is_string( $post_id ) || strpos( $post_id, 'user_' ) !== 0 ) {
            return $value;
        }
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            return $value;
        }

        $user_id = (int) str_replace( 'user_', '', $post_id );
        if ( $user_id < 1 ) {
            return $value;
        }

        $stored = get_user_meta( $user_id, $field['name'], true );
        if ( is_array( $stored ) ) {
            return $value;
        }

        return $stored;
    }

    /**
     * Ocultar barra de administración a usuarios con rol athlete
     *
     * @param bool $show Mostrar o no la barra
     * @return bool
     */
    public function hide_wpadminbar_to_athletes($show) {
        if ( ! is_bool( $show ) ) {
            return $show;
        }

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( $user instanceof \WP_User && in_array('athlete', (array) $user->roles, true) && false === MemberRoles::can_use_admin( $user ) ) {
                return false;
            }
        }
        return $show;
    }

    /**
     * Obtener datos formateados de un atleta
     *
     * @param int $user_id ID del usuario
     * @return array Datos formateados del atleta
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_athlete_formatted_data( int $user_id, string $date = '' ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        // Servicios, natación y estado salen del historial de socios (spec de altas, parte C).
        // Con $date, la previsión en esa fecha (cambios programados incluidos).
        $summary = '' !== $date
            ? \ClubTriatlonSulayr\Modules\Members\MemberSummary::projected( $user_id, $date )
            : \ClubTriatlonSulayr\Modules\Members\MemberSummary::for_user( $user_id );
        $phone = HelperService::get_user_field( CustomFields::USER_PROFILE_PHONE, $user_id );
        $dni = HelperService::get_user_field( CustomFields::USER_PROFILE_DNI, $user_id );
        $birth_date = HelperService::get_user_field( CustomFields::USER_PROFILE_BIRTH, $user_id );
        $join_date = HelperService::get_user_field( CustomFields::USER_PROFILE_JOIN_DATE, $user_id );
        $athlete_status = $summary['status'];
        $types = \ClubTriatlonSulayr\Modules\Members\MemberSettings::member_types();

        return [
            'ID' => $user_id,
            'name' => HelperService::get_user_full_name( $user_id ),
            'email' => $user->user_email,
            'phone' => $phone ?: '',
            'dni' => $dni ?: '',
            'birth_date' => $birth_date ?: '',
            'birth_date_formatted' => HelperService::format_birth_date( $birth_date ?: '' ),
            'join_date' => $join_date ?: '',
            'join_date_formatted' => HelperService::format_join_date( $join_date ?: '' ),
            'plan' => implode( ', ', $summary['services'] ),
            'plan_value' => $summary['has_training'] ? 'premium' : 'basic',
            'member_type' => $types[ $summary['type'] ] ?? $summary['type'],
            'training_type' => $summary['training_option_label'],
            'training_type_value' => $summary['training_option_key'],
            'swimming' => $summary['swimming'],
            'swimming_label' => $summary['swimming'] ? __( 'Sí', SULAYR_TEXTDOMAIN ) : __( 'No', SULAYR_TEXTDOMAIN ),
            'athlete_status' => HelperService::format_athlete_status( $athlete_status ),
            'athlete_status_value' => $athlete_status,
            'photo_url' => HelperService::get_user_photo_url( $user_id ),
            'changes' => $summary['changes'] ?? [],
            'previous_plan' => implode( ', ', $summary['previous_services'] ?? [] ),
        ];
    }

    /**
     * Obtener todos los deportistas ordenados por tarifa
     *
     * @param string $date Y-m-d: previsión en esa fecha ('' = hoy).
     * @return array Lista de deportistas con sus datos
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_athletes_by_plan( string $date = '' ): array {
        $athletes = get_users( [
            'role' => 'athlete',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ] );

        if ( empty( $athletes ) ) {
            return [];
        }

        $result = [];
        foreach ( $athletes as $athlete ) {
            $athlete_data = $this->get_athlete_formatted_data( $athlete->ID, $date );
            
            // Solo incluir si el estado es "Activo"
            if ( $athlete_data['athlete_status_value'] !== 'active' ) {
                continue;
            }
            
            $result[] = $athlete_data;
        }

        // Ordenar por tarifa (Básica primero, luego Entrenamiento, luego otros)
        usort( $result, function( $a, $b ) {
            $order = [ __( 'Básica', SULAYR_TEXTDOMAIN ), __( 'Entrenamiento', SULAYR_TEXTDOMAIN ) ];
            $pos_a = array_search( $a['plan'], $order );
            $pos_b = array_search( $b['plan'], $order );
            $pos_a = $pos_a === false ? 999 : $pos_a;
            $pos_b = $pos_b === false ? 999 : $pos_b;
            
            if ( $pos_a === $pos_b ) {
                // Si tienen la misma tarifa, ordenar por nombre
                return strcmp( $a['name'], $b['name'] );
            }
            
            return $pos_a <=> $pos_b;
        } );

        return $result;
    }

    /**
     * Deportistas que hoy están activos y que en $date dejan de estarlo por un
     * cambio programado (baja o congelación).
     *
     * @param string $date Y-m-d.
     * @return array Lista de deportistas con sus datos, por nombre.
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_athletes_leaving( string $date ): array {
        $result = [];
        foreach ( get_users( [ 'role' => 'athlete', 'orderby' => 'display_name', 'order' => 'ASC' ] ) as $athlete ) {
            $data = $this->get_athlete_formatted_data( $athlete->ID, $date );
            if ( 'active' === $data['athlete_status_value'] || empty( $data['changes'] ) ) {
                continue;
            }
            if ( 'active' !== \ClubTriatlonSulayr\Modules\Members\MemberSummary::for_user( $athlete->ID )['status'] ) {
                continue;
            }
            $result[] = $data;
        }

        usort( $result, function ( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        } );

        return $result;
    }

    /**
     * Obtener todos los nadadores (deportistas con suplemento de natación activo)
     *
     * @return array Lista de nadadores con sus datos
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_swimmers(): array {
        $athletes = get_users( [
            'role' => 'athlete',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ] );

        if ( empty( $athletes ) ) {
            return [];
        }

        $result = [];
        foreach ( $athletes as $athlete ) {
            $athlete_data = $this->get_athlete_formatted_data( $athlete->ID );
            
            // Solo incluir si el estado es "Activo"
            if ( $athlete_data['athlete_status_value'] !== 'active' ) {
                continue;
            }
            
            // Solo incluir si tiene suplemento activo
            if ( ! $athlete_data['swimming'] ) {
                continue;
            }

            $result[] = [
                'ID' => $athlete_data['ID'],
                'name' => $athlete_data['name'],
                'email' => $athlete_data['email'],
                'phone' => $athlete_data['phone'],
                'dni' => $athlete_data['dni'],
                'plan' => $athlete_data['plan'],
                'training_type' => $athlete_data['training_type'],
            ];
        }

        return $result;
    }

    /**
     * Obtener todos los deportistas agrupados por estado
     *
     * @return array Lista de deportistas agrupados por estado
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_athletes_by_status(): array {
        $athletes = get_users( [
            'role' => 'athlete',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ] );

        if ( empty( $athletes ) ) {
            return [];
        }

        $grouped = [
            'active' => [],
            'congelado' => [],
            'pendiente' => [],
            'baja' => [],
        ];

        foreach ( $athletes as $athlete ) {
            $athlete_data = $this->get_athlete_formatted_data( $athlete->ID );
            $status_value = $athlete_data['athlete_status_value'];
            
            // Agrupar por estado
            if ( isset( $grouped[ $status_value ] ) ) {
                $grouped[ $status_value ][] = $athlete_data;
            } else {
                // Si el estado no es reconocido, ponerlo en 'active' por defecto
                $grouped['active'][] = $athlete_data;
            }
        }

        // Ordenar alfabéticamente cada grupo por nombre
        foreach ( $grouped as $key => $group ) {
            usort( $group, function( $a, $b ) {
                return strcmp( $a['name'], $b['name'] );
            } );
            $grouped[ $key ] = $group;
        }

        return $grouped;
    }

    /**
     * Validar que un ID de usuario sea válido
     *
     * @param int $user_id ID del usuario
     * @return bool true si es válido, false en caso contrario
     * @access private
     */
    private function is_valid_user_id(int $user_id): bool {
        return $user_id > 0 && is_numeric( $user_id );
    }

    /**
     * Validar formato de email
     *
     * @param mixed $email Email a validar
     * @return bool true si es válido, false en caso contrario
     * @access private
     */
    private function is_valid_email($email): bool {
        if ( ! is_string( $email ) || empty( $email ) ) {
            return false;
        }
        return is_email( $email ) !== false;
    }

    /**
     * Validar y sanitizar teléfono
     *
     * @param mixed $phone Teléfono a validar
     * @return string|false Teléfono sanitizado o false si no es válido
     * @access private
     */
    private function validate_phone($phone) {
        if ( empty( $phone ) ) {
            return '';
        }
        if ( ! is_string( $phone ) && ! is_numeric( $phone ) ) {
            return false;
        }
        return sanitize_text_field( (string) $phone );
    }

    /**
     * Validar y sanitizar fecha de nacimiento
     *
     * @param mixed $birth_date Fecha a validar (formato Y-m-d)
     * @return string|false Fecha validada o false si no es válida
     * @access private
     */
    private function validate_birth_date($birth_date) {
        if ( empty( $birth_date ) ) {
            return '';
        }
        if ( ! is_string( $birth_date ) ) {
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $birth_date);
        if ( ! $date || $date->format('Y-m-d') !== $birth_date ) {
            return false;
        }

        // Validar que la fecha no sea futura
        $today = new \DateTime();
        if ( $date > $today ) {
            return false;
        }

        return $birth_date;
    }

    /**
     * Validar fecha de alta en el club
     *
     * @param mixed $join_date Fecha a validar (formato Y-m-d)
     * @return string|false Fecha validada o false si no es válida
     * @access private
     */
    private function validate_join_date($join_date) {
        if ( empty( $join_date ) ) {
            return '';
        }
        if ( ! is_string( $join_date ) ) {
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $join_date);
        if ( ! $date || $date->format('Y-m-d') !== $join_date ) {
            return false;
        }

        // Validar que la fecha no sea futura
        $today = new \DateTime();
        if ( $date > $today ) {
            return false;
        }

        return $join_date;
    }

    /**
     * Validar y sanitizar DNI
     *
     * @param mixed $dni DNI a validar
     * @return string|false DNI sanitizado o false si no es válido
     * @access private
     */
    private function validate_dni($dni) {
        if ( empty( $dni ) ) {
            return '';
        }
        if ( ! is_string( $dni ) ) {
            return false;
        }
        // Sanitizar y validar formato básico de DNI español (8-9 caracteres alfanuméricos)
        $dni_clean = strtoupper( trim( $dni ) );
        if ( ! preg_match( '/^[0-9]{8}[A-Z]$/', $dni_clean ) && ! preg_match( '/^[XYZ][0-9]{7}[A-Z]$/', $dni_clean ) ) {
            return false;
        }
        return sanitize_text_field( $dni_clean );
    }

    /**
     * Validar plan de membresía
     *
     * @param mixed $plan Plan a validar
     * @return string|false Plan validado o false si no es válido
     * @access private
     */
    private function validate_membership_plan($plan) {
        if ( empty( $plan ) && $plan !== '0' && $plan !== 0 ) {
            return '';
        }

        $valid_plans = ['basic', 'premium', 'básica', 'entrenamiento'];
        $plan_normalized = is_string( $plan ) ? strtolower( trim( $plan ) ) : (string) $plan;

        if ( in_array( $plan_normalized, $valid_plans, true ) ) {
            return $plan_normalized;
        }

        // Si no coincide exactamente, verificar con stripos para flexibilidad
        foreach ( $valid_plans as $valid_plan ) {
            if ( stripos( $plan_normalized, $valid_plan ) !== false ) {
                return $plan_normalized;
            }
        }

        return false;
    }

    /**
     * Validar suplemento de natación
     *
     * @param mixed $swimming Valor a validar
     * @return string|false Valor validado ('1' o '0') o false si no es válido
     * @access private
     */
    private function validate_swimming_supplement($swimming) {
        if ( $swimming === false || $swimming === null ) {
            return '0';
        }

        // Convertir a string y normalizar
        $swimming_str = is_string( $swimming ) ? strtolower( trim( $swimming ) ) : (string) $swimming;

        // Valores que indican "Sí"
        $yes_values = ['1', 1, true, 'yes', 'sí', 'si', 'true'];
        if ( in_array( $swimming, $yes_values, true ) || in_array( $swimming_str, $yes_values, true ) ) {
            return '1';
        }

        // Valores que indican "No"
        $no_values = ['0', 0, false, 'no', 'false'];
        if ( in_array( $swimming, $no_values, true ) || in_array( $swimming_str, $no_values, true ) ) {
            return '0';
        }

        return false;
    }

    /**
     * Validar foto (ID de attachment)
     *
     * @param mixed $photo ID de attachment o URL
     * @return int|string|false ID de attachment, URL o false si no es válido
     * @access private
     */
    private function validate_photo($photo) {
        if ( empty( $photo ) ) {
            return '';
        }

        // Si es numérico, validar que sea un attachment válido
        if ( is_numeric( $photo ) ) {
            $attachment_id = (int) $photo;
            $attachment = get_post( $attachment_id );
            if ( $attachment && $attachment->post_type === 'attachment' ) {
                return $attachment_id;
            }
            return false;
        }

        // Si es string, validar que sea una URL válida
        if ( is_string( $photo ) ) {
            $url = esc_url_raw( $photo );
            if ( filter_var( $url, FILTER_VALIDATE_URL ) !== false ) {
                return $url;
            }
        }

        return false;
    }

    /**
     * Resetear el enlace de la página de login
     *
     * @return void
     */
    public function reset_page_login_link(): void
    {
         // Solo en la pantalla de reset completado
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'resetpass' ) :
            $path = CustomTemplates::TEMPLATE_LOGIN['url'];
            $url = home_url( $path ); ?>

            <script>
                (function() {
                    var link = document.querySelector('.message.reset-pass a');
                    if (link) {
                        link.setAttribute('href', '<?php echo esc_js( $url ); ?>');
                    }
                })();
            </script>
    <?php endif;
    }

    /**
     * Redirigir a la página de perfil de los deportistas
     *
     * @return void
     */
    public function redirect_athlete_to_profile(): void
    {
        if ( ! is_user_logged_in() ) {
            return;
        }
    
        $user = wp_get_current_user();
    
        if ( ! in_array( 'athlete', (array) $user->roles, true ) ) {
            return;
        }

        // Un deportista que además es gestor (spec altas §10) sí entra en wp-admin.
        if ( true === MemberRoles::can_use_admin( $user ) ) {
            return;
        }
    
        // Permitir solo admin-ajax.php por si acaso
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
    
        // Cualquier intento de entrar en /wp-admin/ → perfil de deportista
        if ( is_admin() ) {
            wp_redirect( home_url( CustomTemplates::TEMPLATE_MY_PROFILE['url'] ) );
            exit;
        }
    }

}
