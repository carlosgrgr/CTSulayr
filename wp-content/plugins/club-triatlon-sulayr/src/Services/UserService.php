<?php
namespace ClubTriatlonSulayr\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserService {

    public function __construct() {
        // Hooks de usuario
        add_action('user_register', [$this, 'assign_athlete_role'], 10, 1);
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
    }

    /**
     * Asignar rol "athlete" a nuevos usuarios
     */
    public function assign_athlete_role(int $user_id): void {
        $user = get_userdata($user_id);

        if (empty($user->roles) || in_array('subscriber', $user->roles, true)) {
            $user->set_role('athlete');
        }
    }

    /**
     * Redirigir usuarios "athlete" a su perfil tras login
     */
    public function redirect_after_login($redirect_to, $requested_redirect_to, $user) {
        if (isset($user->roles) && in_array('athlete', $user->roles, true)) {
            return home_url('/mi-perfil');
        }
        return $redirect_to;
    }

    /**
     * Obtener perfil completo de un usuario (nativo + ACF)
     */
    public function get_user_profile(int $user_id): array {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        return [
            'ID'         => $user->ID,
            'username'   => $user->user_login,
            'email'      => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name'  => get_user_meta($user->ID, 'last_name', true),

            // Campos ACF
            'phone'       => get_field('phone', 'user_' . $user->ID),
            'birth_date'  => get_field('birth_date', 'user_' . $user->ID),
            'dni'         => get_field('dni', 'user_' . $user->ID),
            'tarifa'      => get_field('tarifa', 'user_' . $user->ID),
            'suplemento'  => (bool) get_field('suplemento', 'user_' . $user->ID),
            'foto'        => get_field('foto', 'user_' . $user->ID), // ID de imagen
        ];
    }

    /**
     * Actualizar perfil de usuario con datos pasados
     */
    public function update_user_profile(int $user_id, array $data): bool {
        if (!$user_id || !get_userdata($user_id)) {
            return false;
        }

        // Nativos
        if (!empty($data['email'])) {
            wp_update_user(['ID' => $user_id, 'user_email' => sanitize_email($data['email'])]);
        }
        if (!empty($data['first_name'])) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($data['first_name']));
        }
        if (!empty($data['last_name'])) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($data['last_name']));
        }

        // ACF
        $acf_fields = ['phone','birth_date','dni','tarifa','suplemento','foto'];
        foreach ($acf_fields as $field) {
            if (isset($data[$field])) {
                update_field($field, $data[$field], 'user_' . $user_id);
            }
        }

        return true;
    }
}
