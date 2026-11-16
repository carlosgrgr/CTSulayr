<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Lectura y validación del formulario de una competición (SPEC-RANKING-INTERNO §5).
 *
 * Recibe los datos tal como llegan (textos) y devuelve los datos limpios, los
 * errores (bloquean el guardado), los avisos (no bloquean) y las referencias
 * que hacen falta para publicar. Sin WordPress, para poder probarlo.
 */
class ResultValidator {

    /**
     * Valida una competición con sus referencias y resultados.
     *
     * @param   array     $input   [ competition => [], references => [ [] ], results => [ [] ] ] en texto.
     * @param   array     $members user_id => [ sex => m|f, group => clave|'' ] de los socios que se pueden elegir.
     * @param   AgeGroups $groups  Grupos de la temporada.
     * @param   string    $today   Y-m-d.
     * @return 	array{competition: array, references: array, results: array, errors: array, warnings: array, required: array, missing: array}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function validate( array $input, array $members, AgeGroups $groups, string $today ): array {
        $errors = [ 'competition' => [], 'references' => [], 'results' => [] ];
        $warnings = [];

        $competition = self::competition( (array) ( $input['competition'] ?? [] ), $today, $errors['competition'] );
        $format = $competition['format'];

        // Referencias.
        $references = [];
        foreach ( (array) ( $input['references'] ?? [] ) as $raw ) {
            $ref = self::reference( (array) $raw, $format, $ref_errors );
            if ( null === $ref ) {
                continue;
            }
            $key = Keys::ref_key( $ref['scope'], $ref['team_category'], $ref['sex'], $ref['age_group'] );
            if ( ! empty( $ref_errors ) ) {
                $errors['references'][ $key ] = $ref_errors;
            }
            $references[ $key ] = $ref;
        }

        // Una referencia de grupo nunca es mejor que la absoluta de su sexo.
        foreach ( $references as $key => $ref ) {
            if ( Keys::SCOPE_GROUP !== $ref['scope'] ) {
                continue;
            }
            $abs = $references[ Keys::ref_key( Keys::SCOPE_ABSOLUTE, $ref['team_category'], $ref['sex'], '' ) ] ?? null;
            if ( null === $abs ) {
                continue;
            }
            if ( null !== $ref['winner_time'] && null !== $abs['winner_time'] && $ref['winner_time'] < $abs['winner_time'] ) {
                $errors['references'][ $key ]['winner_time'] = 'El primero de la categoría no puede ser más rápido que el ganador absoluto.';
            }
            if ( null !== $ref['participants'] && null !== $abs['participants'] && $ref['participants'] > $abs['participants'] ) {
                $errors['references'][ $key ]['participants'] = 'La categoría no puede tener más finalizados que el total de su sexo.';
            }
        }

        // Resultados.
        $results = [];
        $seen = [];
        foreach ( (array) ( $input['results'] ?? [] ) as $index => $raw ) {
            $raw = (array) $raw;
            if ( self::is_empty_row( $raw ) ) {
                continue;
            }
            $row_errors = [];
            $row_warnings = [];
            $row = self::result( $raw, $format, $members, $groups, $row_errors, $row_warnings );

            if ( $row['user_id'] > 0 ) {
                if ( isset( $seen[ $row['user_id'] ] ) ) {
                    $row_errors['user_id'] = 'Este socio ya está en otra fila.';
                }
                $seen[ $row['user_id'] ] = true;
            }

            if ( empty( $row_errors ) ) {
                self::check_against_references( $row, $references, $format, $row_errors );
            }

            if ( ! empty( $row_errors ) ) {
                $errors['results'][ $index ] = $row_errors;
            }
            if ( ! empty( $row_warnings ) ) {
                $warnings[ $index ] = $row_warnings;
            }
            $results[ $index ] = $row;
        }

        $required = self::required_references( $results, $format );
        $missing = self::missing_references( $required, $references );

        return [
            'competition' => $competition,
            'references' => $references,
            'results' => $results,
            'errors' => $errors,
            'warnings' => $warnings,
            'required' => $required,
            'missing' => $missing,
        ];
    }

    /**
     * True si hay algún error.
     *
     * @param   array $errors Errores de validate().
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_errors( array $errors ): bool {
        return ! empty( $errors['competition'] ) || ! empty( $errors['references'] ) || ! empty( $errors['results'] );
    }

    /**
     * Referencias que hacen falta para publicar, según los resultados (R6, R38).
     *
     * @param   array  $results Resultados limpios: user_id, status, time_s, team_category, sex, age_group, race_category.
     * @param   string $format  Formato.
     * @return 	array<string, array{scope: string, team_category: string, sex: string, age_group: string, participants_required: bool}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function required_references( array $results, string $format ): array {
        $required = [];
        $add = function ( string $scope, string $team, string $sex, string $group, bool $participants ) use ( &$required ) {
            $key = Keys::ref_key( $scope, $team, $sex, $group );
            if ( isset( $required[ $key ] ) ) {
                $required[ $key ]['participants_required'] = $required[ $key ]['participants_required'] || $participants;
                return;
            }
            $required[ $key ] = [ 'scope' => $scope, 'team_category' => $team, 'sex' => $sex, 'age_group' => $group, 'participants_required' => $participants ];
        };

        foreach ( $results as $row ) {
            if ( Keys::STATUS_FINISHED !== $row['status'] || '' === (string) $row['sex'] ) {
                continue;
            }
            $team = Keys::has_team( $format ) ? (string) $row['team_category'] : '';
            if ( Keys::FORMAT_TEAM === $format ) {
                $add( Keys::SCOPE_ABSOLUTE, $team, '', '', true );
                continue;
            }
            $add( Keys::SCOPE_ABSOLUTE, $team, (string) $row['sex'], '', true );
            if ( '' !== (string) $row['age_group'] && false === Keys::is_absolute_category( (string) $row['race_category'] ) ) {
                $add( Keys::SCOPE_GROUP, $team, (string) $row['sex'], (string) $row['age_group'], null === $row['time_s'] );
            }
        }

        return $required;
    }

    /**
     * Claves de las referencias obligatorias que faltan o están incompletas.
     *
     * @param   array $required   De required_references().
     * @param   array $references Referencias limpias por clave.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function missing_references( array $required, array $references ): array {
        $missing = [];
        foreach ( $required as $key => $need ) {
            $ref = $references[ $key ] ?? null;
            if ( null === $ref || null === $ref['winner_time'] || ( $need['participants_required'] && null === $ref['participants'] ) ) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Datos de la competición.
     *
     * @param   array  $raw    Textos.
     * @param   string $today  Y-m-d.
     * @param   array  $errors Errores (salida).
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function competition( array $raw, string $today, array &$errors ): array {
        $c = [
            'name' => self::text( $raw['name'] ?? '', 190 ),
            'race_date' => trim( (string) ( $raw['race_date'] ?? '' ) ),
            'location' => self::text( $raw['location'] ?? '', 190 ),
            'modality' => (string) ( $raw['modality'] ?? '' ),
            'distance' => (string) ( $raw['distance'] ?? '' ),
            'format' => (string) ( $raw['format'] ?? '' ),
            'notes' => self::text( $raw['notes'] ?? '', 255 ),
        ];

        if ( '' === $c['name'] ) {
            $errors['name'] = 'Escribe el nombre de la competición.';
        }
        if ( false === self::is_date( $c['race_date'] ) ) {
            $errors['race_date'] = 'Escribe una fecha válida.';
        } elseif ( $c['race_date'] > $today ) {
            $errors['race_date'] = 'La fecha no puede ser posterior a hoy.';
        }
        if ( false === in_array( $c['modality'], Keys::MODALITIES, true ) ) {
            $errors['modality'] = 'Elige la modalidad.';
        }
        if ( false === in_array( $c['distance'], Keys::DISTANCES, true ) ) {
            $errors['distance'] = 'Elige la distancia.';
        }
        if ( false === in_array( $c['format'], Keys::FORMATS, true ) ) {
            $errors['format'] = 'Elige el formato.';
            $c['format'] = Keys::FORMAT_INDIVIDUAL;
        }

        return $c;
    }

    /**
     * Una referencia, o null si viene vacía o con claves que no valen.
     *
     * @param   array  $raw    Textos.
     * @param   string $format Formato.
     * @param   array  $errors Errores (salida).
     * @return 	array|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function reference( array $raw, string $format, &$errors ): ?array {
        $errors = [];
        $scope = (string) ( $raw['scope'] ?? '' );
        $team = Keys::has_team( $format ) ? (string) ( $raw['team_category'] ?? '' ) : '';
        $sex = Keys::FORMAT_TEAM === $format ? '' : (string) ( $raw['sex'] ?? '' );
        $group = Keys::SCOPE_GROUP === $scope ? strtolower( trim( (string) ( $raw['age_group'] ?? '' ) ) ) : '';

        if ( false === in_array( $scope, [ Keys::SCOPE_ABSOLUTE, Keys::SCOPE_GROUP ], true )
            || ( Keys::has_team( $format ) && false === in_array( $team, Keys::TEAM_CATEGORIES, true ) )
            || ( Keys::FORMAT_TEAM !== $format && false === in_array( $sex, Keys::SEXES, true ) )
            || ( Keys::SCOPE_GROUP === $scope && ( Keys::FORMAT_TEAM === $format || 1 !== preg_match( '/^[a-z0-9-]{1,32}$/', $group ) ) ) ) {
            return null;
        }

        $time_raw = trim( (string) ( $raw['winner_time'] ?? '' ) );
        $participants_raw = trim( (string) ( $raw['participants'] ?? '' ) );
        if ( '' === $time_raw && '' === $participants_raw ) {
            return [ 'scope' => $scope, 'team_category' => $team, 'sex' => $sex, 'age_group' => $group, 'winner_time' => null, 'participants' => null ];
        }

        $time = null;
        if ( '' !== $time_raw ) {
            $time = RaceTime::parse( $time_raw );
            if ( null === $time ) {
                $errors['winner_time'] = 'Escribe el tiempo como h:mm:ss.';
            }
        }

        $participants = null;
        if ( '' !== $participants_raw ) {
            if ( 1 !== preg_match( '/^\d+$/', $participants_raw ) || (int) $participants_raw < 1 ) {
                $errors['participants'] = 'Escribe un número entero mayor que 0.';
            } else {
                $participants = (int) $participants_raw;
            }
        }

        return [ 'scope' => $scope, 'team_category' => $team, 'sex' => $sex, 'age_group' => $group, 'winner_time' => $time, 'participants' => $participants ];
    }

    /**
     * Una fila de resultado.
     *
     * @param   array     $raw      Textos.
     * @param   string    $format   Formato.
     * @param   array     $members  Socios que se pueden elegir.
     * @param   AgeGroups $groups   Grupos.
     * @param   array     $errors   Errores (salida).
     * @param   array     $warnings Avisos (salida).
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function result( array $raw, string $format, array $members, AgeGroups $groups, array &$errors, array &$warnings ): array {
        $user_id = (int) ( $raw['user_id'] ?? 0 );
        $member = $members[ $user_id ] ?? null;
        $row = [
            'id' => (int) ( $raw['id'] ?? 0 ),
            'user_id' => $user_id,
            'sex' => null !== $member ? (string) $member['sex'] : '',
            'age_group' => null !== $member ? (string) $member['group'] : '',
            'status' => (string) ( $raw['status'] ?? Keys::STATUS_FINISHED ),
            'time_s' => null,
            'race_category' => strtoupper( trim( (string) ( $raw['race_category'] ?? '' ) ) ),
            'position_sex' => null,
            'position_age_group' => null,
            'team_category' => Keys::has_team( $format ) ? (string) ( $raw['team_category'] ?? '' ) : '',
            'time_raw' => trim( (string) ( $raw['time'] ?? '' ) ),
            'position_sex_raw' => trim( (string) ( $raw['position_sex'] ?? '' ) ),
            'position_age_group_raw' => trim( (string) ( $raw['position_age_group'] ?? '' ) ),
        ];

        if ( null === $member ) {
            $errors['user_id'] = 'Elige un socio activo o congelado con el sexo rellenado.';
        }
        if ( false === in_array( $row['status'], Keys::STATUSES, true ) ) {
            $errors['status'] = 'Elige el estado.';
            $row['status'] = Keys::STATUS_FINISHED;
        }
        if ( Keys::has_team( $format ) && false === in_array( $row['team_category'], Keys::TEAM_CATEGORIES, true ) ) {
            $errors['team_category'] = 'Elige la categoría del equipo.';
        }

        if ( '' !== $row['race_category'] ) {
            if ( 1 !== preg_match( '/^[A-Z0-9]{2}[MF]$/', $row['race_category'] ) ) {
                $errors['race_category'] = 'El código de categoría son dos caracteres y la M o la F (p. ej. V1M).';
            } elseif ( null !== $member && strtolower( substr( $row['race_category'], -1 ) ) !== $row['sex'] ) {
                $errors['race_category'] = 'La letra final del código no coincide con el sexo del socio.';
            } elseif ( null !== $member && $groups->mismatch( $row['race_category'], $row['age_group'] ) ) {
                $warnings[] = sprintf( 'La carrera lo clasifica como %1$s y la web como %2$s: revisa la fecha de nacimiento o las edades de los grupos.', $row['race_category'], $groups->label( $row['age_group'] ) );
            }
        }

        if ( Keys::STATUS_FINISHED !== $row['status'] ) {
            return $row;
        }

        if ( '' !== $row['time_raw'] ) {
            $row['time_s'] = RaceTime::parse( $row['time_raw'] );
            if ( null === $row['time_s'] ) {
                $errors['time'] = 'Escribe el tiempo como h:mm:ss.';
            }
        }
        foreach ( [ 'position_sex', 'position_age_group' ] as $field ) {
            $value = $row[ $field . '_raw' ];
            if ( '' === $value ) {
                continue;
            }
            if ( 1 !== preg_match( '/^\d+$/', $value ) || (int) $value < 1 ) {
                $errors[ $field ] = 'Escribe una posición: un número entero mayor que 0.';
            } else {
                $row[ $field ] = (int) $value;
            }
        }

        if ( '' === $row['time_raw'] && null === $row['position_sex'] && ! isset( $errors['position_sex'] ) ) {
            $errors['time'] = 'Hace falta el tiempo o la posición.';
        }
        if ( null !== $row['position_sex'] && null !== $row['position_age_group'] && $row['position_age_group'] > $row['position_sex'] ) {
            $errors['position_age_group'] = 'La posición en la categoría no puede ser peor que la posición en el sexo.';
        }
        if ( null !== $member && '' === $row['age_group'] ) {
            $warnings[] = 'Sin fecha de nacimiento: no puntúa en grupo de edad.';
        }

        return $row;
    }

    /**
     * Tiempos y posiciones frente a las referencias (R11).
     *
     * @param   array  $row        Resultado.
     * @param   array  $references Referencias por clave.
     * @param   string $format     Formato.
     * @param   array  $errors     Errores (salida).
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function check_against_references( array $row, array $references, string $format, array &$errors ): void {
        if ( Keys::STATUS_FINISHED !== $row['status'] ) {
            return;
        }

        $team = Keys::has_team( $format ) ? $row['team_category'] : '';
        $sex = Keys::FORMAT_TEAM === $format ? '' : $row['sex'];
        $abs = $references[ Keys::ref_key( Keys::SCOPE_ABSOLUTE, $team, $sex, '' ) ] ?? null;

        if ( null !== $abs ) {
            self::check_time( $row['time_s'], $row['position_sex'], $abs, 'position_sex', 'el del ganador', $errors );
            if ( null !== $abs['participants'] && null !== $row['position_sex'] && $row['position_sex'] > $abs['participants'] ) {
                $errors['position_sex'] = sprintf( 'La posición no puede ser mayor que los finalizados (%d).', $abs['participants'] );
            }
        }

        if ( Keys::FORMAT_TEAM === $format || '' === $row['age_group'] || Keys::is_absolute_category( $row['race_category'] ) ) {
            return;
        }

        $grp = $references[ Keys::ref_key( Keys::SCOPE_GROUP, $team, $sex, $row['age_group'] ) ] ?? null;
        if ( null !== $grp ) {
            self::check_time( $row['time_s'], $row['position_age_group'], $grp, 'position_age_group', 'el del primero de su categoría', $errors );
            if ( null !== $grp['participants'] && null !== $row['position_age_group'] && $row['position_age_group'] > $grp['participants'] ) {
                $errors['position_age_group'] = sprintf( 'La posición en la categoría no puede ser mayor que sus finalizados (%d).', $grp['participants'] );
            }
        }
    }

    /**
     * Un tiempo no puede ser menor que el de la referencia; igual, solo si es el primero.
     *
     * @param   int|null $time     Segundos.
     * @param   int|null $position Posición frente a esa referencia.
     * @param   array    $ref      Referencia.
     * @param   string   $field    Campo de la posición.
     * @param   string   $whose    Texto para el mensaje.
     * @param   array    $errors   Errores (salida).
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function check_time( ?int $time, ?int $position, array $ref, string $field, string $whose, array &$errors ): void {
        if ( null === $time || null === $ref['winner_time'] ) {
            return;
        }

        if ( $time < $ref['winner_time'] ) {
            $errors['time'] = sprintf( 'El tiempo no puede ser menor que %1$s (%2$s).', $whose, RaceTime::format( $ref['winner_time'] ) );
        } elseif ( $time === $ref['winner_time'] && 1 !== $position ) {
            $errors[ $field ] = sprintf( 'Con el mismo tiempo que %s, la posición tiene que ser 1.', $whose );
        }
    }

    /**
     * True si la fila no tiene nada rellenado.
     *
     * @param   array $raw Textos.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_empty_row( array $raw ): bool {
        foreach ( [ 'user_id', 'time', 'race_category', 'position_sex', 'position_age_group' ] as $field ) {
            $value = trim( (string) ( $raw[ $field ] ?? '' ) );
            if ( '' !== $value && '0' !== $value ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Texto en una línea, recortado.
     *
     * @param   mixed $value Valor.
     * @param   int   $max   Longitud máxima.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function text( $value, int $max ): string {
        $value = trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
    }

    /**
     * True si es una fecha Y-m-d real.
     *
     * @param   string $value Texto.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_date( string $value ): bool {
        if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return false;
        }

        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
    }
}
