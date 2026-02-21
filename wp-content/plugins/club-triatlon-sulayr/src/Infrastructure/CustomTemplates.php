<?php

namespace ClubTriatlonSulayr\Infrastructure;

/**
 * Custom Templates names
 */
class CustomTemplates
{
    const TEMPLATE_MY_PROFILE = [
        'path' => 'page-user-profile.php',
        'url'  => '/perfil-deportista',
        'name' => 'Perfil de deportista',
    ];
    const TEMPLATE_LOGIN = [
        'path' => 'page-user-login.php',
        'url' => '/iniciar-sesion',
        'name' => 'Iniciar sesión',
    ];
    const ALL_TEMPLATES = [
        self::TEMPLATE_MY_PROFILE,
        self::TEMPLATE_LOGIN,
    ];
}
