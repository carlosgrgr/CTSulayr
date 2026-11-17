<?php
/**
 * Comprobación del ranking con WordPress (SPEC-RANKING-INTERNO §10.2). Solo en local.
 *
 *   wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-ranking.php
 *
 * Crea tres socios y una competición de prueba, comprueba esquema, roles,
 * puntos, clasificación, ocultación de los socios de baja, caché, registro,
 * vistas de wp-admin y del front, y lo borra todo al terminar.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ResultValidator;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Database\RankingSchema;
use ClubTriatlonSulayr\Modules\Ranking\Front\RankingView;
use ClubTriatlonSulayr\Modules\Ranking\RankingLog;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;
use ClubTriatlonSulayr\Modules\Ranking\Results;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$fails = 0;
$check = function ( string $label, bool $ok ) use ( &$fails ) {
    echo ( $ok ? 'OK    ' : 'FALLO ' ) . $label . "\n";
    if ( ! $ok ) {
        $fails++;
    }
};

global $wpdb;
$season = (int) current_time( 'Y' );
$users = [];
$competition_id = 0;
$log_start = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . RankingSchema::table( RankingSchema::LOG ) );
$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) ( $admin[0] ?? 1 ) );

try {
    // Esquema y roles.
    $check( 'Versión del esquema = 2', 2 === (int) get_option( RankingSchema::VERSION_OPTION ) );
    $columns = function ( string $table ) use ( $wpdb ) {
        return $wpdb->get_col( 'SHOW COLUMNS FROM ' . RankingSchema::table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    };
    $check( 'Esquema 2: interest_bonus e interest_label en competiciones', [] === array_diff( [ 'interest_bonus', 'interest_label' ], $columns( RankingSchema::COMPETITIONS ) ) );
    $check( 'Esquema 2: interest_bonus en resultados', in_array( 'interest_bonus', $columns( RankingSchema::RESULTS ), true ) );
    $check( 'Esquema 2: las competiciones que ya había quedan con bonus 0', 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RankingSchema::table( RankingSchema::COMPETITIONS ) . ' WHERE interest_bonus IS NULL' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    foreach ( [ RankingSchema::COMPETITIONS, RankingSchema::REFERENCES, RankingSchema::RESULTS, RankingSchema::LOG ] as $t ) {
        $check( "Tabla {$t}", RankingSchema::table( $t ) === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', RankingSchema::table( $t ) ) ) );
    }
    $role = get_role( MemberRoles::ROLE_RANKING_MANAGER );
    $check( 'Rol gestor de ranking con read, view_admin_dashboard y sulayr_manage_ranking', null !== $role && $role->has_cap( 'read' ) && $role->has_cap( 'view_admin_dashboard' ) && $role->has_cap( MemberRoles::MANAGE_RANKING ) );
    $check( 'Administrador con sulayr_manage_ranking', get_role( 'administrator' )->has_cap( MemberRoles::MANAGE_RANKING ) );
    $check( 'Gestor de socios sin sulayr_manage_ranking', false === get_role( MemberRoles::ROLE_MEMBER_MANAGER )->has_cap( MemberRoles::MANAGE_RANKING ) );
    $check( 'Athlete sin sulayr_manage_ranking', false === get_role( 'athlete' )->has_cap( MemberRoles::MANAGE_RANKING ) );

    // Socios de prueba: A (m, Veterano 1), B (f, Sénior), C (m, Sénior).
    $make = function ( string $login, string $first, string $last, string $sex, string $birth ) use ( &$users ) {
        $id = wp_insert_user( [ 'user_login' => $login, 'user_email' => $login . '@example.invalid', 'user_pass' => wp_generate_password(), 'first_name' => $first, 'last_name' => $last, 'role' => 'athlete' ] );
        if ( is_wp_error( $id ) ) {
            throw new RuntimeException( $id->get_error_message() );
        }
        $users[] = (int) $id;
        update_user_meta( $id, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_ACTIVE );
        update_user_meta( $id, CustomFields::MEMBER_SEX, $sex );
        update_user_meta( $id, CustomFields::USER_PROFILE_BIRTH, str_replace( '-', '', $birth ) );
        return (int) $id;
    };
    $age = function ( int $years ) use ( $season ) {
        return ( $season - $years ) . '-06-15';
    };
    $a = $make( 'rk_test_a', 'Ana', 'Prueba Uno', 'm', $age( 41 ) );
    $b = $make( 'rk_test_b', 'Bea', 'Prueba Dos', 'f', $age( 30 ) );
    $c = $make( 'rk_test_c', 'Carlos', 'Prueba Tres', 'm', $age( 30 ) );
    RankingRoster::flush();
    $check( 'Los tres socios salen en el ranking', RankingRoster::is_visible( $a ) && RankingRoster::is_visible( $b ) && RankingRoster::is_visible( $c ) );

    // Competición: triatlón sprint (×1,1), individual, como la guarda la pantalla.
    $groups = RankingSettings::groups( $season );
    $members = RankingRoster::selectable( $groups, $season );
    $date = current_time( 'Y-m-d' );
    $v = ResultValidator::validate( [
        'competition' => [ 'name' => 'Prueba ranking', 'race_date' => $date, 'location' => 'Local', 'modality' => 'triatlon', 'distance' => 'sprint', 'format' => 'individual' ],
        'references' => [
            [ 'scope' => 'absoluta', 'sex' => 'm', 'winner_time' => '1:00:00', 'participants' => '40' ],
            [ 'scope' => 'absoluta', 'sex' => 'f', 'winner_time' => '1:08:00', 'participants' => '20' ],
            [ 'scope' => 'grupo', 'sex' => 'm', 'age_group' => 'veterano-1', 'winner_time' => '1:05:00' ],
            [ 'scope' => 'grupo', 'sex' => 'm', 'age_group' => 'senior', 'winner_time' => '1:00:00' ],
            [ 'scope' => 'grupo', 'sex' => 'f', 'age_group' => 'senior', 'winner_time' => '1:08:00' ],
        ],
        'results' => [
            [ 'user_id' => $a, 'status' => 'finalizado', 'time' => '1:12:00', 'race_category' => 'V1M', 'position_sex' => '10', 'position_age_group' => '3' ],
            [ 'user_id' => $b, 'status' => 'finalizado', 'time' => '1:08:00', 'race_category' => 'SNF', 'position_sex' => '1', 'position_age_group' => '1' ],
            [ 'user_id' => $c, 'status' => 'finalizado', 'time' => '1:00:00', 'race_category' => 'ABM', 'position_sex' => '1', 'position_age_group' => '1' ],
        ],
    ], $members, $groups, $date );
    $check( 'Validación sin errores', false === ResultValidator::has_errors( $v['errors'] ) );
    $check( 'Sin referencias pendientes', [] === $v['missing'] );

    $competition_id = Competitions::insert( $v['competition'] );
    Competitions::save_references( $competition_id, $v['references'] );
    Results::save_rows( $competition_id, array_values( $v['results'] ) );
    RankingService::recalculate_competition( $competition_id );

    $by_user = [];
    foreach ( Results::for_competition( $competition_id ) as $r ) {
        $by_user[ (int) $r->user_id ] = $r;
    }
    $check( 'A: absoluta 966,67 (T1)', abs( $by_user[ $a ]->points_absolute - 966.67 ) < 0.001 );
    $check( 'A: grupo 1043,06 (T9)', abs( $by_user[ $a ]->points_age_group - 1043.06 ) < 0.001 );
    $check( 'A: grupo Veterano 1 copiado', 'veterano-1' === $by_user[ $a ]->age_group );
    $check( 'B: ganadora 1150 (T3)', abs( $by_user[ $b ]->points_absolute - 1150 ) < 0.001 );
    $check( 'C: AB, grupo de ganador 1150 (R29)', abs( $by_user[ $c ]->points_age_group - 1150 ) < 0.001 && 'absoluta' === $by_user[ $c ]->method_age_group );

    // Sin publicar no cuenta.
    $standings = RankingService::standings( $season );
    $check( 'Borrador: no sale en la clasificación', ! isset( $standings['positions'][ $a ] ) );

    Competitions::set_status( $competition_id, 'publicada' );
    RankingService::bump_cache();
    $standings = RankingService::standings( $season );
    $check( 'Publicada: A en la absoluta masculina', isset( $standings['positions'][ $a ]['absolute'] ) );
    $abs_m = array_column( $standings['absolute']['m'], 'user_id' );
    $check( 'C por delante de A en la absoluta masculina', array_search( $c, $abs_m, true ) < array_search( $a, $abs_m, true ) );
    $check( 'Caché: segunda lectura igual', $standings === RankingService::standings( $season ) );

    // Edición: cambiar el tiempo de A deja registro y recalcula.
    $rows = array_values( $v['results'] );
    $rows[0]['time_s'] = 4000;
    Results::save_rows( $competition_id, $rows );
    RankingService::recalculate_competition( $competition_id );
    $new_a = null;
    foreach ( Results::for_competition( $competition_id ) as $r ) {
        if ( (int) $r->user_id === $a ) {
            $new_a = $r;
        }
    }
    $check( 'A recalculado: 1000 × 0,9 × 1,1 + 50 = 1040', abs( $new_a->points_absolute - 1040 ) < 0.001 );
    $logged = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RankingSchema::table( RankingSchema::LOG ) . " WHERE id > %d AND entity = 'resultado' AND field = 'time_s' AND user_id = %d", $log_start, $a ) );
    $check( 'Registro del cambio de tiempo', 1 === $logged );

    // Cambio de sexo / fecha de nacimiento: recalcula al final de la petición.
    update_user_meta( $a, CustomFields::USER_PROFILE_BIRTH, str_replace( '-', '', $age( 30 ) ) );
    do_action( 'shutdown' );
    $moved = null;
    foreach ( Results::for_competition( $competition_id ) as $r ) {
        if ( (int) $r->user_id === $a ) {
            $moved = $r;
        }
    }
    $check( 'Nueva fecha de nacimiento: A pasa a Sénior', 'senior' === $moved->age_group );
    $check( 'Aviso de categoría (V1M frente a Sénior)', false !== strpos( (string) $moved->calc_note, 'V1M' ) );

    // Baja: desaparece del front pero conserva sus resultados.
    update_user_meta( $b, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_LEFT );
    do_action( 'shutdown' );
    RankingRoster::flush();
    $standings = RankingService::standings( $season );
    $check( 'B de baja: fuera de la clasificación', ! isset( $standings['positions'][ $b ] ) );
    $check( 'B de baja: conserva el resultado', 3 === count( Results::for_competition( $competition_id ) ) );
    $all = RankingService::standings( $season, true );
    $check( 'B de baja: sale en wp-admin marcada', isset( $all['hidden'][ $b ] ) );
    update_user_meta( $b, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_ACTIVE );
    do_action( 'shutdown' );
    RankingRoster::flush();
    $check( 'B vuelve: recupera sus puntos (R34)', isset( RankingService::standings( $season )['positions'][ $b ] ) );

    // Bonus por interés del club (§15): cambiarlo recalcula, queda en el registro y se copia al resultado.
    $values = RankingSettings::interest_values( $season );
    $check( 'Bonus por interés: valores de la temporada (0 a 300 de 50 en 50)', [ 0, 50, 100, 150, 200, 250, 300 ] === $values );
    $with_interest = ResultValidator::validate( [ 'competition' => array_merge( $v['competition'], [ 'interest_bonus' => '100', 'interest_label' => 'Circuito Granadino' ] ) ], [], $groups, $date, $values );
    $check( 'Bonus por interés: 100 se acepta', ! isset( $with_interest['errors']['competition']['interest_bonus'] ) );
    Competitions::update( $competition_id, $with_interest['competition'] );
    RankingService::recalculate_competition( $competition_id, true );
    $bonus_a = null;
    foreach ( Results::for_competition( $competition_id ) as $r ) {
        if ( (int) $r->user_id === $a ) {
            $bonus_a = $r;
        }
    }
    $check( 'Bonus por interés: A pasa de 1040 a 1140', abs( $bonus_a->points_absolute - 1140 ) < 0.001 && 100 === $bonus_a->interest_bonus );
    $logged = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RankingSchema::table( RankingSchema::LOG ) . " WHERE id > %d AND entity = 'competicion' AND field = 'interest_bonus' AND new_value = '100'", $log_start ) );
    $check( 'Bonus por interés: el cambio queda en el registro', 1 === $logged );

    // Baremo por temporada (R35): la anterior no cambia.
    $previous = $season - 1;
    $had_previous = false !== get_option( RankingSettings::SEASON_OPTION . $previous );
    $baremo = RankingSettings::for_season( $previous );
    $baremo['coefficients']['triatlon']['sprint'] = 1.5;
    update_option( RankingSettings::SEASON_OPTION . $previous, $baremo, 'no' );
    $check( 'Baremo de la temporada anterior independiente', 1.1 === RankingSettings::for_season( $season )['coefficients']['triatlon']['sprint'] && 1.5 === RankingSettings::for_season( $previous )['coefficients']['triatlon']['sprint'] );
    if ( ! $had_previous ) {
        delete_option( RankingSettings::SEASON_OPTION . $previous );
    }

    // Vistas: front como socio y pantallas de wp-admin como administrador.
    wp_set_current_user( $a );
    RankingRoster::flush();
    $html = RankingView::render( home_url( '/mi-cuenta/ranking-interno/' ) );
    $check( 'Front: clasificación con la fila propia', false !== strpos( $html, 'sulayr-ranking-table' ) && false !== strpos( $html, 'is-me' ) );
    $_GET['deportista'] = (string) $c;
    $html = RankingView::render( home_url( '/mi-cuenta/ranking-interno/' ) );
    $check( 'Front: ficha del deportista', false !== strpos( $html, 'Prueba Tres' ) && false !== strpos( $html, 'sulayr-ranking-breakdown' ) );
    $check( 'Front: desglose con el interés del club', false !== strpos( $html, '+ 100 interés del club' ) );
    unset( $_GET['deportista'] );
    $_GET['competicion'] = (string) $competition_id;
    $html = RankingView::render( home_url( '/mi-cuenta/ranking-interno/' ) );
    $check( 'Front: ficha de la competición', false !== strpos( $html, 'Prueba ranking' ) && false !== strpos( $html, '1:00:00' ) );
    $check( 'Front: etiqueta del bonus por interés', false !== strpos( $html, 'sulayr-ranking-interest' ) && false !== strpos( $html, '+100 · Circuito Granadino' ) );
    unset( $_GET['competicion'] );

    update_user_meta( $a, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_LEFT );
    RankingRoster::flush();
    $html = RankingView::render( home_url( '/mi-cuenta/ranking-interno/' ) );
    $check( 'Front: un socio de baja no lo ve', false === strpos( $html, 'sulayr-ranking-table' ) );
    update_user_meta( $a, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_ACTIVE );
    RankingRoster::flush();

    wp_set_current_user( (int) ( $admin[0] ?? 1 ) );
    $pages = [
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\RankingMenu(), 'render', [ 'page' => 'sulayr-ranking' ] ],
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\CompetitionEditPage(), 'render', [ 'page' => 'sulayr-ranking-competicion', 'id' => (string) $competition_id ] ],
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\CompetitionEditPage(), 'render', [ 'page' => 'sulayr-ranking-competicion' ] ],
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\StandingsPage(), 'render', [ 'page' => 'sulayr-ranking-clasificacion', 'vista' => 'grupo', 'grupo' => 'senior' ] ],
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\AthletesPage(), 'render', [ 'page' => 'sulayr-ranking-deportistas' ] ],
        [ new \ClubTriatlonSulayr\Modules\Ranking\Admin\LogPage(), 'render', [ 'page' => 'sulayr-ranking-registro' ] ],
    ];
    foreach ( $pages as list( $object, $method, $get ) ) {
        $_GET = $get;
        ob_start();
        $object->$method();
        $out = ob_get_clean();
        $check( 'wp-admin: ' . http_build_query( $get ), false !== strpos( $out, 'sulayr-brand' ) && false === stripos( $out, 'warning:' ) && false === stripos( $out, 'fatal' ) );
    }
    $_GET = [];

    // Borrado.
    Competitions::delete( $competition_id );
    $check( 'Borrado: sin resultados ni referencias', [] === Results::for_competition( $competition_id ) && [] === Competitions::references( $competition_id ) );
    $deleted = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RankingSchema::table( RankingSchema::LOG ) . " WHERE competition_id = %d AND field = 'borrado' AND entity = 'competicion'", $competition_id ) );
    $check( 'Borrado: copia en el registro', 1 === $deleted );
    $competition_id = 0;
} catch ( Throwable $e ) {
    $check( 'Excepción: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(), false );
} finally {
    if ( $competition_id > 0 ) {
        Competitions::delete( $competition_id );
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    require_once ABSPATH . 'wp-admin/includes/ms.php';
    foreach ( $users as $id ) {
        $wpdb->delete( $wpdb->prefix . 'sulayr_member_log', [ 'user_id' => $id ], [ '%d' ] );
        is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id );
    }
    // El registro del ranking solo inserta; las entradas de la prueba se quitan aquí a mano.
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . RankingSchema::table( RankingSchema::LOG ) . ' WHERE id > %d', $log_start ) );
    RankingService::bump_cache();
}

echo "\n" . ( 0 === $fails ? 'Todo correcto.' : "{$fails} comprobaciones fallidas." ) . "\n";
