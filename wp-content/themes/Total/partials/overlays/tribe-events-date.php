<?php

/**
 * Overlay: Tribe Events Date.
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

// Required functions
if ( ! function_exists( 'tribe_get_start_date' )
	|| ! function_exists( 'tribe_get_end_date' )
	|| ! is_callable( 'Tribe__Events__Timezones::get_event_timezone_string' )
	|| ! is_callable( 'Tribe__Events__Timezones::is_mode' )
) {
	return;
}

// Get overlay position
$position = $args['position'] ?? $position;

// Only used for inside position
if ( 'inside_link' !== $position ) {
	return;
}

// Get event ID
$post_id = get_the_ID();

if ( 'tribe_events' !== get_post_type( $post_id ) ) {
	$post_id = get_queried_object_id(); // likely a dynamic template or single image element
}

// Get event data
$start_date = tribe_get_start_date( $post_id, false );
$end_date   = tribe_get_end_date( $post_id, false );

// Display a dummy date in builder-mode
if ( ! $start_date && in_array( get_post_type( get_queried_object() ), [ 'wpex_card', 'elementor_library', 'wpex_templates' ], true ) ) {
	$start_date = get_current_date_time();
}

// Start date required
if ( ! $start_date ) {
	return;
}

// Get time zone
if ( Tribe__Events__Timezones::is_mode( 'site' ) ) {
    $timezone = get_option( 'timezone_string', 'UTC' );
} else {
    $timezone = Tribe__Events__Timezones::get_event_timezone_string( $post_id );
}

if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
    $timezone = 'UTC';
}

// Format dates
$start_date = new DateTime( $start_date, new DateTimeZone( $timezone ) );
$start_date = $start_date->format( 'U' );

if ( $end_date ) {
	$end_date = new DateTime( $end_date, new DateTimeZone( $timezone ) );
	$end_date = $end_date->format( 'U' );
}

// Wrap class
$wrap_class = 'overlay-tribe-events-date theme-overlay wpex-absolute wpex-m-10 wpex-surface-1 wpex-text-1 wpex-m-5 wpex-nowrap wpex-leading-none wpex-uppercase wpex-tracking-tight';

if ( ! empty( $args['color_scheme'] ) ) {
	$wrap_class .= ' ' . $args['color_scheme'];
}

$align = $args['align'] ?? 'top_right';

switch( $align ) {
	case 'top_left':
		$wrap_class .= ' wpex-top-0 wpex-left-0';
		break;
	case 'top_right':
		$wrap_class .= ' wpex-top-0 wpex-right-0';
		break;
	case 'bottom_left':
		$wrap_class .= ' wpex-bottom-0 wpex-left-0';
		break;
	case 'bottom_right':
		$wrap_class .= ' wpex-bottom-0 wpex-right-0';
		break;
}

?>

<div class="<?php echo \esc_attr( $wrap_class ); ?>" style="min-width:60px;">
	<div class="overlay-tribe-events-date__inner wpex-flex wpex-flex-col wpex-items-center wpex-gap-5 wpex-m-5">
		<div class="overlay-tribe-events-date__month wpex-text-xs"><?php
			echo esc_html( wp_date( 'M', $start_date ) );
		?></div>
		<div class="overlay-tribe-events-date__day wpex-text-2xl"><?php
			echo esc_html( wp_date( 'j', $start_date ) );
		?></div>
		<?php if ( $end_date && $start_date !== $end_date && $end_date_formatted = wp_date( 'M j', $end_date ) ) { ?>
			<div class="overlay-tribe-events-date__end wpex-text-xs wpex-border-t wpex-border-solid wpex-border-surface-4 wpex-pt-5"><?php
				printf(
					esc_html_x( 'To %s', 'Adverb: event date overlay end date prefix', 'total' ),
					esc_html( $end_date_formatted )
				);
			?></div>
		<?php } ?>
	</div>
</div>
