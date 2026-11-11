<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Join\MemberMailer;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Listado del entrenador (D54, 9.2).
 *
 * Socios activos hoy con algún servicio vigente marcado «aparece en el
 * listado del entrenador». Los congelados no salen aunque conserven el
 * servicio (decisión 13 del 11 de noviembre de 2026). Se agrupa por
 * modalidad (la opción del servicio, o el servicio si no tiene opciones).
 *
 * Las novedades (altas, bajas y cambios de modalidad) se calculan contra la
 * foto guardada al «Marcar como enviado» o al enviarlo por email. Nunca se
 * envía solo.
 */
class CoachList {

    public const SENT_OPTION = 'sulayr_coach_list_sent';

    /**
     * Listado de hoy: user_id => [ name, email, group, key ].
     *
     * @return 	array<int, array{name: string, email: string, group: string, key: string}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function current(): array {
        $today = current_time( 'Y-m-d' );
        $coach_services = [];
        foreach ( ServiceCatalog::all() as $service ) {
            if ( $service->coach_list ) {
                $coach_services[ $service->id ] = $service;
            }
        }
        if ( empty( $coach_services ) ) {
            return [];
        }

        $out = [];
        foreach ( get_users( [ 'role' => 'athlete', 'number' => -1 ] ) as $user ) {
            $period = StatusPeriods::on( $user->ID, $today );
            if ( null === $period || CustomFields::STATUS_ACTIVE !== $period->status ) {
                continue;
            }
            foreach ( MemberServices::active_on( $user->ID, $today ) as $row ) {
                $service = $coach_services[ (int) $row->service_id ] ?? null;
                if ( null === $service ) {
                    continue;
                }
                $option = (string) $row->option_key;
                $out[ $user->ID ] = [
                    'name' => HelperService::get_user_full_name( $user->ID ) ?: $user->display_name,
                    'email' => $user->user_email,
                    'group' => '' !== $option ? $service->option_label( $option ) : $service->title,
                    'key' => $service->id . '|' . $option,
                ];
                break;
            }
        }

        uasort( $out, function ( $a, $b ) {
            return strcmp( $a['group'], $b['group'] ) ?: strcasecmp( $a['name'], $b['name'] );
        } );

        return $out;
    }

    /**
     * Último envío: [ at, by, items ] o null.
     *
     * @return 	array|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function last_sent(): ?array {
        $sent = get_option( self::SENT_OPTION );

        return is_array( $sent ) ? $sent : null;
    }

    /**
     * Novedades frente al último envío.
     *
     * @param   array $current Listado de hoy.
     * @return 	array{new: int[], changed: array<int, string>, gone: array<int, array>}
     *          changed: user_id => grupo anterior; gone: user_id => fila anterior.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function changes( array $current ): array {
        $sent = self::last_sent();
        $before = null !== $sent ? (array) $sent['items'] : [];
        $out = [ 'new' => [], 'changed' => [], 'gone' => [] ];
        if ( null === $sent ) {
            return $out;
        }

        foreach ( $current as $user_id => $item ) {
            if ( false === isset( $before[ $user_id ] ) ) {
                $out['new'][] = $user_id;
            } elseif ( (string) $before[ $user_id ]['key'] !== $item['key'] ) {
                $out['changed'][ $user_id ] = (string) $before[ $user_id ]['group'];
            }
        }
        foreach ( $before as $user_id => $item ) {
            if ( false === isset( $current[ $user_id ] ) ) {
                $out['gone'][ (int) $user_id ] = (array) $item;
            }
        }

        return $out;
    }

    /**
     * Texto para WhatsApp, agrupado por modalidad y con las novedades.
     *
     * @param   array $current Listado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function text( array $current ): string {
        $changes = self::changes( $current );
        $lines = [];
        foreach ( self::grouped( $current ) as $group => $items ) {
            $lines[] = '*' . $group . '* (' . count( $items ) . ')';
            foreach ( $items as $user_id => $item ) {
                $lines[] = '- ' . $item['name'] . ' · ' . $item['email'] . self::mark( (int) $user_id, $changes );
            }
            $lines[] = '';
        }
        if ( ! empty( $changes['gone'] ) ) {
            $lines[] = '*' . __( 'Ya no están', SULAYR_TEXTDOMAIN ) . '*';
            foreach ( $changes['gone'] as $item ) {
                $lines[] = '- ' . $item['name'] . ' (' . $item['group'] . ')';
            }
        }

        return trim( implode( "\n", $lines ) );
    }

    /**
     * Listado en HTML para el email (marcador {listado}).
     *
     * @param   array $current Listado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function html( array $current ): string {
        $changes = self::changes( $current );
        $mono = "'JetBrains Mono',Consolas,'Courier New',monospace";
        $label = 'font-family:' . $mono . ';font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#566079;margin:24px 0 6px;';
        $cell = 'padding:8px 10px;border-bottom:1px solid #D6DCE5;font-size:15px;color:#0B1638;';
        $tag = 'font-family:' . $mono . ';font-size:11px;letter-spacing:1px;text-transform:uppercase;padding:2px 6px;border-radius:4px;';

        $table = function ( array $rows ) use ( $cell ): string {
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
            foreach ( array_values( $rows ) as $i => $row ) {
                $html .= '<tr><td style="' . $cell . ( 1 === $i % 2 ? 'background:#F3F7FA;' : '' ) . '">' . $row . '</td></tr>';
            }
            return $html . '</table>';
        };

        $html = '';
        foreach ( self::grouped( $current ) as $group => $items ) {
            $html .= '<p style="' . $label . '">' . esc_html( $group ) . ' · ' . count( $items ) . '</p>';
            $rows = [];
            foreach ( $items as $user_id => $item ) {
                $mark = '';
                if ( in_array( (int) $user_id, $changes['new'], true ) ) {
                    $mark = ' <span style="' . $tag . 'background:#1EE8A3;color:#0B1638;">' . esc_html__( 'nuevo', SULAYR_TEXTDOMAIN ) . '</span>';
                } elseif ( isset( $changes['changed'][ $user_id ] ) ) {
                    $mark = ' <span style="' . $tag . 'background:#4FE6F2;color:#0B1638;">' . esc_html( sprintf( __( 'antes %s', SULAYR_TEXTDOMAIN ), $changes['changed'][ $user_id ] ) ) . '</span>';
                }
                $rows[] = '<strong>' . esc_html( $item['name'] ) . '</strong>' . $mark . '<br><span style="font-family:' . $mono . ';font-size:12px;color:#566079;">' . esc_html( $item['email'] ) . '</span>';
            }
            $html .= $table( $rows );
        }
        if ( ! empty( $changes['gone'] ) ) {
            $html .= '<p style="' . $label . '">' . esc_html__( 'Ya no están', SULAYR_TEXTDOMAIN ) . '</p>';
            $html .= $table( array_map( function ( $item ) {
                return esc_html( $item['name'] ) . ' <span style="color:#566079;">(' . esc_html( $item['group'] ) . ')</span>';
            }, $changes['gone'] ) );
        }

        return $html;
    }

    /**
     * Guarda la foto de hoy como último envío.
     *
     * @param   array $current Listado.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mark_sent( array $current ): void {
        update_option( self::SENT_OPTION, [ 'at' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'items' => $current ], false );
    }

    /**
     * Envía el listado a los emails del entrenador y del club, y lo marca
     * como enviado (decisión 13).
     *
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function email() {
        $to = array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', (string) MemberSettings::get( 'coach_emails' ) ) ), 'is_email' );
        $club = (string) MemberSettings::get( 'club_email' );
        if ( is_email( $club ) ) {
            $to[] = $club;
        }
        $to = array_values( array_unique( $to ) );
        if ( empty( $to ) ) {
            return new \WP_Error( 'sulayr_coach_emails', __( 'No hay emails configurados en Socios → Ajustes → Datos del club.', SULAYR_TEXTDOMAIN ) );
        }

        $current = self::current();
        if ( false === MemberMailer::send( 'coach', $to, [ 'listado_html' => self::html( $current ) ], true ) ) {
            return new \WP_Error( 'sulayr_coach_send', __( 'No se pudo enviar el email.', SULAYR_TEXTDOMAIN ) );
        }
        self::mark_sent( $current );

        return true;
    }

    /**
     * Marca de novedad de un socio.
     *
     * @param   int   $user_id Socio.
     * @param   array $changes Novedades.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mark( int $user_id, array $changes ): string {
        if ( in_array( $user_id, $changes['new'], true ) ) {
            return ' (' . __( 'nuevo', SULAYR_TEXTDOMAIN ) . ')';
        }
        if ( isset( $changes['changed'][ $user_id ] ) ) {
            return ' (' . sprintf( __( 'antes %s', SULAYR_TEXTDOMAIN ), $changes['changed'][ $user_id ] ) . ')';
        }

        return '';
    }

    /**
     * Agrupa por modalidad.
     *
     * @param   array $current Listado.
     * @return 	array<string, array>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function grouped( array $current ): array {
        $groups = [];
        foreach ( $current as $user_id => $item ) {
            $groups[ $item['group'] ][ $user_id ] = $item;
        }

        return $groups;
    }
}
