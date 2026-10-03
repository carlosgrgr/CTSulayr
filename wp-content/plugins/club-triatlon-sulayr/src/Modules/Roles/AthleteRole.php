<?php
namespace ClubTriatlonSulayr\Modules\Roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AthleteRole extends Role {

    const ROLE_NAME = 'athlete';

    protected string $display_name = 'Deportista';

    protected array $capabilities = [
        'read' => true, // puede leer contenido
        // no le damos permisos de edición ni acceso a posts
    ];

    public function __construct() {
        parent::__construct();
    }
}
