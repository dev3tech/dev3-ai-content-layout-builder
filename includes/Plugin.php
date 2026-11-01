<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder;

use Dev3\AiContentLayoutBuilder\Admin\Admin_Page;
use Dev3\AiContentLayoutBuilder\Core\Installer;
use Dev3\AiContentLayoutBuilder\Core\Settings;
use Dev3\AiContentLayoutBuilder\Integrations\Elementor\Elementor;
use Dev3\AiContentLayoutBuilder\Integrations\Gutenberg\Gutenberg;
use Dev3\AiContentLayoutBuilder\Integrations\Media\Media;
use Dev3\AiContentLayoutBuilder\Integrations\WPBakery\WPBakery;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns (and boots) the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'dev3-ai-content-layout-builder', false, dirname( plugin_basename( DEV3CL_FILE ) ) . '/languages' );

		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );
		add_action( Installer::CRON_HOOK, array( Installer::class, 'maintenance' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( 'init', array( Assets::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( Assets::class, 'maybe_enqueue_frontend' ) );

		if ( is_admin() ) {
			( new Admin_Page() )->register();
		}

		$integrations = (array) Settings::get( 'integrations', array() );
		if ( ! empty( $integrations['elementor'] ) ) {
			( new Elementor() )->register();
		}
		if ( ! empty( $integrations['wpbakery'] ) ) {
			( new WPBakery() )->register();
		}
		if ( ! empty( $integrations['gutenberg'] ) ) {
			( new Gutenberg() )->register();
		}
		if ( ! empty( $integrations['media'] ) ) {
			( new Media() )->register();
		}

		/**
		 * Fires after DEV3 AI Content & Layout Builder has loaded.
		 */
		do_action( 'dev3cl_loaded' );
	}

	/**
	 * Registers REST controllers.
	 */
	public function register_rest() {
		$controllers = array(
			new Rest\Settings_Controller(),
			new Rest\Generate_Controller(),
			new Rest\Library_Controller(),
		);
		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
