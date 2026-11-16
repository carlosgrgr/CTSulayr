<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Front;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode [sulayr_ranking] (SPEC-RANKING-INTERNO §8.2).
 *
 * Pinta lo mismo que la pestaña de Mi cuenta, con las mismas reglas de
 * acceso, en cualquier página. No admite atributos.
 */
class RankingShortcode {

    public const SHORTCODE = 'sulayr_ranking';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_shortcode( self::SHORTCODE, [ $this, 'render' ] );
    }

    /**
     * HTML del shortcode.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): string {
        $url = get_permalink();

        return RankingView::render( false !== $url ? (string) $url : home_url( '/' ) );
    }
}
