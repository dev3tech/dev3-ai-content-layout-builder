<?php
/**
 * Script/style registration for the built bundles.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder;

use Dev3\AiContentLayoutBuilder\AI\Prompt_Orchestrator;
use Dev3\AiContentLayoutBuilder\Core\Capabilities;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers build/<entry>.js with dependencies from build/<entry>.asset.php.
 */
class Assets {

	const ENTRIES = array( 'editor', 'admin', 'elementor', 'wpbakery', 'gutenberg', 'media' );

	/**
	 * Registers every bundle.
	 */
	public static function register() {
		if ( ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			wp_register_script( 'react-jsx-runtime', DEV3CL_URL . 'assets/vendor/react-jsx-runtime.js', array( 'react' ), '18.3.1', true );
		}
		foreach ( self::ENTRIES as $entry ) {
			$asset_file = DEV3CL_PATH . 'build/' . $entry . '.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				continue;
			}
			$asset = include $asset_file;
			$deps  = $asset['dependencies'];
			if ( 'editor' !== $entry && 'admin' !== $entry ) {
				$deps[] = 'dev3cl-editor';
			}
			wp_register_script( 'dev3cl-' . $entry, DEV3CL_URL . 'build/' . $entry . '.js', $deps, $asset['version'], true );
			wp_set_script_translations( 'dev3cl-' . $entry, 'dev3-ai-content-layout-builder', DEV3CL_PATH . 'languages' );
			if ( is_readable( DEV3CL_PATH . 'build/' . $entry . '.css' ) ) {
				wp_register_style( 'dev3cl-' . $entry, DEV3CL_URL . 'build/' . $entry . '.css', array( 'wp-components' ), $asset['version'] );
			}
		}
		wp_add_inline_script( 'dev3cl-editor', 'window.dev3clConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );
		wp_add_inline_script( 'dev3cl-admin', 'window.dev3clConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );

		wp_register_script( 'dev3cl-frontend', DEV3CL_URL . 'assets/frontend/toggle.js', array(), DEV3CL_VERSION, true );
		wp_register_style( 'dev3cl-frontend', DEV3CL_URL . 'assets/frontend/toggle.css', array(), DEV3CL_VERSION );
	}

	/**
	 * Enqueues the shared modal for an editor context.
	 *
	 * @param string $bridge Bridge entry (elementor|wpbakery|gutenberg|media) or '' for the modal only.
	 */
	public static function enqueue_editor( $bridge = '' ) {
		if ( ! Capabilities::can_generate() ) {
			return;
		}
		if ( ! wp_script_is( 'dev3cl-editor', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_script( 'dev3cl-editor' );
		wp_enqueue_style( 'dev3cl-editor' );
		if ( $bridge ) {
			wp_enqueue_script( 'dev3cl-' . $bridge );
			if ( wp_style_is( 'dev3cl-' . $bridge, 'registered' ) ) {
				wp_enqueue_style( 'dev3cl-' . $bridge );
			}
		}
	}

	/**
	 * Boot data for the browser. Never contains the API key.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = Settings::all();
		$tones    = array();
		foreach ( Prompt_Orchestrator::tone_labels() as $id => $label ) {
			$tones[] = array(
				'value' => $id,
				'label' => $label,
			);
		}
		return array(
			'version'         => DEV3CL_VERSION,
			'restUrl'         => esc_url_raw( rest_url( 'dev3cl/v1/' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'userId'          => get_current_user_id(),
			'canManage'       => Capabilities::can_manage(),
			'hasKey'          => '' !== Settings::api_key(),
			'settingsUrl'     => admin_url( 'admin.php?page=dev3-ai-content-layout-builder' ),
			'assetsUrl'       => DEV3CL_URL . 'assets/',
			'defaultTone'     => $settings['default_tone'],
			'defaultLanguage' => $settings['default_language'],
			'defaultModel'    => $settings['default_model'],
			'proModel'        => $settings['pro_model'],
			'temperature'     => (float) $settings['temperature'],
			'historyEnabled'  => (bool) $settings['history_enabled'],
			'tones'           => $tones,
		);
	}

	/**
	 * Enqueues the billing-period toggle only on pages that contain one.
	 */
	public static function maybe_enqueue_frontend() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$found = false !== strpos( $post->post_content, 'dev3cl-period-toggle' ) || false !== strpos( $post->post_content, '[vc_raw_html' );
		if ( ! $found ) {
			$data  = (string) get_post_meta( $post->ID, '_elementor_data', true );
			$found = false !== strpos( $data, 'dev3cl-period-toggle' );
		}
		/**
		 * Filters whether the frontend toggle assets load on this page.
		 *
		 * @param bool     $found Found marker.
		 * @param \WP_Post $post  Post.
		 */
		if ( apply_filters( 'dev3cl_enqueue_frontend', $found, $post ) ) {
			wp_enqueue_script( 'dev3cl-frontend' );
			wp_enqueue_style( 'dev3cl-frontend' );
		}
	}
}
