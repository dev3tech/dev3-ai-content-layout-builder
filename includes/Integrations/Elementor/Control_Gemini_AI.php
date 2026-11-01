<?php
/**
 * Elementor "gemini_ai" control.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * UI-only control (stores no value): renders a launcher button bound to a sibling control.
 * Its view is registered in src/elementor as elementor.addControlView('gemini_ai').
 */
class Control_Gemini_AI extends \Elementor\Base_UI_Control {

	const TYPE = 'gemini_ai';

	/**
	 * Control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return self::TYPE;
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		return array(
			'target' => '',
			'field'  => 'text',
		);
	}

	/**
	 * Underscore template.
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field dev3cl-el-control">
			<button type="button" class="elementor-button dev3cl-el-launch" data-target="{{ data.target }}" data-field="{{ data.field }}">
				<span class="dev3cl-wand-icon" aria-hidden="true"></span>
				<span>{{{ data.label }}}</span>
			</button>
		</div>
		<?php
	}
}
