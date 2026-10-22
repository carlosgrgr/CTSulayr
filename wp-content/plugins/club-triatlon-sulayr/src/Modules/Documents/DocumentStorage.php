<?php

namespace ClubTriatlonSulayr\Modules\Documents;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;

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
 *
 * Ciclo de vida (D7, sin versionado): al cambiar el archivo de un documento se
 * borra el anterior; al borrar definitivamente el documento se borran sus
 * archivos. Nunca se borra un archivo que use otro documento.
 */
class DocumentStorage {

    /** Subdirectorio dentro de uploads, sin barras. */
    public const DIRECTORY = 'sulayr-documentos';

    /** True mientras dura una subida desde el campo de archivo del documento. */
    private bool $redirecting_upload = false;

    /** Archivo de cada documento antes de que ACF guarde: [ document_id => attachment_id ]. */
    private array $previous_files = [];

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

        // ACF guarda los valores en acf/save_post con prioridad 10.
        add_action( 'acf/save_post', [ $this, 'remember_previous_file' ], 5 );
        add_action( 'acf/save_post', [ $this, 'handle_file_change' ], 20 );
        add_action( 'before_delete_post', [ $this, 'delete_document_files' ], 10, 2 );
    }

    /**
     * Antes de que ACF guarde: anota el archivo actual del documento.
     *
     * @param   int|string $post_id ID del post que ACF va a guardar.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remember_previous_file( $post_id ): void {
        $document_id = self::document_id( $post_id );
        if ( 0 === $document_id ) {
            return;
        }

        $this->previous_files[ $document_id ] = (int) get_post_meta( $document_id, CustomFields::DOCUMENT_FILE, true );
    }

    /**
     * Después de que ACF guarde: si el archivo ha cambiado, borra el anterior y
     * actualiza la fecha de cambio. Borra también los archivos privados subidos a
     * este documento que no se llegaron a usar.
     *
     * @param   int|string $post_id ID del post que ACF acaba de guardar.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_file_change( $post_id ): void {
        $document_id = self::document_id( $post_id );
        if ( 0 === $document_id || false === array_key_exists( $document_id, $this->previous_files ) ) {
            return;
        }

        $previous = $this->previous_files[ $document_id ];
        unset( $this->previous_files[ $document_id ] );
        $current = (int) get_post_meta( $document_id, CustomFields::DOCUMENT_FILE, true );

        if ( $previous > 0 && $previous !== $current ) {
            $this->delete_if_unused( $previous, $document_id );
            if ( $current > 0 ) {
                update_post_meta( $document_id, CustomFields::DOCUMENT_FILE_UPDATED, current_time( 'mysql' ) );
            }
        }

        foreach ( $this->attached_private_files( $document_id ) as $attachment_id ) {
            if ( $attachment_id !== $current ) {
                $this->delete_if_unused( $attachment_id, $document_id );
            }
        }
    }

    /**
     * Borrado definitivo de un documento: elimina su archivo y los archivos
     * privados subidos a él. La papelera no pasa por aquí: conserva el archivo.
     *
     * @param   int           $post_id ID del post que se va a borrar.
     * @param   \WP_Post|null $post    Post (WordPress 5.5+).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function delete_document_files( int $post_id, $post = null ): void {
        if ( DocumentPostType::POST_TYPE_NAME !== get_post_type( $post_id ) ) {
            return;
        }

        $files = $this->attached_private_files( $post_id );
        $files[] = (int) get_post_meta( $post_id, CustomFields::DOCUMENT_FILE, true );

        foreach ( array_unique( array_filter( $files ) ) as $attachment_id ) {
            $this->delete_if_unused( $attachment_id, $post_id );
        }
    }

    /**
     * Borra un adjunto privado (registro y archivo) si ningún otro documento lo usa.
     * Los adjuntos que no son privados no se tocan nunca.
     *
     * @param   int $attachment_id ID del adjunto.
     * @param   int $document_id   Documento que deja de usarlo.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function delete_if_unused( int $attachment_id, int $document_id ): void {
        if ( false === self::is_private_attachment( $attachment_id ) ) {
            return;
        }

        // Todos los estados: un documento en la papelera o en borrador también cuenta como uso.
        $users = get_posts( [
            'post_type' => DocumentPostType::POST_TYPE_NAME,
            'post_status' => array_values( get_post_stati() ),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'post__not_in' => [ $document_id ],
            'meta_query' => [
                [
                    'key' => CustomFields::DOCUMENT_FILE,
                    'value' => $attachment_id,
                    'compare' => '=',
                ],
            ],
        ] );
        if ( ! empty( $users ) ) {
            return;
        }

        wp_delete_attachment( $attachment_id, true );
    }

    /**
     * Adjuntos privados subidos a un documento (post_parent).
     *
     * @param   int $document_id ID del documento.
     * @return 	int[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function attached_private_files( int $document_id ): array {
        return array_map( 'intval', get_posts( [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_parent' => $document_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => CustomFields::DOCUMENT_PRIVATE_ATTACHMENT,
            'meta_value' => '1',
        ] ) );
    }

    /**
     * ID del documento si el post es un sulayr_document, o 0.
     *
     * @param   int|string $post_id ID recibido de ACF.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function document_id( $post_id ): int {
        if ( false === is_numeric( $post_id ) ) {
            return 0;
        }

        return DocumentPostType::POST_TYPE_NAME === get_post_type( (int) $post_id ) ? (int) $post_id : 0;
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
