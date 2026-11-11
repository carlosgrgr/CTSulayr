<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Modules\Members\Migration\LegacyMigrator;
use ClubTriatlonSulayr\Modules\Members\Migration\SheetCsvReader;
use ClubTriatlonSulayr\Modules\Members\Migration\StripeCleanup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Socios → Migración»: la migración (spec §11) y la limpieza de Stripe
 * (spec §12) desde wp-admin, para servidores sin SSH ni WP-CLI.
 *
 * Solo administradores. Los CSV se leen del archivo temporal de la subida y
 * no se guardan. Ensayo por defecto; ejecutar exige marcar una casilla.
 */
class MigrationPage {

    public const PAGE_SLUG = 'sulayr-socios-migracion';
    private const CAPABILITY = 'manage_options';
    private const NONCE = 'sulayr_members_migration';

    /** Resultado de la acción de esta petición. */
    private ?array $result = null;
    private string $error = '';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 12 );
    }

    /**
     * Submenú.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        $hook = add_submenu_page(
            MembersMenu::SLUG,
            __( 'Migración de socios', SULAYR_TEXTDOMAIN ),
            __( 'Migración', SULAYR_TEXTDOMAIN ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );

        add_action( 'load-' . $hook, [ $this, 'handle' ] );
    }

    /**
     * Procesa el formulario antes de pintar la página.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['sulayr_action'] ) ) {
            return;
        }

        if ( false === current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'No tienes permiso para hacer esto.', SULAYR_TEXTDOMAIN ) );
        }

        check_admin_referer( self::NONCE );

        $action = sanitize_key( wp_unslash( $_POST['sulayr_action'] ) );
        $execute = isset( $_POST['sulayr_execute'] ) && '1' === $_POST['sulayr_execute'];

        try {
            if ( 'stripe' === $action ) {
                $this->result = [
                    'type' => 'stripe',
                    'dry_run' => false === $execute,
                    'lines' => StripeCleanup::run( false === $execute ),
                ];
                return;
            }

            if ( 'migrate' === $action ) {
                $current = $this->read_upload( 'sulayr_socios' );
                $former = $this->read_upload( 'sulayr_antiguos' );
                $ids = isset( $_POST['sulayr_alta_excel'] ) ? array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['sulayr_alta_excel'] ) ) ) ) ) : [];

                if ( empty( $current ) && empty( $former ) ) {
                    $this->error = __( 'Sube al menos uno de los dos CSV.', SULAYR_TEXTDOMAIN );
                    return;
                }

                $migrator = new LegacyMigrator( false === $execute, $current, $former, $ids );
                $this->result = array_merge( [ 'type' => 'migrate', 'dry_run' => false === $execute ], $migrator->run() );
            }
        } catch ( \Throwable $e ) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Filas de un CSV subido, o [] si no se subió.
     *
     * @param   string $field Nombre del campo de archivo.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function read_upload( string $field ): array {
        if ( empty( $_FILES[ $field ]['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] ) {
            return [];
        }

        $name = (string) $_FILES[ $field ]['name'];
        if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
            throw new \RuntimeException( sprintf( __( '«%s» no es un archivo CSV.', SULAYR_TEXTDOMAIN ), $name ) );
        }

        // Se lee del temporal de PHP, que se borra al terminar la petición.
        return SheetCsvReader::read( (string) $_FILES[ $field ]['tmp_name'] );
    }

    /**
     * Página.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        if ( false === current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ) );
        }

        $migrated = get_option( LegacyMigrator::MIGRATED_OPTION );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Migración de socios', SULAYR_TEXTDOMAIN ); ?></h1>

            <?php if ( false !== $migrated ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( sprintf( __( 'La migración ya se ejecutó el %s. Repetirla solo añade lo que falte: los socios ya migrados se saltan.', SULAYR_TEXTDOMAIN ), $migrated ) ); ?></p></div>
            <?php endif; ?>

            <?php if ( '' !== $this->error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $this->error ); ?></p></div>
            <?php endif; ?>

            <?php $this->render_result(); ?>

            <h2><?php esc_html_e( '1. Limpiar Stripe', SULAYR_TEXTDOMAIN ); ?></h2>
            <p><?php esc_html_e( 'Borra las claves de Stripe, los datos de suscripción de los usuarios y los productos de tarifa. Un producto que aparezca en algún pedido pasa a borrador.', SULAYR_TEXTDOMAIN ); ?></p>
            <form method="post">
                <?php wp_nonce_field( self::NONCE ); ?>
                <input type="hidden" name="sulayr_action" value="stripe">
                <?php $this->render_execute_box( __( 'Borrar de verdad (si no, solo se muestra lo que se borraría)', SULAYR_TEXTDOMAIN ) ); ?>
                <?php submit_button( __( 'Limpiar Stripe', SULAYR_TEXTDOMAIN ), 'secondary', 'submit', false ); ?>
            </form>

            <h2><?php esc_html_e( '2. Migrar socios', SULAYR_TEXTDOMAIN ); ?></h2>
            <p><?php esc_html_e( 'Exporta a CSV las pestañas de la hoja «Datos de socios» (Archivo → Descargar → CSV, una pestaña cada vez). Los archivos se leen al enviar el formulario y no se guardan en el servidor.', SULAYR_TEXTDOMAIN ); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( self::NONCE ); ?>
                <input type="hidden" name="sulayr_action" value="migrate">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sulayr_socios"><?php esc_html_e( '«Datos de los socios» (CSV)', SULAYR_TEXTDOMAIN ); ?></label></th>
                        <td><input type="file" id="sulayr_socios" name="sulayr_socios" accept=".csv"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sulayr_antiguos"><?php esc_html_e( '«Antiguos deportistas» (CSV)', SULAYR_TEXTDOMAIN ); ?></label></th>
                        <td><input type="file" id="sulayr_antiguos" name="sulayr_antiguos" accept=".csv"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sulayr_alta_excel"><?php esc_html_e( 'Usar la fecha de alta del Excel en', SULAYR_TEXTDOMAIN ); ?></label></th>
                        <td>
                            <input type="text" id="sulayr_alta_excel" name="sulayr_alta_excel" class="regular-text" value="<?php echo esc_attr( isset( $_POST['sulayr_alta_excel'] ) ? sanitize_text_field( wp_unslash( $_POST['sulayr_alta_excel'] ) ) : '' ); ?>" placeholder="17,40,63">
                            <p class="description"><?php esc_html_e( 'IDs de usuario separados por comas. En el resto manda la fecha de la web.', SULAYR_TEXTDOMAIN ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php $this->render_execute_box( __( 'Migrar de verdad (si no, es un ensayo que no escribe nada)', SULAYR_TEXTDOMAIN ) ); ?>
                <?php submit_button( __( 'Migrar socios', SULAYR_TEXTDOMAIN ), 'primary', 'submit', false ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Casilla para ejecutar de verdad.
     *
     * @param   string $label Texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_execute_box( string $label ): void {
        printf(
            '<p><label><input type="checkbox" name="sulayr_execute" value="1"> %s</label></p>',
            esc_html( $label )
        );
    }

    /**
     * Resultado de la acción.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_result(): void {
        if ( null === $this->result ) {
            return;
        }

        $title = $this->result['dry_run'] ? __( 'Ensayo: no se ha escrito nada', SULAYR_TEXTDOMAIN ) : __( 'Hecho', SULAYR_TEXTDOMAIN );
        echo '<div class="notice ' . ( $this->result['dry_run'] ? 'notice-warning' : 'notice-success' ) . '"><p><strong>' . esc_html( $title ) . '</strong></p>';

        if ( 'stripe' === $this->result['type'] ) {
            echo '<ul>';
            foreach ( $this->result['lines'] as $line ) {
                echo '<li>' . esc_html( $line ) . '</li>';
            }
            echo '</ul></div>';
            return;
        }

        $stats = $this->result['stats'];
        $levels = array_count_values( array_column( $this->result['report'], 'level' ) );
        printf(
            '<p>%s</p></div>',
            esc_html( sprintf(
                'Deportistas en la web: %d · ya migrados: %d · %s: %d · antiguos %s: %d · números nuevos: %d · errores: %d · avisos: %d',
                $stats['socios_web'],
                $stats['ya_migrados'],
                $this->result['dry_run'] ? 'se migrarían' : 'migrados',
                $stats['migrados'],
                $this->result['dry_run'] ? 'que se crearían' : 'creados',
                $stats['antiguos_creados'],
                $stats['numeros_nuevos'],
                $levels[ LegacyMigrator::LEVEL_ERROR ] ?? 0,
                $levels[ LegacyMigrator::LEVEL_WARNING ] ?? 0
            ) )
        );

        $order = [ LegacyMigrator::LEVEL_ERROR => 0, LegacyMigrator::LEVEL_WARNING => 1, LegacyMigrator::LEVEL_INFO => 2 ];
        $report = $this->result['report'];
        usort( $report, function ( $a, $b ) use ( $order ) {
            return ( $order[ $a['level'] ] ?? 3 ) <=> ( $order[ $b['level'] ] ?? 3 ) ?: strcmp( $a['who'], $b['who'] );
        } );

        echo '<h2>' . esc_html__( 'Informe', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th style="width:70px">' . esc_html__( 'Nivel', SULAYR_TEXTDOMAIN ) . '</th><th style="width:30%">' . esc_html__( 'Quién', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Mensaje', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
        foreach ( $report as $row ) {
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( $row['level'] ),
                esc_html( $row['who'] ),
                esc_html( $row['message'] )
            );
        }
        echo '</tbody></table>';
    }
}
