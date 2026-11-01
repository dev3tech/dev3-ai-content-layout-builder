<?php
/**
 * WPBakery Page Builder integration.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Integrations\WPBakery;

use Dev3\AiContentLayoutBuilder\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the gemini_ai_prompt param, adds it to core elements and loads the edit-popup bridge.
 */
class WPBakery {

	const PARAM = 'gemini_ai_prompt';

	/**
	 * Shortcode → [ target param => field hint ].
	 *
	 * @return array
	 */
	public static function targets() {
		$targets = array(
			'vc_column_text'    => array( 'content' => 'wysiwyg' ),
			'vc_custom_heading' => array( 'text' => 'heading' ),
			'vc_btn'            => array( 'title' => 'button' ),
			'vc_cta'            => array( 'content' => 'wysiwyg' ),
			'vc_message'        => array( 'content' => 'wysiwyg' ),
			'vc_toggle'         => array( 'content' => 'wysiwyg' ),
			'vc_raw_html'       => array( 'content' => 'html' ),
			'vc_tta_section'    => array( 'title' => 'heading' ),
			'vc_text_separator' => array( 'title' => 'heading' ),
		);
		/**
		 * Filters which WPBakery elements get the Gemini AI param.
		 *
		 * @param array $targets shortcode => [ param => field ].
		 */
		return apply_filters( 'dev3cl_wpbakery_targets', $targets );
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'vc_before_init', array( $this, 'register_param' ) );
		add_action( 'vc_after_init', array( $this, 'add_params' ) );
		add_action( 'vc_backend_editor_enqueue_js_css', array( $this, 'enqueue' ) );
		add_action( 'vc_frontend_editor_enqueue_js_css', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers the param type with vc_add_shortcode_param().
	 */
	public function register_param() {
		if ( function_exists( 'vc_add_shortcode_param' ) ) {
			vc_add_shortcode_param( self::PARAM, array( $this, 'render_param' ) );
		}
	}

	/**
	 * Adds the param to each target element.
	 */
	public function add_params() {
		if ( ! function_exists( 'vc_add_param' ) ) {
			return;
		}
		foreach ( self::targets() as $shortcode => $params ) {
			foreach ( $params as $target => $field ) {
				vc_add_param(
					$shortcode,
					array(
						'type'        => self::PARAM,
						'heading'     => __( 'DEV3 AI Assistant', 'dev3-ai-content-layout-builder' ),
						'param_name'  => 'dev3cl_ai',
						'target'      => $target,
						'field'       => $field,
						'description' => __( 'Generate, rewrite, translate or change the tone of this element\'s text.', 'dev3-ai-content-layout-builder' ),
						'weight'      => 100,
					)
				);
			}
		}
	}

	/**
	 * Param field markup (holds no value).
	 *
	 * @param array  $settings Param settings.
	 * @param string $value    Value.
	 * @return string
	 */
	public function render_param( $settings, $value ) {
		unset( $value );
		return sprintf(
			'<div class="dev3cl-vc-param" data-target="%1$s" data-field="%2$s"><button type="button" class="button dev3cl-vc-launch"><span class="dev3cl-wand-icon" aria-hidden="true"></span> %3$s</button><input type="hidden" name="%4$s" class="wpb_vc_param_value %4$s %5$s_field" value=""></div>',
			esc_attr( isset( $settings['target'] ) ? $settings['target'] : 'content' ),
			esc_attr( isset( $settings['field'] ) ? $settings['field'] : 'text' ),
			esc_html__( 'Write with DEV3 AI', 'dev3-ai-content-layout-builder' ),
			esc_attr( $settings['param_name'] ),
			esc_attr( self::PARAM )
		);
	}

	/**
	 * Editor assets.
	 */
	public function enqueue() {
		Assets::enqueue_editor( 'wpbakery' );
	}
}
