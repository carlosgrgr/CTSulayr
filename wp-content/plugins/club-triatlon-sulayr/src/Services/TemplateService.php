<?php
namespace ClubTriatlonSulayr\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TemplateService
 * Servicio para cargar y renderizar plantillas
 */
class TemplateService {
    /**
     * Carga una plantilla desde la carpeta templates
     *
     * @param string $template_name Nombre de la plantilla (sin extensión .php)
     * @param array  $vars          Variables a pasar a la plantilla
     * @return void
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function load( string $template_name, array $vars = [] ): void {
        // Extraer variables para que estén disponibles en la plantilla
        extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

        // Construir ruta de la plantilla
        $template_path = SULAYR_TEMPLATE_DIR . '/' . $template_name . '.php';

        // Verificar que la plantilla existe
        if ( ! file_exists( $template_path ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            wp_die( sprintf( __( 'Error: No se encontró la plantilla %s', SULAYR_TEXTDOMAIN ), esc_html( $template_name ) ) );
        }

        // Incluir la plantilla
        include $template_path;
    }

    /**
     * Obtiene la ruta completa de una plantilla
     *
     * @param string $template_name Nombre de la plantilla (sin extensión .php)
     * @return string Ruta completa de la plantilla
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function get_path( string $template_name ): string {
        return SULAYR_TEMPLATE_DIR . '/' . $template_name . '.php';
    }

    /**
     * Verifica si una plantilla existe
     *
     * @param string $template_name Nombre de la plantilla (sin extensión .php)
     * @return bool true si existe, false en caso contrario
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function exists( string $template_name ): bool {
        return file_exists( self::get_path( $template_name ) );
    }

    /**
     * Renderiza una plantilla y retorna el contenido como string
     * Útil para emails y otros casos donde necesitas capturar el output
     *
     * @param string $template_name Nombre de la plantilla (sin extensión .php)
     * @param array  $vars          Variables a pasar a la plantilla
     * @return string Contenido renderizado de la plantilla
     * @access public
     * @package club-triatlon-sulayr
     */
    public static function render( string $template_name, array $vars = [] ): string {
        // Extraer variables para que estén disponibles en la plantilla
        extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

        // Construir ruta de la plantilla
        $template_path = SULAYR_TEMPLATE_DIR . '/' . $template_name . '.php';

        // Verificar que la plantilla existe
        if ( ! file_exists( $template_path ) ) {
            return sprintf( __( 'Error: No se encontró la plantilla %s', SULAYR_TEXTDOMAIN ), esc_html( $template_name ) );
        }

        // Capturar el output
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}

