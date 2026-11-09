<?php
/**
 * Plugin Name:       Sulayr Scripts Manager
 * Description:       Inserta códigos de seguimiento (Google Tag Manager, Analytics…) en el head, al abrir el body o tras el footer. Cada sitio de la red tiene sus propios códigos en Ajustes → Scripts.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Club Triatlón Sulayr
 * Text Domain:       sulayr-scripts-manager
 *
 * @package SulayrScriptsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Códigos de seguimiento por sitio.
 *
 * Los códigos son HTML sin filtrar (<script>), así que solo puede verlos y
 * guardarlos quien tenga `unfiltered_html`. En multisite eso es solo el
 * superadministrador de la red.
 */
final class Sulayr_Scripts_Manager {

	const OPTION     = 'sulayr_scripts_manager_codes';
	const GROUP      = 'sulayr_scripts_manager';
	const PAGE       = 'sulayr-scripts-manager';
	const CAPABILITY = 'unfiltered_html';

	/**
	 * Ubicaciones disponibles: clave => array( etiqueta, ayuda ).
	 *
	 * @return array
	 */
	private static function locations() {
		return array(
			'head'   => array(
				'Justo después de <head>',
				'Para el script principal de Google Tag Manager o Analytics.',
			),
			'body'   => array(
				'Justo después de <body>',
				'Por ejemplo, el <noscript> de Google Tag Manager.',
			),
			'footer' => array(
				'Después del footer (antes de </body>)',
				'Scripts que pueden cargarse al final de la página.',
			),
		);
	}

	/**
	 * Engancha la salida en el front y la página de opciones.
	 */
	public static function init() {
		// Astra ofrece ganchos justo tras <head> y <body>; con otros temas se usan los de WordPress.
		add_action( 'astra_head_top', array( __CLASS__, 'print_head' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
		add_action( 'astra_body_top', array( __CLASS__, 'print_body' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_body' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 100 );

		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'capability' ) );
	}

	/**
	 * Código guardado para una ubicación.
	 *
	 * @param string $location head, body o footer.
	 * @return string
	 */
	private static function code( $location ) {
		$codes = get_option( self::OPTION, array() );

		return is_array( $codes ) && isset( $codes[ $location ] ) ? (string) $codes[ $location ] : '';
	}

	/**
	 * Imprime una ubicación una sola vez por petición, aunque se llame desde dos ganchos.
	 *
	 * @param string $location head, body o footer.
	 */
	private static function output( $location ) {
		static $done = array();

		if ( isset( $done[ $location ] ) ) {
			return;
		}
		$done[ $location ] = true;

		$code = trim( self::code( $location ) );
		if ( '' === $code ) {
			return;
		}

		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML guardado por un usuario con unfiltered_html.
	}

	/** Salida tras <head>. */
	public static function print_head() {
		self::output( 'head' );
	}

	/** Salida tras <body>. */
	public static function print_body() {
		self::output( 'body' );
	}

	/** Salida al final de la página. */
	public static function print_footer() {
		self::output( 'footer' );
	}

	/**
	 * Capacidad necesaria para guardar desde options.php.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAPABILITY;
	}

	/** Registra la opción en la API de ajustes. */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Guarda el HTML tal cual solo si el usuario puede publicar HTML sin filtrar.
	 *
	 * @param mixed $input Valor enviado.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return get_option( self::OPTION, array() );
		}

		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( array_keys( self::locations() ) as $location ) {
			$clean[ $location ] = isset( $input[ $location ] ) ? trim( (string) $input[ $location ] ) : '';
		}

		return $clean;
	}

	/** Añade Ajustes → Scripts. */
	public static function add_page() {
		add_options_page(
			'Scripts',
			'Scripts',
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/** Pinta la página de opciones. */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Scripts</h1>
			<p>Pega aquí los códigos de seguimiento de este sitio. Se insertan tal cual en todas las páginas públicas. Deja un campo vacío para no insertar nada en esa ubicación.</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( self::locations() as $location => $labels ) : ?>
						<?php $id = 'sulayr-scripts-manager-' . $location; ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $labels[0] ); ?></label></th>
							<td>
								<textarea
									id="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( self::OPTION . '[' . $location . ']' ); ?>"
									rows="8"
									class="large-text code"
									spellcheck="false"
								><?php echo esc_textarea( self::code( $location ) ); ?></textarea>
								<p class="description"><?php echo esc_html( $labels[1] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

Sulayr_Scripts_Manager::init();
