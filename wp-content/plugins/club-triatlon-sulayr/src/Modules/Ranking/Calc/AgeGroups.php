<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Grupos de edad del ranking (SPEC-RANKING-INTERNO §3.2, R3).
 *
 * La edad es la que se cumple el 31 de diciembre del año de la temporada:
 * temporada − año de nacimiento. Un socio no cambia de grupo dentro de una
 * temporada. El primer grupo cuyo tramo contiene la edad es el bueno.
 */
class AgeGroups {

    /** Categorías de la federación que dio el club el 16 de noviembre de 2026. */
    public const DEFAULTS = [
        [ 'key' => 'prebenjamin', 'label' => 'Prebenjamín', 'min' => null, 'max' => 7, 'code' => '' ],
        [ 'key' => 'benjamin', 'label' => 'Benjamín', 'min' => 8, 'max' => 9, 'code' => '' ],
        [ 'key' => 'alevin', 'label' => 'Alevín', 'min' => 10, 'max' => 11, 'code' => '' ],
        [ 'key' => 'infantil', 'label' => 'Infantil', 'min' => 12, 'max' => 13, 'code' => '' ],
        [ 'key' => 'cadete', 'label' => 'Cadete', 'min' => 14, 'max' => 15, 'code' => 'CD' ],
        [ 'key' => 'juvenil', 'label' => 'Juvenil', 'min' => 16, 'max' => 17, 'code' => 'JV' ],
        [ 'key' => 'junior', 'label' => 'Júnior', 'min' => 18, 'max' => 19, 'code' => 'JN' ],
        [ 'key' => 'sub23', 'label' => 'Sub-23', 'min' => 20, 'max' => 23, 'code' => '23' ],
        [ 'key' => 'senior', 'label' => 'Sénior', 'min' => 24, 'max' => 39, 'code' => 'SN' ],
        [ 'key' => 'veterano-1', 'label' => 'Veterano 1', 'min' => 40, 'max' => 49, 'code' => 'V1' ],
        [ 'key' => 'veterano-2', 'label' => 'Veterano 2', 'min' => 50, 'max' => 59, 'code' => 'V2' ],
        [ 'key' => 'veterano-3', 'label' => 'Veterano 3', 'min' => 60, 'max' => 69, 'code' => 'V3' ],
        [ 'key' => 'veterano-4', 'label' => 'Veterano 4', 'min' => 70, 'max' => 79, 'code' => 'V4' ],
        [ 'key' => 'veterano-5', 'label' => 'Veterano 5', 'min' => 80, 'max' => null, 'code' => '' ],
    ];

    /** @var array<int, array{key: string, label: string, min: int|null, max: int|null, code: string}> */
    private array $groups = [];

    /**
     * __construct()
     *
     * @param   array $groups Filas con key, label, min, max y code. Las que no tienen clave se ignoran.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( array $groups ) {
        foreach ( $groups as $row ) {
            $key = strtolower( trim( (string) ( $row['key'] ?? '' ) ) );
            if ( '' === $key || 1 !== preg_match( '/^[a-z0-9-]{1,32}$/', $key ) ) {
                continue;
            }
            $this->groups[] = [
                'key' => $key,
                'label' => trim( (string) ( $row['label'] ?? '' ) ) ?: $key,
                'min' => self::int_or_null( $row['min'] ?? null ),
                'max' => self::int_or_null( $row['max'] ?? null ),
                'code' => strtoupper( trim( (string) ( $row['code'] ?? '' ) ) ),
            ];
        }
    }

    /**
     * Edad a 31 de diciembre de la temporada, o null si la fecha no vale.
     *
     * @param   string $birth  Y-m-d.
     * @param   int    $season Año.
     * @return 	int|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function age( string $birth, int $season ): ?int {
        if ( 1 !== preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $birth, $m ) ) {
            return null;
        }
        $age = $season - (int) $m[1];

        return $age >= 0 ? $age : null;
    }

    /**
     * Clave del grupo de una fecha de nacimiento en una temporada, o ''.
     *
     * @param   string $birth  Y-m-d.
     * @param   int    $season Año.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function for_birth( string $birth, int $season ): string {
        $age = self::age( $birth, $season );
        if ( null === $age ) {
            return '';
        }

        foreach ( $this->groups as $group ) {
            if ( ( null === $group['min'] || $age >= $group['min'] ) && ( null === $group['max'] || $age <= $group['max'] ) ) {
                return $group['key'];
            }
        }

        return '';
    }

    /**
     * Nombre de un grupo, o la clave si no existe.
     *
     * @param   string $key Clave.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function label( string $key ): string {
        foreach ( $this->groups as $group ) {
            if ( $group['key'] === $key ) {
                return $group['label'];
            }
        }

        return $key;
    }

    /**
     * Código de la federación de un grupo ('' si no tiene).
     *
     * @param   string $key Clave.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function code( string $key ): string {
        foreach ( $this->groups as $group ) {
            if ( $group['key'] === $key ) {
                return $group['code'];
            }
        }

        return '';
    }

    /**
     * True si un código de categoría de la carrera (p. ej. V1M) no encaja con
     * el grupo calculado (R28). La categoría absoluta y los grupos sin código
     * no se comprueban.
     *
     * @param   string $race_category Código de la carrera.
     * @param   string $key           Grupo calculado.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function mismatch( string $race_category, string $key ): bool {
        $race_category = strtoupper( trim( $race_category ) );
        if ( '' === $race_category || '' === $key || Keys::is_absolute_category( $race_category ) ) {
            return false;
        }

        $code = $this->code( $key );

        return '' !== $code && substr( $race_category, 0, 2 ) !== $code;
    }

    /**
     * Claves y nombres, en orden.
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function options(): array {
        $options = [];
        foreach ( $this->groups as $group ) {
            $options[ $group['key'] ] = $group['label'];
        }

        return $options;
    }

    /**
     * Filas normalizadas.
     *
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function rows(): array {
        return $this->groups;
    }

    /**
     * Errores de una lista de grupos: claves repetidas o vacías y tramos que se solapan.
     *
     * @param   array $groups Filas.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function problems( array $groups ): array {
        $problems = [];
        $seen = [];
        $ranges = [];
        foreach ( $groups as $row ) {
            $key = strtolower( trim( (string) ( $row['key'] ?? '' ) ) );
            if ( 1 !== preg_match( '/^[a-z0-9-]{1,32}$/', $key ) ) {
                $problems[] = sprintf( 'Clave no válida: «%s». Usa minúsculas, números y guiones.', $key );
                continue;
            }
            if ( isset( $seen[ $key ] ) ) {
                $problems[] = sprintf( 'Clave repetida: «%s».', $key );
            }
            $seen[ $key ] = true;
            $min = self::int_or_null( $row['min'] ?? null );
            $max = self::int_or_null( $row['max'] ?? null );
            if ( null !== $min && null !== $max && $min > $max ) {
                $problems[] = sprintf( 'En «%s» la edad mínima es mayor que la máxima.', $key );
                continue;
            }
            $ranges[] = [ $key, null === $min ? PHP_INT_MIN : $min, null === $max ? PHP_INT_MAX : $max ];
        }

        $count = count( $ranges );
        for ( $i = 0; $i < $count; $i++ ) {
            for ( $j = $i + 1; $j < $count; $j++ ) {
                if ( $ranges[ $i ][1] <= $ranges[ $j ][2] && $ranges[ $j ][1] <= $ranges[ $i ][2] ) {
                    $problems[] = sprintf( 'Las edades de «%s» y «%s» se solapan.', $ranges[ $i ][0], $ranges[ $j ][0] );
                }
            }
        }

        return $problems;
    }

    /**
     * Entero o null para un valor vacío.
     *
     * @param   mixed $value Valor.
     * @return 	int|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function int_or_null( $value ): ?int {
        if ( null === $value || '' === $value || false === is_numeric( $value ) ) {
            return null;
        }

        return (int) $value;
    }
}
