<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Empareja los deportistas de una clasificación oficial con los socios, por nombre
 * (SPEC-RANKING-INTERNO §14.4).
 *
 * La clasificación de la federación no trae licencia ni DNI: solo nombre, club y
 * categoría. Se compara sin tildes, sin mayúsculas y sin importar el orden, y se
 * contrasta con el sexo y el grupo de edad que da la categoría («V1M»).
 *
 * - Seguro: un único socio con los apellidos y el nombre de pila en el nombre del
 *   PDF, del mismo sexo y sin contradicción de categoría. Si el deportista no corre
 *   en un equipo del club, solo es seguro con el nombre completo idéntico.
 * - Duda: hay algún parecido, pero no basta. Lo decide el gestor.
 * - Ninguno: no se parece a ningún socio.
 *
 * Sin WordPress, para poder probarlo.
 */
class NameMatcher {

    public const SURE = 'seguro';
    public const DOUBT = 'duda';
    public const NONE = 'ninguno';

    /** Palabras de enlace que no cuentan como nombre ni apellido. */
    private const PARTICLES = [ 'de', 'del', 'al', 'la', 'las', 'los', 'y', 'i', 'e', 'da', 'das', 'do', 'dos', 'di', 'van', 'von', 'el' ];

    /** Niveles de parecido, de más a menos. */
    private const LEVEL_FULL = 4;
    private const LEVEL_STRONG = 3;
    private const LEVEL_WEAK = 2;
    private const LEVEL_LOOSE = 1;

    /** @var array<int, array{first: string[], last: string[], all: string[], sex: string, code: string, group_label: string}> */
    private array $members = [];

    /** @var string[] */
    private array $club_keywords = [];

    /**
     * @param   array    $members       user_id => [ first, last, sex (m|f), code (código de federación del grupo, '' si no hay), group_label ].
     * @param   string[] $club_keywords Textos que identifican un equipo del club en la columna «Club» (p. ej. SULAYR).
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( array $members, array $club_keywords ) {
        foreach ( $members as $id => $m ) {
            $first = self::tokens( (string) ( $m['first'] ?? '' ) );
            $last = self::tokens( (string) ( $m['last'] ?? '' ) );
            if ( empty( $first ) && empty( $last ) ) {
                continue;
            }
            $this->members[ (int) $id ] = [
                'first' => $first,
                'last' => $last,
                'all' => array_merge( $first, $last ),
                'sex' => (string) ( $m['sex'] ?? '' ),
                'code' => strtoupper( (string) ( $m['code'] ?? '' ) ),
                'group_label' => (string) ( $m['group_label'] ?? '' ),
            ];
        }
        foreach ( $club_keywords as $keyword ) {
            $keyword = self::normalize( (string) $keyword );
            if ( '' !== $keyword ) {
                $this->club_keywords[] = $keyword;
            }
        }
    }

    /**
     * Texto en minúsculas, sin tildes y con solo letras, números y espacios.
     *
     * @param   string $text Texto.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function normalize( string $text ): string {
        $text = strtr( $text, [
            'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ã' => 'a', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Õ' => 'o', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'n', 'ñ' => 'n', 'Ç' => 'c', 'ç' => 'c', '·' => '',
        ] );
        $text = strtolower( $text );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );

        return trim( (string) $text );
    }

    /**
     * Palabras significativas de un nombre, sin palabras de enlace.
     *
     * @param   string $text Nombre.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function tokens( string $text ): array {
        $normalized = self::normalize( $text );
        if ( '' === $normalized ) {
            return [];
        }

        return array_values( array_filter( explode( ' ', $normalized ), function ( $word ) {
            return '' !== $word && false === in_array( $word, self::PARTICLES, true );
        } ) );
    }

    /**
     * True si la columna «Club» es de un equipo del club.
     *
     * @param   string $club Club o equipo.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function is_club_team( string $club ): bool {
        $club = ' ' . self::normalize( $club ) . ' ';
        foreach ( $this->club_keywords as $keyword ) {
            if ( false !== strpos( $club, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Empareja un deportista.
     *
     * @param   string $name Nombre tal como sale en el PDF.
     * @param   string $club Club o equipo.
     * @param   string $sex  m|f ('' si no se sabe).
     * @param   string $cat  Código de categoría de la carrera (p. ej. V1M), o ''.
     * @return 	array{status: string, club_team: bool, candidates: array<int, array{user_id: int, reason: string}>}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function match( string $name, string $club, string $sex, string $cat ): array {
        $club_team = $this->is_club_team( $club );
        $tokens = self::tokens( $name );
        $cat = strtoupper( trim( $cat ) );
        $code = strlen( $cat ) >= 3 ? substr( $cat, 0, 2 ) : '';

        $found = [];
        foreach ( $this->members as $id => $m ) {
            if ( '' !== $sex && '' !== $m['sex'] && $sex !== $m['sex'] ) {
                continue;
            }
            $level = self::level( $tokens, $m );
            if ( 0 === $level ) {
                continue;
            }
            // Un parecido flojo solo interesa en los equipos del club.
            if ( $level < self::LEVEL_STRONG && false === $club_team ) {
                continue;
            }

            $reasons = [ self::level_text( $level ) ];
            $mismatch = '' !== $code && '' !== $m['code'] && false === Keys::is_absolute_category( $cat ) && $code !== $m['code'];
            if ( $mismatch ) {
                $reasons[] = sprintf( 'la carrera lo clasifica como %1$s y la web como %2$s', $cat, '' !== $m['group_label'] ? $m['group_label'] : $m['code'] );
            }
            if ( false === $club_team ) {
                $reasons[] = 'corre en un equipo de otro club';
            }
            $found[ $id ] = [ 'level' => $level, 'mismatch' => $mismatch, 'reason' => implode( '; ', $reasons ) ];
        }

        if ( empty( $found ) ) {
            return [ 'status' => self::NONE, 'club_team' => $club_team, 'candidates' => [] ];
        }

        uasort( $found, function ( $a, $b ) {
            return $b['level'] <=> $a['level'];
        } );
        $candidates = [];
        foreach ( $found as $id => $f ) {
            $candidates[] = [ 'user_id' => (int) $id, 'reason' => $f['reason'] ];
        }

        $best = reset( $found );
        $top = array_filter( $found, function ( $f ) use ( $best ) {
            return $f['level'] === $best['level'];
        } );
        $sure = 1 === count( $top )
            && 1 === count( array_filter( $found, function ( $f ) {
                return $f['level'] >= self::LEVEL_STRONG;
            } ) )
            && false === $best['mismatch']
            && ( self::LEVEL_FULL === $best['level'] || ( self::LEVEL_STRONG === $best['level'] && $club_team ) );

        return [ 'status' => $sure ? self::SURE : self::DOUBT, 'club_team' => $club_team, 'candidates' => $candidates ];
    }

    /**
     * Empareja una lista y deja en duda a un socio que salga seguro en más de un deportista.
     *
     * @param   array $athletes índice => [ name, club, sex, cat ].
     * @return 	array<int|string, array{status: string, club_team: bool, candidates: array}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function match_all( array $athletes ): array {
        $out = [];
        $sure_by_member = [];
        foreach ( $athletes as $index => $a ) {
            $out[ $index ] = $this->match( (string) ( $a['name'] ?? '' ), (string) ( $a['club'] ?? '' ), (string) ( $a['sex'] ?? '' ), (string) ( $a['cat'] ?? '' ) );
            if ( self::SURE === $out[ $index ]['status'] ) {
                $sure_by_member[ $out[ $index ]['candidates'][0]['user_id'] ][] = $index;
            }
        }
        foreach ( $sure_by_member as $indexes ) {
            if ( count( $indexes ) < 2 ) {
                continue;
            }
            foreach ( $indexes as $index ) {
                $out[ $index ]['status'] = self::DOUBT;
                $out[ $index ]['candidates'][0]['reason'] .= '; el mismo socio sale en otra fila';
            }
        }

        return $out;
    }

    /**
     * Nivel de parecido entre las palabras del PDF y un socio (0 = ninguno).
     *
     * @param   string[] $tokens Palabras del nombre del PDF.
     * @param   array    $m      Socio.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function level( array $tokens, array $m ): int {
        if ( empty( $tokens ) ) {
            return 0;
        }

        $all = $m['all'];
        $sorted_pdf = $tokens;
        $sorted_member = $all;
        sort( $sorted_pdf );
        sort( $sorted_member );
        if ( $sorted_pdf === $sorted_member ) {
            return self::LEVEL_FULL;
        }

        // Sin apellidos guardados, todo el nombre hace de apellidos.
        $last = ! empty( $m['last'] ) ? $m['last'] : $all;
        $first = ! empty( $m['last'] ) ? $m['first'] : [];

        $last_exact = 0;
        $last_close = 0;
        foreach ( $last as $word ) {
            $hit = self::find( $word, $tokens );
            if ( 2 === $hit ) {
                $last_exact++;
            } elseif ( 1 === $hit ) {
                $last_close++;
            }
        }
        $first_hit = 0;
        foreach ( $first as $word ) {
            $first_hit = max( $first_hit, self::find( $word, $tokens ) );
        }

        $all_last = $last_exact + $last_close === count( $last );
        if ( $all_last && 0 === $last_close && 2 === $first_hit ) {
            return self::LEVEL_STRONG;
        }
        if ( $all_last && ( $first_hit > 0 || ( empty( $first ) && $last_exact > 0 ) || count( $last ) >= 2 ) ) {
            return self::LEVEL_WEAK;
        }
        if ( $last_exact + $last_close > 0 && $first_hit > 0 ) {
            return self::LEVEL_LOOSE;
        }

        return 0;
    }

    /**
     * 2 si la palabra está tal cual, 1 si hay una parecida (una letra de diferencia, palabras de 5 o más), 0 si no.
     *
     * @param   string   $word   Palabra.
     * @param   string[] $tokens Palabras donde buscar.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function find( string $word, array $tokens ): int {
        if ( in_array( $word, $tokens, true ) ) {
            return 2;
        }
        if ( strlen( $word ) < 5 ) {
            return 0;
        }
        foreach ( $tokens as $token ) {
            if ( strlen( $token ) >= 5 && levenshtein( $word, $token ) <= 1 ) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Motivo legible de un nivel.
     *
     * @param   int $level Nivel.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function level_text( int $level ): string {
        switch ( $level ) {
            case self::LEVEL_FULL:
                return 'Nombre idéntico';
            case self::LEVEL_STRONG:
                return 'Coinciden los apellidos y el nombre';
            case self::LEVEL_WEAK:
                return 'Coinciden los apellidos, pero no del todo el nombre';
            default:
                return 'Coincide solo una parte del nombre';
        }
    }
}
