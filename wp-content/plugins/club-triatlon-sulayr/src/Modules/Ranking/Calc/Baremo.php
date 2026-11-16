<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Baremo de una temporada (SPEC-RANKING-INTERNO §2.6 y §3.1).
 *
 * Array con base, bonus, best_n, coefficients[modalidad][distancia] y
 * age_groups. Cada temporada guarda su copia (RankingSettings::for_season()).
 */
class Baremo {

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
