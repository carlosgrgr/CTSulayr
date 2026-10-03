<?php
namespace ClubTriatlonSulayr\Services;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CsvImporterService
 * Servicio para importar deportistas desde archivos CSV
 */
class CsvImporterService {
    
    private UserService $user_service;
    
    /**
     * Constructor
     *
     * @param UserService $user_service
     */
    public function __construct( UserService $user_service ) {
        $this->user_service = $user_service;
    }
    
    /**
     * Importa deportistas desde un archivo CSV
     *
     * @param string $file_path Ruta al archivo CSV
     * @return array Resultado de la importación con estadísticas
     */
    public function import_from_csv( string $file_path ): array {
        $result = [
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'messages' => [],
        ];
        
        // Leer el contenido del archivo directamente en memoria
        // Esto evita problemas con permisos y sistemas de archivos FTP
        $file_content = @file_get_contents( $file_path );
        if ( $file_content === false ) {
            $result['messages'][] = __( 'No se pudo leer el archivo CSV.', SULAYR_TEXTDOMAIN );
            return $result;
        }
        
        // Convertir el contenido a un array de líneas
        // Manejar diferentes tipos de saltos de línea
        $file_content = str_replace( "\r\n", "\n", $file_content );
        $file_content = str_replace( "\r", "\n", $file_content );
        $lines = explode( "\n", $file_content );
        
        // Filtrar líneas vacías al final
        $lines = array_filter( $lines, function( $line ) {
            return trim( $line ) !== '';
        } );
        
        if ( empty( $lines ) ) {
            $result['messages'][] = __( 'El archivo CSV está vacío.', SULAYR_TEXTDOMAIN );
            return $result;
        }
        
        // Leer la primera línea (encabezados)
        $headers_line = array_shift( $lines );
        if ( empty( $headers_line ) ) {
            $result['messages'][] = __( 'El archivo CSV no tiene encabezados válidos.', SULAYR_TEXTDOMAIN );
            return $result;
        }
        
        // Parsear encabezados usando str_getcsv para manejar correctamente el separador
        $headers = str_getcsv( $headers_line, ';' );
        
        if ( empty( $headers ) ) {
            $result['messages'][] = __( 'No se pudieron leer los encabezados del CSV.', SULAYR_TEXTDOMAIN );
            return $result;
        }
        
        // Normalizar encabezados (eliminar espacios y convertir a minúsculas)
        $headers = array_map( function( $header ) {
            return trim( strtolower( $header ) );
        }, $headers );
        
        // Validar que los encabezados requeridos estén presentes
        $required_headers = [ 'nombre', 'apellidos', 'email', 'dni' ];
        $missing_headers = array_diff( $required_headers, $headers );
        if ( ! empty( $missing_headers ) ) {
            $result['messages'][] = sprintf(
                __( 'Faltan columnas requeridas: %s', SULAYR_TEXTDOMAIN ),
                implode( ', ', $missing_headers )
            );
            return $result;
        }
        
        // Procesar cada fila
        $line_number = 1;
        foreach ( $lines as $line ) {
            $line_number++;
            
            // Saltar líneas vacías
            if ( trim( $line ) === '' ) {
                continue;
            }
            
            // Parsear la línea usando str_getcsv
            $row = str_getcsv( $line, ';' );
            
            // Saltar filas vacías
            if ( empty( array_filter( $row ) ) ) {
                continue;
            }
            
            // Crear array asociativo con los datos
            $data = [];
            foreach ( $headers as $index => $header ) {
                $data[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
            }
            
            // Importar el deportista
            $import_result = $this->import_athlete( $data, $line_number );
            
            if ( $import_result['success'] ) {
                $result['success']++;
            } elseif ( $import_result['skipped'] ) {
                $result['skipped']++;
            } else {
                $result['errors']++;
            }
            
            if ( ! empty( $import_result['message'] ) ) {
                $result['messages'][] = $import_result['message'];
            }
        }
        
        return $result;
    }
    
    /**
     * Importa un deportista individual
     *
     * @param array $data Datos del deportista
     * @param int   $line_number Número de línea en el CSV
     * @return array Resultado de la importación
     */
    private function import_athlete( array $data, int $line_number ): array {
        $result = [
            'success' => false,
            'skipped' => false,
            'message' => '',
        ];
        
        // Validar datos requeridos
        $nombre = $data['nombre'] ?? '';
        $apellidos = $data['apellidos'] ?? '';
        $email = $data['email'] ?? '';
        $dni = $data['dni'] ?? '';
        
        if ( empty( $email ) ) {
            $result['message'] = sprintf(
                __( 'Línea %d: Email vacío, se omite.', SULAYR_TEXTDOMAIN ),
                $line_number
            );
            $result['skipped'] = true;
            return $result;
        }
        
        // Validar formato de email
        if ( ! is_email( $email ) ) {
            $result['message'] = sprintf(
                __( 'Línea %d: Email inválido (%s), se omite.', SULAYR_TEXTDOMAIN ),
                $line_number,
                esc_html( $email )
            );
            $result['skipped'] = true;
            return $result;
        }
        
        // Verificar si el usuario ya existe
        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user ) {
            $result['message'] = sprintf(
                __( 'Línea %d: Usuario con email %s ya existe, se omite.', SULAYR_TEXTDOMAIN ),
                $line_number,
                esc_html( $email )
            );
            $result['skipped'] = true;
            return $result;
        }
        
        // Crear nombre de usuario único
        $username = $this->generate_username( $email, $nombre, $apellidos );
        
        // Generar contraseña aleatoria
        $password = wp_generate_password( 12, true, true );
        
        // Crear el usuario
        $user_id = wp_create_user( $username, $password, $email );
        
        if ( is_wp_error( $user_id ) ) {
            $result['message'] = sprintf(
                __( 'Línea %d: Error al crear usuario: %s', SULAYR_TEXTDOMAIN ),
                $line_number,
                $user_id->get_error_message()
            );
            return $result;
        }
        
        // Asignar rol athlete (esto se hace automáticamente en UserService, pero lo hacemos explícito)
        $user = get_userdata( $user_id );
        if ( $user ) {
            $user->set_role( 'athlete' );
        }
        
        // Actualizar datos del usuario
        $user_data = [
            'first_name' => $nombre,
            'last_name' => $apellidos,
            'display_name' => trim( $nombre . ' ' . $apellidos ),
        ];
        
        // Actualizar campos nativos
        if ( ! empty( $nombre ) ) {
            update_user_meta( $user_id, 'first_name', sanitize_text_field( $nombre ) );
        }
        if ( ! empty( $apellidos ) ) {
            update_user_meta( $user_id, 'last_name', sanitize_text_field( $apellidos ) );
        }
        
        // Actualizar campos ACF directamente usando update_field
        // Teléfono
        if ( ! empty( $data['teléfono'] ?? '' ) ) {
            $phone = sanitize_text_field( $data['teléfono'] );
            if ( ! empty( $phone ) ) {
                update_field( CustomFields::USER_PROFILE_PHONE, $phone, 'user_' . $user_id );
            }
        }
        
        // DNI
        if ( ! empty( $dni ) ) {
            // Limpiar DNI (eliminar caracteres especiales como ?)
            $dni_clean = preg_replace( '/[^0-9A-Za-z]/', '', $dni );
            $dni_clean = strtoupper( $dni_clean );
            if ( ! empty( $dni_clean ) ) {
                update_field( CustomFields::USER_PROFILE_DNI, $dni_clean, 'user_' . $user_id );
            }
        }
        
        // Fecha de nacimiento
        if ( ! empty( $data['fecha de nacimiento'] ?? '' ) ) {
            $birth_date = $this->parse_birth_date( $data['fecha de nacimiento'] );
            if ( $birth_date ) {
                update_field( CustomFields::USER_PROFILE_BIRTH, $birth_date, 'user_' . $user_id );
            }
        }
        
        $result['success'] = true;
        $result['message'] = sprintf(
            __( 'Línea %d: Usuario %s creado correctamente.', SULAYR_TEXTDOMAIN ),
            $line_number,
            esc_html( $email )
        );
        
        return $result;
    }
    
    /**
     * Genera un nombre de usuario único
     *
     * @param string $email Email del usuario
     * @param string $nombre Nombre
     * @param string $apellidos Apellidos
     * @return string Nombre de usuario
     */
    private function generate_username( string $email, string $nombre, string $apellidos ): string {
        // Intentar usar la parte antes del @ del email
        $username = sanitize_user( explode( '@', $email )[0], true );
        
        // Si el usuario ya existe, añadir números
        $original_username = $username;
        $counter = 1;
        while ( username_exists( $username ) ) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Parsea una fecha de nacimiento desde diferentes formatos
     *
     * @param string $date_string Fecha en formato texto
     * @return string|null Fecha en formato Y-m-d o null si no es válida
     */
    private function parse_birth_date( string $date_string ): ?string {
        // Limpiar la fecha (eliminar caracteres especiales como ?)
        $date_string = preg_replace( '/[^0-9\/\-]/', '', trim( $date_string ) );
        
        if ( empty( $date_string ) ) {
            return null;
        }
        
        // Intentar diferentes formatos
        $formats = [ 'd/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d' ];
        
        foreach ( $formats as $format ) {
            $date = \DateTime::createFromFormat( $format, $date_string );
            if ( $date && $date->format( $format ) === $date_string ) {
                return $date->format( 'Y-m-d' );
            }
        }
        
        // Si no funciona, intentar parsear manualmente formato DD/MM/YYYY
        if ( preg_match( '/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date_string, $matches ) ) {
            $day = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
            $month = str_pad( $matches[2], 2, '0', STR_PAD_LEFT );
            $year = $matches[3];
            
            if ( checkdate( (int) $month, (int) $day, (int) $year ) ) {
                return sprintf( '%s-%s-%s', $year, $month, $day );
            }
        }
        
        return null;
    }
}

