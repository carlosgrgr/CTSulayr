<?php

namespace TotalThemeCore\Vcex;

defined( 'ABSPATH' ) || exit;

/**
 * Class used for query filtering based on URL params.
 * 
 * WIP - DO NOT USE YET!
 */
class Url_Query_Filter_Manager {

	/**
	 * The singleton instance.
	 */
	private static $instance;

	/**
	 * Query parameters for all elements.
	 */
	private $queries = [];

	/**
	 * Registered filter configurations.
	 */
	private $filters = [];

	/**
	 * Current element ID in context.
	 */
	private $current_filter = '';

	/**
	 * Private constructor.
	 */
	private function __construct() {
		add_action( 'wp_footer', [ $this, '_on_wp_footer' ] );
	}

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register new parameters for given filter.
	 */
	public function register_params( string $filter_id, array $params ): void {
		$this->current_filter = $filter_id;

		if ( isset( $this->filters[ $filter_id ] ) ) {
			$this->filters[ $filter_id ] = array_merge( $this->filters[ $filter_id ], $params );
		} else {
			$this->filters[ $filter_id ] = $params;
		}

		foreach ( $params as $name => $args ) {
			if ( $param_value = $this->get_param_value( $name ) ) {
				$this->add_param_to_query( $filter_id, $param_value, $args );
			}
		}
	}

	/**
	 * Register a single parameter
	 */
	public function register_param( string $filter_id, string $name, $args = '' ): void {
		$param = [ $name => $args ];
		if ( isset( $this->filters[ $filter_id ] ) ) {
			$this->filters[ $filter_id ] = array_merge( $this->filters[ $filter_id ], $param );
			if ( $param_value = $this->get_param_value( $name ) ) {
				$this->add_param_to_query( $filter_id, $param_value, $args );
			}
		} else {
			$this->register_params( $filter_id, [ $param ] );
		}
	}

	/**
	 * Internal function adds a new param to a filter query.
	 */
	private function add_param_to_query( $filter_id, $value, $args ): void {
		if ( ! isset( $this->queries[ $filter_id ] ) ) {
			$this->queries[ $filter_id ] = [];
		}
		$param_type = $args['type'] ?? $args;
		$this->queries[ $filter_id ][ $param_type ] = $value;
	}

	/**
	 * Internal function used to get the value of a param.
	 */
	private function get_param_value( $param ) {
		if ( isset( $_GET[ $param ] ) ) {
			return sanitize_text_field( urldecode( $_GET[ $param ] ) );
		}
	}

	/**
	 * Returns the registered params for the current filter.
	 */
	public function get_params(): array {
		return $this->filters[ $this->current_filter ] ?? [];
	}

	/**
	 * Returns query parameters for the current filter.
	 *
	 * This is primarily used for the Post Cards element and it will
	 * only work if the filter has been registered ahead of time.
	 */
	public function get_query( string $filter_id = '' ): array {
		$filter_id = $filter_id ? $this->sanitize_filter_id( $filter_id ) : $this->current_filter;
		return $this->queries[ $filter_id ] ?? [];
	}

	/**
	 * Return the value for a specific filter type.
	 */
	public function get_value( string $type ) {
		$query_params = $this->get_query();
		return $query_params[ $type ] ?? '';
	}

	/**
	 * Check if a given filter value is active based on the current URL query.
	 */
	public function is_selected( string $type = '', string $value = '' ): bool {
		if ( ! $type || ! $value || empty( $this->current_filter ) ) {
			return false;
		}

		$value_to_check = (string) $value;
		$param_val      = $this->get_value( $type );

		if ( is_scalar( $param_val ) ) {
			if ( $param_val === $value_to_check ) {
				return true;
			}
			if ( is_string( $param_val ) ) {
				$param_val = explode( ',', $param_val );
			}
		}

		if ( is_array( $param_val ) ) {
			return in_array( $value_to_check, $param_val, true );
		}

		return false;
	}

	/**
	 * Hooks into the wp_footer to add js object with the list of filters for use with the ajax script.
	 */
	public function _on_wp_footer(): void {
		if ( $this->filters ) {
			wp_localize_script( 'vcex-ajax', 'vcex_ajax_url_query_filters', $this->filters );
		}
	}

	/**
	 * Returns all filters.
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	/**
	 * Returns all queries.
	 */
	public function get_queries(): array {
		return $this->queries;
	}

}
