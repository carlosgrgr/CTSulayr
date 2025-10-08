<?php

use TotalTheme\ToggleBar;

/**
 * Togglebar HTML.
 *
 * @package Total WordPress theme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TotalTheme\ToggleBar' ) ) {
	return;
}

?>

<div id="toggle-bar-wrap" <?php ToggleBar::wrapper_class() . Togglebar::wrapper_data(); ?>>
	<?php if ( ! wpex_theme_do_location( 'togglebar' ) ) : ?>
		<div id="toggle-bar" <?php ToggleBar::inner_class(); ?>><?php
			if ( ToggleBar::is_dismissible() ) {
				wpex_get_template_part( 'togglebar_dismiss' );
			}
			wpex_get_template_part( 'togglebar_content' );
		?></div>
	<?php endif;?>
</div>
