<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Identidad Sulayr en las pantallas del menú Socios (partes B, C y D).
 *
 * Todo lo que va dentro de .sulayr-brand: Archivo en títulos, Instrument
 * Sans en el texto, JetBrains Mono en cifras, fechas y etiquetas, tablas con
 * cabecera Noche y filas alternas Nieve, franja tricolor bajo el título,
 * botones Noche y la acción principal (.sulayr-cta) en Sol de Alba. Texto
 * siempre en Noche sobre fondo claro; Cielo, Menta y Sol solo como fondo de
 * estados o filetes. Las fuentes son las de Google Fonts que ya carga el tema
 * del club. Las páginas de opciones de ACF (Ajustes) y las del catálogo y el
 * monedero (pantallas de WordPress) no se tocan.
 */
class BrandStyles {

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'fonts' ] );
        add_action( 'admin_head', [ $this, 'styles' ] );
    }

    /**
     * Fuentes de marca en las pantallas de Socios.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function fonts(): void {
        if ( false === self::is_members_screen() ) {
            return;
        }

        wp_enqueue_style( 'sulayr-admin-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,400..900;1,62..125,400..900&family=Instrument+Sans:wght@400;600&family=JetBrains+Mono:wght@400;600&display=swap', [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts.
    }

    /**
     * Hoja de estilos de marca.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function styles(): void {
        if ( false === self::is_members_screen() ) {
            return;
        }

        echo '<style>
            .sulayr-brand{--s-noche:#0B1638;--s-cielo:#4FE6F2;--s-menta:#1EE8A3;--s-sol:#F46F5C;--s-nieve:#F3F7FA;--s-pizarra:#2A3550;--s-nube:#8C93A3;--s-texto2:#566079;--s-acento:#0A7C8A;--s-linea:#D6DCE5;
                --s-display:"Archivo","Arial Narrow",Arial,sans-serif;--s-texto:"Instrument Sans",system-ui,"Segoe UI",Arial,sans-serif;--s-mono:"JetBrains Mono",Consolas,monospace;
                font-family:var(--s-texto);color:var(--s-noche)}
            .sulayr-brand p,.sulayr-brand td,.sulayr-brand th,.sulayr-brand li,.sulayr-brand label,.sulayr-brand input,.sulayr-brand select,.sulayr-brand textarea,.sulayr-brand .button,.sulayr-brand summary,.sulayr-brand legend{font-family:var(--s-texto)}
            .sulayr-brand .sulayr-eyebrow{font-family:var(--s-mono);font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--s-acento);margin:12px 0 0}
            .sulayr-brand h1{font-family:var(--s-display);font-weight:900;font-style:italic;font-stretch:118%;font-size:30px;line-height:1.05;color:var(--s-noche);padding-bottom:12px;margin-bottom:12px;position:relative}
            .sulayr-brand h1::after{content:"";position:absolute;left:0;bottom:0;width:120px;height:4px;background:linear-gradient(90deg,var(--s-cielo) 0 33.33%,var(--s-menta) 33.33% 66.66%,var(--s-sol) 66.66% 100%)}
            .sulayr-brand h1.wp-heading-inline{margin-right:8px}
            .sulayr-brand .wp-header-end{border:0;margin:0}
            .sulayr-brand h2{font-family:var(--s-display);font-weight:700;font-stretch:108%;font-size:20px;color:var(--s-noche);margin-top:28px}
            .sulayr-brand h3{font-family:var(--s-display);font-weight:700;font-size:16px;color:var(--s-noche)}
            .sulayr-brand a:not(.button):not(.page-title-action){color:var(--s-acento)}
            .sulayr-brand a:not(.button):not(.page-title-action):hover{color:var(--s-noche)}
            .sulayr-brand .description{color:var(--s-texto2)}
            .sulayr-brand .subsubsub{font-family:var(--s-texto)}
            .sulayr-brand .subsubsub a.current{color:var(--s-noche);font-weight:600;box-shadow:inset 0 -3px 0 var(--s-menta)}
            .sulayr-brand .subsubsub .count{font-family:var(--s-mono);color:var(--s-texto2)}
            /* Tablas */
            .sulayr-brand .widefat{border:1px solid var(--s-linea);border-radius:6px;overflow:hidden;border-collapse:separate;border-spacing:0;box-shadow:none}
            .sulayr-brand .widefat thead th,.sulayr-brand .widefat thead td{background:var(--s-noche);color:var(--s-nieve);font-family:var(--s-mono);font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:400;border-bottom:0}
            .sulayr-brand .widefat tfoot th{background:var(--s-nieve);color:var(--s-noche);font-weight:600}
            .sulayr-brand .widefat.striped>tbody>:nth-child(odd){background:#fff}
            .sulayr-brand .widefat.striped>tbody>:nth-child(even){background:var(--s-nieve)}
            .sulayr-brand .widefat td{color:var(--s-noche)}
            .sulayr-brand .sulayr-t-month td:nth-child(n+4):nth-child(-n+6),.sulayr-brand .sulayr-t-month tfoot th:nth-child(n+2):nth-child(-n+4),
            .sulayr-brand .sulayr-t-lines td:nth-child(3),.sulayr-brand .sulayr-t-lines tfoot th:nth-child(2),
            .sulayr-brand .sulayr-t-payments td:nth-child(1),.sulayr-brand .sulayr-t-payments td:nth-child(3),
            .sulayr-brand .sulayr-t-member td:nth-child(n+3):nth-child(-n+4),
            .sulayr-brand .sulayr-t-month td:first-child,.sulayr-brand .sulayr-t-month td:nth-child(2),.sulayr-brand .sulayr-t-member td:nth-child(2),
            .sulayr-brand .sulayr-t-members td:first-child,.sulayr-brand .sulayr-t-dates td:nth-child(2),.sulayr-brand .sulayr-t-dates td:nth-child(3){font-family:var(--s-mono);font-variant-numeric:tabular-nums}
            /* Ficha de datos (form-table) */
            .sulayr-brand .form-table th{font-family:var(--s-mono);font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:400;color:var(--s-texto2)}
            .sulayr-brand .form-table td{color:var(--s-noche)}
            /* Formularios */
            .sulayr-brand input[type=text],.sulayr-brand input[type=email],.sulayr-brand input[type=number],.sulayr-brand input[type=date],.sulayr-brand input[type=month],.sulayr-brand select,.sulayr-brand textarea{border-color:var(--s-linea);border-radius:4px;color:var(--s-noche)}
            .sulayr-brand input:focus,.sulayr-brand select:focus,.sulayr-brand textarea:focus{border-color:var(--s-acento);box-shadow:0 0 0 1px var(--s-acento)}
            .sulayr-brand input[type=checkbox]:focus,.sulayr-brand input[type=radio]:focus{box-shadow:0 0 0 2px var(--s-acento)}
            .sulayr-brand details{background:#fff;border:1px solid var(--s-linea);border-radius:6px;margin:10px 0;padding:0 16px;max-width:1100px}
            .sulayr-brand details>summary{cursor:pointer;padding:12px 0;font-family:var(--s-display);font-weight:700;font-stretch:108%;font-size:15px;color:var(--s-noche)}
            .sulayr-brand details[open]>summary{border-bottom:1px solid var(--s-linea);margin-bottom:8px}
            .sulayr-brand .sulayr-join-group{border-color:var(--s-linea);border-radius:6px}
            .sulayr-brand .sulayr-join-admin legend{font-family:var(--s-display);font-weight:700;font-stretch:108%;color:var(--s-noche)}
            .sulayr-brand .sulayr-join-error{color:var(--s-noche);font-weight:600;border-left:3px solid var(--s-sol);padding-left:8px}
            .sulayr-brand .sulayr-tag{background:var(--s-nieve);border:1px solid var(--s-linea);border-radius:4px;font-family:var(--s-mono);font-size:11px;letter-spacing:.06em}
            .sulayr-brand .sulayr-tag-interest{background:var(--s-noche);border-color:var(--s-noche);color:var(--s-menta);white-space:nowrap}
            /* Botones */
            .sulayr-brand .button,.sulayr-brand .page-title-action{border-radius:4px;border:1px solid var(--s-noche);color:var(--s-noche);background:#fff}
            .sulayr-brand .button:hover,.sulayr-brand .button:focus,.sulayr-brand .page-title-action:hover{background:var(--s-nieve);border-color:var(--s-noche);color:var(--s-noche)}
            .sulayr-brand .button-primary{background:var(--s-noche);border-color:var(--s-noche);color:var(--s-nieve)}
            .sulayr-brand .button-primary:hover,.sulayr-brand .button-primary:focus{background:var(--s-pizarra);border-color:var(--s-pizarra);color:var(--s-nieve)}
            .sulayr-brand .button-primary.sulayr-cta{background:var(--s-sol);border-color:var(--s-sol);color:var(--s-noche);font-weight:600}
            .sulayr-brand .button-primary.sulayr-cta:hover,.sulayr-brand .button-primary.sulayr-cta:focus{background:var(--s-noche);border-color:var(--s-noche);color:var(--s-nieve)}
            .sulayr-brand .button-link-delete{background:transparent;border-color:transparent;color:var(--s-noche);text-decoration:underline;text-decoration-color:var(--s-sol);text-underline-offset:3px}
            .sulayr-brand .button-link-delete:hover,.sulayr-brand .button-link-delete:focus{background:var(--s-nieve);border-color:var(--s-sol);color:var(--s-noche)}
            .sulayr-brand .button:focus,.sulayr-brand .page-title-action:focus{box-shadow:0 0 0 2px #fff,0 0 0 4px var(--s-acento)}
            /* Bloques y estados */
            .sulayr-brand-block{background:#fff;border:1px solid var(--s-linea);border-radius:6px;padding:4px 20px 16px;margin:20px 0;max-width:1100px}
            .sulayr-brand .sulayr-daily{background:var(--s-nieve);border-left:3px solid var(--s-menta);padding:2px 12px;margin:8px 0 12px;max-width:1100px}
            .sulayr-inline{display:inline-block;margin:0 4px 4px 0}
            .sulayr-receipt-status,.sulayr-receipt-outdated{display:inline-block;padding:2px 8px;border-radius:4px;font-family:"JetBrains Mono",Consolas,monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;background:#F3F7FA;color:#0B1638;border:1px solid #D6DCE5}
            .sulayr-receipt-pagado{background:#1EE8A3;border-color:#1EE8A3}
            .sulayr-receipt-parcial{background:#4FE6F2;border-color:#4FE6F2}
            .sulayr-receipt-impagado{background:#F46F5C;border-color:#F46F5C}
            .sulayr-receipt-anulado{color:#566079;text-decoration:line-through}
            .sulayr-receipt-outdated{background:#fff;border-color:#F46F5C}
            .sulayr-line-cancelled td{color:#8C93A3;text-decoration:line-through}
            .sulayr-brand .sulayr-coach-new td{box-shadow:inset 4px 0 0 #1EE8A3}
            .sulayr-brand #sulayr-coach-text{font-family:var(--s-mono);font-size:13px;border-radius:4px;border-color:var(--s-linea)}
            .sulayr-check{margin-right:8px;white-space:nowrap}
            @media (max-width:782px){.sulayr-brand h1{font-size:24px}.sulayr-brand-block,.sulayr-brand details{padding-left:12px;padding-right:12px}}
        </style>';
    }

    /**
     * True en una pantalla del menú Socios (listado, ficha, solicitudes,
     * nueva alta, recibos y listados) o del menú Ranking.
     *
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_members_screen(): bool {
        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo decide si pinta estilos.

        return 0 === strpos( $page, MembersMenu::SLUG ) || 0 === strpos( $page, 'sulayr-ranking' );
    }
}
