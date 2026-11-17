<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Baremo de una temporada (SPEC-RANKING-INTERNO §2.6 y §3.1).
 *
 * Array con base, bonus, best_n, coefficients[modalidad][distancia],
 * age_groups e interest_step / interest_max (bonus por interés del club, §15). Cada temporada guarda su copia (RankingSettings::for_season()).
 */
class Baremo {

    /** Bonus por interés del club por defecto (R43): de 50 en 50 hasta 300. */
    public const INTEREST_STEP = 50;
    public const INTEREST_MAX = 300;

    /** Límites del salto y del máximo en Ajustes (§15.3). */
    public const INTEREST_STEP_LIMIT = 500;
    public const INTEREST_MAX_LIMIT = 1000;

    /** Coeficientes del baremo (R8, R36). Lo que no está vale 1,0. */
    private const COEFFICIENTS = [
        'triatlon' => [ 'sprint' => 1.1, 'estandar' => 1.2, 'media' => 1.4, 'larga' => 1.6 ],
        'triatlon_cros' => [ 'sprint' => 1.1, 'estandar' => 1.2, 'media' => 1.4, 'larga' => 1.6 ],
        'duatlon' => [ 'estandar' => 1.2, 'media' => 1.4, 'larga' => 1.6 ],
        'duatlon_cros' => [ 'estandar' => 1.2, 'media' => 1.4, 'larga' => 1.6 ],
    ];

    /**
     * Baremo por defecto.
     *
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function defaults(): array {
        $coefficients = [];
        foreach ( Keys::MODALITIES as $modality ) {
            foreach ( Keys::DISTANCES as $distance ) {
                $coefficients[ $modality ][ $distance ] = self::COEFFICIENTS[ $modality ][ $distance ] ?? 1.0;
            }
        }

        return [
            'base' => 1000.0,
            'bonus' => 50.0,
            'best_n' => 6,
            'coefficients' => $coefficients,
            'age_groups' => AgeGroups::DEFAULTS,
            'interest_step' => self::INTEREST_STEP,
            'interest_max' => self::INTEREST_MAX,
        ];
    }

    /**
     * Completa un baremo con los valores por defecto y corrige tipos.
     *
     * @param   array $baremo Baremo guardado.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function normalize( array $baremo ): array {
        $defaults = self::defaults();
        $out = [
            'base' => self::positive( $baremo['base'] ?? null, $defaults['base'] ),
            'bonus' => isset( $baremo['bonus'] ) && is_numeric( $baremo['bonus'] ) && (float) $baremo['bonus'] >= 0 ? (float) $baremo['bonus'] : $defaults['bonus'],
            'best_n' => max( 1, (int) self::positive( $baremo['best_n'] ?? null, $defaults['best_n'] ) ),
            'coefficients' => [],
            'age_groups' => ! empty( $baremo['age_groups'] ) && is_array( $baremo['age_groups'] ) ? array_values( $baremo['age_groups'] ) : $defaults['age_groups'],
        ];

        // Baremos guardados antes del §15: 50 y 300. Un máximo que no es múltiplo del salto se redondea hacia abajo.
        $step = (int) ( $baremo['interest_step'] ?? 0 );
        $step = $step >= 1 && $step <= self::INTEREST_STEP_LIMIT ? $step : $defaults['interest_step'];
        $max = isset( $baremo['interest_max'] ) && is_numeric( $baremo['interest_max'] ) ? (int) $baremo['interest_max'] : $defaults['interest_max'];
        $max = max( 0, min( self::INTEREST_MAX_LIMIT, $max ) );
        $out['interest_step'] = $step;
        $out['interest_max'] = $max - $max % $step;

        foreach ( Keys::MODALITIES as $modality ) {
            foreach ( Keys::DISTANCES as $distance ) {
                $out['coefficients'][ $modality ][ $distance ] = self::positive( $baremo['coefficients'][ $modality ][ $distance ] ?? null, $defaults['coefficients'][ $modality ][ $distance ] );
            }
        }

        return $out;
    }

    /**
     * Coeficiente de una modalidad y distancia; 1,0 si no está.
     *
     * @param   array  $baremo   Baremo.
     * @param   string $modality Modalidad.
     * @param   string $distance Distancia.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function coefficient( array $baremo, string $modality, string $distance ): float {
        $value = $baremo['coefficients'][ $modality ][ $distance ] ?? null;

        return is_numeric( $value ) && (float) $value > 0 ? (float) $value : 1.0;
    }

    /**
     * Valores del bonus por interés que se pueden elegir: 0 y los múltiplos del salto hasta el máximo.
     *
     * @param   array $baremo Baremo.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function interest_values( array $baremo ): array {
        $step = max( 1, (int) ( $baremo['interest_step'] ?? self::INTEREST_STEP ) );
        $max = max( 0, (int) ( $baremo['interest_max'] ?? self::INTEREST_MAX ) );

        return range( 0, $max - $max % $step, $step );
    }

    /**
     * Problemas del salto y el máximo que se quieren guardar en Ajustes.
     *
     * @param   mixed $step Salto.
     * @param   mixed $max  Máximo.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function interest_problems( $step, $max ): array {
        $problems = [];
        $step_ok = is_numeric( $step ) && (float) $step === (float) (int) $step && (int) $step >= 1 && (int) $step <= self::INTEREST_STEP_LIMIT;
        if ( ! $step_ok ) {
            $problems[] = sprintf( 'El salto del bonus por interés tiene que ser un número entero entre 1 y %d.', self::INTEREST_STEP_LIMIT );
        }
        if ( ! is_numeric( $max ) || (float) $max !== (float) (int) $max || (int) $max < 0 || (int) $max > self::INTEREST_MAX_LIMIT ) {
            $problems[] = sprintf( 'El máximo del bonus por interés tiene que ser un número entero entre 0 y %d.', self::INTEREST_MAX_LIMIT );
        } elseif ( $step_ok && 0 !== (int) $max % (int) $step ) {
            $problems[] = sprintf( 'El máximo del bonus por interés (%1$d) tiene que ser múltiplo del salto (%2$d).', (int) $max, (int) $step );
        }

        return $problems;
    }

    /**
     * Competiciones cuyo bonus por interés deja de valer con unos valores nuevos.
     *
     * @param   int[] $values       Valores que se podrán elegir.
     * @param   array $competitions Lista de [ name, interest_bonus ].
     * @return 	string[] «Nombre (+bonus)».
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function interest_conflicts( array $values, array $competitions ): array {
        $out = [];
        foreach ( $competitions as $c ) {
            $bonus = (int) ( $c['interest_bonus'] ?? 0 );
            if ( ! in_array( $bonus, $values, true ) ) {
                $out[] = sprintf( '%s (+%d)', (string) ( $c['name'] ?? '' ), $bonus );
            }
        }

        return $out;
    }

    /**
     * Número positivo o el valor por defecto.
     *
     * @param   mixed $value   Valor.
     * @param   float $default Por defecto.
     * @return 	float
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function positive( $value, $default ): float {
        return is_numeric( $value ) && (float) $value > 0 ? (float) $value : (float) $default;
    }
}
