<?php

namespace SulayrReservas\Sync;

use SulayrReservas\Context\BlogContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registro técnico en el sitio del club.
 *
 * Un fallo al escribir no debe deshacer la sincronización.
 */
class SyncLog {

	const KEEP_DAYS = 180;
	const KEEP_ROWS = 1000;

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param BlogContext $blog_context Cambio de sitio con restauración.
	 */
	public function __construct( BlogContext $blog_context ) {
		$this->blog_context = $blog_context;
	}

	/**
	 * @param int    $user_id  Usuario afectado. 0 si no hay.
	 * @param string $name     Nombre visible, ya saneado.
	 * @param string $origin   automatic o manual.
	 * @param string $result   ok o failed.
	 * @param string $action   Acción o vacío si falló antes.
	 * @param string $error    Mensaje fijo, sin SQL ni secretos.
	 * @return bool
	 */
	public function write( $user_id, $name, $origin, $result, $action, $error ) {
		try {
			return (bool) $this->blog_context->onClub(
				function () use ( $user_id, $name, $origin, $result, $action, $error ) {
					global $wpdb;

					$now     = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
					$created = $wpdb->insert(
						$this->table(),
						array(
							'created_at'    => $now,
							'user_id'       => $user_id > 0 ? (int) $user_id : null,
							'athlete_name'  => $this->limit( $name, 191 ),
							'origin'        => $this->token( $origin ),
							'result'        => $this->token( $result ),
							'action'        => $this->token( $action ),
							'error_message' => $this->limit( $error, 1000 ),
						)
					);

					if ( false === $created ) {
						return false;
					}

					$this->prune();

					return true;
				}
			);
		} catch ( \Throwable $exception ) {
			return false;
		}
	}

	/**
	 * @param int $page     Página desde 1.
	 * @param int $per_page Filas por página.
	 * @return array{rows: object[], total: int}
	 */
	public function page( $page, $per_page ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		return $this->blog_context->onClub(
			function () use ( $per_page, $offset ) {
				global $wpdb;

				$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() );
				$rows  = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, created_at, user_id, athlete_name, origin, result, action, error_message FROM ' . $this->table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
						$per_page,
						$offset
					)
				);

				return array(
					'rows'  => is_array( $rows ) ? $rows : array(),
					'total' => $total,
				);
			}
		);
	}

	/**
	 * @return void
	 */
	private function prune() {
		global $wpdb;

		$cutoff = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-' . self::KEEP_DAYS . ' days' )->format( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->table() . ' WHERE created_at < %s', $cutoff ) );

		$boundary = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table() . ' ORDER BY id DESC LIMIT 1 OFFSET %d',
				self::KEEP_ROWS - 1
			)
		);

		if ( $boundary ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->table() . ' WHERE id < %d', (int) $boundary ) );
		}
	}

	/**
	 * @return string
	 */
	private function table() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_sync_log';
	}

	/**
	 * @param string $value Texto.
	 * @param int    $limit Máximo.
	 * @return string
	 */
	private function limit( $value, $limit ) {
		$value = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	/**
	 * @param string $value Identificador corto.
	 * @return string
	 */
	private function token( $value ) {
		$value = sanitize_key( (string) $value );

		return strlen( $value ) > 30 ? substr( $value, 0, 30 ) : $value;
	}
}
