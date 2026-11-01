<?php
/**
 * Turns a task request into a Gemini system instruction + prompt.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Task registry and prompt builder.
 */
class Prompt_Orchestrator {

	/**
	 * Task definitions: group (selects the model), whether source text is required, output kind.
	 *
	 * @return array
	 */
	public static function tasks() {
		$tasks = array(
			'generate'            => array(
				'group'      => 'copy',
				'needs_text' => false,
			),
			'expand'              => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'rewrite'             => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'shorten'             => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'summarize'           => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'fix_grammar'         => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'tone'                => array(
				'group'      => 'copy',
				'needs_text' => true,
			),
			'headline_variations' => array(
				'group'      => 'copy',
				'needs_text' => false,
				'output'     => 'json',
			),
			'items'               => array(
				'group'      => 'copy',
				'needs_text' => false,
				'output'     => 'json',
			),
			'translate'           => array(
				'group'      => 'translate',
				'needs_text' => true,
			),
			'seo_meta'            => array(
				'group'      => 'seo',
				'needs_text' => false,
				'output'     => 'json',
			),
			'layout'              => array(
				'group'      => 'layout',
				'needs_text' => false,
				'output'     => 'json',
			),
			'vision'              => array(
				'group'      => 'vision',
				'needs_text' => false,
				'output'     => 'json',
			),
		);
		/**
		 * Filters the task registry. Add a task with a 'builder' callable to customise prompts.
		 *
		 * @param array $tasks Tasks.
		 */
		return apply_filters( 'dev3cl_tasks', $tasks );
	}

	/**
	 * Human labels for tones.
	 *
	 * @return array
	 */
	public static function tone_labels() {
		return array(
			'professional' => __( 'Professional', 'dev3-ai-content-layout-builder' ),
			'casual'       => __( 'Casual', 'dev3-ai-content-layout-builder' ),
			'persuasive'   => __( 'Persuasive', 'dev3-ai-content-layout-builder' ),
			'technical'    => __( 'Technical', 'dev3-ai-content-layout-builder' ),
			'friendly'     => __( 'Friendly', 'dev3-ai-content-layout-builder' ),
			'witty'        => __( 'Witty', 'dev3-ai-content-layout-builder' ),
			'formal'       => __( 'Formal', 'dev3-ai-content-layout-builder' ),
			'empathetic'   => __( 'Empathetic', 'dev3-ai-content-layout-builder' ),
		);
	}

	/**
	 * Builds the request for a task.
	 *
	 * @param array $req {
	 *     Request.
	 *     @type string $task       Task id.
	 *     @type string $prompt     User instruction.
	 *     @type string $text       Existing / selected content.
	 *     @type string $tone       Tone id.
	 *     @type string $language   Target language.
	 *     @type string $format     plain|html.
	 *     @type string $field      Field hint: heading|text|textarea|wysiwyg|html|button|title.
	 *     @type int    $variations Number of headline variations.
	 * }
	 * @return array|\WP_Error { group, system, prompt, schema|null, output }
	 */
	public static function build( array $req ) {
		$tasks = self::tasks();
		$task  = isset( $req['task'] ) ? (string) $req['task'] : 'generate';
		if ( ! isset( $tasks[ $task ] ) ) {
			return new \WP_Error( 'dev3cl_unknown_task', __( 'Unknown AI task.', 'dev3-ai-content-layout-builder' ), array( 'status' => 400 ) );
		}
		$def      = $tasks[ $task ];
		$prompt   = isset( $req['prompt'] ) ? trim( (string) $req['prompt'] ) : '';
		$text     = isset( $req['text'] ) ? trim( (string) $req['text'] ) : '';
		$tone     = isset( $req['tone'] ) && in_array( $req['tone'], Settings::TONES, true ) ? $req['tone'] : Settings::get( 'default_tone', 'professional' );
		$language = isset( $req['language'] ) && '' !== $req['language'] ? (string) $req['language'] : (string) Settings::get( 'default_language', 'English' );
		$format   = isset( $req['format'] ) && 'html' === $req['format'] ? 'html' : 'plain';
		$field    = isset( $req['field'] ) ? (string) $req['field'] : 'text';

		if ( ! empty( $def['needs_text'] ) && '' === $text ) {
			return new \WP_Error( 'dev3cl_missing_text', __( 'This action needs existing text. Type or select some content first.', 'dev3-ai-content-layout-builder' ), array( 'status' => 400 ) );
		}
		if ( empty( $def['needs_text'] ) && '' === $prompt && '' === $text && 'vision' !== $task ) {
			return new \WP_Error( 'dev3cl_missing_prompt', __( 'Describe what you want the AI to write.', 'dev3-ai-content-layout-builder' ), array( 'status' => 400 ) );
		}

		$output = isset( $def['output'] ) ? $def['output'] : $format;
		$system = self::system_instruction( 'json' === $output ? 'json' : $format, $field, $tone, $language );
		$schema = null;
		$extra  = '' !== $prompt ? "\n\nAdditional instructions from the user:\n" . $prompt : '';
		$source = "\n\n<content>\n" . $text . "\n</content>";

		switch ( $task ) {
			case 'generate':
				$body = '' !== $prompt ? $prompt : 'Write new content about this:';
				if ( '' !== $text ) {
					$body .= "\n\nUse this existing content as context:" . $source;
				}
				break;
			case 'expand':
				$body = 'Expand the content below into a fuller, richer version (roughly 2–3× longer) while keeping its meaning and key points.' . $source . $extra;
				break;
			case 'rewrite':
				$body = 'Rewrite the content below so it reads better and feels fresh, keeping the meaning and approximate length.' . $source . $extra;
				break;
			case 'shorten':
				$body = 'Make the content below about half as long without losing the key message.' . $source . $extra;
				break;
			case 'summarize':
				$body = 'Summarize the content below concisely.' . $source . $extra;
				break;
			case 'fix_grammar':
				$system = self::system_instruction( $format, $field, '', $language, false );
				$body   = 'Fix spelling, grammar and punctuation in the content below. Change nothing else: keep wording, tone, language and formatting.' . $source;
				break;
			case 'tone':
				$label = self::tone_labels()[ $tone ];
				$body  = sprintf( 'Rewrite the content below in a %s tone. Keep the meaning and approximate length.', strtolower( $label ) ) . $source . $extra;
				break;
			case 'translate':
				$system = self::system_instruction( $format, $field, '', $language, false );
				$body   = sprintf( 'Translate the content below into %s. Preserve formatting, HTML tags, placeholders, brand and product names. Output only the translation.', $language ) . $source . $extra;
				break;
			case 'headline_variations':
				$count  = isset( $req['variations'] ) ? min( 10, max( 2, (int) $req['variations'] ) ) : 5;
				$body   = sprintf( 'Write %d distinct headline options (each under 12 words).', $count ) . ( '' !== $prompt ? "\n\nTopic: " . $prompt : '' ) . ( '' !== $text ? "\n\nBased on:" . $source : '' );
				$schema = array(
					'type'       => 'OBJECT',
					'properties' => array(
						'items' => array(
							'type'  => 'ARRAY',
							'items' => array( 'type' => 'STRING' ),
						),
					),
					'required'   => array( 'items' ),
				);
				break;
			case 'items':
				$count  = isset( $req['variations'] ) ? min( 12, max( 2, (int) $req['variations'] ) ) : 5;
				$body   = sprintf( 'Write %d items, each with a short title and a helpful body of 1–3 sentences (for an accordion, FAQ, toggle or tabs).', $count )
					. ( '' !== $prompt ? "\n\nTopic / instructions: " . $prompt : '' )
					. ( '' !== $text ? "\n\nExisting items for context:" . $source : '' );
				$schema = array(
					'type'       => 'OBJECT',
					'properties' => array(
						'items' => array(
							'type'  => 'ARRAY',
							'items' => array(
								'type'       => 'OBJECT',
								'properties' => array(
									'title' => array( 'type' => 'STRING' ),
									'text'  => array( 'type' => 'STRING' ),
								),
								'required'   => array( 'title', 'text' ),
							),
						),
					),
					'required'   => array( 'items' ),
				);
				break;
			case 'seo_meta':
				$body   = 'Write SEO metadata for a web page: an SEO title (max 60 characters), a meta description (max 155 characters, with a call to action), a URL slug, 5–8 focus keywords, and an Open Graph title.'
					. ( '' !== $prompt ? "\n\nPage topic / instructions: " . $prompt : '' )
					. ( '' !== $text ? "\n\nPage content:\n<content>\n" . self::clip( $text, 12000 ) . "\n</content>" : '' );
				$schema = array(
					'type'       => 'OBJECT',
					'properties' => array(
						'title'       => array( 'type' => 'STRING' ),
						'description' => array( 'type' => 'STRING' ),
						'slug'        => array( 'type' => 'STRING' ),
						'keywords'    => array(
							'type'  => 'ARRAY',
							'items' => array( 'type' => 'STRING' ),
						),
						'og_title'    => array( 'type' => 'STRING' ),
					),
					'required'   => array( 'title', 'description', 'slug', 'keywords' ),
				);
				break;
			case 'layout':
				$system = Layout_Schema::system_instruction( $tone, $language, isset( $req['builder'] ) ? (string) $req['builder'] : 'elementor' );
				$body   = 'Design this page section: ' . ( '' !== $prompt ? $prompt : $text );
				$schema = Layout_Schema::response_schema();
				break;
			case 'vision':
				$system = self::system_instruction( 'json', 'text', $tone, $language );
				$body   = Vision::prompt( isset( $req['vision_fields'] ) ? (array) $req['vision_fields'] : array(), $prompt );
				$schema = Vision::response_schema();
				break;
			default:
				if ( isset( $def['builder'] ) && is_callable( $def['builder'] ) ) {
					$custom = call_user_func( $def['builder'], $req, $system );
					if ( is_wp_error( $custom ) ) {
						return $custom;
					}
					return array_merge(
						array(
							'group'  => $def['group'],
							'schema' => null,
							'output' => $output,
							'system' => $system,
						),
						$custom
					);
				}
				$body = $prompt . ( '' !== $text ? $source : '' );
		}

		$built = array(
			'group'  => $def['group'],
			'system' => $system,
			'prompt' => $body,
			'schema' => $schema,
			'output' => $output,
		);

		/**
		 * Filters the built prompt for a task.
		 *
		 * @param array $built Built request.
		 * @param array $req   Original request.
		 */
		return apply_filters( 'dev3cl_built_prompt', $built, $req );
	}

	/**
	 * Base system instruction shared by copy tasks.
	 *
	 * @param string $format   plain|html.
	 * @param string $field    Field hint.
	 * @param string $tone     Tone id ('' for none).
	 * @param string $language Output language.
	 * @param bool   $voice    Include the brand voice.
	 * @return string
	 */
	public static function system_instruction( $format, $field, $tone, $language, $voice = true ) {
		$lines   = array();
		$lines[] = 'You are an expert website copywriter working inside a WordPress page builder. You write clear, conversion-focused, accessible web copy.';
		$lines[] = 'Return ONLY the final content — no preamble, no explanations, no quotes around it, no code fences.';
		if ( 'json' === $format ) {
			$lines[] = 'Answer with a single JSON object that matches the response schema. Inside JSON string values use plain text (no Markdown); HTML only where a field explicitly asks for it.';
		} elseif ( 'html' === $format ) {
			$lines[] = 'Format the answer as clean HTML using only <p>, <h2>, <h3>, <h4>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <br>, <blockquote>. No <html>, <body>, <style>, <script> or inline styles. Do not use Markdown.';
		} else {
			$lines[] = 'Return plain text without Markdown or HTML.';
		}
		$hints = array(
			'heading' => 'The content is a heading: keep it short and punchy (ideally under 10 words), no trailing period.',
			'title'   => 'The content is a title: keep it under 12 words, no trailing period.',
			'button'  => 'The content is a button label: 1–4 words, action-oriented.',
			'html'    => 'The content goes into a raw HTML widget; well-formed semantic HTML is welcome.',
		);
		if ( isset( $hints[ $field ] ) ) {
			$lines[] = $hints[ $field ];
		}
		if ( '' !== $tone ) {
			$labels  = self::tone_labels();
			$lines[] = 'Tone of voice: ' . ( isset( $labels[ $tone ] ) ? $labels[ $tone ] : $tone ) . '.';
		}
		$lines[] = 'Write in ' . $language . ' unless the task explicitly asks for another language.';
		$brand   = $voice ? trim( (string) Settings::get( 'brand_voice', '' ) ) : '';
		if ( '' !== $brand ) {
			$lines[] = "Brand guidelines to follow:\n" . $brand;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Clips long context.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Max characters.
	 * @return string
	 */
	public static function clip( $text, $chars ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $chars ) : substr( $text, 0, $chars );
	}
}
