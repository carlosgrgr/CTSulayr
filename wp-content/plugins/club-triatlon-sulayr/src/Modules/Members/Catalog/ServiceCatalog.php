<?php
namespace ClubTriatlonSulayr\Modules\Members\Catalog;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lectura del catálogo y cálculo del precio vigente (spec §4.3).
 */
class ServiceCatalog {

    /** Clave de precio que vale para cualquier tipo de socio. No puede usarse como tipo de socio. */
    public const GENERAL = 'general';

    /** @var array<int, Service|null> */
    private static array $cache = [];

    /**
     * Vacía la caché (tras guardar un servicio o en pruebas).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function flush(): void {
        self::$cache = [];
    }

    /**
     * Servicio por ID, o null si no existe o no es un servicio.
     *
     * @param   int $id ID del post.
     * @return 	Service|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?Service {
        if ( array_key_exists( $id, self::$cache ) ) {
            return self::$cache[ $id ];
        }

        $post = get_post( $id );
        if ( null === $post || ServicePostType::POST_TYPE_NAME !== $post->post_type ) {
            self::$cache[ $id ] = null;
            return null;
        }

        $service = new Service();
        $service->id = (int) $post->ID;
        $service->slug = (string) $post->post_name;
        $service->title = (string) get_the_title( $post );
        $service->is_base = (bool) get_field( CustomFields::SERVICE_IS_BASE, $id );
        $service->prices = self::read_prices( $id );
        $service->prorated = self::read_bool( CustomFields::SERVICE_PRORATED, $id, true );
        $service->cancel_on_freeze = (bool) get_field( CustomFields::SERVICE_CANCEL_ON_FREEZE, $id );
        $service->public = (bool) get_field( CustomFields::SERVICE_PUBLIC, $id );
        $service->options = self::read_options( $id );
        $service->incompatible = array_map( 'intval', (array) ( get_field( CustomFields::SERVICE_INCOMPATIBLE, $id ) ?: [] ) );
        $service->grants_swimming = (bool) get_field( CustomFields::SERVICE_GRANTS_SWIMMING, $id );
        $service->coach_list = (bool) get_field( CustomFields::SERVICE_COACH_LIST, $id );
        $service->welcome_text = (string) get_field( CustomFields::SERVICE_WELCOME_TEXT, $id );
        $service->steps = [];
        foreach ( (array) ( get_field( CustomFields::SERVICE_STEPS, $id ) ?: [] ) as $row ) {
            $label = trim( (string) ( $row[ CustomFields::SERVICE_STEP_LABEL ] ?? '' ) );
            if ( '' !== $label ) {
                $service->steps[] = $label;
            }
        }

        self::$cache[ $id ] = $service;

        return $service;
    }

    /**
     * Servicio publicado por su clave (slug).
     *
     * @param   string $slug Clave.
     * @return 	Service|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get_by_slug( string $slug ): ?Service {
        $post = get_page_by_path( $slug, OBJECT, ServicePostType::POST_TYPE_NAME );
        if ( null === $post || 'publish' !== $post->post_status ) {
            return null;
        }

        return self::get( (int) $post->ID );
    }

    /**
     * Servicios publicados, en el orden del catálogo.
     *
     * @return 	Service[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function all(): array {
        $ids = get_posts( [
            'post_type' => ServicePostType::POST_TYPE_NAME,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
            'fields' => 'ids',
        ] );

        return array_values( array_filter( array_map( [ self::class, 'get' ], array_map( 'intval', $ids ) ) ) );
    }

    /**
     * La cuota base publicada, o null si no hay.
     *
     * @return 	Service|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function base(): ?Service {
        foreach ( self::all() as $service ) {
            if ( $service->is_base ) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Tipos de socio con la entrada «General» delante.
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function member_type_labels(): array {
        return array_merge( [ self::GENERAL => __( 'General', SULAYR_TEXTDOMAIN ) ], MemberSettings::member_types() );
    }

    /**
     * Precio aplicable a un tipo de socio en una fecha.
     *
     * Es el de la entrada más reciente con fecha de inicio ≤ $date: la del tipo
     * de socio si existe alguna vigente, si no la general. Para un mes, $date
     * es su día 1.
     *
     * @param   Service $service     Servicio.
     * @param   string  $member_type Clave del tipo de socio (GENERAL = sin tipo).
     * @param   string  $date        Fecha Y-m-d.
     * @return 	array{amount: float, general: float|null, member_type: string}|null  null si no hay precio vigente.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function price_for( Service $service, string $member_type, string $date ): ?array {
        $general = self::latest( $service->prices, self::GENERAL, $date );
        $typed = self::GENERAL !== $member_type ? self::latest( $service->prices, $member_type, $date ) : null;

        if ( null !== $typed ) {
            return [
                'amount' => $typed,
                'general' => $general,
                'member_type' => $member_type,
            ];
        }

        if ( null === $general ) {
            return null;
        }

        return [
            'amount' => $general,
            'general' => $general,
            'member_type' => self::GENERAL,
        ];
    }

    /**
     * Importe de la entrada más reciente de un tipo con fecha ≤ $date.
     *
     * @param   array  $prices      Filas de precio.
     * @param   string $member_type Tipo.
     * @param   string $date        Fecha Y-m-d.
     * @return 	float|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function latest( array $prices, string $member_type, string $date ): ?float {
        $best = null;
        $best_from = '';

        foreach ( $prices as $row ) {
            if ( $row['member_type'] !== $member_type || $row['valid_from'] > $date ) {
                continue;
            }

            if ( null === $best || $row['valid_from'] >= $best_from ) {
                $best = $row['amount'];
                $best_from = $row['valid_from'];
            }
        }

        return $best;
    }

    /**
     * Filas de precio normalizadas.
     *
     * @param   int $id Servicio.
     * @return 	array<int, array{member_type: string, amount: float, valid_from: string}>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function read_prices( int $id ): array {
        $rows = [];
        foreach ( (array) ( get_field( CustomFields::SERVICE_PRICES, $id ) ?: [] ) as $row ) {
            $from = (string) ( $row[ CustomFields::SERVICE_PRICE_VALID_FROM ] ?? '' );
            if ( '' === $from || ! isset( $row[ CustomFields::SERVICE_PRICE_AMOUNT ] ) || '' === $row[ CustomFields::SERVICE_PRICE_AMOUNT ] ) {
                continue;
            }

            $rows[] = [
                'member_type' => sanitize_key( (string) ( $row[ CustomFields::SERVICE_PRICE_MEMBER_TYPE ] ?? '' ) ) ?: self::GENERAL,
                'amount' => round( (float) $row[ CustomFields::SERVICE_PRICE_AMOUNT ], 2 ),
                'valid_from' => $from,
            ];
        }

        return $rows;
    }

    /**
     * Opciones del servicio como clave => nombre.
     *
     * @param   int $id Servicio.
     * @return 	array<string, string>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function read_options( int $id ): array {
        $options = [];
        foreach ( (array) ( get_field( CustomFields::SERVICE_OPTIONS, $id ) ?: [] ) as $row ) {
            $key = sanitize_key( (string) ( $row[ CustomFields::SERVICE_OPTION_KEY ] ?? '' ) );
            if ( '' !== $key ) {
                $options[ $key ] = (string) ( $row[ CustomFields::SERVICE_OPTION_LABEL ] ?? $key );
            }
        }

        return $options;
    }

    /**
     * true_false con valor por defecto si el campo nunca se guardó.
     *
     * @param   string $name    Campo.
     * @param   int    $id      Servicio.
     * @param   bool   $default Valor por defecto.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function read_bool( string $name, int $id, bool $default ): bool {
        if ( '' === (string) get_post_meta( $id, $name, true ) && false === metadata_exists( 'post', $id, $name ) ) {
            return $default;
        }

        return (bool) get_field( $name, $id );
    }
}
