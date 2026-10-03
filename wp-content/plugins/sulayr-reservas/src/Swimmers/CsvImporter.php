<?php

namespace SulayrReservas\Swimmers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura y validación del CSV de nadadores. No crea usuarios.
 */
class CsvImporter {

	const REQUIRED = array( 'nombre', 'apellidos', 'email' );

	const COLUMNS = array( 'nombre', 'apellidos', 'email', 'telefono', 'limite_semanal', 'estado' );

	/**
	 * @return string
	 */
	public function template() {
		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return '';
		}

		fwrite( $handle, "\xEF\xBB\xBF" );
		fputcsv( $handle, self::COLUMNS, ',', '"', '\\' );
		fputcsv( $handle, array( 'Ana', 'García López', 'ana.garcia@example.com', '600000000', '3', '1' ), ',', '"', '\\' );
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return is_string( $csv ) ? $csv : '';
	}

	/**
	 * @param string $contents Contenido del archivo.
	 * @return array{error: string, rows: array<int, array<string, string>>}
	 */
	public function parse( $contents ) {
		$contents = (string) $contents;
		if ( strncmp( $contents, "\xEF\xBB\xBF", 3 ) === 0 ) {
			$contents = substr( $contents, 3 );
		}
		$contents = trim( $contents );
		if ( '' === $contents ) {
			return array(
				'error' => 'El archivo está vacío.',
				'rows'  => array(),
			);
		}

		$first = strtok( str_replace( array( "\r\n", "\r" ), "\n", $contents ), "\n" );
		$comma = substr_count( (string) $first, ',' );
		$semi  = substr_count( (string) $first, ';' );
		$delim = $semi > $comma ? ';' : ',';

		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return array(
				'error' => 'No se ha podido leer el archivo.',
				'rows'  => array(),
			);
		}
		fwrite( $handle, $contents );
		rewind( $handle );

		$header = fgetcsv( $handle, 0, $delim, '"', '\\' );
		if ( ! is_array( $header ) ) {
			fclose( $handle );
			return array(
				'error' => 'El archivo no tiene cabecera.',
				'rows'  => array(),
			);
		}

		$map = array();
		foreach ( $header as $index => $column ) {
			$name = strtolower( trim( (string) $column ) );
			if ( in_array( $name, self::COLUMNS, true ) ) {
				$map[ $name ] = (int) $index;
			}
		}
		foreach ( self::REQUIRED as $column ) {
			if ( ! isset( $map[ $column ] ) ) {
				fclose( $handle );
				return array(
					'error' => 'Faltan columnas obligatorias. Hacen falta nombre, apellidos y email.',
					'rows'  => array(),
				);
			}
		}

		$rows = array();
		$line = 1;
		while ( ( $cells = fgetcsv( $handle, 0, $delim, '"', '\\' ) ) !== false ) {
			++$line;
			if ( ! is_array( $cells ) || $this->blank( $cells ) ) {
				continue;
			}
			$row = array();
			foreach ( self::COLUMNS as $column ) {
				$index        = isset( $map[ $column ] ) ? $map[ $column ] : null;
				$row[ $column ] = null === $index || ! isset( $cells[ $index ] ) ? '' : trim( (string) $cells[ $index ] );
			}
			$row['_line'] = (string) $line;
			$rows[]       = $row;
		}
		fclose( $handle );

		if ( array() === $rows ) {
			return array(
				'error' => 'El archivo no tiene filas de datos.',
				'rows'  => array(),
			);
		}

		return array(
			'error' => '',
			'rows'  => $rows,
		);
	}

	/**
	 * @param array<int, array<string, string>> $rows  Filas ya leídas.
	 * @param EmailChangeService                $email Localización de correos existentes.
	 * @return array<int, array<string, mixed>>
	 */
	public function validate( array $rows, EmailChangeService $email ) {
		$seen    = array();
		$checked = array();

		foreach ( $rows as $row ) {
			$messages = array();
			$line     = isset( $row['_line'] ) ? (int) $row['_line'] : 0;
			$nombre   = sanitize_text_field( isset( $row['nombre'] ) ? $row['nombre'] : '' );
			$apellidos = sanitize_text_field( isset( $row['apellidos'] ) ? $row['apellidos'] : '' );
			$correo   = sanitize_email( isset( $row['email'] ) ? $row['email'] : '' );
			$telefono = sanitize_text_field( isset( $row['telefono'] ) ? $row['telefono'] : '' );
			$limite   = trim( (string) ( isset( $row['limite_semanal'] ) ? $row['limite_semanal'] : '' ) );
			$estado   = trim( (string) ( isset( $row['estado'] ) ? $row['estado'] : '' ) );

			if ( '' === $nombre || mb_strlen( $nombre ) > 100 ) {
				$messages[] = 'El nombre es obligatorio y no puede superar 100 caracteres.';
			}
			if ( '' === $apellidos || mb_strlen( $apellidos ) > 100 ) {
				$messages[] = 'Los apellidos son obligatorios y no pueden superar 100 caracteres.';
			}
			if ( ! is_email( $correo ) || strlen( $correo ) > 100 ) {
				$messages[] = 'El correo no es válido.';
			}
			if ( mb_strlen( $telefono ) > 50 ) {
				$messages[] = 'El teléfono no puede superar 50 caracteres.';
			}

			$limit = WeeklyLimit::DEFAULT_LIMIT;
			if ( '' === $limite ) {
				$limit = WeeklyLimit::DEFAULT_LIMIT;
			} elseif ( ! WeeklyLimit::isValid( $limite ) ) {
				$messages[] = 'El límite semanal debe estar entre 1 y 5.';
			} else {
				$limit = (int) $limite;
			}

			$active = 1;
			if ( '' === $estado ) {
				$active = 1;
			} elseif ( '1' === $estado || '0' === $estado ) {
				$active = (int) $estado;
			} else {
				$messages[] = 'El estado debe ser 1, 0 o estar vacío.';
			}

			$place = '';
			$key   = strtolower( $correo );
			if ( is_email( $correo ) ) {
				if ( isset( $seen[ $key ] ) ) {
					$messages[] = 'El correo está repetido en el archivo (fila ' . $seen[ $key ] . ').';
				} else {
					$seen[ $key ] = $line;
					$place        = $email->emailPlace( $correo );
					if ( '' !== $place ) {
						$messages[] = 'El correo ya existe (' . EmailChangeService::placeLabel( $place ) . '). No se modificará.';
					}
				}
			}

			$checked[] = array(
				'line'     => $line,
				'ok'       => array() === $messages,
				'messages' => $messages,
				'place'    => $place,
				'data'     => array(
					'first_name'    => $nombre,
					'last_name'     => $apellidos,
					'email'         => $correo,
					'phone'         => $telefono,
					'weekly_limit'  => $limit,
					'active'        => $active,
				),
			);
		}

		return $checked;
	}

	/**
	 * @param array $cells Celdas de una fila.
	 * @return bool
	 */
	private function blank( array $cells ) {
		foreach ( $cells as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}

		return true;
	}
}
