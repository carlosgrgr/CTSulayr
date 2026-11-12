<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\MemberConsents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consentimiento de uso de imagen en Mi cuenta → Perfil de deportista
 * (D10, D33): el socio lo da o lo revoca. Cada cambio es una fila nueva con
 * la versión vigente del texto.
 */
class ImageConsentAccount {

    private const ACTION = 'sulayr_image_consent';
    private const DONE_QUERY = 'imagen';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'sulayr_athlete_profile_edit_after', [ $this, 'render' ] );
        add_action( 'template_redirect', [ $this, 'handle' ], 6 );
    }

    /**
     * Bloque con la casilla.
     *
     * @param   int $user_id Socio que mira su perfil.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render( $user_id ): void {
        $user_id = (int) $user_id;
        if ( $user_id !== get_current_user_id() ) {
            return;
        }

        $latest = MemberConsents::latest( $user_id, MemberConsents::IMAGE );
        $accepted = null !== $latest && (bool) $latest->accepted;

        echo '<div class="sulayr-image-consent">';
        echo '<h3 class="sulayr-info-block-title">' . esc_html__( 'Uso de mi imagen', SULAYR_TEXTDOMAIN ) . '</h3>';
        if ( 'guardado' === ( $_GET[ self::DONE_QUERY ] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo muestra el aviso.
            echo '<div class="sulayr-alert sulayr-alert-success" role="status">' . esc_html__( 'Preferencia guardada.', SULAYR_TEXTDOMAIN ) . '</div>';
        }
        echo '<form method="post">';
        wp_nonce_field( self::ACTION );
        printf( '<input type="hidden" name="%s" value="1">', esc_attr( self::ACTION ) );
        printf(
            '<div class="form-check"><input class="form-check-input" type="checkbox" id="sulayr-image-consent" name="accepted" value="1"%s> <label class="form-check-label" for="sulayr-image-consent">%s</label></div>',
            checked( $accepted, true, false ),
            wp_kses_post( wp_strip_all_tags( MemberConsents::text( MemberConsents::IMAGE ), true ) )
        );
        if ( null !== $latest ) {
            printf(
                '<p class="sulayr-image-consent-date">%s</p>',
                esc_html( sprintf( $accepted ? __( 'Autorizado el %s.', SULAYR_TEXTDOMAIN ) : __( 'No autorizado desde el %s.', SULAYR_TEXTDOMAIN ), date_i18n( 'd/m/Y', strtotime( (string) $latest->created_at ) ) ) )
            );
        }
        printf( '<p><button type="submit" class="button">%s</button></p>', esc_html__( 'Guardar preferencia', SULAYR_TEXTDOMAIN ) );
        echo '</form></div>';
    }

    /**
     * Guarda el cambio si lo hay.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST[ self::ACTION ] ) || false === is_user_logged_in() ) {
            return;
        }

        check_admin_referer( self::ACTION );

        $user_id = get_current_user_id();
        if ( false === in_array( 'athlete', (array) wp_get_current_user()->roles, true ) ) {
            return;
        }

        $accepted = ! empty( $_POST['accepted'] );
        $latest = MemberConsents::latest( $user_id, MemberConsents::IMAGE );
        $current = null !== $latest && (bool) $latest->accepted;
        if ( $accepted !== $current || null === $latest ) {
            MemberConsents::record( MemberConsents::IMAGE, $accepted, MemberConsents::BY_MEMBER, $user_id );
        }

        $url = remove_query_arg( [ self::DONE_QUERY, 'updated', 'tab' ], wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se valida con wp_safe_redirect.
        wp_safe_redirect( add_query_arg( [ self::DONE_QUERY => 'guardado', 'tab' => 'edit' ], home_url( $url ) ) );
        exit;
    }
}
