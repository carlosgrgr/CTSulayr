<?php

use TotalTheme\Footer\Core as Footer;
use TotalTheme\Footer\Widgets as Footer_Widgets;

/**
 * Classic Footer Layout.
 *
 * @package TotalTheme
 * @subpackage Partials
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

?>

<?php wpex_hook_footer_before(); ?>

<?php if ( ! wpex_theme_do_location( 'footer' ) ) : ?>

	<?php if ( Footer_Widgets::is_enabled() ) : ?>

		<footer id="footer" <?php Footer::wrapper_class(); ?>>

			<?php wpex_hook_footer_top(); ?>

			<div id="footer-inner" class="site-footer-inner container wpex-pt-40 wpex-clr"><?php

				wpex_hook_footer_inner();

			?></div>

			<?php wpex_hook_footer_bottom(); ?>

		</footer>

	<?php endif; ?>

<?php endif; ?>

<?php wpex_hook_footer_after(); ?>
