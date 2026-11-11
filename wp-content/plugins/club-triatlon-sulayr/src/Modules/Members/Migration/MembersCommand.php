<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Comandos WP-CLI de socios.
 *
 * Ejecutar siempre en el blog del club, p. ej.:
 *   wp --url=clubtriatlonsulayr.local.com sulayr socios migrar --socios=socios.csv --antiguos=antiguos.csv
 */
class MembersCommand {

    /**
     * Migra los deportistas y la hoja «Datos de socios» al modelo nuevo.
     *
     * Sin --ejecutar es un ensayo: no escribe nada y muestra el informe.
     *
     * ## OPTIONS
     *
     * [--socios=<csv>]
     * : Pestaña «Datos de los socios» exportada a CSV.
     *
     * [--antiguos=<csv>]
     * : Pestaña «Antiguos deportistas» exportada a CSV.
     *
     * [--informe=<csv>]
     * : Guarda el informe en este archivo CSV.
     *
     * [--alta-excel=<ids>]
     * : IDs de usuario, separados por comas, en los que manda la fecha de alta del Excel.
     *
     * [--ejecutar]
     * : Escribe de verdad. Sin esta opción solo se ensaya.
     *
     * ## EXAMPLES
     *
     *     wp sulayr socios migrar --socios=socios.csv --antiguos=antiguos.csv --informe=informe.csv
     *     wp sulayr socios migrar --socios=socios.csv --antiguos=antiguos.csv --ejecutar
     *
     * @param   array $args       Argumentos.
     * @param   array $assoc_args Opciones.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function migrar( $args, $assoc_args ): void {
        $dry_run = empty( $assoc_args['ejecutar'] );

        $current = isset( $assoc_args['socios'] ) ? SheetCsvReader::read( (string) $assoc_args['socios'] ) : [];
        $former = isset( $assoc_args['antiguos'] ) ? SheetCsvReader::read( (string) $assoc_args['antiguos'] ) : [];

        if ( empty( $current ) && empty( $former ) ) {
            \WP_CLI::warning( 'Sin CSV del Excel: no se traerán números de socio ni fechas de baja, y todos recibirán un número nuevo.' );
        }

        if ( false === $dry_run && false !== get_option( LegacyMigrator::MIGRATED_OPTION ) ) {
            \WP_CLI::log( sprintf( 'La migración ya se ejecutó el %s. Los socios ya migrados se saltarán.', get_option( LegacyMigrator::MIGRATED_OPTION ) ) );
        }

        \WP_CLI::log( $dry_run ? '== ENSAYO: no se escribe nada ==' : '== MIGRACIÓN REAL ==' );

        $excel_join_ids = isset( $assoc_args['alta-excel'] ) ? array_filter( array_map( 'intval', explode( ',', (string) $assoc_args['alta-excel'] ) ) ) : [];

        $migrator = new LegacyMigrator( $dry_run, $current, $former, $excel_join_ids );
        $result = $migrator->run();

        if ( ! empty( $result['report'] ) ) {
            \WP_CLI\Utils\format_items( 'table', $result['report'], [ 'level', 'who', 'message' ] );
        }

        if ( isset( $assoc_args['informe'] ) ) {
            $this->write_report( (string) $assoc_args['informe'], $result['report'] );
            \WP_CLI::log( 'Informe guardado en ' . $assoc_args['informe'] );
        }

        $levels = array_count_values( array_column( $result['report'], 'level' ) );
        \WP_CLI::log( sprintf(
            'Deportistas en la web: %d · ya migrados: %d · %s: %d · antiguos %s: %d · números nuevos: %d · errores: %d · avisos: %d',
            $result['stats']['socios_web'],
            $result['stats']['ya_migrados'],
            $dry_run ? 'se migrarían' : 'migrados',
            $result['stats']['migrados'],
            $dry_run ? 'que se crearían' : 'creados',
            $result['stats']['antiguos_creados'],
            $result['stats']['numeros_nuevos'],
            $levels[ LegacyMigrator::LEVEL_ERROR ] ?? 0,
            $levels[ LegacyMigrator::LEVEL_WARNING ] ?? 0
        ) );

        \WP_CLI::success( $dry_run ? 'Ensayo terminado. Revisa el informe y repite con --ejecutar.' : 'Migración terminada.' );
    }

    /**
     * Borra los datos que dejó la integración con Stripe (spec §12).
     *
     * Sin --ejecutar solo muestra lo que borraría.
     *
     * ## OPTIONS
     *
     * [--ejecutar]
     * : Borra de verdad.
     *
     * @subcommand limpiar-stripe
     *
     * @param   array $args       Argumentos.
     * @param   array $assoc_args Opciones.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function limpiar_stripe( $args, $assoc_args ): void {
        $dry_run = empty( $assoc_args['ejecutar'] );
        $result = StripeCleanup::run( $dry_run );

        foreach ( $result as $line ) {
            \WP_CLI::log( $line );
        }

        \WP_CLI::success( $dry_run ? 'Ensayo terminado. Repite con --ejecutar para borrar.' : 'Limpieza terminada.' );
    }

    /**
     * Escribe el informe en CSV (UTF-8 con BOM para que Excel lea las tildes).
     *
     * @param   string $path   Ruta.
     * @param   array  $report Entradas.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function write_report( string $path, array $report ): void {
        $handle = fopen( $path, 'w' );
        if ( false === $handle ) {
            \WP_CLI::error( 'No se puede escribir ' . $path );
        }

        fwrite( $handle, "\xEF\xBB\xBF" );
        fputcsv( $handle, [ 'Nivel', 'Quién', 'Mensaje' ], ';', '"', '\\' );
        foreach ( $report as $row ) {
            fputcsv( $handle, [ $row['level'], $row['who'], $row['message'] ], ';', '"', '\\' );
        }
        fclose( $handle );
    }
}
