<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Respuesta de la API REST de Redsys a un cobro sin el socio (SPEC-COBROS §5.2).
 *
 * La respuesta llega en la misma llamada, de una de estas formas:
 *
 * - Firmada (Ds_SignatureVersion, Ds_MerchantParameters, Ds_Signature), como
 *   una notificación: Ds_Response dice si está autorizada o denegada.
 * - {"errorCode": "SIS…"}: Redsys rechazó la petición y no hizo el cargo.
 *
 * Cualquier otra cosa (sin respuesta, HTTP distinto de 200, JSON roto, firma
 * no válida, otro pedido) es «desconocida»: no se sabe si se cobró, así que
 * la operación se queda «enviada» hasta consultarla (§5.2, paso 3).
 */
class RedsysRestResult {

    public const OUTCOME_AUTHORIZED = 'autorizada';
    public const OUTCOME_DENIED = 'denegada';
    public const OUTCOME_REJECTED = 'rechazada';
    public const OUTCOME_UNKNOWN = 'desconocida';

    /**
     * Errores SIS que no aseguran que el cargo no se hizo. SIS0051: pedido
     * repetido, puede que la primera petición sí llegara.
     */
    private const UNCERTAIN_ERRORS = [ 'SIS0051' ];

    /** @var string */
    private $outcome;

    /** @var RedsysNotification|null */
    private $notification;

    /** @var string Código SIS o motivo de la respuesta desconocida. */
    private $error;

    /** @var string Descripción del error. */
    private $error_text;

    /**
     * __construct()
     *
     * @param   string                  $outcome      Una de las constantes OUTCOME_*.
     * @param   RedsysNotification|null $notification Respuesta firmada.
     * @param   string                  $error        Código o motivo.
     * @param   string                  $error_text   Descripción.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function __construct( string $outcome, ?RedsysNotification $notification = null, string $error = '', string $error_text = '' ) {
        $this->outcome = $outcome;
        $this->notification = $notification;
        $this->error = $error;
        $this->error_text = $error_text;
    }

    /**
     * Interpreta la respuesta HTTP.
     *
     * @param   RedsysConfig $config    Credenciales.
     * @param   string       $order     Ds_Order enviado.
     * @param   int          $http_code Código HTTP (0 si no hubo respuesta).
     * @param   string       $body      Cuerpo recibido.
     * @return 	self
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function parse( RedsysConfig $config, string $order, int $http_code, string $body ): self {
        if ( 200 !== $http_code ) {
            return self::unknown( 'http_' . $http_code );
        }

        $data = json_decode( $body, true );
        if ( false === is_array( $data ) ) {
            return self::unknown( 'json' );
        }

        if ( isset( $data['errorCode'] ) && is_scalar( $data['errorCode'] ) && '' !== trim( (string) $data['errorCode'] ) ) {
            $code = trim( (string) $data['errorCode'] );
            $text = isset( $data['errorCodeDescription'] ) && is_scalar( $data['errorCodeDescription'] ) ? trim( (string) $data['errorCodeDescription'] ) : '';
            if ( in_array( $code, self::UNCERTAIN_ERRORS, true ) ) {
                return new self( self::OUTCOME_UNKNOWN, null, $code, $text );
            }

            return new self( self::OUTCOME_REJECTED, null, $code, $text );
        }

        $notification = RedsysNotification::from_request( $config, $data );
        if ( null === $notification ) {
            return self::unknown( 'firma' );
        }
        if ( $notification->order() !== $order ) {
            return self::unknown( 'pedido' );
        }

        return new self( $notification->is_authorized() ? self::OUTCOME_AUTHORIZED : self::OUTCOME_DENIED, $notification );
    }

    /**
     * Sin respuesta (error de red o tiempo agotado).
     *
     * @param   string $reason Motivo.
     * @return 	self
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function unknown( string $reason ): self {
        return new self( self::OUTCOME_UNKNOWN, null, $reason );
    }

    /** @return string Una de las constantes OUTCOME_*. */
    public function outcome(): string {
        return $this->outcome;
    }

    /** @return bool Redsys contestó con firma válida (autorizada o denegada). */
    public function is_signed(): bool {
        return null !== $this->notification;
    }

    /** @return RedsysNotification|null Respuesta firmada. */
    public function notification(): ?RedsysNotification {
        return $this->notification;
    }

    /** @return string Código SIS o motivo de la respuesta desconocida. */
    public function error(): string {
        return $this->error;
    }

    /** @return string Descripción del error. */
    public function error_text(): string {
        return $this->error_text;
    }

    /**
     * Datos para guardar en la operación (TpvTransactions los limpia).
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function params(): array {
        if ( null !== $this->notification ) {
            return $this->notification->params();
        }

        return array_filter( [
            'errorCode' => $this->error,
            'errorCodeDescription' => $this->error_text,
        ], 'strlen' );
    }
}
