<?php
/**
 * Plugin Name:       DEV3 AI Content & Layout Builder for Elementor, WPBakery & Gutenberg
 * Plugin URI:        https://dev3.tech/marketplace/dev3-ai-content-layout-builder
 * Description:       Generate copy, translate, write SEO meta, build section layouts and describe images with DEV3 AI — right inside Elementor, WPBakery, the block editor and the Media Library.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DEV3 Technologies
 * Author URI:        https://dev3.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dev3-ai-content-layout-builder
 * Domain Path:       /languages
 * Elementor tested up to: 3.30.0
 *
 * @package Dev3\AiContentLayoutBuilder
 */

defined( 'ABSPATH' ) || exit;

define( 'DEV3CL_VERSION', '1.0.1' );
define( 'DEV3CL_FILE', __FILE__ );
define( 'DEV3CL_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEV3CL_URL', plugin_dir_url( __FILE__ ) );
define( 'DEV3CL_SLUG', 'dev3-ai-content-layout-builder' );
define( 'DEV3CL_MIN_PHP', '7.4' );
define( 'DEV3CL_MIN_WP', '6.0' );

if ( version_compare( PHP_VERSION, DEV3CL_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			/* translators: %s: minimum PHP version. */
			echo esc_html( sprintf( __( 'DEV3 AI Content & Layout Builder requires PHP %s or newer.', 'dev3-ai-content-layout-builder' ), DEV3CL_MIN_PHP ) );
			echo '</p></div>';
		}
	);
	return;
}

require_once DEV3CL_PATH . 'includes/autoload.php';

register_activation_hook( __FILE__, array( '\Dev3\AiContentLayoutBuilder\Core\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Dev3\AiContentLayoutBuilder\Core\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( '\Dev3\AiContentLayoutBuilder\Plugin', 'instance' ) );
