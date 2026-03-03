<?php
/**
 * The template for displaying all search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package ct-sulayr-theme
 */

use CTSulayrTheme\Infrastructure\CustomActions;
use CTSulayrTheme\Infrastructure\CustomFields;
use CTSulayrTheme\Services\SearchService;

get_header();

do_action( CustomActions::CT_SULAYR_THEME_SEARCH_BEFORE_TITLE );

global $wp_query, $wpdb;

$posts = $wp_query->posts;
$current_post_type = $_GET['post_type'] ?? null;
$search_term = get_search_query();
$base_url = get_search_link( $search_term );

$post_type_labels = SearchService::get_searchable_post_types_labels();

$count_results = [];
$total_count = 0;

foreach ( $post_type_labels as $post_type => $label ) {
    $args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        's'              => $search_term,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ];

    $query = new WP_Query( $args );
    $count = $query->post_count;

    $count_results[ $post_type ] = (object)[ 'post_type' => $post_type, 'count' => $count ];
    $total_count += $count;
}

?>

<!--  Search section start -->
<section class="c-search" style="padding-top: 2rem">
    <div class="container px-3 px-md-4 px-xl-8 d-flex flex-column align-items-center">
        <?php get_template_part( 'templates/parts/content', 'search_form' ); ?>
    </div>
</section>
<!--  Search section end -->

<!-- Search results section -->
<section class="c-search-results">
    <div class="container">
        <div class="row">
            <div class="col-12 px-xl-8 px-4">
                <h3 class="u-title-2"><?php _e( 'Search results', CT_SULAYR_THEME_TEXTDOMAIN ); ?></h3>
                <?php if ( $total_count > 0 ) : ?>
                    <div class="filter-wrapper">
                        <div class="row">
                            <div class="col-12 ps-0">
                                <!-- Versión mobile -->
                                <div class="post-type-dropdown d-md-none dropdown">
                                    <button class="btn btn-light dropdown-toggle" type="button" id="dropdownPostTypes" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?php if ( isset( $current_post_type ) && isset( $post_type_labels[$current_post_type] ) ) :
                                            echo esc_html( $post_type_labels[$current_post_type] ) . ' (' . intval( $count_results[$current_post_type]->count ?? 0 ) . ')';
                                        else :
                                            echo __( 'All', CT_SULAYR_THEME_TEXTDOMAIN ) . ' (' . $total_count . ')';
                                        endif; ?>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M13.8606 5.90983L8.54062 11.2198C8.39999 11.3603 8.20937 11.4392 8.01062 11.4392C7.81187 11.4392 7.62125 11.3603 7.48062 11.2198L2.14062 5.87983L3.20062 4.81982L8.01062 9.62983L12.8006 4.84982L13.8606 5.90983Z" fill="#0B0C0C"/>
                                        </svg>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownPostTypes">
                                        <li>
                                            <a class="dropdown-item <?php echo $current_post_type === null ? 'active' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>">
                                                <?php _e( 'All', CT_SULAYR_THEME_TEXTDOMAIN ); ?> (<?php echo $total_count; ?>)
                                                <?php if ( $current_post_type === null ) : ?>
                                                    <i class="bi bi-check2"></i>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                        <?php foreach ( $post_type_labels as $post_type => $label ) :
                                            $count = intval($count_results[$post_type]->count ?? 0);
                                            if ( $count === 0 ) continue;
                                            $url = add_query_arg( 'post_type', $post_type, $base_url ); ?>
                                            <li>
                                                <a class="dropdown-item <?php echo $current_post_type === $post_type ? 'active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
                                                    <?php echo esc_html($label) . ' (' . $count . ')'; ?>
                                                    <?php if ( $current_post_type === $post_type ) : ?>
                                                        <i class="bi bi-check2"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    </div>
                                <!-- Versión desktop -->
                                <ul class="post-type-tabs d-none d-md-inline-flex">
                                    <li class="<?php echo $current_post_type === null ? 'active' : ''; ?>">
                                        <a href="<?php echo esc_url( $base_url ); ?>"><?php echo __( 'All', CT_SULAYR_THEME_TEXTDOMAIN ) . ' (' . $total_count . ')'; ?></a>
                                    </li>
                                    <?php foreach ( $post_type_labels as $post_type => $label ) :
                                        $count = isset( $count_results[$post_type] ) ? intval( $count_results[$post_type]->count ) : 0;
                                        if ( $count === 0 ) continue;
                                        $url = add_query_arg( 'post_type', $post_type, $base_url );
                                        $active = $current_post_type === $post_type ? 'active' : ''; ?>
                                        <li class="<?php echo $active; ?>">
                                            <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ) . ' (' . $count . ')'; ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row mt-3">
                    <div class="col-12 ps-0">
                        <div id="search-results-wrapper" class="row">
                            <?php if ( !empty( $posts ) ) : ?> 
                                <?php foreach ( $posts as $post ) :
                                    get_template_part( 'templates/parts/content', 'search_result', ['post' => $post] );
                                endforeach;
                            else :
                                $no_results_group = get_field( CustomFields::SEARCH_SETTINGS_NO_RESULTS, 'option' );
                                $image            = isset( $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_IMAGE] ) ? $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_IMAGE] : [];
                                $main_text        = isset( $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_MAIN_TEXT] ) ? $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_MAIN_TEXT] : '';
                                $secundary_text   = isset( $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_SECUNDARY_TEXT] ) ? $no_results_group[CustomFields::SEARCH_SETTINGS_NO_RESULTS_SECUNDARY_TEXT] : ''; ?>
                                <div class="text-center">
                                    <?php if ( !empty( $no_results_group ) ) :
                                        if ( !empty( $image ) ) : ?>
                                            <div class="no-results-image mb-4">
                                                <img src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>">
                                            </div>
                                        <?php endif; ?>
                                        <div class="no-results-text">
                                            <?php if ( !empty( $main_text ) ) : ?><h2 class="intro"><?php echo $main_text; ?></h2><?php endif; ?>
                                            <?php if ( !empty( $secundary_text ) ) : ?><p class="help-text"><?php echo $secundary_text; ?></p><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ( $wp_query->max_num_pages > 1 ) : ?>
                    <div class="mt-5 text-center">
                        <button id="buttonMore" class="c-primary-btn"><?php _e( 'Show more', CT_SULAYR_THEME_TEXTDOMAIN ); ?></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php 
get_footer();
