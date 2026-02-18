<?php
namespace ClubTriatlonSulayr\Services;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HelperService
 * Funciones helper reutilizables para formatear y obtener datos
 * 
 * Esta clase puede usarse como instancia (para inyección de dependencias y testing)
 * o mediante métodos estáticos (para compatibilidad y facilidad de uso).
 * Los métodos estáticos delegan internamente a una instancia singleton.
 */
class HelperService {
    /**
     * Instancia singleton para métodos estáticos
     *
     * @var HelperService|null
     * @access private
     */
    private static ?HelperService $instance = null;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function init(): void {    
    }

    /**
     * Obtiene la instancia singleton
     *
     * @return HelperService Instancia única de HelperService
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function get_instance(): HelperService {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener un campo ACF con fallback a get_user_meta
     *
     * @param string $field_name Nombre del campo ACF
     * @param int    $user_id    ID del usuario
     * @return mixed Valor del campo o false/null si no existe
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_user_field_instance( string $field_name, int $user_id ) {
        $value = get_field( $field_name, 'user_' . $user_id );
        
        // Si get_field() devuelve false/null/vacío, intentar con get_user_meta
        if ( $value === false || $value === null || $value === '' ) {
            $value = get_user_meta( $user_id, $field_name, true );
        }
        
        return $value;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param string $field_name Nombre del campo ACF
     * @param int    $user_id    ID del usuario
     * @return mixed Valor del campo o false/null si no existe
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function get_user_field( string $field_name, int $user_id ) {
        return self::get_instance()->get_user_field_instance( $field_name, $user_id );
    }

    /**
     * Formatear el valor de tarifa a etiqueta legible
     *
     * @param mixed $plan_value Valor de la tarifa (puede ser string, número, etc.)
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_membership_plan_instance( $plan_value ): string {
        if ( empty( $plan_value ) && $plan_value !== '0' && $plan_value !== 0 ) {
            return __( 'Sin tarifa', SULAYR_TEXTDOMAIN );
        }

        // Normalizar el valor
        $plan_normalized = is_string( $plan_value ) ? strtolower( trim( $plan_value ) ) : (string) $plan_value;
        
        // Comparaciones flexibles
        if ( $plan_normalized === 'basic' || $plan_normalized === 'básica' || stripos( $plan_normalized, 'basic' ) !== false ) {
            return __( 'Básica', SULAYR_TEXTDOMAIN );
        }
        
        if ( $plan_normalized === 'premium' || $plan_normalized === 'entrenamiento' || 
             stripos( $plan_normalized, 'premium' ) !== false || stripos( $plan_normalized, 'entrenamiento' ) !== false ) {
            return __( 'Entrenamiento', SULAYR_TEXTDOMAIN );
        }
        
        // Si no coincide, devolver el valor original escapado
        return esc_html( $plan_value );
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param mixed $plan_value Valor de la tarifa
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_membership_plan( $plan_value ): string {
        return self::get_instance()->format_membership_plan_instance( $plan_value );
    }

    /**
     * Formatear el valor de tipo de entrenamiento a etiqueta legible
     *
     * @param mixed $training_type_value Valor del tipo de entrenamiento
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_training_type_instance( $training_type_value ): string {
        if ( empty( $training_type_value ) ) {
            return '-';
        }

        // Normalizar el valor
        $type_normalized = is_string( $training_type_value ) ? strtolower( trim( $training_type_value ) ) : (string) $training_type_value;
        
        // Comparaciones flexibles
        if ( $type_normalized === 'sprint' || stripos( $type_normalized, 'sprint' ) !== false ) {
            return __( 'Sprint', SULAYR_TEXTDOMAIN );
        }
        
        if ( $type_normalized === 'olimpic' || $type_normalized === 'olímpico' || 
             stripos( $type_normalized, 'olimpic' ) !== false || stripos( $type_normalized, 'olímpico' ) !== false ) {
            return __( 'Olímpico', SULAYR_TEXTDOMAIN );
        }
        
        // Si no coincide, devolver el valor original escapado
        return esc_html( $training_type_value );
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param mixed $training_type_value Valor del tipo de entrenamiento
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_training_type( $training_type_value ): string {
        return self::get_instance()->format_training_type_instance( $training_type_value );
    }

    /**
     * Formatear el valor de suplemento natación a booleano
     *
     * @param mixed $swimming_value Valor del suplemento
     * @return bool true si tiene suplemento activo, false en caso contrario
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_swimming_supplement_to_bool_instance( $swimming_value ): bool {
        if ( $swimming_value === false || $swimming_value === null ) {
            return false;
        }

        // Normalizar el valor
        $swimming_normalized = is_string( $swimming_value ) ? trim( $swimming_value ) : $swimming_value;
        $swimming_str = (string) $swimming_normalized;
        
        // Verificar si tiene suplemento activo
        if ( $swimming_normalized === '1' || $swimming_normalized === 1 || $swimming_normalized === true || 
             $swimming_str === '1' || strtolower( $swimming_str ) === 'yes' || 
             strtolower( $swimming_str ) === 'sí' || strtolower( $swimming_str ) === 'si' || 
             $swimming_normalized === 'true' ) {
            return true;
        }
        
        return false;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param mixed $swimming_value Valor del suplemento
     * @return bool true si tiene suplemento activo, false en caso contrario
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_swimming_supplement_to_bool( $swimming_value ): bool {
        return self::get_instance()->format_swimming_supplement_to_bool_instance( $swimming_value );
    }

    /**
     * Formatear el valor de suplemento natación a etiqueta legible
     *
     * @param mixed $swimming_value Valor del suplemento
     * @return string Etiqueta formateada ('Sí' o 'No')
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_swimming_supplement_to_label_instance( $swimming_value ): string {
        if ( $this->format_swimming_supplement_to_bool_instance( $swimming_value ) ) {
            return __( 'Sí', SULAYR_TEXTDOMAIN );
        }
        
        return __( 'No', SULAYR_TEXTDOMAIN );
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param mixed $swimming_value Valor del suplemento
     * @return string Etiqueta formateada ('Sí' o 'No')
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_swimming_supplement_to_label( $swimming_value ): string {
        return self::get_instance()->format_swimming_supplement_to_label_instance( $swimming_value );
    }

    /**
     * Formatear el valor de estado del deportista a etiqueta legible
     *
     * @param mixed $status_value Valor del estado (puede ser string, número, etc.)
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_athlete_status_instance( $status_value ): string {
        if ( empty( $status_value ) && $status_value !== '0' && $status_value !== 0 ) {
            return __( 'Sin estado', SULAYR_TEXTDOMAIN );
        }

        // Normalizar el valor
        $status_normalized = is_string( $status_value ) ? strtolower( trim( $status_value ) ) : (string) $status_value;
        
        // Comparaciones flexibles
        if ( $status_normalized === 'active' || $status_normalized === 'activo' || stripos( $status_normalized, 'active' ) !== false || stripos( $status_normalized, 'activo' ) !== false ) {
            return __( 'Activo', SULAYR_TEXTDOMAIN );
        }
        
        if ( $status_normalized === 'standby' || $status_normalized === 'stand by' || stripos( $status_normalized, 'standby' ) !== false || stripos( $status_normalized, 'stand by' ) !== false ) {
            return __( 'Stand By', SULAYR_TEXTDOMAIN );
        }
        
        if ( $status_normalized === 'baja' || stripos( $status_normalized, 'baja' ) !== false ) {
            return __( 'Baja', SULAYR_TEXTDOMAIN );
        }
        
        if ( $status_normalized === 'moroso' || stripos( $status_normalized, 'moroso' ) !== false ) {
            return __( 'Moroso', SULAYR_TEXTDOMAIN );
        }
        
        // Si no coincide, devolver el valor original escapado
        return esc_html( $status_value );
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param mixed $status_value Valor del estado
     * @return string Etiqueta formateada
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_athlete_status( $status_value ): string {
        return self::get_instance()->format_athlete_status_instance( $status_value );
    }

    /**
     * Formatear fecha de nacimiento
     *
     * @param string $birth_date Fecha en formato Y-m-d
     * @param string $format     Formato de salida (por defecto d/m/Y)
     * @return string Fecha formateada o string vacío si no es válida
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_birth_date_instance( string $birth_date, string $format = 'd/m/Y' ): string {
        if ( empty( $birth_date ) ) {
            return '';
        }

        $date = \DateTime::createFromFormat( 'Y-m-d', $birth_date );
        if ( $date ) {
            return $date->format( $format );
        }
        
        // Si no se puede parsear, devolver el valor original
        return $birth_date;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param string $birth_date Fecha en formato Y-m-d
     * @param string $format     Formato de salida (por defecto d/m/Y)
     * @return string Fecha formateada o string vacío si no es válida
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_birth_date( string $birth_date, string $format = 'd/m/Y' ): string {
        return self::get_instance()->format_birth_date_instance( $birth_date, $format );
    }

    /**
     * Formatear fecha de alta en el club
     *
     * @param string $join_date Fecha en formato Y-m-d
     * @param string $format     Formato de salida (por defecto d/m/Y)
     * @return string Fecha formateada o string vacío si no es válida
     * @access public
     * @package club-triatlon-sulayr
     */
    public function format_join_date_instance( string $join_date, string $format = 'd/m/Y' ): string {
        if ( empty( $join_date ) ) {
            return '';
        }

        $date = \DateTime::createFromFormat( 'Y-m-d', $join_date );
        if ( $date ) {
            return $date->format( $format );
        }

        return $join_date;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param string $join_date Fecha en formato Y-m-d
     * @param string $format     Formato de salida (por defecto d/m/Y)
     * @return string Fecha formateada o string vacío si no es válida
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function format_join_date( string $join_date, string $format = 'd/m/Y' ): string {
        return self::get_instance()->format_join_date_instance( $join_date, $format );
    }

    /**
     * Obtener nombre completo del usuario
     *
     * @param int $user_id ID del usuario
     * @return string Nombre completo o display_name o user_login como fallback
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_user_full_name_instance( int $user_id ): string {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return '';
        }

        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name = get_user_meta( $user_id, 'last_name', true );
        $full_name = trim( $first_name . ' ' . $last_name );
        
        if ( ! empty( $full_name ) ) {
            return $full_name;
        }
        
        if ( ! empty( $user->display_name ) ) {
            return $user->display_name;
        }
        
        return $user->user_login;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param int $user_id ID del usuario
     * @return string Nombre completo o display_name o user_login como fallback
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function get_user_full_name( int $user_id ): string {
        return self::get_instance()->get_user_full_name_instance( $user_id );
    }

    /**
     * Obtener URL de la foto de perfil del usuario
     *
     * @param int    $user_id      ID del usuario
     * @param string $default_url  URL por defecto si no hay foto
     * @param string $size         Tamaño de la imagen (thumbnail, medium, large, full)
     * @return string URL de la imagen
     * @access public
     * @package club-triatlon-sulayr
     */
    public function get_user_photo_url_instance( int $user_id, string $default_url = '', string $size = 'medium' ): string {
        if ( empty( $default_url ) ) {
            $default_url = SULAYR_PUBLIC_ASSETS . '/images/default-user.png';
        }

        $photo_id = $this->get_user_field_instance( CustomFields::USER_PROFILE_PHOTO, $user_id );
        
        if ( ! $photo_id ) {
            return $default_url;
        }

        // Si es numérico, es un ID de attachment
        if ( is_numeric( $photo_id ) ) {
            $url = wp_get_attachment_image_url( $photo_id, $size );
            return $url ?: $default_url;
        }
        
        // Si es una URL directa
        return $photo_id;
    }

    /**
     * Método estático que delega a la instancia singleton
     *
     * @param int    $user_id      ID del usuario
     * @param string $default_url  URL por defecto si no hay foto
     * @param string $size         Tamaño de la imagen (thumbnail, medium, large, full)
     * @return string URL de la imagen
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function get_user_photo_url( int $user_id, string $default_url = '', string $size = 'medium' ): string {
        return self::get_instance()->get_user_photo_url_instance( $user_id, $default_url, $size );
    }
}
