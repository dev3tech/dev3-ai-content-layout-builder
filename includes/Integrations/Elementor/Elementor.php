<?php
/**
 * Elementor integration.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Integrations\Elementor;

use Dev3\AiContentLayoutBuilder\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Gemini AI control, injects it into core widgets and loads the editor bridge.
 * A wand is also added next to every text/textarea/WYSIWYG/code control by the JS bridge.
 */
class Elementor {

	/**
	 * Widget → section → [ target control => field hint ].
	 *
	 * @return array
	 */
	public static function targets() {
		$targets = array(
			'heading'        => array( 'section_title' => array( 'title' => 'heading' ) ),
			'text-editor'    => array( 'section_editor' => array( 'editor' => 'wysiwyg' ) ),
			'html'           => array( 'section_title' => array( 'html' => 'html' ) ),
			'button'         => array( 'section_button' => array( 'text' => 'button' ) ),
			'icon-box'       => array( 'section_icon' => array( 'description_text' => 'textarea' ) ),
			'image-box'      => array( 'section_image' => array( 'description_text' => 'textarea' ) ),
			'testimonial'    => array( 'section_testimonial' => array( 'testimonial_content' => 'textarea' ) ),
			'accordion'      => array( 'section_title' => array( 'tabs' => 'repeater' ) ),
			'toggle'         => array( 'section_toggle' => array( 'tabs' => 'repeater' ) ),
			'tabs'           => array( 'section_tabs' => array( 'tabs' => 'repeater' ) ),
			'call-to-action' => array( 'section_content' => array( 'description' => 'textarea' ) ),
		);
		/**
		 * Filters which Elementor widgets get an inline Gemini AI control.
		 *
		 * @param array $targets widget => section => [ control => field ].
		 */
		return apply_filters( 'dev3cl_elementor_targets', $targets );
	}

	/**
	 * Registers hooks once Elementor is loaded.
	 */
	public function register() {
		add_action( 'elementor/loaded', array( $this, 'on_loaded' ) );
		if ( did_action( 'elementor/loaded' ) ) {
			$this->on_loaded();
		}
	}

	/**
	 * Elementor is available.
	 */
	public function on_loaded() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_action( 'elementor/controls/register', array( $this, 'register_control' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue' ) );
		// Generated pricing toggles must work inside the editor preview too.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview' ) );

		foreach ( self::targets() as $widget => $sections ) {
			foreach ( $sections as $section => $controls ) {
				add_action(
					"elementor/element/{$widget}/{$section}/before_section_end",
					function ( $element ) use ( $controls ) {
						$this->inject( $element, $controls );
					},
					10,
					1
				);
			}
		}
	}

	/**
	 * Registers the control type.
	 *
	 * @param \Elementor\Controls_Manager $manager Controls manager.
	 */
	public function register_control( $manager ) {
		$manager->register( new Control_Gemini_AI() );
	}

	/**
	 * Adds the inline control to a widget section.
	 *
	 * @param \Elementor\Controls_Stack $element  Widget.
	 * @param array                     $controls Target control => field hint.
	 */
	private function inject( $element, array $controls ) {
		foreach ( $controls as $target => $field ) {
			$element->add_control(
				'dev3cl_ai_' . str_replace( '-', '_', $target ),
				array(
					'type'      => Control_Gemini_AI::TYPE,
					'label'     => 'repeater' === $field ? __( 'Generate items with DEV3 AI', 'dev3-ai-content-layout-builder' ) : __( 'Write with DEV3 AI', 'dev3-ai-content-layout-builder' ),
					'target'    => $target,
					'field'     => $field,
					'separator' => 'before',
				)
			);
		}
	}

	/**
	 * Editor assets.
	 */
	public function enqueue() {
		Assets::enqueue_editor( 'elementor' );
	}

	/**
	 * Frontend toggle assets in the preview iframe.
	 */
	public function enqueue_preview() {
		wp_enqueue_style( 'dev3cl-frontend' );
		wp_enqueue_script( 'dev3cl-frontend' );
	}
}
