<?php

namespace ClubTriatlonSulayr\Services;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Documents\DocumentCapabilities;
use ClubTriatlonSulayr\Modules\Documents\DocumentStorage;
use ClubTriatlonSulayr\Modules\Fields\DocumentFields\DocumentFields;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;
use ClubTriatlonSulayr\Modules\Taxonomies\DocumentCategoryTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reglas y consultas de los documentos.
 *
 * user_can_view() es la única regla de acceso: la usan la lista y la descarga.
 * Falla cerrada: audiencia vacía o desconocida, lista de destinatarios vacía o
 * archivo no privado significan que nadie (salvo quien gestiona) lo ve.
 */
class DocumentService {

    public const ENDPOINT = 'documentos';

    private const ACTIVE_STATUS = 'active';

    /** Tipos que se pueden servir: extensión => MIME. Debe coincidir con DocumentFields. */
    private const SERVABLE_TYPES = [
        'pdf' => 'application/pdf',
        'jpg|jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * True si el deportista está en estado activo. Vacío cuenta como activo,
     * igual que en UserService::get_athlete_formatted_data().
     *
     * @param   int $user_id ID del usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function is_active_athlete( int $user_id ): bool {
        $status = HelperService::get_user_field( CustomFields::USER_PROFILE_ATHLETE_STATUS, $user_id );
        if ( null === $status || false === $status || '' === $status ) {
            return true;
        }

        return self::ACTIVE_STATUS === (string) $status;
    }

    /**
     * True si el usuario gestiona documentos (ve todo, incluidos borradores).
     *
     * @param   int $user_id ID del usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function is_manager( int $user_id ): bool {
        return $user_id > 0 && user_can( $user_id, 'edit_sulayr_documents' );
    }

    /**
     * True si el usuario puede entrar en la sección Documentos.
     *
     * @param   int $user_id ID del usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function can_access_section( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        if ( true === $this->is_manager( $user_id ) ) {
            return true;
        }

        return user_can( $user_id, DocumentCapabilities::VIEW ) && $this->is_active_athlete( $user_id );
    }

    /**
     * Regla de acceso de un usuario a un documento. No comprueba el archivo:
     * eso lo hace get_file(), que la lista y la descarga usan siempre.
     *
     * @param   int $document_id ID del documento.
     * @param   int $user_id     ID del usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function user_can_view( int $document_id, int $user_id ): bool {
        $post = get_post( $document_id );
        if ( null === $post || DocumentPostType::POST_TYPE_NAME !== $post->post_type || $user_id <= 0 ) {
            return false;
        }

        if ( true === user_can( $user_id, 'edit_post', $document_id ) ) {
            $can = true;
        } elseif ( 'publish' !== $post->post_status || false === $this->can_access_section( $user_id ) ) {
            $can = false;
        } else {
            $audience = (string) get_post_meta( $document_id, CustomFields::DOCUMENT_AUDIENCE, true );
            if ( DocumentFields::AUDIENCE_ALL === $audience ) {
                $can = true;
            } elseif ( DocumentFields::AUDIENCE_USERS === $audience ) {
                $recipients = array_map( 'intval', get_post_meta( $document_id, CustomFields::DOCUMENT_USER_INDEX ) );
                $can = in_array( $user_id, $recipients, true );
            } else {
                $can = false;
            }
        }

        /**
         * Permite ampliar la regla (p. ej. visibilidad por colectivo).
         *
         * @param bool $can         Resultado de la regla del plugin.
         * @param int  $document_id ID del documento.
         * @param int  $user_id     ID del usuario.
         */
        return (bool) apply_filters( 'sulayr_document_user_can_view', $can, $document_id, $user_id );
    }

    /**
     * Archivo servible de un documento, o null si no hay archivo privado válido.
     *
     * @param   int $document_id ID del documento.
     * @return 	array|null { attachment_id, path, mime, ext, size }
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function get_file( int $document_id ): ?array {
        $attachment_id = (int) get_post_meta( $document_id, CustomFields::DOCUMENT_FILE, true );
        if ( false === DocumentStorage::is_private_attachment( $attachment_id ) ) {
            return null;
        }

        $path = (string) get_attached_file( $attachment_id );
        if ( false === is_readable( $path ) ) {
            return null;
        }

        $type = wp_check_filetype( $path, self::SERVABLE_TYPES );
        if ( empty( $type['ext'] ) || empty( $type['type'] ) ) {
            return null;
        }

        return [
            'attachment_id' => $attachment_id,
            'path' => $path,
            'mime' => $type['type'],
            'ext' => strtolower( $type['ext'] ),
            'size' => (int) filesize( $path ),
        ];
    }

    /**
     * Documentos que el usuario puede ver, separados en personales y generales.
     * Solo publicados, con archivo válido y que pasen user_can_view().
     *
     * @param   int $user_id ID del usuario.
     * @return 	array{personal: array[], general: array[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function get_visible_documents( int $user_id ): array {
        $result = [
            'personal' => [],
            'general' => [],
        ];

        if ( false === $this->can_access_section( $user_id ) ) {
            return $result;
        }

        $groups = [
            'general' => [
                [
                    'key' => CustomFields::DOCUMENT_AUDIENCE,
                    'value' => DocumentFields::AUDIENCE_ALL,
                    'compare' => '=',
                ],
            ],
            'personal' => [
                [
                    'key' => CustomFields::DOCUMENT_AUDIENCE,
                    'value' => DocumentFields::AUDIENCE_USERS,
                    'compare' => '=',
                ],
                [
                    'key' => CustomFields::DOCUMENT_USER_INDEX,
                    'value' => $user_id,
                    'compare' => '=',
                ],
            ],
        ];

        foreach ( $groups as $group => $meta_query ) {
            $posts = get_posts( [
                'post_type' => DocumentPostType::POST_TYPE_NAME,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'orderby' => [
                    'menu_order' => 'ASC',
                    'date' => 'DESC',
                ],
                'meta_query' => array_merge( [ 'relation' => 'AND' ], $meta_query ),
            ] );

            foreach ( $posts as $post ) {
                if ( false === $this->user_can_view( $post->ID, $user_id ) ) {
                    continue;
                }

                $file = $this->get_file( $post->ID );
                if ( null === $file ) {
                    continue;
                }

                $result[ $group ][] = $this->present( $post, $file );
            }
        }

        return $result;
    }

    /**
     * URL de descarga controlada de un documento.
     *
     * @param   int $document_id ID del documento.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function download_url( int $document_id ): string {
        return wc_get_endpoint_url( self::ENDPOINT, (string) $document_id, wc_get_page_permalink( 'myaccount' ) );
    }

    /**
     * URL de la sección Documentos.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function section_url(): string {
        return wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) );
    }

    /**
     * Nombre de archivo para la descarga: título en ASCII + extensión real.
     *
     * @param   int    $document_id ID del documento.
     * @param   string $ext         Extensión del archivo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function download_filename( int $document_id, string $ext ): string {
        $name = sanitize_file_name( remove_accents( get_the_title( $document_id ) ) );
        $name = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
        $name = trim( (string) $name, '.-_' );
        if ( '' === $name ) {
            $name = 'documento';
        }

        return $name . '.' . $ext;
    }

    /**
     * Datos de presentación de un documento.
     *
     * @param   \WP_Post $post Documento.
     * @param   array    $file Resultado de get_file().
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function present( \WP_Post $post, array $file ): array {
        $updated = (string) get_post_meta( $post->ID, CustomFields::DOCUMENT_FILE_UPDATED, true );

        return [
            'id' => $post->ID,
            'title' => get_the_title( $post ),
            'description' => (string) get_post_meta( $post->ID, CustomFields::DOCUMENT_DESCRIPTION, true ),
            'type_label' => 'pdf' === $file['ext'] ? 'PDF' : 'IMG',
            'ext' => $file['ext'],
            'size' => $file['size'],
            'date' => '' !== $updated ? $updated : $post->post_date,
            'date_is_update' => '' !== $updated,
            'url' => $this->download_url( $post->ID ),
            'category' => $this->category_of( $post->ID ),
        ];
    }

    /**
     * Agrupa documentos generales por categoría: grupos por nombre de categoría y
     * «Otros documentos» al final. Si ninguno tiene categoría, un único grupo sin
     * título (lista plana).
     *
     * @param   array $documents Documentos ya presentados (present()).
     * @return 	array[] Lista de [ 'title' => string, 'documents' => array ].
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function group_by_category( array $documents ): array {
        if ( empty( $documents ) ) {
            return [];
        }

        $groups = [];
        $uncategorized = [];
        foreach ( $documents as $doc ) {
            if ( null === $doc['category'] ) {
                $uncategorized[] = $doc;
                continue;
            }

            $id = $doc['category']['id'];
            if ( ! isset( $groups[ $id ] ) ) {
                $groups[ $id ] = [
                    'title' => $doc['category']['name'],
                    'documents' => [],
                ];
            }
            $groups[ $id ]['documents'][] = $doc;
        }

        if ( empty( $groups ) ) {
            return [
                [
                    'title' => '',
                    'documents' => $uncategorized,
                ],
            ];
        }

        // Por nombre, sin distinguir mayúsculas ni tildes.
        uasort( $groups, function ( array $a, array $b ): int {
            return strcasecmp( remove_accents( $a['title'] ), remove_accents( $b['title'] ) );
        } );
        $groups = array_values( $groups );

        if ( ! empty( $uncategorized ) ) {
            $groups[] = [
                'title' => __( 'Otros documentos', SULAYR_TEXTDOMAIN ),
                'documents' => $uncategorized,
            ];
        }

        return $groups;
    }

    /**
     * Categoría del documento, o null. Si por error tuviera varias, la primera por nombre.
     *
     * @param   int $document_id ID del documento.
     * @return 	array|null { id, name }
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function category_of( int $document_id ): ?array {
        $terms = get_the_terms( $document_id, DocumentCategoryTaxonomy::TAXONOMY_NAME );
        if ( false === is_array( $terms ) || empty( $terms ) ) {
            return null;
        }

        usort( $terms, function ( \WP_Term $a, \WP_Term $b ): int {
            return strcasecmp( remove_accents( $a->name ), remove_accents( $b->name ) );
        } );

        return [
            'id' => (int) $terms[0]->term_id,
            'name' => $terms[0]->name,
        ];
    }
}
