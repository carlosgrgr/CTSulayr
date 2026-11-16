<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Claves cerradas del ranking interno (SPEC-RANKING-INTERNO §2.3).
 *
 * Solo claves: los nombres visibles están en Ranking\Labels. Sin WordPress,
 * para poder usarlas en los tests.
 */
class Keys {

    public const MODALITIES = [ 'triatlon', 'triatlon_cros', 'duatlon', 'duatlon_cros', 'acuatlon', 'acuabike' ];
    public const DISTANCES = [ 'supersprint', 'sprint', 'estandar', 'media', 'larga' ];

    public const FORMAT_INDIVIDUAL = 'individual';
    public const FORMAT_RELAY = 'relevos';
    public const FORMAT_TEAM = 'equipos';
    public const FORMATS = [ self::FORMAT_INDIVIDUAL, self::FORMAT_RELAY, self::FORMAT_TEAM ];

    public const STATUS_FINISHED = 'finalizado';
    public const STATUSES = [ self::STATUS_FINISHED, 'dnf', 'dsq', 'dns', 'nc' ];

    public const COMPETITION_DRAFT = 'borrador';
    public const COMPETITION_PUBLISHED = 'publicada';

    public const SEX_MALE = 'm';
    public const SEX_FEMALE = 'f';
    public const SEXES = [ self::SEX_MALE, self::SEX_FEMALE ];

    /** Clasificaciones de relevos y equipos: open masculina, open femenina y mixta. */
    public const TEAM_CATEGORIES = [ 'm', 'f', 'mixto' ];

    public const SCOPE_ABSOLUTE = 'absoluta';
    public const SCOPE_GROUP = 'grupo';

    public const METHOD_TIME = 'tiempo';
    public const METHOD_POSITION = 'posicion';
    public const METHOD_ABSOLUTE_CATEGORY = 'absoluta';
    public const METHOD_STATUS = 'estado';
    public const METHOD_NONE = 'ninguno';

    /** Prefijo del código de la categoría absoluta de la federación (ABM / ABF). */
    public const ABSOLUTE_CATEGORY_CODE = 'AB';

    /**
     * True si el formato usa categoría de equipo.
     *
     * @param   string $format Formato.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_team( string $format ): bool {
        return self::FORMAT_RELAY === $format || self::FORMAT_TEAM === $format;
    }

    /**
     * True si un código de categoría de la carrera es la absoluta (R29).
     *
     * @param   string $code Código, p. ej. ABM.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_absolute_category( string $code ): bool {
        return 0 === strpos( strtoupper( $code ), self::ABSOLUTE_CATEGORY_CODE );
    }

    /**
     * Clave de una referencia: scope|categoría de equipo|sexo|grupo.
     *
     * @param   string $scope         SCOPE_*.
     * @param   string $team_category '' en individual.
     * @param   string $sex           '' en equipos.
     * @param   string $age_group     '' en la absoluta.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function ref_key( string $scope, string $team_category, string $sex, string $age_group ): string {
        return implode( '|', [ $scope, $team_category, $sex, $age_group ] );
    }
}
