<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\MemberFields;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Migración de los deportistas actuales y de la hoja «Datos de socios» al
 * modelo nuevo (spec §11).
 *
 * En modo ensayo no escribe nada: solo llena el informe. Es idempotente: un
 * socio que ya tiene servicios o periodos se salta.
 */
class LegacyMigrator {

    public const MIGRATED_OPTION = 'sulayr_members_migrated_at';

    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'aviso';
    public const LEVEL_ERROR = 'error';

    private bool $dry_run;
    /** @var array<int, array> Filas de «Datos de los socios». */
    private array $current_rows;
    /** @var array<int, array> Filas de «Antiguos deportistas». */
    private array $former_rows;

    /** @var array<int, array{level: string, who: string, message: string}> */
    private array $report = [];
    private array $stats = [
        'socios_web' => 0,
        'ya_migrados' => 0,
        'migrados' => 0,
        'antiguos_creados' => 0,
        'numeros_nuevos' => 0,
    ];

    /** Filas del Excel ya cruzadas, por clave «hoja:línea». */
    private array $matched = [];
    /** Números de socio ya usados en esta ejecución => quién. */
    private array $numbers = [];
    /** Usuarios migrados sin número, para numerarlos al final. */
    private array $without_number = [];
    /** IDs de usuario en los que manda la fecha de alta del Excel. */
    private array $excel_join_ids;
    /** Bajas avisadas a crear como solicitudes aprobadas: user_id => primer día de baja. */
    private array $scheduled_leaves = [];

    /**
     * __construct()
     *
     * @param   bool  $dry_run      true = ensayo.
     * @param   array $current_rows Filas leídas de «Datos de los socios».
     * @param   array $former_rows  Filas leídas de «Antiguos deportistas».
     * @param   int[] $excel_join_ids Usuarios en los que manda la fecha de alta del Excel.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( bool $dry_run, array $current_rows = [], array $former_rows = [], array $excel_join_ids = [] ) {
        $this->dry_run = $dry_run;
        $this->current_rows = $current_rows;
        $this->former_rows = $former_rows;
        $this->excel_join_ids = array_map( 'intval', $excel_join_ids );
    }

    /**
     * Ejecuta la migración.
     *
     * @return 	array{report: array, stats: array}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function run(): array {
        $runner = function () {
            $this->check_sheet_rows();
            $services = $this->prepare_catalog();
            $this->load_existing_numbers();

            $athletes = get_users( [ 'role' => 'athlete', 'number' => -1, 'orderby' => 'ID' ] );
            $this->stats['socios_web'] = count( $athletes );
            foreach ( $athletes as $user ) {
                $this->migrate_user( $user, $services );
            }

            $this->report_unmatched_current();
            $this->create_former_members( $services );
            $this->assign_missing_numbers();

            if ( false === $this->dry_run ) {
                update_option( self::MIGRATED_OPTION, current_time( 'mysql' ) );
            }
        };

        if ( $this->dry_run ) {
            $runner();
        } else {
            MemberLog::with_note( __( 'Migración', SULAYR_TEXTDOMAIN ), $runner );
        }

        return [
            'report' => $this->report,
            'stats' => $this->stats,
        ];
    }

    /**
     * Avisos de calidad de las filas del Excel y números repetidos.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function check_sheet_rows(): void {
        $seen = [];
        foreach ( [ 'socios' => $this->current_rows, 'antiguos' => $this->former_rows ] as $sheet => $rows ) {
            foreach ( $rows as $row ) {
                $who = $this->sheet_who( $sheet, $row );
                foreach ( $row['issues'] as $issue ) {
                    $this->add( self::LEVEL_WARNING, $who, $issue );
                }

                if ( null !== $row['number'] ) {
                    if ( isset( $seen[ $row['number'] ] ) ) {
                        if ( false === self::same_person( $seen[ $row['number'] ]['row'], $row ) ) {
                            $this->add( self::LEVEL_ERROR, $who, sprintf( 'Nº de socio %d repetido (también en %s). No se asigna: recibirá un número nuevo.', $row['number'], $seen[ $row['number'] ]['who'] ) );
                        }
                    } else {
                        $seen[ $row['number'] ] = [ 'who' => $who, 'row' => $row ];
                    }
                }
            }
        }
    }

    /**
     * Crea el catálogo inicial si falta (o lo anota en el ensayo).
     *
     * @return 	array Servicios de LegacyMapping::services(), o definiciones de ensayo.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function prepare_catalog(): array {
        $missing = CatalogSeeder::missing();

        if ( ! empty( $missing ) ) {
            if ( $this->dry_run ) {
                $this->add( self::LEVEL_INFO, 'Catálogo', 'Se crearían los servicios: ' . implode( ', ', $missing ) . ', y los tipos de socio «normal» y «junta» si no hay.' );
            } else {
                $created = CatalogSeeder::ensure();
                $this->add( self::LEVEL_INFO, 'Catálogo', 'Servicios creados: ' . implode( ', ', $created ) . '.' );
            }
        }

        $services = LegacyMapping::services();
        if ( null !== $services ) {
            return $services;
        }

        if ( false === $this->dry_run ) {
            throw new \RuntimeException( 'Faltan servicios del catálogo inicial (cuota-base, entrenamiento-generico, natacion). ¿Están en la papelera?' );
        }

        // En el ensayo sin catálogo: servicios de mentira para poder describir el resultado.
        $stub = function ( string $slug, string $title ) {
            $service = new \ClubTriatlonSulayr\Modules\Members\Catalog\Service();
            $service->id = 0;
            $service->slug = $slug;
            $service->title = $title;
            return $service;
        };

        return [
            'base' => $stub( LegacyMapping::SLUG_BASE, 'Cuota base' ),
            'training' => $stub( LegacyMapping::SLUG_TRAINING, 'Entrenamiento genérico' ),
            'swimming' => $stub( LegacyMapping::SLUG_SWIMMING, 'Natación' ),
        ];
    }

    /**
     * Números de socio que ya tienen los usuarios (por si se repite la migración).
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function load_existing_numbers(): void {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            CustomFields::MEMBER_NUMBER
        ) );

        foreach ( $rows as $row ) {
            if ( '' !== (string) $row->meta_value ) {
                $this->numbers[ (int) $row->meta_value ] = 'usuario ' . (int) $row->user_id;
            }
        }
    }

    /**
     * Migra un deportista de la web.
     *
     * @param   \WP_User $user     Usuario.
     * @param   array    $services Servicios del catálogo inicial.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function migrate_user( \WP_User $user, array $services ): void {
        $user_id = (int) $user->ID;
        $who = $this->user_who( $user );

        // Cruzar siempre, también si ya está migrado, para que su fila del Excel no cuente como «sin cruzar».
        $row = $this->match_row( $user );

        if ( self::has_history( $user_id ) ) {
            $this->stats['ya_migrados']++;
            return;
        }
        $mapped = LegacyMapping::status( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) );
        $status = $mapped['status'];
        if ( '' !== $mapped['warning'] ) {
            $this->add( self::LEVEL_WARNING, $who, $mapped['warning'] );
        }

        // Solo en «Antiguos deportistas»: ya no es socio aunque la web diga otra cosa.
        if ( null !== $row && [ 'antiguos' ] === $row['sheets'] && CustomFields::STATUS_LEFT !== $status ) {
            $this->add( self::LEVEL_WARNING, $who, sprintf( 'En la web está %s pero solo aparece en «Antiguos deportistas»: se migra como baja.', StatusPeriods::label( $status ) ) );
            $status = CustomFields::STATUS_LEFT;
        }

        // Fila con las columnas corridas (la fecha de alta no es una fecha): su fecha de baja no es fiable.
        if ( null !== $row && '' === $row['join'] && '' !== $row['join_raw'] && '' !== $row['leave'] && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN !== $row['leave'] ) {
            $this->add( self::LEVEL_WARNING, $who, sprintf( 'Fila con columnas corridas (alta «%s»): la fecha de baja %s no es fiable y se guarda como desconocida.', $row['join_raw'], $row['leave'] ) );
            $row['leave'] = CustomFields::MEMBER_LEAVE_DATE_UNKNOWN;
        }

        // Reservas solo da acceso con estado exactamente «active» (spec §11, A4).
        $legacy_status = (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
        if ( '1' === (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_SWIMMING, true ) && 'active' !== $legacy_status && CustomFields::STATUS_ACTIVE === $status ) {
            $this->add( self::LEVEL_WARNING, $who, sprintf( 'Tiene natación y estado «%s»: al guardarse como activo podrá reservar en CN Nazarí en la próxima sincronización.', $legacy_status ) );
        }

        // Fecha de alta: la de la web manda; si falta, la del Excel; si no, la de registro.
        $join = LegacyMapping::acf_date_to_iso( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_JOIN_DATE, true ) );
        $join_from_excel = false;
        if ( null !== $row && '' !== $row['join'] && '' !== $join && $row['join'] !== $join ) {
            if ( in_array( $user_id, $this->excel_join_ids, true ) ) {
                $this->add( self::LEVEL_INFO, $who, sprintf( 'Fecha de alta distinta: web %s, Excel %s. Se usa la del Excel (indicado con --alta-excel).', $join, $row['join'] ) );
                $join = $row['join'];
                $join_from_excel = true;
            } else {
                $this->add( self::LEVEL_WARNING, $who, sprintf( 'Fecha de alta distinta: web %s, Excel %s. Se usa la de la web.', $join, $row['join'] ) );
            }
        }
        if ( '' === $join && null !== $row && '' !== $row['join'] ) {
            $join = $row['join'];
            $this->add( self::LEVEL_INFO, $who, sprintf( 'Sin fecha de alta en la web: se usa la del Excel (%s).', $join ) );
        }
        if ( '' === $join ) {
            $join = substr( (string) $user->user_registered, 0, 10 );
            $this->add( self::LEVEL_WARNING, $who, sprintf( 'Sin fecha de alta: se usa la de registro en la web (%s).', $join ) );
        }

        if ( null === $row ) {
            $this->add( self::LEVEL_WARNING, $who, 'No aparece en el Excel (ni por DNI ni por email).' );
        } else {
            $this->compare_personal_data( $user, $row, $who );
        }

        // Número de socio.
        $number = (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true );
        $new_number = null;
        if ( '' === $number && null !== $row && null !== $row['number'] ) {
            if ( isset( $this->numbers[ $row['number'] ] ) ) {
                $this->add( self::LEVEL_ERROR, $who, sprintf( 'El Nº %d del Excel ya lo tiene %s. Recibirá un número nuevo.', $row['number'], $this->numbers[ $row['number'] ] ) );
            } else {
                $new_number = $row['number'];
                $this->numbers[ $new_number ] = $who;
            }
        }
        if ( '' === $number && null === $new_number ) {
            $this->without_number[] = [ 'user_id' => $user_id, 'who' => $who, 'preferred' => $user_id ];
        }

        // Fecha de baja.
        $leave = '';
        if ( CustomFields::STATUS_LEFT === $status ) {
            $leave = null !== $row && '' !== $row['leave'] ? $row['leave'] : CustomFields::MEMBER_LEAVE_DATE_UNKNOWN;
            if ( CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $leave ) {
                $this->add( self::LEVEL_INFO, $who, 'De baja con fecha desconocida: se guarda el periodo de baja sin fecha y sin servicios.' );
            } elseif ( $leave < $join ) {
                $this->add( self::LEVEL_ERROR, $who, sprintf( 'Fecha de baja (%s) anterior a la de alta (%s). Se guarda como desconocida.', $leave, $join ) );
                $leave = CustomFields::MEMBER_LEAVE_DATE_UNKNOWN;
            }
        } elseif ( null !== $row && '' !== $row['leave'] && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN !== $row['leave'] ) {
            if ( $row['leave'] >= current_time( 'Y-m-d' ) ) {
                $first_day = self::next_day( $row['leave'] );
                $this->scheduled_leaves[ $user_id ] = $first_day;
                $this->add( self::LEVEL_INFO, $who, sprintf( 'Baja avisada: sigue %s y se guarda una solicitud de baja aprobada con efecto el %s (la aplicará la parte C).', StatusPeriods::label( $status ), $first_day ) );
            } else {
                $this->add( self::LEVEL_WARNING, $who, sprintf( 'El Excel tiene fecha de baja %s pero en la web está %s. No se cambia el estado.', $row['leave'], StatusPeriods::label( $status ) ) );
            }
        }

        $desired = LegacyMapping::desired_services( $user_id, $services );
        foreach ( $desired['warnings'] as $warning ) {
            $this->add( self::LEVEL_WARNING, $who, $warning );
        }

        $type = LegacyMapping::member_type( $user_id );

        $this->stats['migrados']++;

        if ( $this->dry_run ) {
            return;
        }

        update_user_meta( $user_id, CustomFields::MEMBER_TYPE, $type );
        if ( null !== $new_number ) {
            update_user_meta( $user_id, CustomFields::MEMBER_NUMBER, $new_number );
        }
        if ( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) !== $status ) {
            update_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, $status );
        }
        if ( $join_from_excel || '' === (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_JOIN_DATE, true ) ) {
            update_user_meta( $user_id, CustomFields::USER_PROFILE_JOIN_DATE, LegacyMapping::iso_to_acf_date( $join ) );
        }
        if ( '' !== $leave ) {
            update_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, $leave );
        }

        $this->write_history( $user_id, $status, $join, $leave, $desired['items'] );

        if ( isset( $this->scheduled_leaves[ $user_id ] ) ) {
            $this->save_scheduled_leave( $user_id, $this->scheduled_leaves[ $user_id ] );
        }
    }

    /**
     * Guarda una baja ya avisada como solicitud aprobada y, si está congelado,
     * pone el fin previsto de la congelación en el día anterior.
     *
     * @param   int    $user_id   Socio.
     * @param   string $first_day Primer día de baja Y-m-d.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function save_scheduled_leave( int $user_id, string $first_day ): void {
        global $wpdb;

        $wpdb->insert(
            MembersSchema::table( MembersSchema::REQUESTS ),
            [
                'type' => 'baja',
                'user_id' => $user_id,
                'status' => 'aprobada',
                'data' => (string) wp_json_encode( [ 'origen' => 'migracion' ] ),
                'effective_date' => $first_day,
                'resolved_by' => get_current_user_id(),
                'resolved_at' => current_time( 'mysql' ),
                'reason' => __( 'Baja avisada (migración desde el Excel)', SULAYR_TEXTDOMAIN ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        $current = StatusPeriods::current( $user_id );
        if ( null !== $current && CustomFields::STATUS_FROZEN === $current->status ) {
            $wpdb->update(
                MembersSchema::table( MembersSchema::STATUS_PERIODS ),
                [ 'planned_end_date' => self::previous_day( $first_day ), 'reason' => sprintf( __( 'Migrado desde «Stand By». Baja avisada desde el %s.', SULAYR_TEXTDOMAIN ), $first_day ) ],
                [ 'id' => (int) $current->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        MemberLog::record( $user_id, MemberLog::AREA_STATUS, 'solicitud', '', sprintf( __( 'Baja aprobada con efecto el %s', SULAYR_TEXTDOMAIN ), $first_day ), __( 'Baja avisada', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Periodos de estado y servicios de un socio migrado.
     *
     * @param   int    $user_id Socio.
     * @param   string $status  Estado nuevo.
     * @param   string $join    Fecha de alta.
     * @param   string $leave   Fecha de baja, «desconocida» o ''.
     * @param   array  $items   Servicios deseados.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function write_history( int $user_id, string $status, string $join, string $leave, array $items ): void {
        $reason = __( 'Migración', SULAYR_TEXTDOMAIN );
        $today = current_time( 'Y-m-d' );

        if ( CustomFields::STATUS_LEFT === $status ) {
            if ( CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $leave ) {
                StatusPeriods::open( $user_id, CustomFields::STATUS_LEFT, null, __( 'Fecha de baja desconocida', SULAYR_TEXTDOMAIN ) );
                return;
            }

            StatusPeriods::open( $user_id, CustomFields::STATUS_ACTIVE, $join, $reason, $leave );
            StatusPeriods::open( $user_id, CustomFields::STATUS_LEFT, self::next_day( $leave ), $reason );
            foreach ( $items as $item ) {
                MemberServices::open( $user_id, $item['service_id'], $item['option_key'], $join, MemberServices::ORIGIN_MIGRATION, $leave );
            }
            return;
        }

        if ( CustomFields::STATUS_FROZEN === $status ) {
            $yesterday = self::previous_day( $today );
            StatusPeriods::open( $user_id, CustomFields::STATUS_ACTIVE, $join, $reason, $yesterday >= $join ? $yesterday : $join );
            StatusPeriods::open( $user_id, CustomFields::STATUS_FROZEN, $today, __( 'Migrado desde «Stand By»: revisa el motivo y la fecha de fin prevista.', SULAYR_TEXTDOMAIN ) );
        } else {
            StatusPeriods::open( $user_id, $status, $join, $reason );
        }

        foreach ( $items as $item ) {
            MemberServices::open( $user_id, $item['service_id'], $item['option_key'], $join, MemberServices::ORIGIN_MIGRATION );
        }
    }

    /**
     * Filas del Excel de socios actuales que no se cruzaron con nadie de la web.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function report_unmatched_current(): void {
        foreach ( $this->current_rows as $row ) {
            if ( false === isset( $this->matched[ 'socios:' . $row['line'] ] ) ) {
                $this->add( self::LEVEL_ERROR, $this->sheet_who( 'socios', $row ), 'Está en el Excel de socios actuales pero no hay ningún deportista con ese DNI o email en la web. No se crea: revísalo a mano.' );
            }
        }
    }

    /**
     * Crea como socios de baja a los antiguos deportistas que no están en la web.
     *
     * @param   array $services Servicios del catálogo inicial.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function create_former_members( array $services ): void {
        foreach ( $this->former_rows as $row ) {
            if ( isset( $this->matched[ 'antiguos:' . $row['line'] ] ) ) {
                continue;
            }

            $who = $this->sheet_who( 'antiguos', $row );

            if ( '' === $row['name'] || ( '' === $row['email'] && '' === $row['dni'] ) ) {
                $this->add( self::LEVEL_WARNING, $who, 'Antiguo deportista sin nombre o sin email ni DNI: no se crea.' );
                continue;
            }

            $existing = '' !== $row['email'] && is_email( $row['email'] ) ? get_user_by( 'email', $row['email'] ) : false;
            if ( false === $existing && '' !== $row['dni'] ) {
                $by_dni = get_users( [ 'blog_id' => 0, 'meta_key' => CustomFields::USER_PROFILE_DNI, 'meta_value' => $row['dni'], 'number' => 1 ] );
                $existing = empty( $by_dni ) ? false : $by_dni[0];
            }
            if ( false !== $existing && self::has_history( (int) $existing->ID ) ) {
                continue;
            }

            $number = null;
            if ( null !== $row['number'] && false === isset( $this->numbers[ $row['number'] ] ) ) {
                $number = $row['number'];
                $this->numbers[ $number ] = $who;
            }

            $this->stats['antiguos_creados']++;

            if ( $this->dry_run ) {
                $this->add( self::LEVEL_INFO, $who, false !== $existing ? 'Ya tiene cuenta en otra web de la red: se le añadiría como deportista de baja.' : 'Se crearía como socio de baja, sin acceso.' );
                if ( null === $number ) {
                    $this->without_number[] = [ 'user_id' => 0, 'who' => $who, 'preferred' => false !== $existing ? (int) $existing->ID : 0 ];
                }
                continue;
            }

            if ( false !== $existing ) {
                $user_id = (int) $existing->ID;
                if ( is_multisite() && false === is_user_member_of_blog( $user_id ) ) {
                    add_user_to_blog( get_current_blog_id(), $user_id, 'athlete' );
                } else {
                    $existing->add_role( 'athlete' );
                }
            } else {
                $user_id = $this->insert_former_user( $row );
                if ( 0 === $user_id ) {
                    $this->add( self::LEVEL_ERROR, $who, 'No se pudo crear el usuario.' );
                    continue;
                }
            }

            $this->fill_former_meta( $user_id, $row, $number );
            if ( null === $number ) {
                $this->without_number[] = [ 'user_id' => $user_id, 'who' => $who, 'preferred' => $user_id ];
            }

            $leave = '' !== $row['leave'] ? $row['leave'] : CustomFields::MEMBER_LEAVE_DATE_UNKNOWN;
            if ( '' !== $row['join'] && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN !== $leave && $leave >= $row['join'] ) {
                StatusPeriods::open( $user_id, CustomFields::STATUS_ACTIVE, $row['join'], __( 'Migración', SULAYR_TEXTDOMAIN ), $leave );
                StatusPeriods::open( $user_id, CustomFields::STATUS_LEFT, self::next_day( $leave ), __( 'Migración', SULAYR_TEXTDOMAIN ) );
            } else {
                StatusPeriods::open( $user_id, CustomFields::STATUS_LEFT, null, __( 'Fecha de baja desconocida', SULAYR_TEXTDOMAIN ) );
            }
        }
    }

    /**
     * Inserta un usuario para un antiguo deportista.
     *
     * @param   array $row Fila del Excel.
     * @return 	int   ID o 0.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function insert_former_user( array $row ): int {
        $parts = explode( ' ', $row['name'], 2 );
        $base_login = sanitize_user( strtolower( remove_accents( str_replace( ' ', '.', $row['name'] ) ) ), true );
        if ( '' === $base_login ) {
            $base_login = 'antiguo.socio';
        }
        $login = $base_login;
        $suffix = 2;
        while ( username_exists( $login ) ) {
            $login = $base_login . $suffix;
            $suffix++;
        }

        $user_id = wp_insert_user( [
            'user_login' => $login,
            'user_pass' => wp_generate_password( 32, true, true ),
            'user_email' => is_email( $row['email'] ) ? $row['email'] : '',
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? '',
            'display_name' => $row['name'],
            'role' => 'athlete',
        ] );

        return is_wp_error( $user_id ) ? 0 : (int) $user_id;
    }

    /**
     * Metas de un antiguo deportista creado o vinculado.
     *
     * @param   int      $user_id Usuario.
     * @param   array    $row     Fila del Excel.
     * @param   int|null $number  Nº de socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function fill_former_meta( int $user_id, array $row, ?int $number ): void {
        $metas = [
            CustomFields::USER_PROFILE_PHONE => $row['phone'],
            CustomFields::USER_PROFILE_DNI => $row['dni'],
            CustomFields::USER_PROFILE_BIRTH => LegacyMapping::iso_to_acf_date( $row['birth'] ),
            CustomFields::USER_PROFILE_JOIN_DATE => LegacyMapping::iso_to_acf_date( $row['join'] ),
        ];
        foreach ( $metas as $key => $value ) {
            if ( '' !== $value && '' === (string) get_user_meta( $user_id, $key, true ) ) {
                update_user_meta( $user_id, $key, $value );
            }
        }

        update_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_LEFT );
        update_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, '' !== $row['leave'] ? $row['leave'] : CustomFields::MEMBER_LEAVE_DATE_UNKNOWN );
        update_user_meta( $user_id, CustomFields::MEMBER_TYPE, LegacyMapping::TYPE_NORMAL );
        if ( null !== $number ) {
            update_user_meta( $user_id, CustomFields::MEMBER_NUMBER, $number );
        }
    }

    /**
     * Da número (máximo + 1) a quien no lo tenga.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function assign_missing_numbers(): void {
        // Como en el Excel, el número es el ID de usuario de WordPress si está libre.
        foreach ( $this->without_number as $k => $item ) {
            $preferred = (int) $item['preferred'];
            if ( $preferred > 0 && false === isset( $this->numbers[ $preferred ] ) ) {
                $this->numbers[ $preferred ] = $item['who'];
                $this->stats['numeros_nuevos']++;
                $this->add( self::LEVEL_INFO, $item['who'], sprintf( 'Sin número en el Excel: %s su ID de usuario, Nº %d.', $this->dry_run ? 'recibiría' : 'recibe', $preferred ) );
                if ( false === $this->dry_run && $item['user_id'] > 0 ) {
                    update_user_meta( $item['user_id'], CustomFields::MEMBER_NUMBER, $preferred );
                }
                unset( $this->without_number[ $k ] );
            }
        }

        // El resto (ID ocupado o usuario aún por crear en el ensayo): el siguiente al mayor.
        $next = empty( $this->numbers ) ? 1 : max( array_keys( $this->numbers ) ) + 1;
        if ( false === $this->dry_run ) {
            $next = max( $next, MemberFields::next_number() );
        }

        foreach ( $this->without_number as $item ) {
            $this->stats['numeros_nuevos']++;
            $this->add( self::LEVEL_INFO, $item['who'], sprintf( 'Sin número en el Excel: %s el Nº %d.', $this->dry_run ? ( $item['user_id'] > 0 ? 'recibiría' : 'recibiría su ID de usuario o, si está ocupado,' ) : 'recibe', $next ) );
            if ( false === $this->dry_run && $item['user_id'] > 0 ) {
                update_user_meta( $item['user_id'], CustomFields::MEMBER_NUMBER, $next );
            }
            $next++;
        }
    }

    /**
     * Fila del Excel de un usuario (DNI primero, luego email), o null.
     *
     * @param   \WP_User $user Usuario.
     * @return 	array|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function match_row( \WP_User $user ): ?array {
        $dni = SheetCsvReader::normalize_dni( (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_DNI, true ) );
        $email = SheetCsvReader::normalize_email( (string) $user->user_email );

        // Todas las filas de la persona, en las dos pestañas (puede estar en ambas).
        $found = [ 'socios' => [], 'antiguos' => [] ];
        foreach ( [ 'socios' => $this->current_rows, 'antiguos' => $this->former_rows ] as $sheet => $rows ) {
            foreach ( $rows as $row ) {
                $key = $sheet . ':' . $row['line'];
                if ( isset( $this->matched[ $key ] ) ) {
                    continue;
                }
                if ( ( '' !== $dni && $row['dni'] === $dni ) || ( '' !== $email && $row['email'] === $email ) ) {
                    $this->matched[ $key ] = $user->ID;
                    $row['sheet'] = $sheet;
                    $found[ $sheet ][] = $row;
                }
            }
        }

        if ( empty( $found['socios'] ) && empty( $found['antiguos'] ) ) {
            return null;
        }

        // Principal: la de «Antiguos» si en la web está de baja; si no, la de «Socios».
        $is_left = CustomFields::STATUS_LEFT === (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
        $order = $is_left ? [ 'antiguos', 'socios' ] : [ 'socios', 'antiguos' ];
        $primary = $found[ $order[0] ][0] ?? $found[ $order[1] ][0];

        // Datos que falten en la principal se toman de las otras filas.
        foreach ( array_merge( $found['socios'], $found['antiguos'] ) as $row ) {
            foreach ( [ 'number', 'join', 'leave' ] as $field ) {
                if ( ( null === $primary[ $field ] || '' === $primary[ $field ] ) && null !== $row[ $field ] && '' !== $row[ $field ] ) {
                    $primary[ $field ] = $row[ $field ];
                }
                // Una fecha de baja real gana a «desconocida» (la persona puede tener varias filas).
                if ( 'leave' === $field && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $primary['leave'] && '' !== $row['leave'] && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN !== $row['leave'] ) {
                    $primary['leave'] = $row['leave'];
                }
            }
        }

        $primary['sheets'] = array_keys( array_filter( $found ) );

        if ( ! empty( $found['socios'] ) && ! empty( $found['antiguos'] ) ) {
            $this->add( self::LEVEL_INFO, $this->user_who( $user ), sprintf( 'Aparece en las dos pestañas (socios línea %d, antiguos línea %d): se usa la de %s.', $found['socios'][0]['line'], $found['antiguos'][0]['line'], 'antiguos' === $primary['sheet'] ? '«Antiguos»' : '«Socios»' ) );
        }

        return $primary;
    }

    /** True si dos filas del Excel son de la misma persona (mismo DNI o email). */
    private static function same_person( array $a, array $b ): bool {
        return ( '' !== $a['dni'] && $a['dni'] === $b['dni'] ) || ( '' !== $a['email'] && $a['email'] === $b['email'] );
    }

    /**
     * Avisa de diferencias de datos personales entre web y Excel.
     *
     * @param   \WP_User $user Usuario.
     * @param   array    $row  Fila.
     * @param   string   $who  Quién.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function compare_personal_data( \WP_User $user, array $row, string $who ): void {
        $web = [
            'email' => SheetCsvReader::normalize_email( (string) $user->user_email ),
            'dni' => SheetCsvReader::normalize_dni( (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_DNI, true ) ),
            'birth' => LegacyMapping::acf_date_to_iso( (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_BIRTH, true ) ),
            'phone' => (string) preg_replace( '/[^\d+]/', '', (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_PHONE, true ) ),
        ];
        $labels = [
            'email' => 'Email',
            'dni' => 'DNI',
            'birth' => 'Fecha de nacimiento',
            'phone' => 'Teléfono',
        ];

        foreach ( $web as $field => $value ) {
            if ( '' !== $value && '' !== $row[ $field ] && $value !== $row[ $field ] ) {
                $this->add( self::LEVEL_WARNING, $who, sprintf( '%s no coincide: web «%s», Excel «%s». Se queda el valor de la web.', $labels[ $field ], $value, $row[ $field ] ) );
            }
        }
    }

    /** True si el socio ya tiene servicios o periodos (ya migrado). */
    private static function has_history( int $user_id ): bool {
        return MemberServices::has_any( $user_id ) || ! empty( StatusPeriods::for_user( $user_id ) );
    }

    /** Texto «Nombre (#ID)» para el informe. */
    private function user_who( \WP_User $user ): string {
        $name = HelperService::get_user_full_name( $user->ID ) ?: $user->display_name;
        return sprintf( '%s (#%d)', $name, $user->ID );
    }

    /** Texto «Nombre (hoja, línea N)» para el informe. */
    private function sheet_who( string $sheet, array $row ): string {
        return sprintf( '%s (%s, línea %d)', '' !== $row['name'] ? $row['name'] : '—', $sheet, $row['line'] );
    }

    /** Añade una entrada al informe. */
    private function add( string $level, string $who, string $message ): void {
        $this->report[] = [
            'level' => $level,
            'who' => $who,
            'message' => $message,
        ];
    }

    /** Día siguiente a una fecha Y-m-d. */
    private static function next_day( string $date ): string {
        return gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
    }

    /** Día anterior a una fecha Y-m-d. */
    private static function previous_day( string $date ): string {
        return gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
    }
}
