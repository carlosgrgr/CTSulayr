<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Modules\Members\Admin\MembersMenu;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página «Socios → Ajustes» (spec §4.4).
 *
 * Página de opciones de ACF con post_id POST_ID: cada campo se guarda en la
 * opción «sulayr_members_<nombre>». get() devuelve el valor por defecto
 * cuando el campo está vacío, para que todo funcione sin configurar nada.
 */
class MemberSettings {

    public const POST_ID = 'sulayr_members';
    public const PAGE_SLUG = 'sulayr-socios-ajustes';

    /** Valores por defecto de los campos simples y de las listas. */
    private const DEFAULTS = [
        'member_types' => [
            [ 'key' => 'normal', 'label' => 'Normal' ],
            [ 'key' => 'junta', 'label' => 'Junta directiva' ],
        ],
        'enrollment_fee' => 20,
        'enrollment_fee_return' => 10,
        'enrollment_promotions' => [],
        'notice_days' => 5,
        'join_rate_limit' => 3,
        'join_form_intro' => '<p>Rellena este formulario para hacerte socio/a del club. Revisaremos tu solicitud y te escribiremos por email cuando esté aprobada.</p>',
        'join_success_message' => '<p>¡Gracias! Hemos recibido tu solicitud. Te escribiremos por email cuando la revisemos.</p>',
        'penalty_amount' => 2,
        'penalty_day' => 20,
        'receipts_day' => 1,
        'receipt_email_enabled' => false,
        'coach_emails' => '',
        'club_email' => 'clubtriatlonsulayr@gmail.com',
        'club_name' => 'Club Triatlón Sulayr',
        'club_cif' => '',
        'club_address' => '',
        'club_logo' => 0,
        'whatsapp_link' => '',
        'retention_rejected_days' => 30,
        'retention_pending_days' => 60,
        'retention_former_years' => 6,
        'welcome_items' => [
            [ 'label' => 'Camiseta' ],
            [ 'label' => 'Pantalón' ],
            [ 'label' => 'Gorro de natación' ],
            [ 'label' => 'Bidón' ],
        ],
        'steps_join' => [
            [ 'label' => 'Dar de alta en SportEasy' ],
            [ 'label' => 'Añadir a la comunidad de WhatsApp' ],
            [ 'label' => 'Bienvenida dada' ],
        ],
        'steps_change' => [
            [ 'label' => 'Actualizar importe en SportEasy' ],
        ],
        'steps_leave' => [
            [ 'label' => 'Dar de baja en SportEasy' ],
            [ 'label' => 'Quitar de la comunidad de WhatsApp' ],
        ],
        'email_welcome_subject' => '¡Bienvenido/a al Club Triatlón Sulayr, {nombre}!',
        'email_welcome_body' => "<p>Hola {nombre}:</p>\n<p>Ya eres socio/a del Club Triatlón Sulayr con el número {numero_socio}. Tu alta cuenta desde el {fecha_alta}.</p>\n<p>Crea tu contraseña para entrar en Mi cuenta: {enlace_contrasena}</p>\n<p>Únete a la comunidad de WhatsApp del club: {enlace_whatsapp}</p>\n{bloques_servicios}\n<p>¡Nos vemos entrenando!</p>",
        'email_rejected_subject' => 'Tu solicitud de alta en el Club Triatlón Sulayr',
        'email_rejected_body' => "<p>Hola {nombre}:</p>\n<p>No hemos podido aprobar tu solicitud de alta. Motivo: {motivo}</p>\n<p>Si tienes dudas, responde a este email.</p>",
        'email_request_club_subject' => 'Nueva solicitud: {tipo} de {nombre_completo}',
        'email_request_club_body' => "<p>Hay una nueva solicitud de {tipo} de {nombre_completo}.</p>\n<p>Revísala en wp-admin → Socios → Solicitudes.</p>",
        'email_change_result_subject' => 'Tu solicitud en el Club Triatlón Sulayr',
        'email_change_result_body' => "<p>Hola {nombre}:</p>\n<p>Tu solicitud de {tipo} ha sido {resultado}. {detalle}</p>",
        'email_leave_subject' => 'Confirmación de baja en el Club Triatlón Sulayr',
        'email_leave_body' => "<p>Hola {nombre}:</p>\n<p>Confirmamos tu baja en el club con fecha {fecha_efectiva}. ¡Gracias por estos años con nosotros!</p>",
        'email_coach_subject' => 'Deportistas con plan de entrenamiento',
        'email_coach_body' => "<p>Listado actualizado de deportistas con plan de entrenamiento:</p>\n{listado}",
        'consent_privacy_text' => 'He leído y acepto la política de privacidad del Club Triatlón Sulayr.',
        'consent_privacy_version' => '1',
        'consent_rules_text' => 'He leído y acepto las normas y condiciones del club para la temporada.',
        'consent_rules_version' => '1',
        'consent_image_text' => 'Autorizo al club a usar fotos y vídeos en los que aparezca en su web y redes sociales.',
        'consent_image_version' => '1',
    ];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_page' ] );
        add_action( 'acf/init', [ $this, 'register_fields' ] );
    }

    /**
     * Valor de un ajuste, o su valor por defecto si está vacío.
     *
     * @param   string $name Nombre del campo (sin prefijo).
     * @return 	mixed
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( string $name ) {
        $value = function_exists( 'get_field' ) ? get_field( $name, self::POST_ID ) : null;

        if ( null === $value || '' === $value || [] === $value || false === $value ) {
            return self::DEFAULTS[ $name ] ?? $value;
        }

        return $value;
    }

    /**
     * Tipos de socio como [ clave => nombre ].
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function member_types(): array {
        $types = [];
        foreach ( (array) self::get( 'member_types' ) as $row ) {
            $key = sanitize_key( $row['key'] ?? '' );
            if ( '' !== $key && 'general' !== $key ) {
                $types[ $key ] = (string) ( $row['label'] ?? $key );
            }
        }

        return $types;
    }

    /**
     * Textos de una lista de una columna (pasos, artículos).
     *
     * @param   string $name Nombre del repetidor.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function labels( string $name ): array {
        $labels = [];
        foreach ( (array) self::get( $name ) as $row ) {
            $label = trim( (string) ( $row['label'] ?? '' ) );
            if ( '' !== $label ) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Página de opciones bajo el menú Socios.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_page(): void {
        if ( false === function_exists( 'acf_add_options_sub_page' ) ) {
            return;
        }

        acf_add_options_sub_page( [
            'page_title' => __( 'Ajustes de socios', SULAYR_TEXTDOMAIN ),
            'menu_title' => __( 'Ajustes', SULAYR_TEXTDOMAIN ),
            'menu_slug' => self::PAGE_SLUG,
            'parent_slug' => MembersMenu::SLUG,
            'capability' => MemberRoles::MANAGE_MEMBERS,
            'post_id' => self::POST_ID,
            'autoload' => false,
            'update_button' => __( 'Guardar ajustes', SULAYR_TEXTDOMAIN ),
            'updated_message' => __( 'Ajustes guardados.', SULAYR_TEXTDOMAIN ),
        ] );
    }

    /**
     * Campos de la página.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_fields(): void {
        if ( false === function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $d = self::DEFAULTS;

        $fields = [
            $this->tab( 'general', __( 'Socios y plazos', SULAYR_TEXTDOMAIN ) ),
            [
                'key' => 'field_sulayr_members_member_types',
                'label' => __( 'Tipos de socio', SULAYR_TEXTDOMAIN ),
                'name' => 'member_types',
                'type' => 'repeater',
                'instructions' => __( 'La clave no se debe cambiar una vez usada: los precios y las fichas la guardan. Si se deja vacío se usan «normal» y «junta».', SULAYR_TEXTDOMAIN ),
                'layout' => 'table',
                'button_label' => __( 'Añadir tipo', SULAYR_TEXTDOMAIN ),
                'sub_fields' => [
                    $this->text( 'member_type_key', 'key', __( 'Clave', SULAYR_TEXTDOMAIN ), '', true ),
                    $this->text( 'member_type_label', 'label', __( 'Nombre', SULAYR_TEXTDOMAIN ), '', true ),
                ],
            ],
            $this->number( 'notice_days', __( 'Días de aviso para cambios y bajas', SULAYR_TEXTDOMAIN ), $d['notice_days'], __( 'Días antes de fin de mes. Si se avisa más tarde, el cambio se aplica un mes después.', SULAYR_TEXTDOMAIN ) ),

            $this->tab( 'join_form', __( 'Formulario de alta', SULAYR_TEXTDOMAIN ) ),
            [
                'key' => 'field_sulayr_members_join_form_help',
                'label' => __( 'Dónde se usa', SULAYR_TEXTDOMAIN ),
                'name' => '',
                'type' => 'message',
                'message' => __( 'Pon el shortcode [sulayr_solicitud_alta] en una página del club. Los servicios que salen son los del catálogo marcados como visibles en el formulario público.', SULAYR_TEXTDOMAIN ),
            ],
            $this->wysiwyg( 'join_form_intro', __( 'Texto encima del formulario', SULAYR_TEXTDOMAIN ), $d['join_form_intro'] ),
            $this->wysiwyg( 'join_success_message', __( 'Mensaje tras enviar la solicitud', SULAYR_TEXTDOMAIN ), $d['join_success_message'] ),
            $this->number( 'join_rate_limit', __( 'Solicitudes por hora desde una misma conexión (IP)', SULAYR_TEXTDOMAIN ), $d['join_rate_limit'], __( 'Antispam. Las siguientes se rechazan hasta que pase la hora.', SULAYR_TEXTDOMAIN ), 1, '', 1 ),

            $this->tab( 'enrollment', __( 'Matrícula', SULAYR_TEXTDOMAIN ) ),
            $this->number( 'enrollment_fee', __( 'Matrícula (€)', SULAYR_TEXTDOMAIN ), $d['enrollment_fee'], '', 0.01 ),
            $this->number( 'enrollment_fee_return', __( 'Matrícula de reingreso (€)', SULAYR_TEXTDOMAIN ), $d['enrollment_fee_return'], '', 0.01 ),
            [
                'key' => 'field_sulayr_members_enrollment_promotions',
                'label' => __( 'Promociones de matrícula', SULAYR_TEXTDOMAIN ),
                'name' => 'enrollment_promotions',
                'type' => 'repeater',
                'instructions' => __( 'Durante cada periodo, la matrícula de las altas aprobadas usa este importe.', SULAYR_TEXTDOMAIN ),
                'layout' => 'table',
                'button_label' => __( 'Añadir promoción', SULAYR_TEXTDOMAIN ),
                'sub_fields' => [
                    $this->date( 'promo_start', 'start', __( 'Desde', SULAYR_TEXTDOMAIN ), true ),
                    $this->date( 'promo_end', 'end', __( 'Hasta', SULAYR_TEXTDOMAIN ), true ),
                    $this->number( 'promo_amount', __( 'Importe (€)', SULAYR_TEXTDOMAIN ), 0, '', 0.01, 'amount' ),
                ],
            ],

            $this->tab( 'receipts', __( 'Recibos', SULAYR_TEXTDOMAIN ) ),
            $this->number( 'receipts_day', __( 'Día de generación automática de recibos', SULAYR_TEXTDOMAIN ), $d['receipts_day'], '', 1, '', 1, 28 ),
            $this->number( 'penalty_amount', __( 'Penalización por retraso (€)', SULAYR_TEXTDOMAIN ), $d['penalty_amount'], '', 0.01 ),
            $this->number( 'penalty_day', __( 'Día desde el que se puede penalizar', SULAYR_TEXTDOMAIN ), $d['penalty_day'], '', 1, '', 1, 28 ),
            [
                'key' => 'field_sulayr_members_receipt_email_enabled',
                'label' => __( 'Avisar por email del recibo mensual', SULAYR_TEXTDOMAIN ),
                'name' => 'receipt_email_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 0,
            ],

            $this->tab( 'club', __( 'Datos del club', SULAYR_TEXTDOMAIN ) ),
            $this->text( 'club_name', 'club_name', __( 'Nombre', SULAYR_TEXTDOMAIN ), $d['club_name'] ),
            $this->text( 'club_cif', 'club_cif', __( 'CIF', SULAYR_TEXTDOMAIN ) ),
            [
                'key' => 'field_sulayr_members_club_address',
                'label' => __( 'Dirección', SULAYR_TEXTDOMAIN ),
                'name' => 'club_address',
                'type' => 'textarea',
                'rows' => 2,
            ],
            [
                'key' => 'field_sulayr_members_club_logo',
                'label' => __( 'Logo para recibos', SULAYR_TEXTDOMAIN ),
                'name' => 'club_logo',
                'type' => 'image',
                'return_format' => 'id',
                'preview_size' => 'thumbnail',
            ],
            [
                'key' => 'field_sulayr_members_club_email',
                'label' => __( 'Email del club para avisos', SULAYR_TEXTDOMAIN ),
                'name' => 'club_email',
                'type' => 'email',
                'default_value' => $d['club_email'],
            ],
            [
                'key' => 'field_sulayr_members_coach_emails',
                'label' => __( 'Emails para el listado del entrenador', SULAYR_TEXTDOMAIN ),
                'name' => 'coach_emails',
                'type' => 'textarea',
                'rows' => 3,
                'instructions' => __( 'Uno por línea.', SULAYR_TEXTDOMAIN ),
            ],
            [
                'key' => 'field_sulayr_members_whatsapp_link',
                'label' => __( 'Enlace a la comunidad de WhatsApp', SULAYR_TEXTDOMAIN ),
                'name' => 'whatsapp_link',
                'type' => 'url',
            ],

            $this->tab( 'welcome', __( 'Welcome pack', SULAYR_TEXTDOMAIN ) ),
            $this->label_list( 'welcome_items', __( 'Artículos del welcome pack', SULAYR_TEXTDOMAIN ), __( 'Añadir artículo', SULAYR_TEXTDOMAIN ) ),

            $this->tab( 'steps', __( 'Pasos', SULAYR_TEXTDOMAIN ) ),
            $this->label_list( 'steps_join', __( 'Pasos de alta', SULAYR_TEXTDOMAIN ), __( 'Añadir paso', SULAYR_TEXTDOMAIN ), __( 'Además, cada servicio puede añadir los suyos (p. ej. TrainingPeaks).', SULAYR_TEXTDOMAIN ) ),
            $this->label_list( 'steps_change', __( 'Pasos de cambio', SULAYR_TEXTDOMAIN ), __( 'Añadir paso', SULAYR_TEXTDOMAIN ) ),
            $this->label_list( 'steps_leave', __( 'Pasos de baja', SULAYR_TEXTDOMAIN ), __( 'Añadir paso', SULAYR_TEXTDOMAIN ) ),

            $this->tab( 'emails', __( 'Emails', SULAYR_TEXTDOMAIN ) ),
            [
                'key' => 'field_sulayr_members_emails_help',
                'label' => __( 'Marcadores', SULAYR_TEXTDOMAIN ),
                'name' => '',
                'type' => 'message',
                'message' => __( '{nombre}, {nombre_completo}, {numero_socio}, {fecha_alta}, {fecha_efectiva}, {enlace_contrasena}, {enlace_whatsapp}, {bloques_servicios}, {tipo}, {resultado}, {detalle}, {motivo}, {listado}', SULAYR_TEXTDOMAIN ),
            ],
        ];

        $emails = [
            'welcome' => __( 'Bienvenida', SULAYR_TEXTDOMAIN ),
            'rejected' => __( 'Solicitud de alta rechazada', SULAYR_TEXTDOMAIN ),
            'request_club' => __( 'Aviso al club de nueva solicitud', SULAYR_TEXTDOMAIN ),
            'change_result' => __( 'Resultado de solicitud de cambio o baja', SULAYR_TEXTDOMAIN ),
            'leave' => __( 'Confirmación de baja', SULAYR_TEXTDOMAIN ),
            'coach' => __( 'Listado del entrenador', SULAYR_TEXTDOMAIN ),
        ];
        foreach ( $emails as $key => $label ) {
            $fields[] = $this->text( "email_{$key}_subject", "email_{$key}_subject", sprintf( __( '%s: asunto', SULAYR_TEXTDOMAIN ), $label ), $d[ "email_{$key}_subject" ] );
            $fields[] = [
                'key' => "field_sulayr_members_email_{$key}_body",
                'label' => sprintf( __( '%s: texto', SULAYR_TEXTDOMAIN ), $label ),
                'name' => "email_{$key}_body",
                'type' => 'wysiwyg',
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => $d[ "email_{$key}_body" ],
            ];
        }

        $fields[] = $this->tab( 'consents', __( 'Consentimientos', SULAYR_TEXTDOMAIN ) );
        $fields[] = [
            'key' => 'field_sulayr_members_consents_help',
            'label' => __( 'Versiones', SULAYR_TEXTDOMAIN ),
            'name' => '',
            'type' => 'message',
            'message' => __( 'Cambia la versión cada vez que cambies un texto: cada aceptación guarda la versión que vio el socio.', SULAYR_TEXTDOMAIN ),
        ];
        $consents = [
            'privacy' => __( 'Política de privacidad (obligatoria)', SULAYR_TEXTDOMAIN ),
            'rules' => __( 'Normas y condiciones del club (obligatoria)', SULAYR_TEXTDOMAIN ),
            'image' => __( 'Uso de imagen (opcional)', SULAYR_TEXTDOMAIN ),
        ];
        foreach ( $consents as $key => $label ) {
            $fields[] = [
                'key' => "field_sulayr_members_consent_{$key}_text",
                'label' => $label,
                'name' => "consent_{$key}_text",
                'type' => 'wysiwyg',
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => $d[ "consent_{$key}_text" ],
            ];
            $fields[] = $this->text( "consent_{$key}_version", "consent_{$key}_version", sprintf( __( '%s: versión', SULAYR_TEXTDOMAIN ), $label ), $d[ "consent_{$key}_version" ] );
        }

        $fields[] = $this->tab( 'retention', __( 'Conservación de datos', SULAYR_TEXTDOMAIN ) );
        $fields[] = $this->number( 'retention_rejected_days', __( 'Borrar solicitudes rechazadas a los (días)', SULAYR_TEXTDOMAIN ), $d['retention_rejected_days'] );
        $fields[] = $this->number( 'retention_pending_days', __( 'Borrar solicitudes pendientes sin tocar a los (días)', SULAYR_TEXTDOMAIN ), $d['retention_pending_days'] );
        $fields[] = $this->number( 'retention_former_years', __( 'Avisar de antiguos socios con más de (años) de baja', SULAYR_TEXTDOMAIN ), $d['retention_former_years'] );

        acf_add_local_field_group( [
            'key' => 'group_sulayr_members_settings',
            'title' => __( 'Ajustes de socios', SULAYR_TEXTDOMAIN ),
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => self::PAGE_SLUG,
                    ],
                ],
            ],
        ] );
    }

    /** Pestaña. */
    private function tab( string $key, string $label ): array {
        return [
            'key' => 'field_sulayr_members_tab_' . $key,
            'label' => $label,
            'name' => '',
            'type' => 'tab',
            'placement' => 'left',
        ];
    }

    /** Editor de texto. */
    private function wysiwyg( string $name, string $label, string $default ): array {
        return [
            'key' => 'field_sulayr_members_' . $name,
            'label' => $label,
            'name' => $name,
            'type' => 'wysiwyg',
            'tabs' => 'all',
            'toolbar' => 'basic',
            'media_upload' => 0,
            'default_value' => $default,
        ];
    }

    /** Campo de texto. */
    private function text( string $key, string $name, string $label, string $default = '', bool $required = false ): array {
        return [
            'key' => 'field_sulayr_members_' . $key,
            'label' => $label,
            'name' => $name,
            'type' => 'text',
            'default_value' => $default,
            'required' => $required ? 1 : 0,
        ];
    }

    /** Campo numérico. */
    private function number( string $name, string $label, $default, string $instructions = '', $step = 1, string $field_name = '', ?int $min = 0, ?int $max = null ): array {
        $field = [
            'key' => 'field_sulayr_members_' . $name,
            'label' => $label,
            'name' => '' !== $field_name ? $field_name : $name,
            'type' => 'number',
            'default_value' => $default,
            'instructions' => $instructions,
            'step' => $step,
            'min' => $min,
        ];

        if ( null !== $max ) {
            $field['max'] = $max;
        }

        return $field;
    }

    /** Campo de fecha. */
    private function date( string $key, string $name, string $label, bool $required = false ): array {
        return [
            'key' => 'field_sulayr_members_' . $key,
            'label' => $label,
            'name' => $name,
            'type' => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format' => 'Y-m-d',
            'required' => $required ? 1 : 0,
        ];
    }

    /** Lista de textos de una columna. */
    private function label_list( string $name, string $label, string $button, string $instructions = '' ): array {
        return [
            'key' => 'field_sulayr_members_' . $name,
            'label' => $label,
            'name' => $name,
            'type' => 'repeater',
            'instructions' => '' !== $instructions ? $instructions : __( 'Si se deja vacío se usan los valores por defecto.', SULAYR_TEXTDOMAIN ),
            'layout' => 'table',
            'button_label' => $button,
            'sub_fields' => [
                [
                    'key' => 'field_sulayr_members_' . $name . '_label',
                    'label' => __( 'Texto', SULAYR_TEXTDOMAIN ),
                    'name' => 'label',
                    'type' => 'text',
                    'required' => 1,
                ],
            ],
        ];
    }
}
