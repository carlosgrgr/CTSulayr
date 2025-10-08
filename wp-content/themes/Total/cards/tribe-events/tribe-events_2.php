<?php

defined( 'ABSPATH' ) || exit;

$bk = $this->get_breakpoint();
$overlay_style = $this->get_var( 'thumbnail_overlay_style' );

if ( $bk ) {
	$bk = "-{$bk}";
	$flex_class = 'wpex-flex wpex-flex-col wpex-flex-grow';
} else {
	$flex_class = 'wpex-flex';
}

// Set flex row class.
if ( $this->has_flex_direction_reverse() ) {
	$flex_row_class = "wpex{$bk}-flex-row-reverse";
} else {
	$flex_row_class = "wpex{$bk}-flex-row";
}

$html = '<div class="wpex-card-inner ' . $flex_class . ' ' . $flex_row_class . ' wpex-overflow-hidden wpex-gap-20 wpex' . $bk . '-gap-30">';

	$html .= $this->get_media( [
		'class' => "wpex{$bk}-w-40 wpex-flex-shrink-0 wpex-self-stretch",
		'link' => true,
	] );

	$html .= '<div class="wpex-card-details wpex-flex wpex-flex-col wpex-flex-grow">';

		$html .= $this->get_title( [
			'class' => 'wpex-heading wpex-child-inherit-color wpex-text-xl wpex-mb-5',
			'link' => true,
		] );

		if ( $overlay_style && str_starts_with( $overlay_style, 'tribe-events-date' ) ) {
			if ( function_exists( 'tribe_event_is_all_day' ) & tribe_event_is_all_day( $this->post_id ) ) {
				$html .= $this->get_element( [
					'class'    => 'wpex-card-date wpex-mb-20',
					'content'  => esc_html_x( 'All day', 'All day label for event', 'the-events-calendar' ),
				] );
			} elseif ( function_exists( 'tribe_get_start_time' ) && function_exists( 'tribe_get_end_time' ) ) {
				$start_time = tribe_get_start_time( $this->post_id );
				$end_time   = tribe_get_end_time( $this->post_id );
				if ( $start_time !== $end_time ) {
					$separator = function_exists( 'tec_events_get_time_range_separator' ) ? tec_events_get_time_range_separator() : '-';
					$formatted_time = "{$start_time} {$separator} {$end_time}";
				} else {
					$formatted_time = $start_time;
				}
				$html .= $this->get_element( [
					'class'    => 'wpex-card-time wpex-mb-20',
					'content'  => $formatted_time,
				] );
			}
		} else {
			$html .= $this->get_element( [
				'class'    => 'wpex-card-date wpex-mb-20',
				'content'  => tribe_events_event_schedule_details( $this->post_id, false, false ),
			] );
		}

		$html .= $this->get_excerpt( [
			'class' => 'wpex-mb-25',
		] );

		$html .= $this->get_more_link( [
			'link_class' => 'theme-button',
			'text' => esc_html__( 'View Event', 'total' ),
		] );

	$html .= '</div>';

$html .= '</div>';

return $html;
