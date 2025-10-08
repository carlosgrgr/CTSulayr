<?php

namespace TotalTheme\Customizer\Controls;

use WP_Customize_Control;

defined( 'ABSPATH' ) || exit;

/**
 * Customizer Background Image Position Control.
 */
class Background_Image_Position extends WP_Customize_Control {

	/**
	 * The control type.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'totaltheme_background_image_position';

	/**
	 * Send data to content_template.
	 */
	public function to_json() {
		parent::to_json();

		$this->json['value'] = $this->value();
		$this->json['id']    = $this->id;
	}

	/**
	 * Don't render the control content from PHP, as it's rendered via JS on load.
	 */
	public function render_content() {}

	/**
	 * Render the content
	 */
	public function content_template() {
		?>
		<# if ( data.label ) { #>
			<label for="_customize-input-{{ data.id }}" class="customize-control-title">{{ data.label }}</label>
		<# } #>
		<span id="_customize-description-{{ data.id }}" class="description customize-control-description"><?php esc_html_e( 'Enter a custom position for your background image using values like top, bottom, left, right, or specific measurements such as 50%, 10px, etc. You can also use two values for more control over the X and Y axis (e.g., 50% 30px)', 'total' ); ?></span>
		<input
			type="text"
			id="_customize-input-{{ data.id }}" data-customize-setting-link="{{ data.id }}"
			list="bg-positions-datalist-{{ data.id }}" 
			aria-describedby="_customize-description-{{ data.id }}"
		>
		<datalist id="bg-positions-datalist-{{ data.id }}">
			<option value="top">
			<option value="center">
			<option value="bottom">
			<option value="left">
			<option value="right">
			<option value="top left">
			<option value="top center">
			<option value="top right">
			<option value="center left">
			<option value="center center">
			<option value="center right">
			<option value="bottom left">
			<option value="bottom center">
			<option value="bottom right">
		</datalist>
		<?php
	}
}
