<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Credenciales y URLs de Redsys (SPEC-COBROS §5, C29).
 *
 * Las credenciales viven solo en wp-config.php, nunca en la base de datos ni
 * en el repositorio. Así una copia de la base de datos de PROD en local no
 * lleva la clave real ni cobra de verdad:
 *
 *     define( 'SULAYR_REDSYS_MERCHANT_CODE', '999008881' );
 *     define( 'SULAYR_REDSYS_TERMINAL', '1' );
 *     define( 'SULAYR_REDSYS_SECRET_KEY', '...' );
 *     define( 'SULAYR_REDSYS_ENVIRONMENT', 'test' ); // o 'live'
 *
 * El entorno real solo se usa si se pone 'live' de forma explícita.
 *
 * Las URLs son las públicas de Redsys. Hay que confirmarlas con el manual
 * que dé el técnico.
 */
class RedsysConfig {

    public const ENV_TEST = 'test';
    public const ENV_LIVE = 'live';

    /** Euro (ISO 4217 numérico). */
    public const CURRENCY_EUR = '978';

    private const URLS = [
        self::ENV_TEST => [
            'redirect' => 'https://sis-t.redsys.es:25443/sis/realizarPago',
            'rest' => 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST',
        ],
        self::ENV_LIVE => [
            'redirect' => 'https://sis.redsys.es/sis/realizarPago',
            'rest' => 'https://sis.redsys.es/sis/rest/trataPeticionREST',
        ],
    ];

    /** @var string */
    private $merchant_code;

    /** @var string */
    private $terminal;

    /** @var string */
    private $secret_key;

    /** @var string */
    private $environment;

    /**
     * __construct()
     *
     * @param   string $merchant_code Código de comercio (FUC).
     * @param   string $terminal      Terminal.
     * @param   string $secret_key    Clave SHA-256 del comercio (en base64).
     * @param   string $environment   ENV_TEST o ENV_LIVE. Cualquier otro valor es pruebas.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( string $merchant_code, string $terminal, string $secret_key, string $environment = self::ENV_TEST ) {
        $this->merchant_code = trim( $merchant_code );
        $this->terminal = trim( $terminal );
        $this->secret_key = trim( $secret_key );
        $this->environment = self::ENV_LIVE === $environment ? self::ENV_LIVE : self::ENV_TEST;
    }

    /**
     * Configuración leída de las constantes de wp-config.php.
     *
     * @return 	self
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function from_constants(): self {
        $read = static function ( string $name ): string {
            return defined( $name ) ? (string) constant( $name ) : '';
        };

        return new self(
            $read( 'SULAYR_REDSYS_MERCHANT_CODE' ),
            $read( 'SULAYR_REDSYS_TERMINAL' ),
            $read( 'SULAYR_REDSYS_SECRET_KEY' ),
            $read( 'SULAYR_REDSYS_ENVIRONMENT' )
        );
    }

    /**
     * Hay credenciales completas y la clave tiene el formato de Redsys.
     *
     * Una clave mal copiada en wp-config.php cuenta como sin configurar: no
     * salen los botones de pago, no se cobra y los avisos se rechazan, en vez
     * de un error fatal en RedsysSignature (staging, 25 de noviembre de 2026).
     *
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function is_configured(): bool {
        return '' !== $this->merchant_code && '' !== $this->terminal && $this->has_valid_key();
    }

    /**
     * La clave es base64 de 24 bytes, como la que da Redsys (la exige RedsysSignature).
     *
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function has_valid_key(): bool {
        $key = base64_decode( $this->secret_key, true );
        return false !== $key && 24 === strlen( $key );
    }

    /** @return string */
    public function merchant_code(): string {
        return $this->merchant_code;
    }

    /** @return string */
    public function terminal(): string {
        return $this->terminal;
    }

    /** @return string */
    public function secret_key(): string {
        return $this->secret_key;
    }

    /** @return string */
    public function environment(): string {
        return $this->environment;
    }

    /** @return bool */
    public function is_live(): bool {
        return self::ENV_LIVE === $this->environment;
    }

    /**
     * URL del formulario de pago con redirección (§5.1).
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function redirect_url(): string {
        return self::URLS[ $this->environment ]['redirect'];
    }

    /**
     * URL de la API REST para cobros sin el socio delante (§5.2).
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function rest_url(): string {
        return self::URLS[ $this->environment ]['rest'];
    }
}
