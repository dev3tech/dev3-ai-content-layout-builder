<?php
/**
 * End-to-end generation: prompt → Gemini → parsed, sanitized result.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

use Dev3\AiContentLayoutBuilder\Api\Api_Error;
use Dev3\AiContentLayoutBuilder\Api\Gemini_Client;
use Dev3\AiContentLayoutBuilder\Core\Settings;
use Dev3\AiContentLayoutBuilder\Data\History_Repository;
use Dev3\AiContentLayoutBuilder\Data\Templates;
use Dev3\AiContentLayoutBuilder\Data\Usage_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Runs one task for the current user.
 */
class Generator {

	/**
	 * Client.
	 *
	 * @var Gemini_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Gemini_Client|null $client Client (injectable for tests).
	 */
	public function __construct( $client = null ) {
		$this->client = $client ? $client : new Gemini_Client();
	}

	/**
	 * Runs a task.
	 *
	 * @param array $req Sanitized request (see Prompt_Orchestrator::build + template_id, values, model,
	 *                   temperature, context, builder, attachment_id, image_url, elementor_mode).
	 * @return array|\WP_Error
	 */
	public function run( array $req ) {
		// Layouts with thinking can take 40s+; shared hosts often default to 30s.
		if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 180 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		$user_id = get_current_user_id();
		$context = isset( $req['context'] ) ? (string) $req['context'] : 'admin';

		$limit = (int) Settings::get( 'rate_limit', 30 );
		/**
		 * Filters the per-user requests-per-minute limit (0 disables it).
		 *
		 * @param int $limit   Limit.
		 * @param int $user_id User.
		 */
		$limit = (int) apply_filters( 'dev3cl_rate_limit', $limit, $user_id );
		if ( $limit > 0 && Usage_Repository::last_minute( $user_id ) >= $limit ) {
			return Api_Error::make(
				'dev3cl_rate_limited',
				__( 'You are sending requests too quickly.', 'dev3-ai-content-layout-builder' ),
				__( 'Wait a minute and try again. Administrators can raise the limit in General Settings.', 'dev3-ai-content-layout-builder' ),
				429,
				true
			);
		}

		if ( ! empty( $req['template_id'] ) ) {
			$req = $this->apply_template( $req );
			if ( is_wp_error( $req ) ) {
				return $req;
			}
		}

		$images = array();
		if ( 'vision' === $req['task'] ) {
			$image = Vision::load( isset( $req['attachment_id'] ) ? (int) $req['attachment_id'] : 0, isset( $req['image_url'] ) ? (string) $req['image_url'] : '' );
			if ( is_wp_error( $image ) ) {
				return $image;
			}
			$images[] = $image;
		}

		$built = Prompt_Orchestrator::build( $req );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$model = Settings::model_for( $built['group'] );
		if ( ! empty( $req['model'] ) ) {
			if ( 'pro' === $req['model'] ) {
				$model = Settings::get( 'pro_model' );
			} elseif ( 'default' === $req['model'] ) {
				$model = Settings::get( 'default_model' );
			} elseif ( Settings::sanitize_model( $req['model'] ) ) {
				$model = Settings::sanitize_model( $req['model'] );
			}
		}

		$args = array(
			'model'  => $model,
			'system' => $built['system'],
			'prompt' => $built['prompt'],
			'images' => $images,
			'schema' => $built['schema'],
		);
		if ( isset( $req['temperature'] ) && '' !== $req['temperature'] && null !== $req['temperature'] ) {
			$args['temperature'] = min( 1, max( 0, (float) $req['temperature'] ) );
		}
		if ( 'json' === $built['output'] && 'layout' !== $req['task'] ) {
			// Structured answers at high temperature without thinking can loop until MAX_TOKENS.
			$args['temperature'] = min( isset( $args['temperature'] ) ? $args['temperature'] : 1, 0.3 );
		}
		if ( 'layout' === $req['task'] ) {
			// Layout JSON is long; give it room regardless of the copy-oriented default.
			$args['max_tokens']      = max( 12288, (int) Settings::get( 'max_tokens', 4096 ) );
			$args['thinking_budget'] = 2048;
			$args['timeout']         = 150;
			$args['temperature']     = min( isset( $args['temperature'] ) ? $args['temperature'] : (float) Settings::get( 'temperature', 0.7 ), 0.7 );
		}
		if ( 'vision' === $req['task'] ) {
			$args['max_tokens']     = 2048;
			$args['light_thinking'] = true;
		}

		$result = $this->client->generate( $args );

		$log = array(
			'user_id' => $user_id,
			'task'    => $req['task'],
			'context' => $context,
			'model'   => $model,
		);
		if ( is_wp_error( $result ) ) {
			Usage_Repository::log(
				$log + array(
					'status'     => 'error',
					'error_code' => $result->get_error_code(),
				)
			);
			return $result;
		}
		Usage_Repository::log(
			$log + array(
				'usage'       => $result['usage'],
				'duration_ms' => $result['duration_ms'],
			)
		);

		$parsed = $this->parse( $req, $built, $result );
		if ( is_wp_error( $parsed ) && 'dev3cl_bad_json' === $parsed->get_error_code() ) {
			// Malformed or runaway (truncated) structured output: one near-deterministic retry fixes nearly all cases.
			$args['temperature'] = 0.1;
			$retry               = $this->client->generate( $args );
			if ( ! is_wp_error( $retry ) ) {
				Usage_Repository::log(
					$log + array(
						'usage'       => $retry['usage'],
						'duration_ms' => $retry['duration_ms'],
					)
				);
				$result = $retry;
				$parsed = $this->parse( $req, $built, $result );
			}
		}
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$history_output       = is_string( $parsed['output'] ) ? $parsed['output'] : wp_json_encode( $parsed['output'] );
		$parsed['history_id'] = History_Repository::add(
			array(
				'user_id' => $user_id,
				'task'    => $req['task'],
				'context' => $context,
				'model'   => $result['model'],
				'prompt'  => '' !== (string) ( isset( $req['prompt'] ) ? $req['prompt'] : '' ) ? $req['prompt'] : Prompt_Orchestrator::clip( (string) ( isset( $req['text'] ) ? $req['text'] : '' ), 500 ),
				'output'  => 'layout' === $req['task'] ? wp_json_encode( $parsed['tree'] ) : $history_output,
				'meta'    => array(
					'format' => $parsed['format'],
					'tone'   => isset( $req['tone'] ) ? $req['tone'] : '',
				),
			)
		);
		$parsed['model']      = $result['model'];
		$parsed['usage']      = $result['usage'];
		$parsed['truncated']  = $result['truncated'];
		$parsed['task']       = $req['task'];

		/**
		 * Filters a finished generation before it is returned to the editor.
		 *
		 * @param array $parsed Result.
		 * @param array $req    Request.
		 */
		return apply_filters( 'dev3cl_generation_result', $parsed, $req );
	}

	/**
	 * Parses the raw model text per output kind.
	 *
	 * @param array $req    Request.
	 * @param array $built  Built prompt.
	 * @param array $result Client result.
	 * @return array|\WP_Error
	 */
	private function parse( array $req, array $built, array $result ) {
		$task = $req['task'];

		if ( 'json' !== $built['output'] ) {
			$outputs = array();
			foreach ( $result['texts'] as $text ) {
				$outputs[] = Response_Parser::for_format( $text, $built['output'] );
			}
			return array(
				'format'       => $built['output'],
				'output'       => $outputs[0],
				'alternatives' => array_slice( $outputs, 1 ),
			);
		}

		$data = Response_Parser::json( $result['text'] );
		if ( null === $data ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DEV3 AI Content & Layout Builder: unparseable JSON for task ' . $task . ' (finish ' . $result['finish'] . '): ' . substr( $result['text'], 0, 2000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return Api_Error::make(
				'dev3cl_bad_json',
				__( 'Gemini returned malformed data.', 'dev3-ai-content-layout-builder' ),
				$result['truncated'] ? __( 'The answer was cut off — increase Max Output Tokens and regenerate.', 'dev3-ai-content-layout-builder' ) : __( 'Regenerate to try again.', 'dev3-ai-content-layout-builder' ),
				502,
				true
			);
		}

		switch ( $task ) {
			case 'headline_variations':
				$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array_values( array_filter( $data, 'is_string' ) );
				return array(
					'format' => 'list',
					'output' => array_values( array_filter( array_map( array( Response_Parser::class, 'plain' ), array_map( 'strval', $items ) ) ) ),
				);
			case 'items':
				$items = array();
				foreach ( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() as $item ) {
					if ( is_array( $item ) && isset( $item['title'], $item['text'] ) ) {
						$items[] = array(
							'title' => Response_Parser::plain( (string) $item['title'] ),
							'text'  => Response_Parser::html( (string) $item['text'] ),
						);
					}
				}
				return array(
					'format' => 'items',
					'output' => $items,
				);
			case 'seo_meta':
				return array(
					'format' => 'seo',
					'output' => array(
						'title'       => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
						'description' => isset( $data['description'] ) ? sanitize_text_field( (string) $data['description'] ) : '',
						'slug'        => isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : '',
						'keywords'    => isset( $data['keywords'] ) && is_array( $data['keywords'] ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $data['keywords'] ) ) ) : array(),
						'og_title'    => isset( $data['og_title'] ) ? sanitize_text_field( (string) $data['og_title'] ) : '',
					),
				);
			case 'vision':
				return array(
					'format' => 'vision',
					'output' => Vision::clean( $data, isset( $req['vision_fields'] ) ? (array) $req['vision_fields'] : array() ),
				);
			case 'layout':
				$tree = Layout_Schema::normalize( $data );
				if ( is_wp_error( $tree ) ) {
					return $tree;
				}
				return self::layout_payload( $tree, isset( $req['elementor_mode'] ) ? (string) $req['elementor_mode'] : '' );
		}

		return array(
			'format' => 'json',
			'output' => $data,
		);
	}

	/**
	 * Builder outputs for a normalized tree (also used when restoring from history).
	 *
	 * @param array  $tree Normalized tree.
	 * @param string $mode Elementor mode (container|section|'' to detect).
	 * @return array
	 */
	public static function layout_payload( array $tree, $mode = '' ) {
		$mode      = in_array( $mode, array( 'container', 'section' ), true ) ? $mode : Layout_Elementor::detect_mode();
		$elementor = new Layout_Elementor( $mode );
		$wpbakery  = new Layout_WPBakery();
		return array(
			'format'             => 'layout',
			'output'             => $tree['title'],
			'tree'               => $tree,
			'elementor_mode'     => $mode,
			'elementor'          => $elementor->convert( $tree ),
			'elementor_template' => $elementor->template( $tree ),
			'wpbakery'           => $wpbakery->convert( $tree ),
		);
	}

	/**
	 * Expands a template into task + prompt.
	 *
	 * @param array $req Request.
	 * @return array|\WP_Error
	 */
	private function apply_template( array $req ) {
		foreach ( Templates::all() as $tpl ) {
			if ( $tpl['id'] === $req['template_id'] ) {
				$values = isset( $req['values'] ) && is_array( $req['values'] ) ? $req['values'] : array();
				foreach ( $tpl['fields'] as $field ) {
					if ( empty( $values[ $field['key'] ] ) && ! empty( $field['default'] ) ) {
						$values[ $field['key'] ] = $field['default'];
					}
				}
				$rendered      = Templates::render( $tpl['prompt'], $values );
				$req['task']   = $tpl['task'];
				$req['prompt'] = trim( $rendered . ( ! empty( $req['prompt'] ) ? "\n\n" . $req['prompt'] : '' ) );
				// The target field's format wins; the template's only applies when the caller has none.
				if ( 'json' !== $tpl['format'] && empty( $req['format'] ) ) {
					$req['format'] = $tpl['format'];
				}
				return $req;
			}
		}
		return new \WP_Error( 'dev3cl_template_missing', __( 'That template no longer exists.', 'dev3-ai-content-layout-builder' ), array( 'status' => 404 ) );
	}
}
