<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\Catalog\Service;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campos del alta (D8) compartidos por el formulario público y el del gestor:
 * lectura, validación (spec §5.1) y pintado.
 *
 * Los servicios, sus opciones y los textos de los consentimientos salen del
 * catálogo y de Socios → Ajustes.
 */
class JoinForm {

    public const CONTEXT_PUBLIC = 'publico';
    public const CONTEXT_ADMIN = 'gestor';

    /** Nombre del array del formulario. */
    public const INPUT = 'sulayr_join';

    /** Campo trampa para robots: tiene que llegar vacío. */
    public const HONEYPOT = 'sulayr_join_web';

    /** Mayoría de edad en la fecha de inicio (§5.1). */
    private const ADULT_AGE = 18;

    /**
     * Servicios que se pueden elegir en un contexto.
     *
     * @param   string $context CONTEXT_*.
     * @return 	Service[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function services( string $context ): array {
        return array_values( array_filter( ServiceCatalog::all(), function ( Service $service ) use ( $context ) {
            return $service->is_base || self::CONTEXT_ADMIN === $context || $service->public;
        } ) );
    }

    /**
     * True si con esa fecha de nacimiento es menor de edad en la fecha de inicio.
     *
     * @param   string $birth Y-m-d.
     * @param   string $start Y-m-d.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_minor( string $birth, string $start ): bool {
        $b = \DateTime::createFromFormat( '!Y-m-d', $birth );
        $s = \DateTime::createFromFormat( '!Y-m-d', $start );
        if ( false === $b || false === $s ) {
            return false;
        }

        return $b->diff( $s )->y < self::ADULT_AGE || $b > $s;
    }

    /**
     * Lee y valida lo enviado.
     *
     * @param   array  $input   $_POST[ INPUT ] sin barras.
     * @param   string $context CONTEXT_*.
     * @return 	array{data: array, consents: array<string, bool>, errors: array<string, string>}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function parse( array $input, string $context ): array {
        $text = function ( string $key, int $max = 100 ) use ( $input ): string {
            return substr( sanitize_text_field( Identity::clean( (string) ( $input[ $key ] ?? '' ) ) ), 0, $max );
        };

        $errors = [];
        $data = [
            'first_name' => $text( 'first_name' ),
            'last_name' => $text( 'last_name' ),
            'email' => Identity::normalize_email( (string) ( $input['email'] ?? '' ) ),
            'phone' => $text( 'phone', 30 ),
            'dni' => Identity::normalize_dni( (string) ( $input['dni'] ?? '' ) ),
            'birth_date' => $text( 'birth_date', 10 ),
            'sex' => $text( 'sex', 1 ),
            'start_date' => $text( 'start_date', 10 ),
            'tshirt' => $text( 'tshirt', 5 ),
            'notes' => substr( sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ), 0, 2000 ),
            'services' => [],
            'guardian' => null,
            'minor' => false,
            'origin' => $context,
        ];

        foreach ( [ 'first_name' => __( 'Escribe el nombre.', SULAYR_TEXTDOMAIN ), 'last_name' => __( 'Escribe los apellidos.', SULAYR_TEXTDOMAIN ) ] as $key => $message ) {
            if ( '' === $data[ $key ] ) {
                $errors[ $key ] = $message;
            }
        }

        if ( '' === $data['email'] || false === is_email( $data['email'] ) ) {
            $errors['email'] = __( 'Escribe un email válido.', SULAYR_TEXTDOMAIN );
        }

        if ( false === self::is_valid_phone( $data['phone'] ) ) {
            $errors['phone'] = __( 'Escribe un teléfono válido.', SULAYR_TEXTDOMAIN );
        }

        if ( false === Identity::is_valid_dni( $data['dni'] ) ) {
            $errors['dni'] = __( 'El DNI o NIE no es válido: revisa los números y la letra.', SULAYR_TEXTDOMAIN );
        }

        if ( false === array_key_exists( $data['sex'], MemberFields::sexes() ) ) {
            $data['sex'] = '';
            $errors['sex'] = __( 'Elige el sexo.', SULAYR_TEXTDOMAIN );
        }

        $today = current_time( 'Y-m-d' );
        if ( false === self::is_date( $data['birth_date'] ) || $data['birth_date'] >= $today || $data['birth_date'] < '1900-01-01' ) {
            $errors['birth_date'] = __( 'Escribe una fecha de nacimiento válida.', SULAYR_TEXTDOMAIN );
        }

        if ( false === self::is_date( $data['start_date'] ) ) {
            $errors['start_date'] = __( 'Elige la fecha en la que quieres empezar.', SULAYR_TEXTDOMAIN );
        } elseif ( self::CONTEXT_PUBLIC === $context && $data['start_date'] < $today ) {
            $errors['start_date'] = __( 'La fecha de inicio no puede ser anterior a hoy.', SULAYR_TEXTDOMAIN );
        }

        if ( '' !== $data['tshirt'] && false === in_array( $data['tshirt'], MemberFields::TSHIRT_SIZES, true ) ) {
            $data['tshirt'] = '';
        }

        // Servicios.
        $selection = self::parse_services( $input, $context );
        $data['services'] = $selection['services'];
        if ( '' !== $selection['error'] ) {
            $errors['services'] = $selection['error'];
        }

        // Tutor si es menor en la fecha de inicio.
        if ( ! isset( $errors['birth_date'] ) && ! isset( $errors['start_date'] ) ) {
            $data['minor'] = self::is_minor( $data['birth_date'], $data['start_date'] );
        }
        if ( $data['minor'] ) {
            $guardian = (array) ( $input['guardian'] ?? [] );
            $data['guardian'] = [
                'name' => substr( sanitize_text_field( Identity::clean( (string) ( $guardian['name'] ?? '' ) ) ), 0, 150 ),
                'dni' => Identity::normalize_dni( (string) ( $guardian['dni'] ?? '' ) ),
                'phone' => substr( sanitize_text_field( Identity::clean( (string) ( $guardian['phone'] ?? '' ) ) ), 0, 30 ),
                'email' => Identity::normalize_email( (string) ( $guardian['email'] ?? '' ) ),
            ];
            if ( '' === $data['guardian']['name'] ) {
                $errors['guardian_name'] = __( 'Escribe el nombre del tutor.', SULAYR_TEXTDOMAIN );
            }
            if ( false === Identity::is_valid_dni( $data['guardian']['dni'] ) ) {
                $errors['guardian_dni'] = __( 'El DNI o NIE del tutor no es válido.', SULAYR_TEXTDOMAIN );
            }
            if ( false === self::is_valid_phone( $data['guardian']['phone'] ) ) {
                $errors['guardian_phone'] = __( 'Escribe el teléfono del tutor.', SULAYR_TEXTDOMAIN );
            }
            if ( '' === $data['guardian']['email'] || false === is_email( $data['guardian']['email'] ) ) {
                $errors['guardian_email'] = __( 'Escribe el email del tutor.', SULAYR_TEXTDOMAIN );
            }
        }

        // Consentimientos.
        $consents = [];
        $given = (array) ( $input['consents'] ?? [] );
        foreach ( MemberConsents::types() as $type => $def ) {
            $consents[ $type ] = ! empty( $given[ $type ] );
            if ( $def['required'] && false === $consents[ $type ] ) {
                $errors[ 'consent_' . $type ] = sprintf( __( 'Hay que aceptar: %s.', SULAYR_TEXTDOMAIN ), $def['label'] );
            }
        }

        return [
            'data' => $data,
            'consents' => $consents,
            'errors' => $errors,
        ];
    }

    /**
     * Servicios elegidos: la cuota base siempre, sin incompatibles y con opción si la piden.
     *
     * @param   array          $input    Datos enviados.
     * @param   string         $context  CONTEXT_*.
     * @param   Service[]|null $services Servicios que se pueden elegir (null = los del contexto).
     * @return 	array{services: array<int, array{service_id: int, option_key: string}>, error: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function parse_services( array $input, string $context, ?array $services = null ): array {
        $allowed = [];
        foreach ( $services ?? self::services( $context ) as $service ) {
            $allowed[ $service->id ] = $service;
        }

        $base = ServiceCatalog::base();
        if ( null === $base ) {
            return [ 'services' => [], 'error' => __( 'El catálogo no tiene cuota base: avisa al club.', SULAYR_TEXTDOMAIN ) ];
        }

        $chosen = array_map( 'intval', (array) ( $input['services'] ?? [] ) );
        $chosen[] = $base->id;
        $chosen = array_values( array_unique( array_filter( $chosen, function ( $id ) use ( $allowed ) {
            return isset( $allowed[ $id ] );
        } ) ) );

        $options = (array) ( $input['options'] ?? [] );
        $services = [];
        $error = '';
        foreach ( $chosen as $id ) {
            $service = $allowed[ $id ];
            $option = sanitize_key( (string) ( $options[ $id ] ?? '' ) );
            if ( ! empty( $service->options ) && false === isset( $service->options[ $option ] ) ) {
                $error = sprintf( __( 'Elige una opción de «%s».', SULAYR_TEXTDOMAIN ), $service->title );
                $option = '';
            }
            if ( empty( $service->options ) ) {
                $option = '';
            }

            foreach ( $chosen as $other ) {
                if ( $other !== $id && ( in_array( $other, $service->incompatible, true ) || in_array( $id, $allowed[ $other ]->incompatible, true ) ) ) {
                    $error = sprintf( __( '«%1$s» y «%2$s» no se pueden contratar a la vez.', SULAYR_TEXTDOMAIN ), $service->title, $allowed[ $other ]->title );
                }
            }

            $services[] = [ 'service_id' => $id, 'option_key' => $option ];
        }

        return [ 'services' => $services, 'error' => $error ];
    }

    /**
     * Pinta los campos (sin la etiqueta <form>).
     *
     * @param   array  $values   Datos (de parse() o de una solicitud).
     * @param   array  $consents Consentimientos marcados.
     * @param   array  $errors   Errores por campo.
     * @param   string $context  CONTEXT_*.
     * @param   bool   $with_consents Pintar las casillas de consentimiento.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function render( array $values, array $consents, array $errors, string $context, bool $with_consents = true ): void {
        $v = function ( string $key ) use ( $values ): string {
            return (string) ( $values[ $key ] ?? '' );
        };
        $name = function ( string $key ): string {
            return self::INPUT . '[' . $key . ']';
        };
        $error = function ( string $key ) use ( $errors ): void {
            if ( isset( $errors[ $key ] ) ) {
                printf( '<p class="sulayr-join-error" role="alert">%s</p>', esc_html( $errors[ $key ] ) );
            }
        };
        $input = function ( string $key, string $label, string $type = 'text', array $attrs = [] ) use ( $v, $name, $error ): void {
            $extra = '';
            foreach ( $attrs as $attr => $value ) {
                $extra .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
            }
            printf(
                '<div class="sulayr-join-field"><label for="sulayr-join-%1$s">%2$s</label><input class="form-control" type="%3$s" id="sulayr-join-%1$s" name="%4$s" value="%5$s"%6$s>',
                esc_attr( $key ),
                esc_html( $label ),
                esc_attr( $type ),
                esc_attr( $name( $key ) ),
                esc_attr( $v( $key ) ),
                $extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
            );
            $error( $key );
            echo '</div>';
        };

        $today = current_time( 'Y-m-d' );
        $is_admin = self::CONTEXT_ADMIN === $context;

        echo '<fieldset class="sulayr-join-group"><legend>' . esc_html__( 'Datos personales', SULAYR_TEXTDOMAIN ) . '</legend><div class="sulayr-join-grid">';
        $input( 'first_name', __( 'Nombre', SULAYR_TEXTDOMAIN ), 'text', [ 'required' => 'required', 'autocomplete' => 'given-name' ] );
        $input( 'last_name', __( 'Apellidos', SULAYR_TEXTDOMAIN ), 'text', [ 'required' => 'required', 'autocomplete' => 'family-name' ] );
        $input( 'email', __( 'Email', SULAYR_TEXTDOMAIN ), 'email', [ 'required' => 'required', 'autocomplete' => 'email' ] );
        $input( 'phone', __( 'Teléfono', SULAYR_TEXTDOMAIN ), 'tel', [ 'required' => 'required', 'autocomplete' => 'tel' ] );
        $input( 'dni', __( 'DNI o NIE', SULAYR_TEXTDOMAIN ), 'text', [ 'required' => 'required', 'maxlength' => '12' ] );
        $input( 'birth_date', __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ), 'date', [ 'required' => 'required', 'max' => $today, 'data-sulayr-birth' => '1' ] );
        printf( '<div class="sulayr-join-field"><label for="sulayr-join-sex">%s</label><select class="form-select" id="sulayr-join-sex" name="%s" required><option value="">%s</option>', esc_html__( 'Sexo (para las clasificaciones del club)', SULAYR_TEXTDOMAIN ), esc_attr( $name( 'sex' ) ), esc_html__( '— Elige —', SULAYR_TEXTDOMAIN ) );
        foreach ( MemberFields::sexes() as $value => $label ) {
            printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $v( 'sex' ), $value, false ), esc_html( $label ) );
        }
        echo '</select>';
        $error( 'sex' );
        echo '</div>';
        echo '</div></fieldset>';

        // Servicios.
        $selected = [];
        foreach ( (array) ( $values['services'] ?? [] ) as $item ) {
            $selected[ (int) $item['service_id'] ] = (string) $item['option_key'];
        }

        echo '<fieldset class="sulayr-join-group"><legend>' . esc_html__( 'Servicios', SULAYR_TEXTDOMAIN ) . '</legend>';
        self::render_services( self::services( $context ), $selected, '' !== $v( 'start_date' ) ? $v( 'start_date' ) : $today );
        $error( 'services' );
        echo '</fieldset>';

        echo '<fieldset class="sulayr-join-group"><legend>' . esc_html__( 'Inicio y otros datos', SULAYR_TEXTDOMAIN ) . '</legend><div class="sulayr-join-grid">';
        $input( 'start_date', __( 'Fecha de inicio deseada', SULAYR_TEXTDOMAIN ), 'date', $is_admin ? [ 'required' => 'required', 'data-sulayr-start' => '1' ] : [ 'required' => 'required', 'min' => $today, 'data-sulayr-start' => '1' ] );
        printf( '<div class="sulayr-join-field"><label for="sulayr-join-tshirt">%s</label><select class="form-select" id="sulayr-join-tshirt" name="%s"><option value="">%s</option>', esc_html__( 'Talla de camiseta (opcional)', SULAYR_TEXTDOMAIN ), esc_attr( $name( 'tshirt' ) ), esc_html__( '— Sin indicar —', SULAYR_TEXTDOMAIN ) );
        foreach ( MemberFields::TSHIRT_SIZES as $size ) {
            printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $size ), selected( $v( 'tshirt' ), $size, false ) );
        }
        echo '</select></div></div>';
        printf( '<div class="sulayr-join-field"><label for="sulayr-join-notes">%s</label><textarea class="form-control" id="sulayr-join-notes" name="%s" rows="3">%s</textarea></div>', esc_html__( 'Observaciones (opcional)', SULAYR_TEXTDOMAIN ), esc_attr( $name( 'notes' ) ), esc_textarea( $v( 'notes' ) ) );
        echo '</fieldset>';

        // Tutor: se muestra con JavaScript si es menor; sin JavaScript se ve siempre.
        $guardian = (array) ( $values['guardian'] ?? [] );
        echo '<fieldset class="sulayr-join-group sulayr-join-guardian" data-sulayr-guardian="1"><legend>' . esc_html__( 'Datos del tutor (menores de 18 años)', SULAYR_TEXTDOMAIN ) . '</legend>';
        echo '<p class="sulayr-join-note">' . esc_html__( 'Si el deportista es menor de edad en la fecha de inicio, el tutor rellena estos datos y acepta los consentimientos.', SULAYR_TEXTDOMAIN ) . '</p><div class="sulayr-join-grid">';
        $guardian_fields = [
            'name' => [ __( 'Nombre y apellidos del tutor', SULAYR_TEXTDOMAIN ), 'text' ],
            'dni' => [ __( 'DNI o NIE del tutor', SULAYR_TEXTDOMAIN ), 'text' ],
            'phone' => [ __( 'Teléfono del tutor', SULAYR_TEXTDOMAIN ), 'tel' ],
            'email' => [ __( 'Email del tutor', SULAYR_TEXTDOMAIN ), 'email' ],
        ];
        foreach ( $guardian_fields as $key => $def ) {
            printf(
                '<div class="sulayr-join-field"><label for="sulayr-join-guardian-%1$s">%2$s</label><input class="form-control" type="%3$s" id="sulayr-join-guardian-%1$s" name="%4$s[%1$s]" value="%5$s">',
                esc_attr( $key ),
                esc_html( $def[0] ),
                esc_attr( $def[1] ),
                esc_attr( $name( 'guardian' ) ),
                esc_attr( (string) ( $guardian[ $key ] ?? '' ) )
            );
            $error( 'guardian_' . $key );
            echo '</div>';
        }
        echo '</div></fieldset>';

        if ( false === $with_consents ) {
            return;
        }

        // Consentimientos.
        echo '<fieldset class="sulayr-join-group"><legend>' . esc_html__( 'Consentimientos', SULAYR_TEXTDOMAIN ) . '</legend>';
        if ( $is_admin ) {
            echo '<p class="sulayr-join-note">' . esc_html__( 'Marca solo lo que el socio (o su tutor, si es menor) ha aceptado, por ejemplo en papel. Quedará guardado con tu nombre.', SULAYR_TEXTDOMAIN ) . '</p>';
        }
        foreach ( MemberConsents::types() as $type => $def ) {
            printf(
                '<div class="sulayr-join-consent form-check"><input class="form-check-input" type="checkbox" id="sulayr-join-consent-%1$s" name="%2$s[%1$s]" value="1"%3$s> <label class="form-check-label" for="sulayr-join-consent-%1$s"><strong>%4$s%5$s</strong></label><div class="sulayr-join-consent-text">%6$s</div>',
                esc_attr( $type ),
                esc_attr( $name( 'consents' ) ),
                ! empty( $consents[ $type ] ) ? ' checked' : '',
                esc_html( $def['label'] ),
                $def['required'] ? ' ' . esc_html__( '(obligatorio)', SULAYR_TEXTDOMAIN ) : ' ' . esc_html__( '(opcional)', SULAYR_TEXTDOMAIN ),
                wp_kses_post( MemberConsents::text( $type ) )
            );
            $error( 'consent_' . $type );
            echo '</div>';
        }
        echo '</fieldset>';
    }

    /**
     * Casillas de servicios con su opción y precio general (sin <fieldset>).
     * El JavaScript del formulario necesita que estén dentro de form.sulayr-join-form.
     *
     * @param   Service[]            $services    Servicios que se muestran.
     * @param   array<int, string>   $selected    Servicio => opción elegida.
     * @param   string               $price_date  Fecha del precio Y-m-d.
     * @param   string               $member_type Tipo de socio para el precio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function render_services( array $services, array $selected, string $price_date, string $member_type = ServiceCatalog::GENERAL ): void {
        $name = function ( string $key ): string {
            return self::INPUT . '[' . $key . ']';
        };
        foreach ( $services as $service ) {
            $checked = $service->is_base || isset( $selected[ $service->id ] );
            $price = ServiceCatalog::price_for( $service, $member_type, $price_date );
            printf(
                '<div class="sulayr-join-service form-check" data-service="%1$d" data-incompatible="%2$s"><input class="form-check-input" type="checkbox" id="sulayr-join-service-%1$d" name="%3$s[]" value="%1$d"%4$s%5$s> <label class="form-check-label" for="sulayr-join-service-%1$d"><strong>%6$s</strong>%7$s%8$s</label>',
                (int) $service->id,
                esc_attr( implode( ',', $service->incompatible ) ),
                esc_attr( $name( 'services' ) ),
                $checked ? ' checked' : '',
                $service->is_base ? ' disabled' : '',
                esc_html( $service->title ),
                null !== $price ? ' · ' . esc_html( number_format_i18n( $price['amount'], 2 ) . ' €/mes' ) : '',
                $service->is_base ? ' <span class="sulayr-join-note">' . esc_html__( '(incluida siempre)', SULAYR_TEXTDOMAIN ) . '</span>' : ''
            );
            if ( $service->is_base ) {
                printf( '<input type="hidden" name="%s[]" value="%d">', esc_attr( $name( 'services' ) ), (int) $service->id );
            }
            if ( ! empty( $service->options ) ) {
                printf( '<div class="sulayr-join-options"><label for="sulayr-join-option-%1$d">%2$s</label> <select class="form-select" id="sulayr-join-option-%1$d" name="%3$s[%1$d]"><option value="">%4$s</option>', (int) $service->id, esc_html__( 'Opción', SULAYR_TEXTDOMAIN ), esc_attr( $name( 'options' ) ), esc_html__( '— Elige —', SULAYR_TEXTDOMAIN ) );
                foreach ( $service->options as $key => $label ) {
                    printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $selected[ $service->id ] ?? '', $key, false ), esc_html( $label ) );
                }
                echo '</select></div>';
            }
            echo '</div>';
        }
    }

    /**
     * Registra el JavaScript del formulario (tutor, incompatibles, opciones).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function enqueue_script(): void {
        wp_enqueue_script( 'sulayr-join-form', SULAYR_PUBLIC_ASSETS . '/js/join-form.js', [], SULAYR_VERSION, true );
    }

    /**
     * Teléfono con al menos 9 cifras y solo cifras, espacios, guiones, paréntesis o «+».
     *
     * @param   string $phone Teléfono.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_valid_phone( string $phone ): bool {
        return 1 === preg_match( '/^\+?[\d\s\-\(\)\.]+$/', $phone ) && strlen( (string) preg_replace( '/\D/', '', $phone ) ) >= 9;
    }

    /**
     * Fecha Y-m-d válida.
     *
     * @param   string $date Fecha.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_date( string $date ): bool {
        $d = \DateTime::createFromFormat( '!Y-m-d', $date );

        return false !== $d && $d->format( 'Y-m-d' ) === $date;
    }
}
