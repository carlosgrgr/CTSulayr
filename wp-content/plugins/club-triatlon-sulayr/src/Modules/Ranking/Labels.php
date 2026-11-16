<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Nombres visibles de las claves del ranking (SPEC-RANKING-INTERNO §9).
 *
 * En la base de datos solo se guardan claves (Calc\Keys); los textos salen de aquí.
 */
class Labels {

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function modalities(): array {
        return [
            'triatlon' => __( 'Triatlón', SULAYR_TEXTDOMAIN ),
            'triatlon_cros' => __( 'Triatlón cros', SULAYR_TEXTDOMAIN ),
            'duatlon' => __( 'Duatlón', SULAYR_TEXTDOMAIN ),
            'duatlon_cros' => __( 'Duatlón cros', SULAYR_TEXTDOMAIN ),
            'acuatlon' => __( 'Acuatlón', SULAYR_TEXTDOMAIN ),
            'acuabike' => __( 'Acuabike', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function distances(): array {
        return [
            'supersprint' => _x( 'Supersprint', 'distancia', SULAYR_TEXTDOMAIN ),
            'sprint' => _x( 'Sprint', 'distancia', SULAYR_TEXTDOMAIN ),
            'estandar' => _x( 'Estándar / olímpico', 'distancia', SULAYR_TEXTDOMAIN ),
            'media' => _x( 'Media', 'distancia', SULAYR_TEXTDOMAIN ),
            'larga' => _x( 'Larga', 'distancia', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function formats(): array {
        return [
            Keys::FORMAT_INDIVIDUAL => __( 'Individual', SULAYR_TEXTDOMAIN ),
            Keys::FORMAT_RELAY => __( 'Relevos', SULAYR_TEXTDOMAIN ),
            Keys::FORMAT_TEAM => __( 'Contrarreloj por equipos', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function statuses(): array {
        return [
            Keys::STATUS_FINISHED => __( 'Finalizado', SULAYR_TEXTDOMAIN ),
            'dnf' => __( 'DNF (no finaliza)', SULAYR_TEXTDOMAIN ),
            'dsq' => __( 'DSQ (descalificado)', SULAYR_TEXTDOMAIN ),
            'dns' => __( 'DNS (no presentado)', SULAYR_TEXTDOMAIN ),
            'nc' => __( 'NC (corte)', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Estado corto para las tablas.
     *
     * @param   string $status Clave.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function status_short( string $status ): string {
        return Keys::STATUS_FINISHED === $status ? __( 'Finalizado', SULAYR_TEXTDOMAIN ) : strtoupper( $status );
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function team_categories(): array {
        return [
            'm' => __( 'Open masculina', SULAYR_TEXTDOMAIN ),
            'f' => __( 'Open femenina', SULAYR_TEXTDOMAIN ),
            'mixto' => __( 'Mixta', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sexes(): array {
        return [
            Keys::SEX_MALE => __( 'Masculino', SULAYR_TEXTDOMAIN ),
            Keys::SEX_FEMALE => __( 'Femenino', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function competition_statuses(): array {
        return [
            Keys::COMPETITION_DRAFT => __( 'Borrador', SULAYR_TEXTDOMAIN ),
            Keys::COMPETITION_PUBLISHED => __( 'Publicada', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function methods(): array {
        return [
            Keys::METHOD_TIME => __( 'por tiempo', SULAYR_TEXTDOMAIN ),
            Keys::METHOD_POSITION => __( 'por posición', SULAYR_TEXTDOMAIN ),
            Keys::METHOD_ABSOLUTE_CATEGORY => __( 'categoría absoluta', SULAYR_TEXTDOMAIN ),
            Keys::METHOD_STATUS => __( 'no terminó', SULAYR_TEXTDOMAIN ),
            Keys::METHOD_NONE => __( 'sin referencia', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function log_entities(): array {
        return [
            RankingLog::ENTITY_COMPETITION => __( 'Competición', SULAYR_TEXTDOMAIN ),
            RankingLog::ENTITY_REFERENCE => __( 'Referencia', SULAYR_TEXTDOMAIN ),
            RankingLog::ENTITY_RESULT => __( 'Resultado', SULAYR_TEXTDOMAIN ),
            RankingLog::ENTITY_SETTING => __( 'Ajuste', SULAYR_TEXTDOMAIN ),
            RankingLog::ENTITY_RECALC => __( 'Recálculo', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Nombre de una clave en una lista, o la propia clave.
     *
     * @param   array  $labels Lista.
     * @param   string $key    Clave.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function of( array $labels, string $key ): string {
        return $labels[ $key ] ?? $key;
    }

    /**
     * Puntos con dos decimales y separadores del idioma, o '—'.
     *
     * @param   float|string|null $points Puntos.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function points( $points ): string {
        return null === $points || '' === $points ? '—' : number_format_i18n( (float) $points, 2 );
    }

    /**
     * Coeficiente con dos decimales.
     *
     * @param   float|string $coef Coeficiente.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function coef( $coef ): string {
        return number_format_i18n( (float) $coef, 2 );
    }
}
