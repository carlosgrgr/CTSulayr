<?php
namespace ClubTriatlonSulayr\Modules\Members\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Un servicio del catálogo, ya leído de sus campos.
 */
class Service {

    public int $id;
    public string $slug;
    public string $title;
    public bool $is_base;
    /** @var array<int, array{member_type: string, amount: float, valid_from: string}> */
    public array $prices;
    public bool $prorated;
    public bool $cancel_on_freeze;
    public bool $public;
    /** @var array<string, string> clave => nombre */
    public array $options;
    /** @var int[] */
    public array $incompatible;
    public bool $grants_swimming;
    public bool $coach_list;
    public string $welcome_text;
    /** @var string[] */
    public array $steps;

    /**
     * Nombre de una opción, o la clave si no existe.
     *
     * @param   string $key Clave.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function option_label( string $key ): string {
        return $this->options[ $key ] ?? $key;
    }
}
