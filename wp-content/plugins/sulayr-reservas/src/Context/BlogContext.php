<?php

namespace SulayrReservas\Context;

use SulayrReservas\Config\SiteConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ejecuta una operación en el sitio del club o en el de reservas y restaura el contexto.
 */
class BlogContext {

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Configuración de los identificadores.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @param callable $callback Operación a ejecutar en el sitio del club.
	 * @return mixed
	 * @throws BlogContextException Si la configuración no permite el cambio.
	 */
	public function onClub( callable $callback ) {
		return $this->onBlog( $this->config->clubBlogId(), $callback );
	}

	/**
	 * @param callable $callback Operación a ejecutar en el sitio de reservas.
	 * @return mixed
	 * @throws BlogContextException Si la configuración no permite el cambio.
	 */
	public function onBookings( callable $callback ) {
		return $this->onBlog( $this->config->bookingsBlogId(), $callback );
	}

	/**
	 * @param int      $blog_id  Sitio permitido por la configuración.
	 * @param callable $callback Operación a ejecutar.
	 * @return mixed
	 * @throws BlogContextException Si el sitio no está autorizado o la configuración es inválida.
	 */
	private function onBlog( $blog_id, callable $callback ) {
		$errors = $this->config->errors();
		if ( array() !== $errors ) {
			throw new BlogContextException( implode( ' ', $errors ) );
		}

		$blog_id = (int) $blog_id;
		if ( $blog_id !== $this->config->clubBlogId() && $blog_id !== $this->config->bookingsBlogId() ) {
			throw new BlogContextException( 'El sitio solicitado no es el del club ni el de reservas.' );
		}

		switch_to_blog( $blog_id );

		try {
			return $callback();
		} finally {
			restore_current_blog();
		}
	}
}
