<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Emails de socios con los textos de Socios → Ajustes y sus marcadores
 * ({nombre}, {bloques_servicios}...). El remitente lo pone EmailConfig.
 *
 * Todos salen con el diseño de marca Sulayr (layout()): banda Noche con el
 * escudo, franja tricolor, el asunto como titular, texto Noche sobre blanco,
 * acción principal como botón Sol de Alba (button()) y firma del club al pie.
 * El escudo va incrustado en el email (cid:) para que se vea sin descargar
 * imágenes; si no se puede incrustar, se enlaza por URL.
 */
class MemberMailer {

    /** Content-ID del escudo incrustado. */
    private const LOGO_CID = 'sulayr-escudo';

    /**
     * Envía un email de los ajustes (email_<clave>_subject / _body).
     *
     * @param   string               $key          Clave, p. ej. «welcome».
     * @param   string[]             $to           Destinatarios.
     * @param   array<string,string> $placeholders Marcador (sin llaves) => valor. Los que acaban en «_html» no se escapan.
     * @param   bool                 $branded      Con el diseño de marca (por defecto). false = HTML simple.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function send( string $key, array $to, array $placeholders, bool $branded = true ): bool {
        $to = array_values( array_unique( array_filter( array_map( 'sanitize_email', $to ), 'is_email' ) ) );
        if ( empty( $to ) ) {
            return false;
        }

        $subject = wp_strip_all_tags( self::fill( (string) MemberSettings::get( "email_{$key}_subject" ), $placeholders, false ) );
        $body = wpautop( self::fill( (string) MemberSettings::get( "email_{$key}_body" ), $placeholders, true ) );

        if ( false === $branded ) {
            return (bool) wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
        }

        // El escudo se incrusta en el mensaje (PHPMailer) solo durante este envío.
        $logo_file = self::logo_file();
        $embed = null;
        if ( '' !== $logo_file ) {
            $embed = function ( $phpmailer ) use ( $logo_file ) {
                $phpmailer->addEmbeddedImage( $logo_file, self::LOGO_CID, 'escudo-sulayr.' . pathinfo( $logo_file, PATHINFO_EXTENSION ) );
            };
            add_action( 'phpmailer_init', $embed );
        }

        $sent = (bool) wp_mail( $to, $subject, self::layout( $body, $subject, null !== $embed ? 'cid:' . self::LOGO_CID : '' ), [ 'Content-Type: text/html; charset=UTF-8' ] );

        if ( null !== $embed ) {
            remove_action( 'phpmailer_init', $embed );
        }

        return $sent;
    }

    /**
     * Diseño de marca Sulayr para el cuerpo de un email (manual de marca:
     * colores, Archivo / Instrument Sans / JetBrains Mono con respaldo Arial
     * Black, Arial y Consolas, firma con filete Menta). Estilos en línea y
     * tablas, para los clientes de correo.
     *
     * @param   string $body  HTML del cuerpo.
     * @param   string $title Titular (el asunto).
     * @param   string $logo  src del escudo ('' = URL de la biblioteca).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function layout( string $body, string $title = '', string $logo = '' ): string {
        if ( '' === $logo ) {
            $logo_id = (int) MemberSettings::get( 'club_logo' );
            $logo = $logo_id > 0 ? (string) wp_get_attachment_url( $logo_id ) : '';
        }
        $club = (string) MemberSettings::get( 'club_name' );
        $club_email = (string) MemberSettings::get( 'club_email' );
        $site = home_url( '/' );
        $host = (string) wp_parse_url( $site, PHP_URL_HOST );

        $display = "'Archivo','Arial Black',Arial,Helvetica,sans-serif";
        $subtitle = "'Archivo',Arial,Helvetica,sans-serif";
        $text = "'Instrument Sans',Arial,Helvetica,sans-serif";
        $mono = "'JetBrains Mono',Consolas,'Courier New',monospace";

        // Enlaces del texto en el acento legible sobre blanco (los botones ya llevan su estilo).
        $body = str_replace( '<a href=', '<a style="color:#0A7C8A;font-weight:600;text-decoration:underline;" href=', $body );
        // Párrafos y titulares del texto de Ajustes con la tipografía de marca.
        $body = preg_replace( '/<p>/', '<p style="margin:0 0 16px;">', $body );
        $body = preg_replace( '/<h([23])>/', '<h$1 style="font-family:' . $subtitle . ';font-weight:700;font-size:18px;line-height:1.3;color:#0B1638;margin:24px 0 8px;">', $body );

        $escudo = '' !== $logo
            ? '<img src="' . esc_attr( $logo ) . '" width="64" height="64" alt="' . esc_attr( $club ) . '" style="display:block;border:0;outline:none;width:64px;height:64px;color:#F3F7FA;font-family:Arial,sans-serif;font-size:14px;">'
            : '';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title><?php echo esc_html( $title ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,400..900;1,62..125,400..900&amp;family=Instrument+Sans:wght@400;600&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#F3F7FA;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3F7FA;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#FFFFFF;border:1px solid #D6DCE5;border-radius:6px;">
<tr><td style="background:#0B1638;padding:20px 28px;border-radius:6px 6px 0 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="64" valign="middle"><?php echo $escudo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba. ?></td>
<td valign="middle" align="right" style="font-family:<?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#4FE6F2;"><?php echo esc_html( $club ); ?></td>
</tr></table>
</td></tr>
<tr><td style="padding:0;font-size:0;line-height:0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="33%" height="4" style="background:#4FE6F2;height:4px;font-size:0;line-height:0;">&nbsp;</td><td width="34%" height="4" style="background:#1EE8A3;height:4px;font-size:0;line-height:0;">&nbsp;</td><td width="33%" height="4" style="background:#F46F5C;height:4px;font-size:0;line-height:0;">&nbsp;</td>
</tr></table></td></tr>
<?php if ( '' !== $title ) : ?>
<tr><td style="padding:28px 28px 0;font-family:<?php echo $display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>;font-weight:900;font-style:italic;font-stretch:118%;font-size:26px;line-height:1.1;color:#0B1638;"><?php echo esc_html( $title ); ?></td></tr>
<?php endif; ?>
<tr><td style="padding:20px 28px 12px;font-family:<?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>;font-size:16px;line-height:1.6;color:#0B1638;"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del email, con los valores escapados en fill(). ?></td></tr>
<tr><td style="padding:8px 28px 28px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="border-left:3px solid #1EE8A3;padding:2px 0 2px 12px;">
<div style="font-family:<?php echo $subtitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>;font-weight:700;font-style:italic;font-size:16px;color:#0B1638;"><?php echo esc_html( $club ); ?></div>
<div style="font-family:<?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>;font-size:12px;line-height:1.6;color:#566079;"><?php echo esc_html( 'Granada' ); ?><?php if ( '' !== $club_email ) : ?> · <a href="mailto:<?php echo esc_attr( $club_email ); ?>" style="color:#566079;text-decoration:none;"><?php echo esc_html( $club_email ); ?></a><?php endif; ?><?php if ( '' !== $host ) : ?> · <a href="<?php echo esc_url( $site ); ?>" style="color:#566079;text-decoration:none;"><?php echo esc_html( $host ); ?></a><?php endif; ?></div>
</td></tr></table>
</td></tr>
</table>
</td></tr></table>
</body></html>
        <?php
        return (string) ob_get_clean();
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

    /**
     * Botón de acción principal (Sol de Alba con texto Noche), o texto vacío.
     *
     * @param   string $url  URL.
     * @param   string $text Texto del botón.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function button( string $url, string $text ): string {
        if ( '' === $url ) {
            return '';
        }

        return sprintf(
            '<a style="display:inline-block;background:#F46F5C;color:#0B1638;font-family:\'Instrument Sans\',Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;line-height:1.2;text-decoration:none;padding:13px 22px;border-radius:4px;" href="%s">%s</a>',
            esc_url( $url ),
            esc_html( $text )
        );
    }

    /**
     * Archivo local del escudo (Socios → Ajustes → Datos del club), o ''.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function logo_file(): string {
        $id = (int) MemberSettings::get( 'club_logo' );
        $file = $id > 0 ? (string) get_attached_file( $id ) : '';

        return '' !== $file && is_readable( $file ) && in_array( (string) get_post_mime_type( $id ), [ 'image/png', 'image/jpeg', 'image/gif' ], true ) ? $file : '';
    }
}
