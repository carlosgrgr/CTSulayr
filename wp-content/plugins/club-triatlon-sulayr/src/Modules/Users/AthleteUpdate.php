<?php
namespace ClubTriatlonSulayr\Modules\Users;

use ClubTriatlonSulayr\Services\HelperService;
use ClubTriatlonSulayr\Services\TemplateService;
use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AthleteUpdate
 * Actualización de perfil de deportistas
 * 
 */
class AthleteUpdate
{
    private HelperService $helper_service;
    private array $old_field_values = [];
    private array $processing_users = []; // Para evitar procesar el mismo usuario dos veces

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( HelperService $helper_service )
    {
        $this->init();
        $this->helper_service = $helper_service;
    }

    /**
     * init()
     * Registra los filtros de WordPress para la actualización de perfil de deportistas
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function init(): void
    {
        // Capturar valores antiguos antes de que se actualicen usando acf/pre_update_value
        add_filter( 'acf/pre_update_value', [$this, 'capture_old_value_before_update'], 5, 3);
        // Capturar valores antiguos antes de que se actualicen (prioridad 1 = antes de guardar)
        add_action( 'acf/save_post', [$this, 'capture_old_field_values'], 1, 1);
        // Comparar y enviar notificación después de que ACF guarde los campos (prioridad 20 = después de guardar)
        add_action( 'acf/save_post', [$this, 'check_and_send_notification_after_acf_save'], 20, 1);
        // Comparar y enviar notificación después de actualizar (para actualizaciones vía wp_update_user)
        // Usar prioridad más baja para que se ejecute después de ACF
        add_action( 'profile_update', [$this, 'send_update_user_notification'], 20, 3);
    }   

    /**
     * capture_old_value_before_update()
     * Captura el valor antiguo de un campo específico antes de que se actualice
     * 
     * @param   mixed   $value   Nuevo valor
     * @param   int|string  $post_id ID del post o usuario
     * @param   array   $field   Array del campo ACF
     * @return  mixed    Valor original (no modificamos el valor)
     * @access  public
     * @package	club-triatlon-sulayr
     */
    public function capture_old_value_before_update( $value, $post_id, $field ): mixed
    {
        // Solo procesar si es un usuario y es el campo de estado
        if ( ! is_string( $post_id ) || strpos( $post_id, 'user_' ) !== 0 ) {
            return $value;
        }
        
        if ( ! isset( $field['name'] ) || $field['name'] !== CustomFields::USER_PROFILE_ATHLETE_STATUS ) {
            return $value;
        }

        $user_id = (int) str_replace( 'user_', '', $post_id );
        
        // Si aún no tenemos valores antiguos para este usuario, inicializar el array
        if ( ! isset( $this->old_field_values[ $user_id ] ) ) {
            $this->old_field_values[ $user_id ] = [
                'plan' => HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id ),
                'training_type' => HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id ),
                'swimming' => HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id ),
                'status' => 'active', // Valor por defecto
            ];
        }
        
        // Capturar el valor antiguo del estado antes de que se actualice
        $old_status = get_field( CustomFields::USER_PROFILE_ATHLETE_STATUS, $post_id );
        if ( empty( $old_status ) && $old_status !== '0' && $old_status !== 0 ) {
            $old_status = get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
            if ( empty( $old_status ) && $old_status !== '0' && $old_status !== 0 ) {
                $old_status = 'active';
            }
        }
        
        $this->old_field_values[ $user_id ]['status'] = $old_status;
        
        return $value; // No modificamos el valor, solo lo capturamos
    }

    /**
     * capture_old_field_values()
     * Captura los valores antiguos de los campos antes de que se actualicen
     * 
     * @param   int|string  $post_id ID del post o usuario (formato 'user_X')
     * @return  void
     * @access  public
     * @package	club-triatlon-sulayr
     */
    public function capture_old_field_values( $post_id ): void
    {
        // Solo procesar si es un usuario (formato 'user_X')
        if ( ! is_string( $post_id ) || strpos( $post_id, 'user_' ) !== 0 ) {
            return;
        }

        $user_id = (int) str_replace( 'user_', '', $post_id );

        // Capturar valores antiguos antes de que se actualicen
        // Usar get_field directamente para asegurar que obtenemos el valor actual antes de que se actualice
        $old_status = get_field( CustomFields::USER_PROFILE_ATHLETE_STATUS, 'user_' . $user_id );
        if ( empty( $old_status ) && $old_status !== '0' && $old_status !== 0 ) {
            // Si no hay valor en ACF, intentar con user meta
            $old_status = get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
            if ( empty( $old_status ) && $old_status !== '0' && $old_status !== 0 ) {
                $old_status = 'active';
            }
        }
        
        $this->old_field_values[ $user_id ] = [
            'plan' => HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id ),
            'training_type' => HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id ),
            'swimming' => HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id ),
            'status' => $old_status,
        ];
        
        // Guardar también en transient para persistencia entre hooks
        set_transient( 'sulayr_old_values_' . $user_id, $this->old_field_values[ $user_id ], 60 );
    }

    /**
     * check_and_send_notification_after_acf_save()
     * Verifica cambios después de que ACF guarde los campos y envía notificación si es necesario
     * 
     * @param   int|string  $post_id ID del post o usuario (formato 'user_X')
     * @return  void
     * @access  public
     * @package	club-triatlon-sulayr
     */
    public function check_and_send_notification_after_acf_save( $post_id ): void
    {
        // Solo procesar si es un usuario (formato 'user_X')
        if ( ! is_string( $post_id ) || strpos( $post_id, 'user_' ) !== 0 ) {
            return;
        }

        $user_id = (int) str_replace( 'user_', '', $post_id );
        
        // Si no hay valores antiguos capturados, intentar capturarlos ahora (fallback)
        if ( ! isset( $this->old_field_values[ $user_id ] ) ) {
            // Intentar recuperar desde transient como fallback
            $transient_values = get_transient( 'sulayr_old_values_' . $user_id );
            if ( $transient_values !== false ) {
                $this->old_field_values[ $user_id ] = $transient_values;
            } else {
                // Si no hay valores antiguos, no hacer nada
                return;
            }
        }
        
        // Obtener datos del usuario
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Marcar que estamos procesando este usuario desde ACF
        $this->processing_users[ $user_id ] = true;
        
        // Usar el método de notificación existente con un flag para indicar que viene de ACF
        $this->send_update_user_notification( $user_id, $user, [], true );
        
        // Limpiar el flag después de procesar
        unset( $this->processing_users[ $user_id ] );
    }

    /**
     * send_update_user_notification()
     * Compara los valores antiguos con los nuevos y envía email si hay cambios
     * 
     * @param   int  $user_id
     * @param   \WP_User  $old_user_data
     * @param   array  $userdata
     * @param   bool  $from_acf Indica si viene de ACF (true) o de profile_update (false)
     * @return  void
     * @access  public
     * @package	club-triatlon-sulayr
     */
    public function send_update_user_notification( int $user_id, \WP_User $old_user_data, array $userdata, bool $from_acf = false ): void
    {
        // Si viene de profile_update y estamos procesando desde ACF, ignorar esta llamada
        // porque ACF ya se encargará de procesar los cambios
        if ( ! $from_acf && isset( $this->processing_users[ $user_id ] ) ) {
            return;
        }
        
        // Si viene de profile_update y no de ACF, y no tenemos valores capturados, salir
        // porque significa que no se actualizó vía ACF
        if ( ! $from_acf && ! isset( $this->old_field_values[ $user_id ] ) ) {
            return;
        }
        
        // Verificar si tenemos valores antiguos capturados
        if ( ! isset( $this->old_field_values[ $user_id ] ) ) {
            // Intentar recuperar desde transient como fallback
            $transient_values = get_transient( 'sulayr_old_values_' . $user_id );
            if ( $transient_values !== false ) {
                $this->old_field_values[ $user_id ] = $transient_values;
            } else {
                return;
            }
        }

        $old_values = $this->old_field_values[ $user_id ];
        
        // Asegurar que el estado esté en los valores antiguos
        if ( ! isset( $old_values['status'] ) ) {
            // Intentar obtener el estado antiguo de otra manera
            $old_status = get_user_meta( $user_id, '_' . CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
            if ( empty( $old_status ) && $old_status !== '0' && $old_status !== 0 ) {
                $old_status = 'active';
            }
            $old_values['status'] = $old_status;
            $this->old_field_values[ $user_id ]['status'] = $old_status;
        }

        // Obtener valores nuevos (después de la actualización)
        $new_plan = HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id );
        $new_training_type = HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id );
        $new_swimming = HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id );
        
        // Obtener el nuevo estado - primero intentar desde $_POST si está disponible (más rápido)
        $new_status = null;
        if ( isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ) {
            $field_key = 'field_' . md5( CustomFields::USER_PROFILE_ATHLETE_STATUS );
            if ( isset( $_POST['acf'][ $field_key ] ) ) {
                $new_status = sanitize_text_field( $_POST['acf'][ $field_key ] );
            } else {
                // Intentar buscar por el nombre del campo directamente
                if ( isset( $_POST['acf'][ CustomFields::USER_PROFILE_ATHLETE_STATUS ] ) ) {
                    $new_status = sanitize_text_field( $_POST['acf'][ CustomFields::USER_PROFILE_ATHLETE_STATUS ] );
                }
            }
        }
        
        // Si no está en $_POST, obtenerlo de la base de datos
        if ( $new_status === null ) {
            $new_status = HelperService::get_user_field( CustomFields::USER_PROFILE_ATHLETE_STATUS, $user_id );
        }
        
        // Si el estado no existe o está vacío, usar 'active' como valor por defecto
        if ( empty( $new_status ) && $new_status !== '0' && $new_status !== 0 ) {
            $new_status = 'active';
        }
        
        // Verificar si el estado cambió a "Baja"
        $status_changed_to_baja = false;
        $old_status_value = isset( $old_values['status'] ) ? $old_values['status'] : 'active';
        
        if ( $old_status_value !== $new_status && $new_status === 'baja' ) {
            $status_changed_to_baja = true;
            $this->send_status_baja_notification( $user_id, $old_user_data );
        }

        // Comparar valores antiguos con nuevos
        $changes = [];

        if ( $old_values['plan'] !== $new_plan ) {
            $changes['plan'] = [
                'old' => $old_values['plan'],
                'new' => $new_plan,
                'old_formatted' => $this->format_field_value( 'plan', $old_values['plan'] ),
                'new_formatted' => $this->format_field_value( 'plan', $new_plan ),
                'label' => __( 'Tarifa', SULAYR_TEXTDOMAIN ),
            ];
        }

        if ( $old_values['training_type'] !== $new_training_type ) {
            $changes['training_type'] = [
                'old' => $old_values['training_type'],
                'new' => $new_training_type,
                'old_formatted' => $this->format_field_value( 'training_type', $old_values['training_type'] ),
                'new_formatted' => $this->format_field_value( 'training_type', $new_training_type ),
                'label' => __( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ),
            ];
        }

        if ( $old_values['swimming'] !== $new_swimming ) {
            $changes['swimming'] = [
                'old' => $old_values['swimming'],
                'new' => $new_swimming,
                'old_formatted' => $this->format_field_value( 'swimming', $old_values['swimming'] ),
                'new_formatted' => $this->format_field_value( 'swimming', $new_swimming ),
                'label' => __( 'Suplemento natación', SULAYR_TEXTDOMAIN ),
            ];
        }

        // Si hay cambios, enviar emails
        if ( ! empty( $changes ) ) {
            // Configurar headers para HTML
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
            ];

            // Variables comunes para ambos templates
            $template_vars = [
                'user_data' => $old_user_data,
                'user_id' => $user_id,
                'changes' => $changes,
                'new_plan' => $new_plan,
                'new_training_type' => $new_training_type,
                'new_swimming' => $new_swimming,
            ];

            // Enviar email al administrador
            $admin_email = get_option( 'admin_email' );
            $admin_subject = __( 'Actualización de perfil de deportista', SULAYR_TEXTDOMAIN );
            $admin_body = TemplateService::render( 'email/user-profile-update-admin', $template_vars );
            wp_mail( $admin_email, $admin_subject, $admin_body, $headers );

            // Enviar email al usuario
            $user_email = $old_user_data->user_email;
            $user_subject = __( 'Actualización de tu perfil', SULAYR_TEXTDOMAIN );
            $user_body = TemplateService::render( 'email/user-profile-update-user', $template_vars );
            wp_mail( $user_email, $user_subject, $user_body, $headers );

            // Limpiar valores antiguos después de enviar los emails
            unset( $this->old_field_values[ $user_id ] );
            delete_transient( 'sulayr_old_values_' . $user_id );
        } elseif ( $status_changed_to_baja ) {
            // Si solo cambió el estado a baja (sin otros cambios), limpiar valores antiguos
            unset( $this->old_field_values[ $user_id ] );
            delete_transient( 'sulayr_old_values_' . $user_id );
        }
    }

    /**
     * send_status_baja_notification()
     * Envía emails cuando un deportista cambia su estado a "Baja"
     * 
     * @param   int  $user_id
     * @param   \WP_User  $user_data
     * @return  void
     * @access  private
     * @package	club-triatlon-sulayr
     */
    private function send_status_baja_notification( int $user_id, \WP_User $user_data ): void
    {
        // Configurar headers para HTML
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        // Variables para los templates
        $template_vars = [
            'user_data' => $user_data,
            'user_id' => $user_id,
        ];

        // Enviar email al administrador
        $admin_email = get_option( 'admin_email' );
        $admin_subject = __( 'Deportista dado de baja', SULAYR_TEXTDOMAIN );
        $admin_body = TemplateService::render( 'email/athlete-status-baja-admin', $template_vars );
        wp_mail( $admin_email, $admin_subject, $admin_body, $headers );

        // Enviar email al deportista
        $user_email = $user_data->user_email;
        $user_subject = __( 'Baja en el club', SULAYR_TEXTDOMAIN );
        $user_body = TemplateService::render( 'email/athlete-status-baja-user', $template_vars );
        wp_mail( $user_email, $user_subject, $user_body, $headers );
    }

    /**
     * format_field_value()
     * Formatea el valor del campo para mostrarlo en el email
     * 
     * @param   string  $field_key Clave del campo
     * @param   mixed   $value     Valor del campo
     * @return  string  Valor formateado
     * @access  private
     * @package	club-triatlon-sulayr
     */
    private function format_field_value( string $field_key, $value ): string
    {
        if ( empty( $value ) && $value !== '0' && $value !== 0 ) {
            return __( 'Sin valor', SULAYR_TEXTDOMAIN );
        }

        switch ( $field_key ) {
            case 'plan':
                return HelperService::format_membership_plan( $value );
            
            case 'training_type':
                return HelperService::format_training_type( $value );
            
            case 'swimming':
                return HelperService::format_swimming_supplement_to_label( $value );
            
            default:
                return esc_html( $value );
        }
    }
}
