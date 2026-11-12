<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Formulario público de solicitud de alta (spec §5.1): shortcode
 * [sulayr_solicitud_alta].
 *
 * Se envía a la propia página (no a admin-post.php) y no usa nonce: es un
 * formulario para visitantes sin sesión, y una página guardada en caché
 * tendría un nonce caducado. El antispam es el campo trampa y el límite de
 * envíos por IP. Al enviar no se crea usuario: se guarda la solicitud con sus
 * consentimientos y se avisa solo al club (D11, B1).
 */
class PublicJoinForm {

    public const SHORTCODE = 'sulayr_solicitud_alta';
    private const SENT_QUERY = 'solicitud';
    /** Campo oculto que marca el envío, para no depender de dónde está el shortcode. */
    private const MARKER = 'sulayr_join_public';
    private const ANCHOR = 'sulayr-solicitud-alta';

    /** @var array{data: array, consents: array, errors: array}|null Resultado del envío fallido. */
    private static ?array $state = null;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_shortcode( self::SHORTCODE, [ $this, 'render' ] );
        add_action( 'template_redirect', [ $this, 'handle' ], 5 );
    }

    /**
     * Procesa el envío y evita la caché de la página del formulario.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        $post = get_queried_object();
        if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
            self::no_cache();
            nocache_headers();
        }

        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST[ self::MARKER ] ) || false === isset( $_POST[ JoinForm::INPUT ] ) || self::is_member() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Formulario público sin nonce (ver la clase).
            return;
        }

        self::no_cache();
        nocache_headers();

        $page_url = remove_query_arg( self::SENT_QUERY, home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se redirige con wp_safe_redirect.
        $page_url = strtok( $page_url, '#' );
        $done_url = add_query_arg( self::SENT_QUERY, 'enviada', $page_url ) . '#' . self::ANCHOR;

        // Campo trampa relleno: se responde como si fuera bien y no se guarda nada.
        if ( '' !== trim( (string) wp_unslash( $_POST[ JoinForm::HONEYPOT ] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            wp_safe_redirect( $done_url );
            exit;
        }

        $ip = self::ip();
        $input = (array) wp_unslash( $_POST[ JoinForm::INPUT ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JoinForm::parse limpia cada campo.
        $parsed = JoinForm::parse( $input, JoinForm::CONTEXT_PUBLIC );

        if ( JoinRequests::recent_from_ip( $ip ) >= max( 1, (int) MemberSettings::get( 'join_rate_limit' ) ) ) {
            $parsed['errors']['form'] = __( 'Se han enviado demasiadas solicitudes desde esta conexión. Inténtalo dentro de una hora o escribe al club.', SULAYR_TEXTDOMAIN );
        }

        if ( ! empty( $parsed['errors'] ) ) {
            self::$state = $parsed;
            return;
        }

        self::save( $parsed, $ip );

        wp_safe_redirect( $done_url );
        exit;
    }

    /**
     * Guarda la solicitud y sus consentimientos y avisa al club.
     *
     * @param   array  $parsed Resultado de JoinForm::parse() sin errores.
     * @param   string $ip     IP ('' desde wp-admin).
     * @return 	int    Solicitud.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function save( array $parsed, string $ip ): int {
        $data = $parsed['data'];
        $request_id = JoinRequests::create( $data, $ip );

        $given_by = ! empty( $data['minor'] ) ? MemberConsents::BY_GUARDIAN : MemberConsents::BY_MEMBER;
        foreach ( $parsed['consents'] as $type => $accepted ) {
            MemberConsents::record( (string) $type, (bool) $accepted, $given_by, 0, $request_id );
        }

        if ( JoinForm::CONTEXT_PUBLIC === $data['origin'] ) {
            MemberMailer::send( 'request_club', [ (string) MemberSettings::get( 'club_email' ) ], [
                'tipo' => __( 'alta', SULAYR_TEXTDOMAIN ),
                'nombre' => (string) $data['first_name'],
                'nombre_completo' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
            ] );
        }

        return $request_id;
    }

    /**
     * Shortcode.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): string {
        self::no_cache();
        ob_start();

        echo '<div class="sulayr-join" id="' . esc_attr( self::ANCHOR ) . '">';

        if ( self::is_member() ) {
            $account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
            printf(
                '<div class="sulayr-join-notice">%s <a href="%s">%s</a></div></div>',
                esc_html__( 'Ya eres socio/a del club.', SULAYR_TEXTDOMAIN ),
                esc_url( $account ),
                esc_html__( 'Ir a Mi cuenta', SULAYR_TEXTDOMAIN )
            );
            return (string) ob_get_clean();
        }

        if ( 'enviada' === ( $_GET[ self::SENT_QUERY ] ?? '' ) && null === self::$state ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo muestra el mensaje.
            echo '<div class="sulayr-join-notice sulayr-join-success" role="status">' . wp_kses_post( wpautop( (string) MemberSettings::get( 'join_success_message' ) ) ) . '</div></div>';
            return (string) ob_get_clean();
        }

        if ( null === \ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog::base() ) {
            echo '<div class="sulayr-join-notice">' . esc_html__( 'El formulario de alta no está disponible ahora mismo. Escribe al club.', SULAYR_TEXTDOMAIN ) . '</div></div>';
            return (string) ob_get_clean();
        }

        JoinForm::enqueue_script();

        $state = self::$state ?? [ 'data' => [], 'consents' => [], 'errors' => [] ];

        echo '<div class="sulayr-join-intro">' . wp_kses_post( wpautop( (string) MemberSettings::get( 'join_form_intro' ) ) ) . '</div>';

        if ( ! empty( $state['errors'] ) ) {
            echo '<div class="sulayr-join-notice sulayr-join-errors" role="alert"><p>' . esc_html__( 'Revisa los datos marcados:', SULAYR_TEXTDOMAIN ) . '</p>';
            if ( isset( $state['errors']['form'] ) ) {
                echo '<p>' . esc_html( $state['errors']['form'] ) . '</p>';
            }
            echo '</div>';
        }

        printf( '<form class="sulayr-join-form" method="post" action="%s" novalidate>', esc_url( remove_query_arg( self::SENT_QUERY ) . '#' . self::ANCHOR ) );
        printf( '<input type="hidden" name="%s" value="1">', esc_attr( self::MARKER ) );
        JoinForm::render( $state['data'], $state['consents'], $state['errors'], JoinForm::CONTEXT_PUBLIC );
        printf(
            '<div class="sulayr-join-hp" aria-hidden="true"><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off"></div>',
            esc_attr( JoinForm::HONEYPOT ),
            esc_html__( 'Deja este campo vacío', SULAYR_TEXTDOMAIN )
        );
        printf( '<p class="sulayr-join-submit"><button type="submit" class="button sulayr-join-button">%s</button></p>', esc_html__( 'Enviar solicitud', SULAYR_TEXTDOMAIN ) );
        echo '</form></div>';

        return (string) ob_get_clean();
    }

    /**
     * True si quien mira ya es socio en vigor. Un antiguo socio (de baja)
     * puede pedir el reingreso.
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_member(): bool {
        if ( false === is_user_logged_in() ) {
            return false;
        }

        return DuplicateFinder::KIND_MEMBER === DuplicateFinder::classify( wp_get_current_user() )['kind'];
    }

    /**
     * Pide a los plugins de caché que no guarden esta página.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function no_cache(): void {
        if ( false === defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
    }

    /**
     * IP del visitante (la que ve el servidor; no se fía de cabeceras).
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function ip(): string {
        $ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

        return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }
}
