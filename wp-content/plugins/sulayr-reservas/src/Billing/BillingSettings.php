<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tarifa e importe por deportista del sitio de reservas, con su registro de cambios.
 *
 * Debe usarse con el blog de reservas como sitio actual.
 */
class BillingSettings {

	const OPTION_TARIFF      = 'sulayr_billing_tariff';
	const OPTION_START_MONTH = 'sulayr_billing_start_month';

	/**
	 * Al instalar: tarifa de 4.1, su entrada «valor inicial (sistema)» y el mes de instalación.
	 * No hace nada si ya está hecho.
	 *
	 * @return void
	 */
	public function bootstrapIfNeeded() {
		if ( false !== get_option( self::OPTION_TARIFF, false ) ) {
			return;
		}

		$initial = Tariff::initial();
		if ( ! add_option( self::OPTION_TARIFF, $initial->toArray(), '', false ) ) {
			return;
		}

		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		add_option( self::OPTION_START_MONTH, $now->format( 'Y-m' ), '', false );

		foreach ( $initial->toArray() as $field => $value ) {
			if ( null === $value ) {
				continue;
			}
			$this->log( $field, '', self::stored( $field, $value ), 0, 'sistema' );
		}
	}

	/**
	 * @return Tariff
	 */
	public function tariff() {
		$values = get_option( self::OPTION_TARIFF, array() );

		return Tariff::fromArray( is_array( $values ) ? $values : array() );
	}

	/**
	 * Primer mes que se puede generar: el de instalación de esta fase.
	 *
	 * @return string `YYYY-MM`, o cadena vacía si aún no se ha instalado.
	 */
	public function startMonth() {
		$month = (string) get_option( self::OPTION_START_MONTH, '' );

		return preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : '';
	}

	/**
	 * Valida los valores del formulario sin guardarlos.
	 *
	 * @param array<string, string> $input Texto de cada campo.
	 * @return array{tariff: Tariff|null, errors: string[]}
	 */
	public function validate( array $input ) {
		$labels = Tariff::labels();
		$errors = array();
		$values = array();

		foreach ( array( Tariff::BASE_FEE, Tariff::EXTRA_PRICE, Tariff::MAX_FEE ) as $field ) {
			$text  = isset( $input[ $field ] ) ? (string) $input[ $field ] : '';
			$cents = Money::parse( $text );
			if ( null === $cents ) {
				$errors[] = $labels[ $field ] . ': escribe un importe mayor o igual que 0, con dos decimales como máximo.';
			}
			$values[ $field ] = $cents;
		}

		$included = isset( $input[ Tariff::INCLUDED_USERS ] ) ? trim( (string) $input[ Tariff::INCLUDED_USERS ] ) : '';
		if ( ! preg_match( '/^\d{1,6}$/', $included ) ) {
			$errors[] = $labels[ Tariff::INCLUDED_USERS ] . ': escribe un número entero mayor o igual que 0.';
			$values[ Tariff::INCLUDED_USERS ] = null;
		} else {
			$values[ Tariff::INCLUDED_USERS ] = (int) $included;
		}

		$athlete = isset( $input[ Tariff::ATHLETE_AMOUNT ] ) ? trim( (string) $input[ Tariff::ATHLETE_AMOUNT ] ) : '';
		$values[ Tariff::ATHLETE_AMOUNT ] = null;
		if ( '' !== $athlete ) {
			$values[ Tariff::ATHLETE_AMOUNT ] = Money::parse( $athlete );
			if ( null === $values[ Tariff::ATHLETE_AMOUNT ] ) {
				$errors[] = $labels[ Tariff::ATHLETE_AMOUNT ] . ': escribe un importe mayor o igual que 0, con dos decimales como máximo, o déjalo vacío.';
			}
		}

		if ( null !== $values[ Tariff::BASE_FEE ] && null !== $values[ Tariff::MAX_FEE ] && $values[ Tariff::MAX_FEE ] < $values[ Tariff::BASE_FEE ] ) {
			$errors[] = 'El importe máximo mensual no puede ser menor que la cuota base.';
		}

		if ( array() !== $errors ) {
			return array(
				'tariff' => null,
				'errors' => $errors,
			);
		}

		return array(
			'tariff' => Tariff::fromArray( $values ),
			'errors' => array(),
		);
	}

	/**
	 * Guarda los valores y anota cada uno que cambie.
	 *
	 * @param Tariff   $tariff    Valores ya validados.
	 * @param \WP_User $user      Quien hace el cambio.
	 * @return int Número de valores cambiados.
	 */
	public function save( Tariff $tariff, \WP_User $user ) {
		$before  = $this->tariff()->toArray();
		$after   = $tariff->toArray();
		$changed = array();

		foreach ( $after as $field => $value ) {
			if ( $before[ $field ] !== $value ) {
				$changed[ $field ] = $value;
			}
		}

		if ( array() === $changed ) {
			return 0;
		}

		update_option( self::OPTION_TARIFF, $after, false );

		$name = '' !== $user->display_name ? $user->display_name : $user->user_login;
		foreach ( $changed as $field => $value ) {
			$this->log( $field, self::stored( $field, $before[ $field ] ), self::stored( $field, $value ), (int) $user->ID, $name );
		}

		return count( $changed );
	}

	/**
	 * Registro de cambios, del más reciente al más antiguo.
	 *
	 * @param int $limit Máximo de filas.
	 * @return object[]
	 */
	public function changes( $limit = 200 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sulayr_billing_changes';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY changed_at DESC, id DESC LIMIT %d", absint( $limit ) ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Texto visible de un valor guardado en el registro.
	 *
	 * @param string $field Clave del valor.
	 * @param string $value Valor guardado (`9.00`, `25` o vacío).
	 * @return string
	 */
	public static function displayStored( $field, $value ) {
		if ( '' === (string) $value ) {
			return 'vacío';
		}

		if ( ! Tariff::isMoney( $field ) ) {
			return (string) (int) $value;
		}

		return Money::format( (int) round( (float) $value * 100 ) );
	}

	/**
	 * @param string   $field Clave del valor.
	 * @param int|null $value Céntimos o número de usuarios.
	 * @return string
	 */
	private static function stored( $field, $value ) {
		if ( null === $value ) {
			return '';
		}

		if ( ! Tariff::isMoney( $field ) ) {
			return (string) (int) $value;
		}

		return number_format( (int) $value / 100, 2, '.', '' );
	}

	/**
	 * @param string $field     Clave del valor.
	 * @param string $old_value Valor anterior.
	 * @param string $new_value Valor nuevo.
	 * @param int    $user_id   Usuario, o 0 para el sistema.
	 * @param string $user_name Nombre visible.
	 * @return void
	 */
	private function log( $field, $old_value, $new_value, $user_id, $user_name ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sulayr_billing_changes',
			array(
				'changed_at' => current_time( 'mysql' ),
				'user_id'    => (int) $user_id,
				'user_name'  => mb_substr( (string) $user_name, 0, 191 ),
				'field'      => $field,
				'old_value'  => $old_value,
				'new_value'  => $new_value,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
