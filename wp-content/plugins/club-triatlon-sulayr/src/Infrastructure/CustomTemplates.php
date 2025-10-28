<?php

namespace ClubTriatlonSulayr\Infrastructure;

/**
 * Custom Templates names
 */
class CustomTemplates
{
    const TEMPLATE_MY_PROFILE = [
        'path' => 'page-user-profile.php',
        'url' => '/mi-perfil',
        'name' => 'Mi perfil',
    ];
    const TEMPLATE_LOGIN = [
        'path' => 'page-user-login.php',
        'url' => '/iniciar-sesion',
        'name' => 'Iniciar sesión',
    ];
    const TEMPLATE_UNSUBSCRIBE = [
        'path' => 'page-user-unsubscribe.php',
        'url' => '/darse-de-baja',
        'name' => 'Darse de baja',
    ];
    const ALL_TEMPLATES = [
        self::TEMPLATE_MY_PROFILE,
        self::TEMPLATE_LOGIN,
        self::TEMPLATE_UNSUBSCRIBE,
    ];
}
