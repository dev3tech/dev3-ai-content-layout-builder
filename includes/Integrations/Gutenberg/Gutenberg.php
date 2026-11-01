<?php
/**
 * Block editor integration.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Integrations\Gutenberg;

use Dev3\AiContentLayoutBuilder\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Sidebar + block toolbar wand (src/gutenberg).
 */
class Gutenberg {

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Block editor assets (skipped in the site editor's template parts and widgets screen).
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'widgets', 'customize' ), true ) ) {
			return;
		}
		Assets::enqueue_editor( 'gutenberg' );
	}
}
