<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Emails de socios con los textos de Socios → Ajustes y sus marcadores
 * ({nombre}, {bloques_servicios}...). El remitente lo pone EmailConfig.
 */
class MemberMailer {

    /**
     * Envía un email de los ajustes (email_<clave>_subject / _body).
     *
     * @param   string               $key          Clave, p. ej. «welcome».
     * @param   string[]             $to           Destinatarios.
     * @param   array<string,string> $placeholders Marcador (sin llaves) => valor. Los que acaban en «_html» no se escapan.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function send( string $key, array $to, array $placeholders ): bool {
        $to = array_values( array_unique( array_filter( array_map( 'sanitize_email', $to ), 'is_email' ) ) );
        if ( empty( $to ) ) {
            return false;
        }

        $subject = self::fill( (string) MemberSettings::get( "email_{$key}_subject" ), $placeholders, false );
        $body = self::fill( (string) MemberSettings::get( "email_{$key}_body" ), $placeholders, true );

        return (bool) wp_mail( $to, wp_strip_all_tags( $subject ), wpautop( $body ), [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Sustituye los marcadores.
     *
     * @param   string               $template     Texto.
     * @param   array<string,string> $placeholders Valores.
     * @param   bool                 $html         True en el cuerpo: escapa los valores que no son HTML.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function fill( string $template, array $placeholders, bool $html ): string {
        $pairs = [];
        foreach ( $placeholders as $name => $value ) {
            $is_html = '_html' === substr( $name, -5 );
            $tag = $is_html ? substr( $name, 0, -5 ) : $name;
            if ( $html ) {
                $pairs[ '{' . $tag . '}' ] = $is_html ? (string) $value : esc_html( (string) $value );
            } else {
                $pairs[ '{' . $tag . '}' ] = $is_html ? wp_strip_all_tags( (string) $value ) : (string) $value;
            }
        }

        return strtr( $template, $pairs );
    }

    /**
     * Enlace HTML o texto vacío.
     *
     * @param   string $url  URL.
     * @param   string $text Texto del enlace ('' = la URL).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function link( string $url, string $text = '' ): string {
        if ( '' === $url ) {
            return '';
        }

        return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( '' !== $text ? $text : $url ) );
    }
}
