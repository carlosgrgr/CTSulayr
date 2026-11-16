<?php
/**
 * Comprueba un JSON de importación del ranking interno con el mismo código que usa la web
 * (ImportParser e ImportMerger del plugin club-triatlon-sulayr) y resume lo que importará.
 *
 * Uso: php .claude/skills/ranking-importar/validar.php <archivo.json> [AAAA-MM-DD de hoy]
 * Sale con código 1 si el JSON tiene errores.
 */

use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportMerger;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportParser;
use ClubTriatlonSulayr\Modules\Ranking\Calc\NameMatcher;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;

$root = dirname( __DIR__, 3 );
require $root . '/wp-content/plugins/club-triatlon-sulayr/vendor/autoload.php';

$file = $argv[1] ?? '';
if ( '' === $file || ! is_readable( $file ) ) {
    fwrite( STDERR, "Uso: php validar.php <archivo.json> [AAAA-MM-DD]\n" );
    exit( 2 );
}
$today = $argv[2] ?? date( 'Y-m-d' );

$parsed = ImportParser::parse( (string) file_get_contents( $file ), $today );
if ( ! empty( $parsed['errors'] ) ) {
    echo "ERRORES (la web no lo aceptará):\n";
    foreach ( $parsed['errors'] as $error ) {
        echo "  - {$error}\n";
    }
    exit( 1 );
}

$c = $parsed['competition'];
echo "OK: {$c['name']} · {$c['race_date']} · {$c['location']} · {$c['modality']} · {$c['distance']} · {$c['format']}\n\n";

echo "Clasificaciones del JSON:\n";
$count = [];
foreach ( $parsed['athletes'] as $a ) {
    $count[ $a['classification'] ][ $a['status'] ] = ( $count[ $a['classification'] ][ $a['status'] ] ?? 0 ) + 1;
}
foreach ( $parsed['classifications'] as $i => $cl ) {
    $by_status = [];
    foreach ( $count[ $i ] ?? [] as $status => $n ) {
        $by_status[] = "{$status}: {$n}";
    }
    $declared = null !== $cl['finishers'] ? " · FINALIZADOS en el PDF: {$cl['finishers']}" : '';
    echo "  - {$cl['title']} (" . ( '' !== $cl['team_category'] ? 'relevos ' . $cl['team_category'] : 'individual' ) . '): ' . implode( ', ', $by_status ) . "{$declared}\n";
}

$merged = ImportMerger::merge( $parsed );
echo "\nConjuntos del ranking (clasificaciones unificadas):\n";
foreach ( $merged['groups'] as $g ) {
    $titles = array_map( function ( $i ) use ( $parsed ) {
        return $parsed['classifications'][ $i ]['title'];
    }, $g['sources'] );
    echo '  - ' . ImportMerger::group_label( $g['team_category'], $g['sex'] ) . ': ' . implode( ' + ', $titles )
        . " · ganador " . ( null !== $g['winner'] ? RaceTime::format( $g['winner'] ) : '—' )
        . " · finalizados {$g['participants']}\n";
}

if ( ! empty( $merged['warnings'] ) ) {
    echo "\nAVISOS:\n";
    foreach ( $merged['warnings'] as $warning ) {
        echo "  - {$warning}\n";
    }
}

$club = new NameMatcher( [], [ 'SULAYR' ] );
echo "\nDeportistas de equipos del club (Pos y Pos Cat ya unificadas):\n";
$rows = array_filter( $merged['athletes'], function ( $a ) use ( $club ) {
    return $club->is_club_team( $a['club'] );
} );
usort( $rows, function ( $a, $b ) {
    return [ $a['team_category'], $a['sex'], $a['pos'] ?? PHP_INT_MAX ] <=> [ $b['team_category'], $b['sex'], $b['pos'] ?? PHP_INT_MAX ];
} );
foreach ( $rows as $a ) {
    $time = null !== $a['time_s'] ? RaceTime::format( $a['time_s'] ) : strtoupper( $a['status'] );
    $pad = function ( string $text, int $width ): string {
        return $text . str_repeat( ' ', max( 1, $width - mb_strlen( $text ) ) );
    };
    echo '  - ' . $pad( null !== $a['pos'] ? (string) $a['pos'] : '—', 5 ) . $pad( $a['name'], 38 ) . $pad( $a['cat'], 5 ) . $pad( null !== $a['pos_cat'] ? (string) $a['pos_cat'] : '—', 4 ) . $pad( $time, 9 ) . $a['club'] . "\n";
}
echo "\nTotal: " . count( $merged['athletes'] ) . ' deportistas, ' . count( $rows ) . " de equipos del club.\n";
