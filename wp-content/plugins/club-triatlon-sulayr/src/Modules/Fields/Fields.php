<?php
namespace ClubTriatlonSulayr\Modules\Fields;

/**
 * Fields
 */
abstract class Fields
{
    protected array $fields = [];

    /**
     * __construct()
     *
     * @param string $menu
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function init(): void
    {
        // En acf/init (dentro de init) las traducciones del plugin ya están cargadas.
        add_action( 'acf/init', [ $this, 'create_group_fields' ] );
    }

    /**
     * build_fields()
     *
     * Devuelve la definición del grupo de campos. Las subclases la construyen
     * aquí y no en el constructor, para que __() se llame después de cargar
     * las traducciones.
     *
     * @return 	array
     * @access 	protected
     */
    protected function build_fields(): array
    {
        return $this->fields;
    }

    /**
     * create_group_fields()
     *
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function create_group_fields(): void
    {
        $this->fields = $this->build_fields();
        acf_add_local_field_group( $this->fields );
    }
}
