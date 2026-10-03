<?php

namespace SulayrReservas\Swimmers;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fotografía local del nadador en el sitio de reservas.
 *
 * El archivo es un adjunto de ese blog. No otorga `upload_files` ni usa la foto del club.
 */
class PhotoService {

	const MAX_BYTES = 5242880;
	const MAX_EDGE  = 1200;

	/** @var BlogContext */
	private $blog_context;

	/** @var SwimmerRepository */
	private $repository;

	/**
	 * @param BlogContext            $blog_context Cambio de sitio.
	 * @param SwimmerRepository|null $repository   Ficha local.
	 */
	public function __construct( BlogContext $blog_context, ?SwimmerRepository $repository = null ) {
		$this->blog_context = $blog_context;
		$this->repository   = $repository ? $repository : new SwimmerRepository();
	}

	/**
	 * @param int   $user_id Nadador.
	 * @param array $file    Elemento de `$_FILES`.
	 * @return SwimmerResult
	 */
	public function replace( $user_id, array $file ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $file ) {
				return $this->replaceOnBookings( (int) $user_id, $file );
			}
		);
	}

	/**
	 * @param int $user_id Nadador.
	 * @return SwimmerResult
	 */
	public function remove( $user_id ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id ) {
				return $this->removeOnBookings( (int) $user_id );
			}
		);
	}

	/**
	 * @param int $photo_id Adjunto.
	 * @return void
	 */
	public function discard( $photo_id ) {
		$photo_id = (int) $photo_id;
		if ( $photo_id < 1 ) {
			return;
		}

		$this->blog_context->onBookings(
			function () use ( $photo_id ) {
				wp_delete_attachment( $photo_id, true );
			}
		);
	}

	/**
	 * @param int   $user_id Nadador.
	 * @param array $file    Archivo subido.
	 * @return SwimmerResult
	 */
	private function replaceOnBookings( $user_id, array $file ) {
		if ( ! $this->canTouch( $user_id ) ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$row = $this->repository->find( $user_id );
		if ( ! $row ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		$error = $this->fileError( $file );
		if ( '' !== $error ) {
			return $this->result( SwimmerResult::INVALID, $error );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $this->mimes() );
		$type    = isset( $checked['type'] ) ? (string) $checked['type'] : '';
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return $this->result( SwimmerResult::INVALID, 'La imagen debe ser JPG, PNG o WebP.' );
		}

		$info = @getimagesize( $file['tmp_name'] );
		if ( ! is_array( $info ) ) {
			return $this->result( SwimmerResult::INVALID, 'El archivo no es una imagen válida.' );
		}

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $this->mimes(),
			)
		);
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido guardar la imagen.' );
		}

		$processed = $this->shrink( $upload['file'] );
		if ( ! $processed ) {
			wp_delete_file( $upload['file'] );
			return $this->result( SwimmerResult::FAILED, 'No se ha podido procesar la imagen.' );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $type,
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			),
			$upload['file']
		);
		if ( is_wp_error( $attachment_id ) || (int) $attachment_id < 1 ) {
			wp_delete_file( $upload['file'] );
			return $this->result( SwimmerResult::FAILED, 'No se ha podido guardar la imagen.' );
		}

		wp_update_attachment_metadata( (int) $attachment_id, wp_generate_attachment_metadata( (int) $attachment_id, $upload['file'] ) );

		$stamp = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
		try {
			$this->repository->setPhoto( $user_id, (int) $attachment_id, $stamp );
		} catch ( \Throwable $exception ) {
			wp_delete_attachment( (int) $attachment_id, true );
			return $this->result( SwimmerResult::FAILED, 'No se ha podido guardar la imagen.' );
		}

		$previous = (int) $row->photo_id;
		if ( $previous > 0 && $previous !== (int) $attachment_id ) {
			wp_delete_attachment( $previous, true );
		}

		return $this->result( SwimmerResult::UPDATED, 'Fotografía actualizada.', $user_id );
	}

	/**
	 * @param int $user_id Nadador.
	 * @return SwimmerResult
	 */
	private function removeOnBookings( $user_id ) {
		if ( ! $this->canTouch( $user_id ) ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$row = $this->repository->find( $user_id );
		if ( ! $row ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		$stamp = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
		try {
			$this->repository->setPhoto( $user_id, 0, $stamp );
		} catch ( \Throwable $exception ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido quitar la fotografía.' );
		}

		if ( (int) $row->photo_id > 0 ) {
			wp_delete_attachment( (int) $row->photo_id, true );
		}

		return $this->result( SwimmerResult::UPDATED, 'Fotografía eliminada.', $user_id );
	}

	/**
	 * @param int $user_id Nadador.
	 * @return bool
	 */
	private function canTouch( $user_id ) {
		$actor = get_current_user_id();
		if ( $actor < 1 || $user_id < 1 ) {
			return false;
		}

		return $actor === $user_id || current_user_can( Capabilities::MANAGE_SWIMMERS );
	}

	/**
	 * @param array $file Elemento de `$_FILES`.
	 * @return string Vacío si el archivo puede seguir.
	 */
	private function fileError( array $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return 'No se ha recibido ninguna imagen.';
		}
		if ( ! empty( $file['error'] ) ) {
			return UPLOAD_ERR_INI_SIZE === (int) $file['error'] || UPLOAD_ERR_FORM_SIZE === (int) $file['error']
				? 'La imagen supera el tamaño máximo de 5 MB.'
				: 'No se ha podido leer la imagen.';
		}
		if ( ! isset( $file['size'] ) || (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_BYTES ) {
			return 'La imagen supera el tamaño máximo de 5 MB.';
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( ! preg_match( '/\.(jpe?g|png|webp)$/i', $name ) ) {
			return 'La imagen debe ser JPG, PNG o WebP.';
		}

		return '';
	}

	/**
	 * @param string $path Ruta ya guardada en uploads.
	 * @return bool
	 */
	private function shrink( $path ) {
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$size = $editor->get_size();
		if ( ! is_array( $size ) || empty( $size['width'] ) || empty( $size['height'] ) ) {
			return false;
		}
		if ( (int) $size['width'] <= self::MAX_EDGE && (int) $size['height'] <= self::MAX_EDGE ) {
			return true;
		}

		$resized = $editor->resize( self::MAX_EDGE, self::MAX_EDGE, false );
		if ( is_wp_error( $resized ) ) {
			return false;
		}

		$saved = $editor->save( $path );
		return ! is_wp_error( $saved );
	}

	/**
	 * @return array<string, string>
	 */
	private function mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
	}

	/**
	 * @param string $status  Estado.
	 * @param string $message Mensaje.
	 * @param int    $user_id Nadador.
	 * @return SwimmerResult
	 */
	private function result( $status, $message, $user_id = 0 ) {
		return new SwimmerResult( $status, $message, $user_id );
	}
}
