<?php

namespace ClubTriatlonSulayr\Modules\Fields\DocumentFields;

use ClubTriatlonSulayr\Modules\Fields\Fields;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;
use ClubTriatlonSulayr\Modules\Documents\DocumentStorage;
use ClubTriatlonSulayr\Modules\Taxonomies\DocumentCategoryTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campos ACF del CPT Documentos (sulayr_document).
 *
 * También mantiene el índice de destinatarios: el campo ACF de usuarios se
 * guarda serializado, así que al guardar se escribe una fila
 * _sulayr_document_user por deportista para poder consultar con '=' exacto.
 */
class DocumentFields extends Fields {

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_USERS = 'users';

    private const ATHLETE_ROLE = 'athlete';
    private const ALLOWED_TYPES = 'pdf,jpg,jpeg,png';
    private const MAX_SIZE_MB = 10;
    private const DESCRIPTION_MAX_LENGTH = 300;

    private string $group_name = CustomFields::DOCUMENT_FIELDS_GROUP;
    protected array $fields = [];

    /**
     * Clave ACF de un campo a partir de su nombre (mismo criterio que el resto del plugin).
     *
     * @param   string $name Nombre del campo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function key( string $name ): string {
        return 'field_' . md5( $name );
    }

    /**
     * El grupo se define en acf/init para que las traducciones ya estén cargadas.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function init(): void {
        add_action( 'acf/init', [ $this, 'create_group_fields' ] );

        add_filter( 'acf/validate_value/name=' . CustomFields::DOCUMENT_FILE, [ $this, 'validate_file' ], 10, 4 );
        add_filter( 'acf/validate_value/name=' . CustomFields::DOCUMENT_AUDIENCE, [ $this, 'validate_audience' ], 10, 4 );
        add_filter( 'acf/validate_value/name=' . CustomFields::DOCUMENT_USERS, [ $this, 'validate_users' ], 10, 4 );
        add_action( 'acf/save_post', [ $this, 'sync_recipients' ], 20 );
        add_action( 'deleted_user', [ $this, 'remove_deleted_user' ] );
    }

    /**
     * Define y registra el grupo de campos.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function create_group_fields(): void {
        $this->fields = [
            'key' => 'group_' . md5( $this->group_name ),
            'title' => __( 'Datos del documento', SULAYR_TEXTDOMAIN ),
            'position' => 'acf_after_title',
            'style' => 'default',
            'fields' => [
                [
                    'key' => self::key( CustomFields::DOCUMENT_DESCRIPTION ),
                    'label' => __( 'Descripción', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::DOCUMENT_DESCRIPTION,
                    'type' => 'textarea',
                    'required' => 1,
                    'maxlength' => self::DESCRIPTION_MAX_LENGTH,
                    'rows' => 3,
                    'new_lines' => '',
                    'instructions' => __( 'Una o dos frases que expliquen qué es el documento.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::DOCUMENT_FILE ),
                    'label' => __( 'Archivo', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::DOCUMENT_FILE,
                    'type' => 'file',
                    'required' => 1,
                    'return_format' => 'id',
                    'library' => 'uploadedTo',
                    'uploader' => 'wp',
                    'mime_types' => self::ALLOWED_TYPES,
                    'max_size' => self::MAX_SIZE_MB,
                    'instructions' => sprintf(
                        /* translators: %d: tamaño máximo en MB */
                        __( 'PDF o imagen (JPG, PNG), máximo %d MB. Súbelo desde aquí: solo podrán abrirlo los deportistas activos a los que vaya dirigido.', SULAYR_TEXTDOMAIN ),
                        self::MAX_SIZE_MB
                    ),
                ],
                [
                    'key' => self::key( CustomFields::DOCUMENT_CATEGORY ),
                    'label' => __( 'Categoría', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::DOCUMENT_CATEGORY,
                    'type' => 'taxonomy',
                    'taxonomy' => DocumentCategoryTaxonomy::TAXONOMY_NAME,
                    'field_type' => 'radio',
                    'allow_null' => 1,
                    'add_term' => 0,
                    'save_terms' => 1,
                    'load_terms' => 1,
                    'return_format' => 'id',
                    'multiple' => 0,
                    'instructions' => __( 'Opcional. Agrupa el documento en la sección del deportista; sin categoría aparece en «Otros documentos». Los documentos para deportistas concretos se muestran en «Para ti», tengan la categoría que tengan.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::DOCUMENT_AUDIENCE ),
                    'label' => __( 'Visible para', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::DOCUMENT_AUDIENCE,
                    'type' => 'radio',
                    'required' => 1,
                    'choices' => [
                        self::AUDIENCE_ALL => __( 'Todos los deportistas activos', SULAYR_TEXTDOMAIN ),
                        self::AUDIENCE_USERS => __( 'Deportistas concretos', SULAYR_TEXTDOMAIN ),
                    ],
                    'default_value' => self::AUDIENCE_ALL,
                    'layout' => 'horizontal',
                    'return_format' => 'value',
                    'allow_null' => 0,
                    'other_choice' => 0,
                    'instructions' => __( '«Todos» lo verá cualquier deportista activo. Para documentos con datos personales, elige «Deportistas concretos».', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::DOCUMENT_USERS ),
                    'label' => __( 'Deportistas', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::DOCUMENT_USERS,
                    'type' => 'user',
                    'required' => 1,
                    'role' => [ self::ATHLETE_ROLE ],
                    'multiple' => 1,
                    'allow_null' => 0,
                    'return_format' => 'id',
                    'instructions' => __( 'Solo estos deportistas verán el documento, y solo mientras estén activos.', SULAYR_TEXTDOMAIN ),
                    'conditional_logic' => [
                        [
                            [
                                'field' => self::key( CustomFields::DOCUMENT_AUDIENCE ),
                                'operator' => '==',
                                'value' => self::AUDIENCE_USERS,
                            ],
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => DocumentPostType::POST_TYPE_NAME,
                    ],
                ],
            ],
        ];

        parent::create_group_fields();
    }

    /**
     * El archivo tiene que estar en el directorio privado: no se aceptan archivos
     * ya existentes en la Media Library pública.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value ID del adjunto.
     * @param   array       $field Campo ACF.
     * @param   string      $input Nombre del input.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_file( $valid, $value, $field, $input ) {
        if ( true !== $valid || empty( $value ) ) {
            return $valid;
        }

        if ( false === is_numeric( $value ) || false === DocumentStorage::is_private_attachment( (int) $value ) ) {
            return __( 'Sube el archivo desde este campo. No se pueden usar archivos ya existentes en la biblioteca de medios.', SULAYR_TEXTDOMAIN );
        }

        return $valid;
    }

    /**
     * Solo 'all' o 'users'. Un valor desconocido no se convierte en 'all'.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value Valor enviado.
     * @param   array       $field Campo ACF.
     * @param   string      $input Nombre del input.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_audience( $valid, $value, $field, $input ) {
        if ( true !== $valid ) {
            return $valid;
        }

        if ( false === in_array( $value, [ self::AUDIENCE_ALL, self::AUDIENCE_USERS ], true ) ) {
            return __( 'Elige para quién es visible el documento.', SULAYR_TEXTDOMAIN );
        }

        return $valid;
    }

    /**
     * Con audiencia 'users' hace falta al menos un deportista válido.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value IDs enviados.
     * @param   array       $field Campo ACF.
     * @param   string      $input Nombre del input.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_users( $valid, $value, $field, $input ) {
        if ( true !== $valid ) {
            return $valid;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF verifica el nonce antes de validar.
        $audience = isset( $_POST['acf'][ self::key( CustomFields::DOCUMENT_AUDIENCE ) ] ) ? sanitize_key( wp_unslash( $_POST['acf'][ self::key( CustomFields::DOCUMENT_AUDIENCE ) ] ) ) : '';
        if ( self::AUDIENCE_USERS !== $audience ) {
            return $valid;
        }

        if ( empty( self::valid_athlete_ids( (array) $value ) ) ) {
            return __( 'Selecciona al menos un deportista.', SULAYR_TEXTDOMAIN );
        }

        return $valid;
    }

    /**
     * Tras guardar los campos: limpia la lista de destinatarios y reescribe el índice.
     * Con audiencia distinta de 'users' la lista se vacía.
     *
     * @param   int|string $post_id ID del post que ACF acaba de guardar.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function sync_recipients( $post_id ): void {
        if ( false === is_numeric( $post_id ) || DocumentPostType::POST_TYPE_NAME !== get_post_type( (int) $post_id ) ) {
            return;
        }
        $post_id = (int) $post_id;

        $audience = (string) get_post_meta( $post_id, CustomFields::DOCUMENT_AUDIENCE, true );
        $user_ids = [];
        if ( self::AUDIENCE_USERS === $audience ) {
            $user_ids = self::valid_athlete_ids( (array) get_post_meta( $post_id, CustomFields::DOCUMENT_USERS, true ) );
        }

        self::write_recipients( $post_id, $user_ids );
    }

    /**
     * Quita a un usuario borrado de todos los documentos en los que era destinatario.
     *
     * @param   int $user_id ID del usuario borrado.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remove_deleted_user( int $user_id ): void {
        $document_ids = get_posts( [
            'post_type' => DocumentPostType::POST_TYPE_NAME,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => CustomFields::DOCUMENT_USER_INDEX,
                    'value' => $user_id,
                    'compare' => '=',
                ],
            ],
        ] );

        foreach ( $document_ids as $document_id ) {
            $remaining = array_diff( self::recipient_ids( $document_id ), [ $user_id ] );
            self::write_recipients( $document_id, array_values( $remaining ) );
        }
    }

    /**
     * IDs de destinatarios guardados en el campo ACF.
     *
     * @param   int $document_id ID del documento.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recipient_ids( int $document_id ): array {
        return array_map( 'intval', array_filter( (array) get_post_meta( $document_id, CustomFields::DOCUMENT_USERS, true ) ) );
    }

    /**
     * Guarda la lista en el campo ACF y en el índice (una fila por usuario).
     *
     * @param   int   $document_id ID del documento.
     * @param   int[] $user_ids    IDs de destinatarios.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function write_recipients( int $document_id, array $user_ids ): void {
        $user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );

        update_field( self::key( CustomFields::DOCUMENT_USERS ), $user_ids, $document_id );

        delete_post_meta( $document_id, CustomFields::DOCUMENT_USER_INDEX );
        foreach ( $user_ids as $user_id ) {
            add_post_meta( $document_id, CustomFields::DOCUMENT_USER_INDEX, $user_id );
        }
    }

    /**
     * Filtra una lista de IDs y deja solo usuarios existentes con rol athlete.
     *
     * @param   array $ids IDs recibidos.
     * @return 	int[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function valid_athlete_ids( array $ids ): array {
        $valid = [];
        foreach ( $ids as $id ) {
            $user = is_numeric( $id ) ? get_userdata( (int) $id ) : false;
            if ( false !== $user && in_array( self::ATHLETE_ROLE, (array) $user->roles, true ) ) {
                $valid[] = (int) $user->ID;
            }
        }

        return array_values( array_unique( $valid ) );
    }
}
