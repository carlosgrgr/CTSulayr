<?php

namespace ClubTriatlonSulayr\Modules\Documents;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Directorio privado de los archivos de documentos.
 *
 * Los archivos subidos desde el campo ACF sulayr_document_file se guardan en
 * uploads/sulayr-documentos/, bloqueado por .htaccess. Siguen siendo adjuntos
 * de la Media Library (ACF los necesita), marcados con _sulayr_private_document.
 * Solo se sirven por PHP tras comprobar permisos (fase D3).
 */
class DocumentStorage {

    /** Subdirectorio dentro de uploads, sin barras. */
    public const DIRECTORY = 'sulayr-documentos';

    /** True mientras dura una subida desde el campo de archivo del documento. */
    private bool $redirecting_upload = false;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_filter( 'acf/upload_prefilter/name=' . CustomFields::DOCUMENT_FILE, [ $this, 'start_private_upload' ], 10, 3 );
        add_filter( 'wp_handle_upload', [ $this, 'stop_private_upload' ] );
        add_action( 'add_attachment', [ $this, 'mark_private_attachment' ] );
        add_action( 'admin_init', [ $this, 'ensure_protection_for_managers' ] );
    }

    /**
     * Ruta absoluta del directorio privado, con barra final.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function base_path(): string {
        $uploads = wp_get_upload_dir();

        return trailingslashit( $uploads['basedir'] ) . self::DIRECTORY . '/';
    }

    /**
     * True si la ruta está dentro del directorio privado (resolviendo enlaces y "..").
     *
     * @param   string $path Ruta absoluta de un archivo.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_private_path( string $path ): bool {
        $real_file = realpath( $path );
        $real_base = realpath( self::base_path() );
        if ( false === $real_file || false === $real_base ) {
            return false;
        }

        return 0 === strpos( $real_file, trailingslashit( $real_base ) );
    }

    /**
     * True si el adjunto es un archivo privado de documentos (marcado y dentro del directorio).
     *
     * @param   int $attachment_id ID del adjunto.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_private_attachment( int $attachment_id ): bool {
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return false;
        }

        if ( '1' !== (string) get_post_meta( $attachment_id, CustomFields::DOCUMENT_PRIVATE_ATTACHMENT, true ) ) {
            return false;
        }

        $file = get_attached_file( $attachment_id );

        return false !== $file && '' !== $file && self::is_private_path( $file );
    }

    /**
     * Antes de subir desde el campo de archivo: protege el directorio y desvía la subida.
     * Si ACF ya ha rechazado el archivo, no se hace nada.
     *
     * @param   array $errors Errores de ACF.
     * @param   array $file   Archivo subido.
     * @param   array $field  Campo ACF.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function start_private_upload( array $errors, array $file, array $field ): array {
        if ( ! empty( $errors ) ) {
            return $errors;
        }

        add_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
        $this->redirecting_upload = true;

        if ( false === $this->ensure_protection() ) {
            $this->stop_private_upload( [] );
            $errors[] = __( 'No se ha podido preparar el directorio privado de documentos. El archivo no se ha subido.', SULAYR_TEXTDOMAIN );
        }

        return $errors;
    }

    /**
     * Cambia el destino de la subida al directorio privado.
     *
     * @param   array $uploads Datos de wp_upload_dir().
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function private_upload_dir( array $uploads ): array {
        $uploads['subdir'] = '/' . self::DIRECTORY;
        $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
        $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];

        return $uploads;
    }

    /**
     * Termina el desvío al acabar la subida (se llama desde el filtro wp_handle_upload).
     *
     * @param   array $upload Resultado de la subida.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function stop_private_upload( array $upload ): array {
        if ( true === $this->redirecting_upload ) {
            remove_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
            $this->redirecting_upload = false;
        }

        return $upload;
    }

    /**
     * Marca los adjuntos creados dentro del directorio privado.
     *
     * @param   int $attachment_id ID del adjunto.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function mark_private_attachment( int $attachment_id ): void {
        $file = get_attached_file( $attachment_id );
        if ( false === $file || '' === $file || false === self::is_private_path( $file ) ) {
            return;
        }

        update_post_meta( $attachment_id, CustomFields::DOCUMENT_PRIVATE_ATTACHMENT, '1' );
    }

    /**
     * Comprueba la protección en el escritorio de quien gestiona documentos.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function ensure_protection_for_managers(): void {
        if ( wp_doing_ajax() || false === current_user_can( 'edit_sulayr_documents' ) ) {
            return;
        }

        if ( false === $this->ensure_protection() ) {
            add_action( 'admin_notices', [ $this, 'render_protection_error' ] );
        }
    }

    /**
     * Crea el directorio, el .htaccess que deniega el acceso y un index.html vacío si faltan.
     *
     * @return 	bool True si el directorio y el .htaccess existen al terminar.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function ensure_protection(): bool {
        $dir = self::base_path();
        if ( false === wp_mkdir_p( $dir ) ) {
            return false;
        }

        $htaccess = $dir . '.htaccess';
        if ( false === file_exists( $htaccess ) ) {
            $rules = "# Documentos privados del club. Solo se sirven desde Mi cuenta.\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $htaccess, $rules );
        }

        $index = $dir . 'index.html';
        if ( false === file_exists( $index ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $index, '' );
        }

        return file_exists( $htaccess );
    }

    /**
     * Aviso si no se puede proteger el directorio.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_protection_error(): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: ruta del directorio */
                    __( 'Documentos: no se ha podido crear o proteger el directorio %s. No subas documentos hasta resolverlo.', SULAYR_TEXTDOMAIN ),
                    self::base_path()
                )
            )
        );
    }
}
