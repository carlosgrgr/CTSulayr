<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Services\HelperService;
use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PDF del recibo (8.6, D49, D51). Se genera en el momento con dompdf a partir
 * de los datos guardados; no se guarda en disco. Quién puede verlo lo
 * comprueba quien lo llama (wp-admin: gestor; Mis cuotas: el propio socio).
 *
 * Identidad Sulayr para documentos: cabecera con escudo y filete Noche,
 * título en Archivo Black Italic, texto en Instrument Sans, cifras y
 * etiquetas en JetBrains Mono, tabla con cabecera Noche y filas alternas
 * Nieve, y pie con la franja tricolor. Las fuentes (TTF con sus métricas .ufm,
 * licencia OFL) están en assets/pdf-fonts/; si faltan, sale con DejaVu.
 */
class ReceiptPdf {

    /** Familias de fuente del PDF => archivos (sin extensión) por variante. */
    private const FONTS = [
        'sulayr-display' => [ 'normal' => 'Archivo-BlackItalic', 'bold' => 'Archivo-BlackItalic', 'italic' => 'Archivo-BlackItalic', 'bold_italic' => 'Archivo-BlackItalic' ],
        'sulayr-subtitle' => [ 'normal' => 'Archivo-Bold', 'bold' => 'Archivo-Bold', 'italic' => 'Archivo-Bold', 'bold_italic' => 'Archivo-Bold' ],
        'sulayr-text' => [ 'normal' => 'InstrumentSans-Regular', 'bold' => 'InstrumentSans-SemiBold', 'italic' => 'InstrumentSans-Regular', 'bold_italic' => 'InstrumentSans-SemiBold' ],
        'sulayr-mono' => [ 'normal' => 'JetBrainsMono-Regular', 'bold' => 'JetBrainsMono-Regular', 'italic' => 'JetBrainsMono-Regular', 'bold_italic' => 'JetBrainsMono-Regular' ],
    ];

    /**
     * Envía el PDF al navegador y termina.
     *
     * @param   object $receipt Recibo.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function stream( object $receipt ): void {
        $dompdf = self::render( $receipt );

        nocache_headers();
        $dompdf->stream( sanitize_file_name( (string) $receipt->number ) . '.pdf', [ 'Attachment' => false ] );
        exit;
    }

    /**
     * Genera el PDF en memoria.
     *
     * @param   object $receipt Recibo.
     * @return 	Dompdf
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function render( object $receipt ): Dompdf {
        // La caché de métricas de dompdf se escribe en el directorio temporal, no en el plugin.
        $cache = trailingslashit( get_temp_dir() ) . 'sulayr-pdf-fonts';
        if ( false === is_dir( $cache ) ) {
            wp_mkdir_p( $cache );
        }

        $options = new Options();
        $options->set( 'isRemoteEnabled', false );
        $options->set( 'isPhpEnabled', false );
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'tempDir', get_temp_dir() );
        $options->set( 'fontCache', is_writable( $cache ) ? $cache : get_temp_dir() );
        $options->set( 'chroot', [ SULAYR_DIR ] );

        $dompdf = new Dompdf( $options );
        $brand = self::register_fonts( $dompdf );
        $dompdf->loadHtml( self::html( $receipt, $brand ), 'UTF-8' );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        return $dompdf;
    }

    /**
     * HTML del recibo.
     *
     * @param   object $receipt Recibo.
     * @param   bool   $brand   Usar las fuentes de marca (si no, DejaVu Sans).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function html( object $receipt, bool $brand = true ): string {
        $user_id = (int) $receipt->user_id;
        $user = get_userdata( $user_id );
        $meta = function ( string $key ) use ( $user_id ): string {
            return (string) get_user_meta( $user_id, $key, true );
        };
        $e = function ( string $text ): string {
            return esc_html( $text );
        };

        $club_name = (string) MemberSettings::get( 'club_name' );
        $club = [
            $club_name,
            '' !== (string) MemberSettings::get( 'club_cif' ) ? 'CIF ' . MemberSettings::get( 'club_cif' ) : '',
            (string) MemberSettings::get( 'club_address' ),
        ];
        $member = [
            HelperService::get_user_full_name( $user_id ) ?: ( false !== $user ? $user->display_name : '' ),
            '' !== $meta( CustomFields::USER_PROFILE_DNI ) ? sprintf( __( 'DNI %s', SULAYR_TEXTDOMAIN ), $meta( CustomFields::USER_PROFILE_DNI ) ) : '',
            false !== $user ? $user->user_email : '',
        ];
        $guardian = $meta( CustomFields::MEMBER_GUARDIAN . '_name' );
        if ( '' !== $guardian ) {
            $member[] = sprintf( __( 'Tutor: %1$s %2$s', SULAYR_TEXTDOMAIN ), $guardian, $meta( CustomFields::MEMBER_GUARDIAN . '_dni' ) );
        }

        $rows = '';
        foreach ( Receipts::lines( (int) $receipt->id ) as $line ) {
            $cancelled = (int) $line->cancelled > 0;
            $rows .= sprintf(
                '<tr%s><td>%s%s</td><td class="num">%s</td></tr>',
                $cancelled ? ' class="cancelled"' : '',
                $e( (string) $line->concept ),
                $cancelled ? ' <span class="note">' . $e( __( 'anulada', SULAYR_TEXTDOMAIN ) ) . '</span>' : '',
                $e( Receipts::money( (float) $line->amount ) )
            );
        }

        $payments = '';
        foreach ( Receipts::payments( (int) $receipt->id ) as $payment ) {
            $payments .= sprintf( '<tr><td class="mono">%s</td><td>%s</td><td class="num">%s</td></tr>', $e( MemberDates::show( (string) $payment->paid_on ) ), $e( Receipts::method_label( (string) $payment->method ) ), $e( Receipts::money( (float) $payment->amount ) ) );
        }

        $logo = self::logo();
        $status = Receipts::display_status( $receipt );
        $outstanding = Receipts::STATUS_ANNULLED !== $receipt->status ? Receipts::outstanding( $receipt ) : 0.0;
        $footer = (string) MemberSettings::get( 'receipt_footer' );
        $host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        $number = $meta( CustomFields::MEMBER_NUMBER );

        $display = $brand ? '"sulayr-display", "DejaVu Sans"' : '"DejaVu Sans"';
        $subtitle = $brand ? '"sulayr-subtitle", "DejaVu Sans"' : '"DejaVu Sans"';
        $text = $brand ? '"sulayr-text", "DejaVu Sans"' : '"DejaVu Sans"';
        $mono = $brand ? '"sulayr-mono", "DejaVu Sans Mono"' : '"DejaVu Sans Mono"';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title><?php echo $e( (string) $receipt->number ); ?></title>
<style>
@page { margin: 16mm 16mm 22mm; }
body { font-family: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 10.5pt; line-height: 1.5; color: #0B1638; }
.mono, .num, .label, .meta { font-family: <?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; }
.head { width: 100%; border-bottom: 2pt solid #0B1638; padding-bottom: 8px; }
.head td { vertical-align: bottom; }
.logo { height: 64px; }
.meta { text-align: right; font-size: 8.5pt; color: #566079; letter-spacing: 1px; text-transform: uppercase; }
.meta strong { display: block; font-size: 12pt; color: #0B1638; letter-spacing: 0; text-transform: none; }
h1 { font-family: <?php echo $display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 30pt; line-height: 1; margin: 18px 0 2px; color: #0B1638; }
.period { font-family: <?php echo $subtitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 13pt; margin: 0 0 14px; }
.parties { width: 100%; margin: 6px 0 18px; }
.parties td { vertical-align: top; width: 50%; padding-right: 14px; }
.label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1.5px; color: #566079; margin-bottom: 3px; }
.lines { width: 100%; border-collapse: collapse; }
.lines th { background: #0B1638; color: #F3F7FA; text-align: left; padding: 6px 8px; font-family: <?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 7.5pt; letter-spacing: 1.5px; text-transform: uppercase; font-weight: normal; }
.lines td { padding: 6px 8px; border-bottom: 1px solid #D7DEE8; }
.lines tr.even td { background: #F3F7FA; }
.lines .num { text-align: right; white-space: nowrap; }
.cancelled td { color: #8C93A3; text-decoration: line-through; }
.note { font-family: <?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; }
.total td { border-top: 2pt solid #0B1638; border-bottom: none; padding-top: 8px; }
.total .t-label { font-family: <?php echo $subtitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 12pt; }
.total .num { font-size: 13pt; font-weight: bold; }
.status { margin: 14px 0 18px; }
.badge { font-family: <?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 8pt; text-transform: uppercase; letter-spacing: 1.5px; padding: 3px 9px; background: #F3F7FA; border: 1px solid #D7DEE8; color: #0B1638; }
.badge-pagado { background: #1EE8A3; border-color: #1EE8A3; }
.badge-impagado { background: #F46F5C; border-color: #F46F5C; }
.badge-parcial { background: #4FE6F2; border-color: #4FE6F2; }
.badge-anulado { color: #566079; }
.footer { position: fixed; bottom: -14mm; left: 0; right: 0; }
.stripe { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.stripe td { height: 4px; padding: 0; }
.foot { width: 100%; font-family: <?php echo $mono; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?>; font-size: 7.5pt; color: #566079; }
.small { font-size: 8.5pt; color: #566079; }
</style></head><body>

<div class="footer">
<table class="stripe"><tr><td style="background:#4FE6F2"></td><td style="background:#1EE8A3"></td><td style="background:#F46F5C"></td></tr></table>
<table class="foot"><tr><td><?php echo $e( $club_name . ' · Granada' ); ?></td><td style="text-align:right"><?php echo $e( $host ); ?></td></tr></table>
</div>

<table class="head"><tr>
<td><?php echo $logo ? '<img class="logo" src="' . esc_attr( $logo ) . '" alt="">' : '<span class="label">' . $e( $club_name ) . '</span>'; ?></td>
<td class="meta"><?php echo $e( __( 'Recibo nº', SULAYR_TEXTDOMAIN ) ); ?><strong class="mono"><?php echo $e( (string) $receipt->number ); ?></strong><?php echo $e( sprintf( __( 'Emitido el %s', SULAYR_TEXTDOMAIN ), MemberDates::show( substr( (string) $receipt->generated_at, 0, 10 ) ) ) ); ?></td>
</tr></table>

<h1><?php echo $e( __( 'Recibo', SULAYR_TEXTDOMAIN ) ); ?></h1>
<p class="period"><?php echo $e( ucfirst( Receipts::month_label( (string) $receipt->period ) ) ); ?></p>

<table class="parties"><tr>
<td><div class="label"><?php echo $e( __( 'Club', SULAYR_TEXTDOMAIN ) ); ?></div><?php echo nl2br( $e( implode( "\n", array_filter( $club ) ) ) ); ?></td>
<td><div class="label"><?php echo $e( '' !== $number ? sprintf( __( 'Socio nº %s', SULAYR_TEXTDOMAIN ), $number ) : __( 'Socio', SULAYR_TEXTDOMAIN ) ); ?></div><?php echo nl2br( $e( implode( "\n", array_filter( $member ) ) ) ); ?></td>
</tr></table>

<table class="lines">
<tr><th><?php echo $e( __( 'Concepto', SULAYR_TEXTDOMAIN ) ); ?></th><th style="text-align:right"><?php echo $e( __( 'Importe', SULAYR_TEXTDOMAIN ) ); ?></th></tr>
<?php echo self::stripe_rows( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba. ?>
<tr class="total"><td class="t-label"><?php echo $e( __( 'Total', SULAYR_TEXTDOMAIN ) ); ?></td><td class="num"><?php echo $e( Receipts::money( (float) $receipt->total ) ); ?></td></tr>
</table>

<p class="status"><span class="badge badge-<?php echo esc_attr( $status ); ?>"><?php echo $e( Receipts::status_label( $status ) ); ?></span>
<?php if ( $outstanding > 0 ) : ?>
&nbsp; <span class="mono"><?php echo $e( sprintf( __( 'Pendiente: %s', SULAYR_TEXTDOMAIN ), Receipts::money( $outstanding ) ) ); ?></span>
<?php endif; ?></p>

<?php if ( '' !== $payments ) : ?>
<div class="label"><?php echo $e( __( 'Pagos', SULAYR_TEXTDOMAIN ) ); ?></div>
<table class="lines"><tr><th><?php echo $e( __( 'Fecha', SULAYR_TEXTDOMAIN ) ); ?></th><th><?php echo $e( __( 'Medio', SULAYR_TEXTDOMAIN ) ); ?></th><th style="text-align:right"><?php echo $e( __( 'Importe', SULAYR_TEXTDOMAIN ) ); ?></th></tr>
<?php echo self::stripe_rows( $payments ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba. ?>
</table>
<?php endif; ?>

<?php if ( '' !== trim( $footer ) ) : ?>
<p class="small"><?php echo nl2br( $e( $footer ) ); ?></p>
<?php endif; ?>
</body></html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Registra las fuentes de marca en dompdf (solo en memoria).
     *
     * @param   Dompdf $dompdf Dompdf.
     * @return 	bool True si están todas.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function register_fonts( Dompdf $dompdf ): bool {
        $dir = SULAYR_DIR . 'assets/pdf-fonts/';
        $metrics = $dompdf->getFontMetrics();
        foreach ( self::FONTS as $family => $variants ) {
            $paths = [];
            foreach ( $variants as $variant => $file ) {
                if ( false === is_readable( $dir . $file . '.ttf' ) || false === is_readable( $dir . $file . '.ufm' ) ) {
                    return false;
                }
                $paths[ $variant ] = $dir . $file;
            }
            $metrics->setFontFamily( $family, $paths );
        }

        return true;
    }

    /**
     * Marca las filas pares para el fondo Nieve alterno.
     *
     * @param   string $rows Filas <tr>.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function stripe_rows( string $rows ): string {
        $i = 0;

        return (string) preg_replace_callback( '/<tr(\s+class="([^"]*)")?>/', function ( $m ) use ( &$i ) {
            $i++;
            $classes = trim( ( $m[2] ?? '' ) . ( 0 === $i % 2 ? ' even' : '' ) );
            return '' !== $classes ? '<tr class="' . $classes . '">' : '<tr>';
        }, $rows );
    }

    /**
     * Logo de Socios → Ajustes como data URI (dompdf no lee URLs remotas).
     * Sin GD no se pinta un PNG: el recibo sale con el nombre del club.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function logo(): string {
        $id = (int) MemberSettings::get( 'club_logo' );
        $file = $id > 0 ? get_attached_file( $id ) : '';
        $mime = $id > 0 ? (string) get_post_mime_type( $id ) : '';
        if ( '' === (string) $file || false === is_readable( $file ) || false === in_array( $mime, [ 'image/png', 'image/jpeg' ], true ) ) {
            return '';
        }
        if ( 'image/png' === $mime && false === extension_loaded( 'gd' ) ) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Archivo local de la biblioteca.
    }
}
