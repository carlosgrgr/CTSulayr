<?php

namespace ClubTriatlonSulayr\Modules\Documents;

use ClubTriatlonSulayr\Services\DocumentService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Endpoint "documentos" de Mi cuenta (WooCommerce).
 *
 * /mi-cuenta/documentos/      → sección con la lista de documentos
 * /mi-cuenta/documentos/{ID}/ → descarga controlada del archivo
 *
 * No usa admin-post.php: UserService saca a los athlete de wp-admin.
 */
class DocumentsAccountEndpoint {

    private const ATHLETE_ROLE = 'athlete';

    /** El ítem se coloca detrás del primero de estos que exista. */
    private const MENU_AFTER = [ 'mis-pagos', 'mi-monedero', 'dashboard' ];

    /** Subir para forzar que se regeneren las reglas de reescritura al desplegar. */
    private const ENDPOINT_VERSION = 1;
    private const ENDPOINT_VERSION_OPTION = 'sulayr_documents_endpoint_version';

    /** Opción que ya usa WooCommerceIntegration::maybe_flush_rewrite_rules() (init, 999). */
    private const FLUSH_OPTION = 'sulayr_wc_flush_rewrite_rules';

    private const READ_CHUNK_BYTES = 1048576;

    private DocumentService $documents;

    /**
     * __construct()
     *
     * @param   DocumentService $documents Servicio de documentos.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( DocumentService $documents ) {
        $this->documents = $documents;

        add_filter( 'woocommerce_get_query_vars', [ $this, 'add_query_var' ] );
        add_action( 'init', [ $this, 'maybe_request_rewrite_flush' ], 20 );
        add_action( 'template_redirect', [ $this, 'handle_download' ], 2 );
        add_action( 'template_redirect', [ $this, 'guard_section' ], 3 );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 15 );
        add_filter( 'woocommerce_endpoint_' . DocumentService::ENDPOINT . '_title', [ $this, 'endpoint_title' ] );
        add_action( 'woocommerce_account_' . DocumentService::ENDPOINT . '_endpoint', [ $this, 'render_section' ] );
    }

    /**
     * Ítem "Documentos" en el menú de Mi cuenta, solo para deportistas con acceso.
     *
     * @param   array $items Ítems del menú.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_menu_item( array $items ): array {
        if ( false === $this->shows_menu_item() ) {
            return $items;
        }

        $after = null;
        foreach ( self::MENU_AFTER as $key ) {
            if ( isset( $items[ $key ] ) ) {
                $after = $key;
                break;
            }
        }

        $label = __( 'Documentos', SULAYR_TEXTDOMAIN );
        if ( null === $after ) {
            return array_merge( $items, [ DocumentService::ENDPOINT => $label ] );
        }

        $menu = [];
        foreach ( $items as $key => $item_label ) {
            $menu[ $key ] = $item_label;
            if ( $key === $after ) {
                $menu[ DocumentService::ENDPOINT ] = $label;
            }
        }

        return $menu;
    }

    /**
     * Título de la sección.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function endpoint_title(): string {
        return __( 'Documentos', SULAYR_TEXTDOMAIN );
    }

    /**
     * Quien tiene sesión pero no tiene acceso no ve la sección: vuelve a Mi cuenta con un aviso.
     * Sin sesión, WooCommerce muestra el formulario de acceso.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function guard_section(): void {
        global $wp;

        if ( false === function_exists( 'is_account_page' ) || false === is_account_page() || ! isset( $wp->query_vars[ DocumentService::ENDPOINT ] ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( 0 === $user_id || true === $this->documents->can_access_section( $user_id ) ) {
            return;
        }

        wc_add_notice( __( 'Los documentos del club solo están disponibles para deportistas en activo.', SULAYR_TEXTDOMAIN ), 'error' );
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    /**
     * Contenido de la sección.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_section(): void {
        $user_id = get_current_user_id();
        if ( false === $this->documents->can_access_section( $user_id ) ) {
            return;
        }

        $visible = $this->documents->get_visible_documents( $user_id );
        $personal = $visible['personal'];
        $groups = $this->documents->group_by_category( $visible['general'] );
        $total = count( $personal ) + count( $visible['general'] );

        $part = SULAYR_TEMPLATE_DIR . '/parts/content-documents.php';
        if ( file_exists( $part ) ) {
            include $part;
        }
    }

    /**
     * True si el usuario actual debe ver el ítem del menú (mismo criterio que las demás secciones: athlete).
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function shows_menu_item(): bool {
        $user = wp_get_current_user();
        if ( false === $user->exists() || false === in_array( self::ATHLETE_ROLE, (array) $user->roles, true ) ) {
            return false;
        }

        return $this->documents->can_access_section( (int) $user->ID );
    }

    /**
     * Registra el endpoint en WooCommerce.
     *
     * @param   array $vars Query vars de Mi cuenta.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_query_var( array $vars ): array {
        $vars[ DocumentService::ENDPOINT ] = DocumentService::ENDPOINT;

        return $vars;
    }

    /**
     * Pide regenerar las reglas de reescritura una vez por versión del endpoint.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_request_rewrite_flush(): void {
        if ( self::ENDPOINT_VERSION === (int) get_option( self::ENDPOINT_VERSION_OPTION ) ) {
            return;
        }

        update_option( self::FLUSH_OPTION, '1' );
        update_option( self::ENDPOINT_VERSION_OPTION, self::ENDPOINT_VERSION );
    }

    /**
     * Descarga controlada: /mi-cuenta/documentos/{ID}/.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_download(): void {
        $document_id = $this->requested_document_id();
        if ( 0 === $document_id ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }

        if ( false === $this->documents->can_access_section( $user_id ) ) {
            wc_add_notice( __( 'Los documentos del club solo están disponibles para deportistas en activo.', SULAYR_TEXTDOMAIN ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }

        // Documento inexistente, no publicado o de otra persona: misma respuesta, para no revelar que existe.
        $can_view = $this->documents->user_can_view( $document_id, $user_id );
        $file = $can_view ? $this->documents->get_file( $document_id ) : null;
        if ( null === $file ) {
            if ( true === $can_view ) {
                error_log( sprintf( '[sulayr-documentos] El documento %d no tiene un archivo privado válido.', $document_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            wc_add_notice( __( 'Este documento ya no está disponible.', SULAYR_TEXTDOMAIN ), 'error' );
            wp_safe_redirect( $this->documents->section_url() );
            exit;
        }

        /**
         * Se va a enviar un documento a un usuario autorizado.
         *
         * @param int $document_id ID del documento.
         * @param int $user_id     ID del usuario.
         */
        do_action( 'sulayr_document_downloaded', $document_id, $user_id );

        $this->send_file( $file, $this->documents->download_filename( $document_id, $file['ext'] ) );
        exit;
    }

    /**
     * ID pedido en /mi-cuenta/documentos/{ID}/, o 0 si no es una descarga.
     *
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function requested_document_id(): int {
        global $wp;

        if ( false === function_exists( 'is_account_page' ) || false === is_account_page() ) {
            return 0;
        }

        if ( ! isset( $wp->query_vars[ DocumentService::ENDPOINT ] ) ) {
            return 0;
        }

        $value = (string) $wp->query_vars[ DocumentService::ENDPOINT ];
        if ( '' === $value || false === ctype_digit( $value ) ) {
            return 0;
        }

        return absint( $value );
    }

    /**
     * Envía el archivo al navegador para abrirlo en línea.
     *
     * @param   array  $file     Resultado de DocumentService::get_file().
     * @param   string $filename Nombre que verá el usuario.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function send_file( array $file, string $filename ): void {
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 0 );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: ' . $file['mime'] );
        header( 'Content-Length: ' . $file['size'] );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Robots-Tag: noindex, nofollow' );

        if ( 'HEAD' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $file['path'], 'rb' );
        if ( false === $handle ) {
            return;
        }

        while ( false === feof( $handle ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
            echo fread( $handle, self::READ_CHUNK_BYTES );
            flush();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );
    }
}
