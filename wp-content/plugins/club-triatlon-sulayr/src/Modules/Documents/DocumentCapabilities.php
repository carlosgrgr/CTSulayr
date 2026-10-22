<?php

namespace ClubTriatlonSulayr\Modules\Documents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Capacidades de los documentos y su sincronización con los roles.
 *
 * add_role() no modifica un rol que ya existe, así que las capacidades se
 * añaden con add_cap() y se vuelven a aplicar cuando cambia VERSION o cuando
 * a un rol gestionado le falta alguna.
 */
class DocumentCapabilities {

    /** capability_type del CPT: WordPress deriva de aquí edit_sulayr_documents, etc. */
    public const CAPABILITY_TYPE = [ 'sulayr_document', 'sulayr_documents' ];

    /** Capacidad propia para ver la sección Documentos y descargar. */
    public const VIEW = 'sulayr_view_documents';

    /** Gestión completa de documentos (primitivas generadas por capability_type). */
    public const MANAGE = [
        'edit_sulayr_documents',
        'edit_others_sulayr_documents',
        'edit_published_sulayr_documents',
        'edit_private_sulayr_documents',
        'publish_sulayr_documents',
        'delete_sulayr_documents',
        'delete_others_sulayr_documents',
        'delete_published_sulayr_documents',
        'delete_private_sulayr_documents',
        'read_private_sulayr_documents',
    ];

    /** Subir una versión cuando cambie el mapa de capacidades. */
    private const VERSION = 1;
    private const VERSION_OPTION = 'sulayr_documents_caps_version';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        // Después de AthleteRole (init, 10), que crea el rol athlete.
        add_action( 'init', [ $this, 'maybe_sync' ], 20 );
    }

    /**
     * Capacidades que debe tener cada rol gestionado.
     *
     * @return 	array<string, string[]>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function role_map(): array {
        return [
            'administrator' => array_merge( self::MANAGE, [ self::VIEW ] ),
            'athlete' => [ self::VIEW ],
        ];
    }

    /**
     * Sincroniza si cambió la versión o si a un rol gestionado le falta una capacidad.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_sync(): void {
        if ( self::VERSION === (int) get_option( self::VERSION_OPTION ) && false === $this->has_missing_caps() ) {
            return;
        }

        $this->sync();
    }

    /**
     * Añade las capacidades del mapa y retira las del documento que ya no estén en él.
     * Solo toca los roles del mapa y solo capacidades propias de los documentos.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function sync(): void {
        $all_roles_found = true;

        foreach ( self::role_map() as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( null === $role ) {
                $all_roles_found = false;
                continue;
            }

            foreach ( $caps as $cap ) {
                if ( empty( $role->capabilities[ $cap ] ) ) {
                    $role->add_cap( $cap );
                }
            }

            foreach ( array_keys( $role->capabilities ) as $cap ) {
                if ( true === self::is_document_cap( $cap ) && false === in_array( $cap, $caps, true ) ) {
                    $role->remove_cap( $cap );
                }
            }
        }

        // Si falta algún rol, se reintenta en la siguiente petición.
        if ( true === $all_roles_found ) {
            update_option( self::VERSION_OPTION, self::VERSION );
        }
    }

    /**
     * True si la capacidad pertenece a los documentos.
     *
     * @param   string $cap Capacidad.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_document_cap( string $cap ): bool {
        return self::VIEW === $cap || '_sulayr_documents' === substr( $cap, -strlen( '_sulayr_documents' ) );
    }

    /**
     * True si a algún rol gestionado existente le falta una capacidad del mapa.
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function has_missing_caps(): bool {
        foreach ( self::role_map() as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( null === $role ) {
                continue;
            }

            foreach ( $caps as $cap ) {
                if ( empty( $role->capabilities[ $cap ] ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
