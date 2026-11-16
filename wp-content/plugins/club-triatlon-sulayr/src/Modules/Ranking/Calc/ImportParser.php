<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Lectura y validación del JSON de importación (SPEC-RANKING-INTERNO §14.2).
 *
 * El JSON lo genera una IA a partir de los PDF de la federación: la competición
 * y, por cada clasificación del PDF, todos sus deportistas, sean o no del club.
 * Aquí solo se comprueba que el JSON es coherente; la unificación de
 * clasificaciones está en ImportMerger y el emparejamiento en NameMatcher.
 *
 * Sin WordPress, para poder probarlo (también lo usa la skill de Claude Code).
 */
class ImportParser {

    public const SCHEMA = 'sulayr-ranking-import/1';

    /** Límite de deportistas, para no cargar un JSON desproporcionado. */
    private const MAX_ATHLETES = 5000;

    /**
     * Lee el JSON.
     *
     * @param   string $json  Texto.
     * @param   string $today Y-m-d.
     * @return 	array{competition: array, classifications: array, athletes: array, errors: string[], warnings: string[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function parse( string $json, string $today ): array {
        $out = [ 'competition' => [], 'classifications' => [], 'athletes' => [], 'errors' => [], 'warnings' => [] ];

        $json = trim( preg_replace( '/^\xEF\xBB\xBF/', '', $json ) );
        // Si viene envuelto en un bloque de código (```json … ```), se quita.
        if ( 1 === preg_match( '/^```[a-z]*\s*(.*?)\s*```$/s', $json, $m ) ) {
            $json = $m[1];
        }
        if ( '' === $json ) {
            $out['errors'][] = 'El JSON está vacío.';
            return $out;
        }

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            $out['errors'][] = 'No es un JSON válido: ' . json_last_error_msg() . '.';
            return $out;
        }
        if ( self::SCHEMA !== ( $data['schema'] ?? '' ) ) {
            $out['errors'][] = sprintf( 'Falta "schema": "%s" o tiene otro valor.', self::SCHEMA );
            return $out;
        }

        $out['competition'] = self::competition( (array) ( $data['competition'] ?? [] ), $today, $out['errors'] );
        $format = $out['competition']['format'];

        $classifications = $data['classifications'] ?? null;
        if ( ! is_array( $classifications ) || empty( $classifications ) ) {
            $out['errors'][] = 'Falta "classifications" o está vacía.';
            return $out;
        }

        foreach ( array_values( $classifications ) as $c_index => $raw ) {
            $raw = (array) $raw;
            $title = self::text( $raw['title'] ?? '', 120 );
            $where = sprintf( 'Clasificación %d («%s»)', $c_index + 1, '' !== $title ? $title : 'sin título' );
            $team = strtolower( trim( (string) ( $raw['team_category'] ?? '' ) ) );

            if ( Keys::FORMAT_RELAY === $format && false === in_array( $team, Keys::TEAM_CATEGORIES, true ) ) {
                $out['errors'][] = $where . ': en relevos, "team_category" tiene que ser "m", "f" o "mixto".';
            } elseif ( Keys::FORMAT_INDIVIDUAL === $format ) {
                $team = '';
            }

            $finishers = null;
            if ( isset( $raw['finishers'] ) && '' !== $raw['finishers'] && null !== $raw['finishers'] ) {
                if ( false === is_numeric( $raw['finishers'] ) || (int) $raw['finishers'] < 1 ) {
                    $out['errors'][] = $where . ': "finishers" tiene que ser un número mayor que 0.';
                } else {
                    $finishers = (int) $raw['finishers'];
                }
            }

            $out['classifications'][ $c_index ] = [ 'title' => $title, 'team_category' => $team, 'finishers' => $finishers ];

            $athletes = $raw['athletes'] ?? null;
            if ( ! is_array( $athletes ) || empty( $athletes ) ) {
                $out['errors'][] = $where . ': falta "athletes" o está vacía.';
                continue;
            }

            foreach ( array_values( $athletes ) as $a_index => $a ) {
                $row = self::athlete( (array) $a, sprintf( '%s, deportista %d', $where, $a_index + 1 ), $out['errors'] );
                $row['classification'] = $c_index;
                $row['team_category'] = $team;
                $out['athletes'][] = $row;
                if ( count( $out['athletes'] ) > self::MAX_ATHLETES ) {
                    $out['errors'][] = sprintf( 'Hay más de %d deportistas: revisa el JSON.', self::MAX_ATHLETES );
                    return $out;
                }
            }
        }

        // En relevos mixtos no se puede deducir el sexo del título: cada deportista lo tiene que traer.
        foreach ( $out['athletes'] as $row ) {
            if ( '' === $row['sex'] ) {
                $out['errors'][] = sprintf( '«%s»: no se sabe su sexo. Pon "sex": "m" o "f", o la categoría con la M o la F al final.', $row['name'] );
            }
        }

        return $out;
    }

    /**
     * Datos de la competición.
     *
     * @param   array    $raw    Datos.
     * @param   string   $today  Y-m-d.
     * @param   string[] $errors Errores (salida).
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function competition( array $raw, string $today, array &$errors ): array {
        $c = [
            'name' => self::text( $raw['name'] ?? '', 190 ),
            'race_date' => trim( (string) ( $raw['race_date'] ?? '' ) ),
            'location' => self::text( $raw['location'] ?? '', 190 ),
            'modality' => strtolower( trim( (string) ( $raw['modality'] ?? '' ) ) ),
            'distance' => strtolower( trim( (string) ( $raw['distance'] ?? '' ) ) ),
            'format' => strtolower( trim( (string) ( $raw['format'] ?? '' ) ) ),
        ];

        if ( '' === $c['name'] ) {
            $errors[] = 'Competición: falta "name".';
        }
        if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $c['race_date'], $m ) || false === checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            $errors[] = 'Competición: "race_date" tiene que ser una fecha AAAA-MM-DD.';
        } elseif ( $c['race_date'] > $today ) {
            $errors[] = 'Competición: la fecha no puede ser posterior a hoy.';
        }
        if ( false === in_array( $c['modality'], Keys::MODALITIES, true ) ) {
            $errors[] = 'Competición: "modality" tiene que ser uno de ' . implode( ', ', Keys::MODALITIES ) . '.';
        }
        if ( false === in_array( $c['distance'], Keys::DISTANCES, true ) ) {
            $errors[] = 'Competición: "distance" tiene que ser uno de ' . implode( ', ', Keys::DISTANCES ) . '.';
        }
        if ( Keys::FORMAT_TEAM === $c['format'] ) {
            $errors[] = 'Competición: la importación todavía no admite la contrarreloj por equipos. Métela a mano.';
        } elseif ( false === in_array( $c['format'], [ Keys::FORMAT_INDIVIDUAL, Keys::FORMAT_RELAY ], true ) ) {
            $errors[] = 'Competición: "format" tiene que ser "individual" o "relevos".';
        }
        if ( ! in_array( $c['format'], Keys::FORMATS, true ) ) {
            $c['format'] = Keys::FORMAT_INDIVIDUAL;
        }

        return $c;
    }

    /**
     * Un deportista.
     *
     * @param   array    $a      Datos.
     * @param   string   $where  Para los mensajes.
     * @param   string[] $errors Errores (salida).
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function athlete( array $a, string $where, array &$errors ): array {
        $row = [
            'name' => self::text( $a['name'] ?? '', 190 ),
            'club' => self::text( $a['club'] ?? '', 190 ),
            'cat' => strtoupper( trim( (string) ( $a['cat'] ?? '' ) ) ),
            'status' => strtolower( trim( (string) ( $a['status'] ?? Keys::STATUS_FINISHED ) ) ),
            'pos' => null,
            'pos_cat' => null,
            'time' => trim( (string) ( $a['time'] ?? '' ) ),
            'time_s' => null,
            'sex' => strtolower( trim( (string) ( $a['sex'] ?? '' ) ) ),
        ];

        if ( '' === $row['name'] ) {
            $errors[] = $where . ': falta "name".';
        } else {
            $where = sprintf( '%s («%s»)', $where, $row['name'] );
        }
        if ( '' !== $row['cat'] && 1 !== preg_match( '/^[A-Z0-9]{2}[MF]$/', $row['cat'] ) ) {
            $errors[] = $where . ': "cat" son dos caracteres y la M o la F (p. ej. V1M).';
        }
        if ( '' === $row['status'] ) {
            $row['status'] = Keys::STATUS_FINISHED;
        }
        if ( false === in_array( $row['status'], Keys::STATUSES, true ) ) {
            $errors[] = $where . ': "status" tiene que ser uno de ' . implode( ', ', Keys::STATUSES ) . '.';
        }

        // Sexo: el que diga el JSON o, si no, la letra final de la categoría.
        $cat_sex = '' !== $row['cat'] ? strtolower( substr( $row['cat'], -1 ) ) : '';
        if ( '' !== $row['sex'] && false === in_array( $row['sex'], Keys::SEXES, true ) ) {
            $errors[] = $where . ': "sex" tiene que ser "m" o "f".';
            $row['sex'] = '';
        } elseif ( '' !== $row['sex'] && in_array( $cat_sex, Keys::SEXES, true ) && $cat_sex !== $row['sex'] ) {
            $errors[] = $where . ': "sex" no coincide con la letra final de "cat".';
        } elseif ( '' === $row['sex'] && in_array( $cat_sex, Keys::SEXES, true ) ) {
            $row['sex'] = $cat_sex;
        }

        foreach ( [ 'pos', 'pos_cat' ] as $field ) {
            $value = $a[ $field ] ?? null;
            if ( null === $value || '' === $value ) {
                continue;
            }
            if ( false === is_numeric( $value ) || (int) $value < 1 || (string) (int) $value !== trim( (string) $value ) ) {
                $errors[] = sprintf( '%s: "%s" tiene que ser un número entero mayor que 0.', $where, $field );
            } else {
                $row[ $field ] = (int) $value;
            }
        }

        if ( Keys::STATUS_FINISHED === $row['status'] ) {
            if ( '' !== $row['time'] ) {
                $row['time_s'] = RaceTime::parse( $row['time'] );
                if ( null === $row['time_s'] ) {
                    $errors[] = $where . ': "time" tiene que ser h:mm:ss.';
                }
            } elseif ( null === $row['pos'] ) {
                $errors[] = $where . ': ha terminado y no tiene ni "time" ni "pos".';
            }
        } else {
            $row['time'] = '';
            $row['pos'] = null;
            $row['pos_cat'] = null;
        }

        return $row;
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
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }
        $value = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
    }
}
