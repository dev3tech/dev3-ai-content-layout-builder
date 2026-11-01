<?php
/**
 * Admin menu, React settings app mount and notices.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Admin;

use Dev3\AiContentLayoutBuilder\Assets;
use Dev3\AiContentLayoutBuilder\Core\Capabilities;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Gemini AI top-level menu.
 */
class Admin_Page {

	const SLUG = 'dev3-ai-content-layout-builder';

	/**
	 * Hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'missing_key_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DEV3CL_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the menu.
	 */
	public function menu() {
		$this->hook = (string) add_menu_page(
			__( 'DEV3 AI Builder', 'dev3-ai-content-layout-builder' ),
			__( 'DEV3 AI Builder', 'dev3-ai-content-layout-builder' ),
			Capabilities::manage(),
			self::SLUG,
			array( $this, 'render' ),
			'data:image/svg+xml;base64,' . base64_encode( self::menu_icon() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			81
		);
	}

	/**
	 * Mount point.
	 */
	public function render() {
		echo '<div class="wrap"><div id="dev3cl-admin-root" class="dev3cl-root"></div></div>';
	}

	/**
	 * Loads the admin app on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		if ( ! wp_script_is( 'dev3cl-admin', 'registered' ) ) {
			Assets::register();
		}
		wp_enqueue_script( 'dev3cl-admin' );
		wp_enqueue_style( 'dev3cl-admin' );
		// The modal powers "Try" on the Prompt Templates tab.
		Assets::enqueue_editor();
	}

	/**
	 * Nudges admins to add a key.
	 */
	public function missing_key_notice() {
		if ( ! Capabilities::can_manage() || '' !== Settings::api_key() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'DEV3 AI Content & Layout Builder is almost ready — add your Google Gemini API key to start generating.', 'dev3-ai-content-layout-builder' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG . '#/api' ) ),
			esc_html__( 'Add API key', 'dev3-ai-content-layout-builder' )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'dev3-ai-content-layout-builder' ) . '</a>' );
		return $links;
	}

	/**
	 * Sparkle icon for the menu (fill controlled by WP admin colours).
	 *
	 * @return string
	 */
	private static function menu_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1.5l1.9 4.9 4.9 1.9-4.9 1.9L10 15.1l-1.9-4.9-4.9-1.9 4.9-1.9L10 1.5zm6 10.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1zM4 13l.7 1.6 1.6.7-1.6.7L4 17.6l-.7-1.6-1.6-.7 1.6-.7L4 13z"/></svg>';
	}
}
