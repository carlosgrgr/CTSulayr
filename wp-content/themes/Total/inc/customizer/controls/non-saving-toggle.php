<?php

namespace TotalTheme\Customizer\Controls;

use WP_Customize_Control;

defined( 'ABSPATH' ) || exit;

/**
 * Customizer Toggle Control.
 */
class Non_Saving_Toggle extends WP_Customize_Control {

	/**
	 * The control type.
	 */
	public $type = 'totaltheme_non_saving_toggle';

	/**
	 * Send data to content_template.
	 */
	public function to_json() {
		parent::to_json();
		$this->json['id'] = $this->id;
	}

	/**
	 * Don't render the control content from PHP, as it's rendered via JS on load.
	 */
	public function render_content() {}

	/**
	 * Render the content
	 */
	public function content_template() { ?>
		<div class="totaltheme-customize-toggle-control">
			<# if ( data.label ) { #>
				<label for="totaltheme-customize-toggle-control--{{ data.id }}" class="customize-control-title">{{ data.label }}</label>
			<# } #>
			<span class="totaltheme-customize-toggle-control__btn">
				<input id="totaltheme-customize-toggle-control--{{ data.id }}" type="checkbox">
				<span class="totaltheme-customize-toggle-control__track"></span>
				<span class="totaltheme-customize-toggle-control__thumb"></span>
			</span>
		</div>
		<# if ( data.description ) { #>
			<span id="totaltheme-customize-toggle-control--{{ data.id }}" class="description customize-control-description">{{{ data.description }}}</span>
		<# } #>
		<?php
	}
}
