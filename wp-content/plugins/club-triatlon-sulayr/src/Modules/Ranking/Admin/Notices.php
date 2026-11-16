<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Avisos y estado de formularios de las pantallas del ranking, por usuario
 * (mismo patrón que Members\Admin\RequestsPage).
 */
class Notices {

    private const NOTICE_TRANSIENT = 'sulayr_ranking_notice_';
    private const STATE_TRANSIENT = 'sulayr_ranking_state_';

    /**
     * Guarda un aviso para la siguiente pantalla.
     *
     * @param   string $type success, warning o error.
     * @param   string $text Texto.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function set( string $type, string $text ): void {
        set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), [ 'type' => $type, 'text' => $text ], 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Guarda un aviso y redirige.
     *
     * @param   string $url  Destino.
     * @param   string $type success, warning o error.
     * @param   string $text Texto.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function redirect( string $url, string $type, string $text ): void {
        self::set( $type, $text );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Pinta y borra el aviso pendiente.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function render(): void {
        $key = self::NOTICE_TRANSIENT . get_current_user_id();
        $notice = get_transient( $key );
        if ( false === is_array( $notice ) ) {
            return;
        }
        delete_transient( $key );

        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['text'] ) );
    }

    /**
     * Guarda lo enviado para volver a pintarlo con los errores.
     *
     * @param   array $state Datos y errores.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function save_state( array $state ): void {
        set_transient( self::STATE_TRANSIENT . get_current_user_id(), $state, 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Recupera y borra lo enviado.
     *
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function take_state(): array {
        $key = self::STATE_TRANSIENT . get_current_user_id();
        $state = get_transient( $key );
        delete_transient( $key );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Corta la petición si el usuario no puede gestionar el ranking.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function require_cap(): void {
        if ( false === current_user_can( \ClubTriatlonSulayr\Modules\Members\MemberRoles::MANAGE_RANKING ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }
    }
}
