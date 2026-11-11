<?php
defined( 'ABSPATH' ) || exit;

// Päta len z Elementor Theme Buildera – téma vlastnú nevypisuje.
if ( function_exists( 'elementor_theme_do_location' ) ) {
	elementor_theme_do_location( 'footer' );
}

wp_footer();
?>
</body>
</html>
