<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServicePostType;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catálogo inicial (modelo 2025/26): cuota base 22 €, entrenamiento genérico
 * 11 € y natación 25 € (junta 29,50 €). Solo crea lo que falta; no toca
 * servicios que ya existen. Después se edita todo desde wp-admin.
 */
class CatalogSeeder {

    /** Fecha desde la que valen los precios iniciales. */
    public const PRICES_FROM = '2000-01-01';

    /**
     * Definición de los servicios iniciales.
     *
     * @return 	array<string, array>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function definitions(): array {
        $bookings_url = self::bookings_url();

        return [
            LegacyMapping::SLUG_BASE => [
                'title' => 'Cuota base',
                'order' => 1,
                'is_base' => true,
                'prices' => [
                    [ ServiceCatalog::GENERAL, 22 ],
                    [ LegacyMapping::TYPE_BOARD, 0 ],
                ],
                'prorated' => true,
                'cancel_on_freeze' => true,
                'public' => true,
                'options' => [],
                'grants_swimming' => false,
                'coach_list' => false,
                'welcome_text' => '',
                'steps' => [],
            ],
            LegacyMapping::SLUG_TRAINING => [
                'title' => 'Entrenamiento genérico',
                'order' => 2,
                'is_base' => false,
                'prices' => [
                    [ ServiceCatalog::GENERAL, 11 ],
                ],
                'prorated' => true,
                'cancel_on_freeze' => false,
                'public' => true,
                'options' => [
                    [ 'sprint', 'Sprint' ],
                    [ 'olimpico', 'Olímpico' ],
                ],
                'grants_swimming' => false,
                'coach_list' => true,
                'welcome_text' => '<p><strong>Plan de entrenamiento.</strong> Tu plan está en TrainingPeaks. Crea tu cuenta y, con la sesión iniciada, abre el enlace de vinculación con el entrenador que te enviaremos.</p>',
                'steps' => [ 'Comprobar que TrainingPeaks está vinculado' ],
            ],
            LegacyMapping::SLUG_SWIMMING => [
                'title' => 'Natación',
                'order' => 3,
                'is_base' => false,
                'prices' => [
                    [ ServiceCatalog::GENERAL, 25 ],
                    [ LegacyMapping::TYPE_BOARD, 29.5 ],
                ],
                'prorated' => false,
                'cancel_on_freeze' => false,
                'public' => true,
                'options' => [],
                'grants_swimming' => true,
                'coach_list' => false,
                'welcome_text' => '<p><strong>Natación.</strong> Reserva tus sesiones en la web de reservas de CN Nazarí' . ( '' !== $bookings_url ? ': <a href="' . esc_url( $bookings_url ) . '">' . esc_html( $bookings_url ) . '</a>' : '' ) . '. Entra con el mismo usuario que en la web del club.</p>',
                'steps' => [],
            ],
        ];
    }

    /**
     * Servicios iniciales que faltan.
     *
     * @return 	string[] Claves.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function missing(): array {
        $missing = [];
        foreach ( array_keys( self::definitions() ) as $slug ) {
            if ( null === get_page_by_path( $slug, OBJECT, ServicePostType::POST_TYPE_NAME ) ) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }

    /**
     * Crea los servicios que faltan y guarda los tipos de socio por defecto si
     * la lista está vacía.
     *
     * @return 	string[] Claves creadas.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function ensure(): array {
        if ( empty( get_field( 'member_types', MemberSettings::POST_ID ) ) ) {
            update_field( 'field_sulayr_members_member_types', [
                [ 'field_sulayr_members_member_type_key' => LegacyMapping::TYPE_NORMAL, 'field_sulayr_members_member_type_label' => 'Normal' ],
                [ 'field_sulayr_members_member_type_key' => LegacyMapping::TYPE_BOARD, 'field_sulayr_members_member_type_label' => 'Junta directiva' ],
            ], MemberSettings::POST_ID );
        }

        $created = [];
        $definitions = self::definitions();
        foreach ( self::missing() as $slug ) {
            self::create( $slug, $definitions[ $slug ] );
            $created[] = $slug;
        }

        ServiceCatalog::flush();

        return $created;
    }

    /**
     * Crea un servicio.
     *
     * @param   string $slug Clave.
     * @param   array  $def  Definición.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function create( string $slug, array $def ): int {
        $post_id = wp_insert_post( [
            'post_type' => ServicePostType::POST_TYPE_NAME,
            'post_status' => 'publish',
            'post_title' => $def['title'],
            'post_name' => $slug,
            'menu_order' => $def['order'],
        ], true );

        if ( is_wp_error( $post_id ) ) {
            throw new \RuntimeException( $post_id->get_error_message() );
        }

        $k = [ ServiceFields::class, 'key' ];

        update_field( $k( CustomFields::SERVICE_IS_BASE ), $def['is_base'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_PRICES ), array_map( function ( $price ) use ( $k ) {
            return [
                $k( CustomFields::SERVICE_PRICE_MEMBER_TYPE ) => $price[0],
                $k( CustomFields::SERVICE_PRICE_AMOUNT ) => $price[1],
                $k( CustomFields::SERVICE_PRICE_VALID_FROM ) => str_replace( '-', '', self::PRICES_FROM ),
            ];
        }, $def['prices'] ), $post_id );
        update_field( $k( CustomFields::SERVICE_PRORATED ), $def['prorated'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_CANCEL_ON_FREEZE ), $def['cancel_on_freeze'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_PUBLIC ), $def['public'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_OPTIONS ), array_map( function ( $option ) use ( $k ) {
            return [
                $k( 'option_' . CustomFields::SERVICE_OPTION_KEY ) => $option[0],
                $k( 'option_' . CustomFields::SERVICE_OPTION_LABEL ) => $option[1],
            ];
        }, $def['options'] ), $post_id );
        update_field( $k( CustomFields::SERVICE_GRANTS_SWIMMING ), $def['grants_swimming'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_COACH_LIST ), $def['coach_list'] ? 1 : 0, $post_id );
        update_field( $k( CustomFields::SERVICE_WELCOME_TEXT ), $def['welcome_text'], $post_id );
        update_field( $k( CustomFields::SERVICE_STEPS ), array_map( function ( $label ) use ( $k ) {
            return [ $k( 'step_' . CustomFields::SERVICE_STEP_LABEL ) => $label ];
        }, $def['steps'] ), $post_id );

        return (int) $post_id;
    }

    /**
     * URL del sitio de reservas si el plugin de reservas está configurado.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function bookings_url(): string {
        $blog_id = (int) get_site_option( 'sulayr_reservas_bookings_blog_id' );
        if ( $blog_id <= 0 || false === is_multisite() ) {
            return '';
        }

        return (string) get_home_url( $blog_id );
    }
}
