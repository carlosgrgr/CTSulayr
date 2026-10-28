<?php

namespace SulayrReservas\Swimmers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV de nadadores y de reservas, con BOM para Excel y escape de fórmulas.
 */
class CsvExporter {

	/**
	 * @param object[] $rows Fichas ya filtradas.
	 * @return string
	 */
	public function swimmers( array $rows ) {
		$lines   = array();
		$lines[] = array( 'Nombre', 'Apellidos', 'Email', 'Teléfono', 'Estado', 'Fecha de alta', 'Límite semanal' );
		foreach ( $rows as $row ) {
			$lines[] = array(
				(string) $row->first_name,
				(string) $row->last_name,
				(string) $row->email,
				(string) $row->phone,
				1 === (int) $row->active ? 'Activo' : 'Inactivo',
				(string) $row->created_at,
				(string) WeeklyLimit::normalize( $row->weekly_limit ),
			);
		}

		return $this->render( $lines );
	}

	/**
	 * @param object[] $rows Reservas que siguen registradas.
	 * @return string
	 */
	public function bookings( array $rows ) {
		$lines   = array();
		$lines[] = array( 'Fecha de la sesión', 'Hora de inicio', 'Estado de la reserva', 'Fecha de la reserva', 'Realizada por' );
		foreach ( $rows as $row ) {
			$owner = (int) $row->user_id;
			$by    = (int) $row->created_by === $owner && $owner > 0 ? 'Nadador' : 'Gestor';
			$lines[] = array(
				(string) $row->session_date,
				substr( (string) $row->start_time, 0, 5 ),
				'Registrada',
				(string) $row->created_at,
				$by,
			);
		}

		return $this->render( $lines );
	}

	/**
	 * @param object[] $rows Usuarios facturados de un resumen.
	 * @return string
	 */
	public function billingUsers( array $rows ) {
		$lines   = array();
		$lines[] = array( 'Nombre', 'Apellidos', 'Tipo' );
		foreach ( $rows as $row ) {
			$lines[] = array(
				(string) $row->first_name,
				(string) $row->last_name,
				1 === (int) $row->linked_to_club ? 'Sulayr' : 'CN Nazarí',
			);
		}

		return $this->render( $lines );
	}

	/**
	 * @param string $filename Nombre del archivo.
	 * @param string $csv      Contenido.
	 * @return void
	 */
	public function download( $filename, $csv ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv;
		exit;
	}

	/**
	 * @param array<int, array<int, string>> $lines Filas.
	 * @return string
	 */
	private function render( array $lines ) {
		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return '';
		}

		fwrite( $handle, "\xEF\xBB\xBF" );
		foreach ( $lines as $line ) {
			$safe = array();
			foreach ( $line as $value ) {
				$safe[] = $this->cell( (string) $value );
			}
			fputcsv( $handle, $safe, ';', '"', '\\' );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return is_string( $csv ) ? $csv : '';
	}

	/**
	 * @param string $value Celda.
	 * @return string
	 */
	private function cell( $value ) {
		if ( preg_match( '/^[\t\r=+@-]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
