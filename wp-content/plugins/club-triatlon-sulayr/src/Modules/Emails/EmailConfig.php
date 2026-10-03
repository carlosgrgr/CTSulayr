<?php
namespace ClubTriatlonSulayr\Modules\Emails;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EmailConfig
 * Configuración de emails del plugin
 * 
 */
class EmailConfig
{
    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init()
     * Registra los filtros de WordPress para la configuración de emails
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function init(): void
    {
        add_filter( 'wp_mail_from', [ $this, 'set_email_from' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'set_email_from_name' ] );
    }

    /**
     * set_email_from()
     * Establece la dirección de email del remitente
     *
     * @param   string  $email  Email actual del remitente
     * @return  string  Email del remitente personalizado
     * @access  public
     * @package club-triatlon-sulayr
     */
    public function set_email_from( string $email ): string
    {
        return 'clubtriatlonsulayr@gmail.com';
    }

    /**
     * set_email_from_name()
     * Establece el nombre del remitente
     *
     * @param   string  $name  Nombre actual del remitente
     * @return  string  Nombre del remitente personalizado
     * @access  public
     * @package club-triatlon-sulayr
     */
    public function set_email_from_name( string $name ): string
    {
        return 'Club Triatlón Sulayr';
    }
}

