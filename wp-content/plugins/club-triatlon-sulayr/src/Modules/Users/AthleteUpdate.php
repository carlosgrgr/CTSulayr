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
        // Capturar valores antiguos antes de que se actualicen (prioridad 1 = antes de guardar)
        add_action( 'acf/save_post', [$this, 'capture_old_field_values'], 1, 1);
        // Comparar y enviar notificación después de que ACF guarde los campos (prioridad 20 = después de guardar)
        add_action( 'acf/save_post', [$this, 'check_and_send_notification_after_acf_save'], 20, 1);
        // Comparar y enviar notificación después de actualizar (para actualizaciones vía wp_update_user)
        add_action( 'profile_update', [$this, 'send_update_user_notification'], 10, 3);
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
        $this->old_field_values[ $user_id ] = [
            'plan' => HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id ),
            'training_type' => HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id ),
            'swimming' => HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id ),
        ];
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
        
        // Obtener datos del usuario
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Usar el método de notificación existente
        $this->send_update_user_notification( $user_id, $user, [] );
    }

    /**
     * send_update_user_notification()
     * Compara los valores antiguos con los nuevos y envía email si hay cambios
     * 
     * @param   int  $user_id
     * @param   \WP_User  $old_user_data
     * @param   array  $userdata
     * @return  void
     * @access  public
     * @package	club-triatlon-sulayr
     */
    public function send_update_user_notification( int $user_id, \WP_User $old_user_data, array $userdata ): void
    {
        // Verificar si tenemos valores antiguos capturados
        if ( ! isset( $this->old_field_values[ $user_id ] ) ) {
            return;
        }

        $old_values = $this->old_field_values[ $user_id ];

        // Obtener valores nuevos (después de la actualización)
        $new_plan = HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id );
        $new_training_type = HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id );
        $new_swimming = HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id );

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
        }
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
