<?php
/**
 * Minimal Google Gemini REST client built on the WordPress HTTP API.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Api;

use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps models.generateContent and models.list.
 */
class Gemini_Client {

	const BASE = 'https://generativelanguage.googleapis.com/v1beta/';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Key; defaults to the stored one.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = null === $api_key ? Settings::api_key() : (string) $api_key;
	}

	/**
	 * Runs a generation.
	 *
	 * @param array $args {
	 *     Generation arguments.
	 *     @type string $model       Model id.
	 *     @type string $system      System instruction.
	 *     @type string $prompt      User prompt.
	 *     @type array  $images      List of [ 'mime' => string, 'data' => base64 string ].
	 *     @type float  $temperature 0..1.
	 *     @type int    $max_tokens  Max output tokens.
	 *     @type array  $schema      Optional responseSchema (forces JSON output).
	 *     @type bool   $json        Force application/json without a schema.
	 *     @type int    $candidates  Number of candidates (variations).
	 * }
	 * @return array|\WP_Error { text, texts, model, finish, usage{prompt,output,total}, duration_ms }
	 */
	public function generate( array $args ) {
		if ( '' === $this->api_key ) {
			return Api_Error::missing_key();
		}

		$model = Settings::sanitize_model( isset( $args['model'] ) ? $args['model'] : Settings::get( 'default_model' ) );
		if ( '' === $model ) {
			$model = 'gemini-2.5-flash';
		}

		$parts = array();
		if ( ! empty( $args['images'] ) ) {
			foreach ( $args['images'] as $image ) {
				$parts[] = array(
					'inline_data' => array(
						'mime_type' => $image['mime'],
						'data'      => $image['data'],
					),
				);
			}
		}
		$parts[] = array( 'text' => (string) $args['prompt'] );

		$config = array(
			'temperature'     => isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) Settings::get( 'temperature', 0.7 ),
			'maxOutputTokens' => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : (int) Settings::get( 'max_tokens', 4096 ),
		);
		if ( ! empty( $args['candidates'] ) && (int) $args['candidates'] > 1 ) {
			$config['candidateCount'] = min( 4, (int) $args['candidates'] );
		}
		if ( ! empty( $args['schema'] ) ) {
			$config['responseMimeType'] = 'application/json';
			$config['responseSchema']   = $args['schema'];
		} elseif ( ! empty( $args['json'] ) ) {
			$config['responseMimeType'] = 'application/json';
		}
		$thinking = isset( $args['thinking_budget'] )
			? self::budget_config( $model, (int) $args['thinking_budget'] )
			: self::thinking_config( $model, (string) Settings::get( 'reasoning', 'low' ), ! empty( $args['light_thinking'] ) );
		if ( $thinking ) {
			$config['thinkingConfig'] = $thinking;
		}

		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => $parts,
				),
			),
			'generationConfig' => $config,
		);
		if ( ! empty( $args['system'] ) ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => (string) $args['system'] ) ) );
		}

		/**
		 * Filters the generateContent request body before it is sent.
		 *
		 * @param array $body  Request body.
		 * @param array $args  Original arguments.
		 * @param string $model Model id.
		 */
		$body = apply_filters( 'dev3cl_request_body', $body, $args, $model );

		$started  = microtime( true );
		$response = wp_remote_post(
			self::BASE . 'models/' . rawurlencode( $model ) . ':generateContent',
			array(
				'timeout' => (int) apply_filters( 'dev3cl_request_timeout', isset( $args['timeout'] ) ? (int) $args['timeout'] : 90, $args ),
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $this->api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return Api_Error::from_transport( $response );
		}
		$http    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();
		if ( $http < 200 || $http >= 300 ) {
			return Api_Error::from_response( $http, $decoded );
		}

		return self::parse( $decoded, $model, $duration );
	}

	/**
	 * Parses a successful generateContent response.
	 *
	 * @param array  $decoded  Response body.
	 * @param string $model    Model id.
	 * @param int    $duration Duration in ms.
	 * @return array|\WP_Error
	 */
	public static function parse( array $decoded, $model, $duration = 0 ) {
		if ( ! empty( $decoded['promptFeedback']['blockReason'] ) ) {
			return Api_Error::make(
				'dev3cl_blocked',
				__( 'Gemini blocked this prompt for safety reasons.', 'dev3-ai-content-layout-builder' ),
				__( 'Rephrase the prompt and try again.', 'dev3-ai-content-layout-builder' ),
				422
			);
		}

		$texts  = array();
		$finish = '';
		$cands  = isset( $decoded['candidates'] ) && is_array( $decoded['candidates'] ) ? $decoded['candidates'] : array();
		foreach ( $cands as $cand ) {
			$finish = isset( $cand['finishReason'] ) ? (string) $cand['finishReason'] : $finish;
			$text   = '';
			if ( ! empty( $cand['content']['parts'] ) ) {
				foreach ( $cand['content']['parts'] as $part ) {
					// Skip thought summaries.
					if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
						$text .= $part['text'];
					}
				}
			}
			if ( '' !== trim( $text ) ) {
				$texts[] = $text;
			}
		}

		if ( empty( $texts ) ) {
			if ( 'SAFETY' === $finish || 'PROHIBITED_CONTENT' === $finish || 'RECITATION' === $finish ) {
				return Api_Error::make(
					'dev3cl_blocked',
					__( 'Gemini stopped because the response was flagged.', 'dev3-ai-content-layout-builder' ),
					__( 'Rephrase the prompt and try again.', 'dev3-ai-content-layout-builder' ),
					422
				);
			}
			if ( 'MAX_TOKENS' === $finish ) {
				return Api_Error::make(
					'dev3cl_max_tokens',
					__( 'Gemini ran out of output tokens before writing an answer.', 'dev3-ai-content-layout-builder' ),
					__( 'Increase Max Output Tokens in API Config, or set Reasoning to "Low".', 'dev3-ai-content-layout-builder' ),
					422,
					true
				);
			}
			return Api_Error::make(
				'dev3cl_empty',
				__( 'Gemini returned an empty response.', 'dev3-ai-content-layout-builder' ),
				__( 'Try again or rephrase the prompt.', 'dev3-ai-content-layout-builder' ),
				502,
				true
			);
		}

		$usage = isset( $decoded['usageMetadata'] ) ? $decoded['usageMetadata'] : array();
		return array(
			'text'        => $texts[0],
			'texts'       => $texts,
			'model'       => isset( $decoded['modelVersion'] ) ? (string) $decoded['modelVersion'] : $model,
			'finish'      => $finish,
			'truncated'   => 'MAX_TOKENS' === $finish,
			'usage'       => array(
				'prompt' => isset( $usage['promptTokenCount'] ) ? (int) $usage['promptTokenCount'] : 0,
				'output' => ( isset( $usage['candidatesTokenCount'] ) ? (int) $usage['candidatesTokenCount'] : 0 ) + ( isset( $usage['thoughtsTokenCount'] ) ? (int) $usage['thoughtsTokenCount'] : 0 ),
				'total'  => isset( $usage['totalTokenCount'] ) ? (int) $usage['totalTokenCount'] : 0,
			),
			'duration_ms' => (int) $duration,
		);
	}

	/**
	 * Lists models usable for generateContent.
	 *
	 * @return array|\WP_Error List of { id, name, input_limit, output_limit }.
	 */
	public function list_models() {
		if ( '' === $this->api_key ) {
			return Api_Error::missing_key();
		}
		$models = array();
		$token  = '';
		$pages  = 0;
		do {
			$url      = add_query_arg(
				array_filter(
					array(
						'pageSize'  => 200,
						'pageToken' => $token,
					)
				),
				self::BASE . 'models'
			);
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'x-goog-api-key' => $this->api_key ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return Api_Error::from_transport( $response );
			}
			$http    = (int) wp_remote_retrieve_response_code( $response );
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			if ( 200 !== $http ) {
				return Api_Error::from_response( $http, $decoded );
			}
			foreach ( isset( $decoded['models'] ) ? $decoded['models'] : array() as $m ) {
				$methods = isset( $m['supportedGenerationMethods'] ) ? (array) $m['supportedGenerationMethods'] : array();
				$id      = preg_replace( '#^models/#', '', (string) $m['name'] );
				// Text-capable Gemini models only; skip embedding, image, TTS and live-audio variants.
				if ( ! in_array( 'generateContent', $methods, true ) || 0 !== strpos( $id, 'gemini' ) || preg_match( '/(image|tts|audio|live|embedding)/', $id ) ) {
					continue;
				}
				$models[] = array(
					'id'           => $id,
					'name'         => isset( $m['displayName'] ) ? (string) $m['displayName'] : $id,
					'input_limit'  => isset( $m['inputTokenLimit'] ) ? (int) $m['inputTokenLimit'] : 0,
					'output_limit' => isset( $m['outputTokenLimit'] ) ? (int) $m['outputTokenLimit'] : 0,
				);
			}
			$token = isset( $decoded['nextPageToken'] ) ? (string) $decoded['nextPageToken'] : '';
			++$pages;
		} while ( '' !== $token && $pages < 5 );

		usort(
			$models,
			static function ( $a, $b ) {
				return strcmp( $b['id'], $a['id'] );
			}
		);
		return $models;
	}

	/**
	 * Explicit thinking budget (layouts need real planning to fill a large schema reliably).
	 *
	 * @param string $model  Model id.
	 * @param int    $budget Tokens.
	 * @return array|null
	 */
	public static function budget_config( $model, $budget ) {
		if ( preg_match( '/^gemini-2\.5-/', $model ) ) {
			return array( 'thinkingBudget' => max( 128, $budget ) );
		}
		if ( preg_match( '/^gemini-[3-9]/', $model ) ) {
			return array( 'thinkingLevel' => $budget >= 2048 ? 'high' : 'low' );
		}
		return null;
	}

	/**
	 * Thinking settings keep editor generations snappy when reasoning is "low".
	 *
	 * @param string $model     Model id.
	 * @param string $reasoning low|auto.
	 * @param bool   $light     Keep a small budget instead of none (structured vision output loops without it).
	 * @return array|null
	 */
	public static function thinking_config( $model, $reasoning, $light = false ) {
		if ( 'low' !== $reasoning ) {
			return null;
		}
		if ( preg_match( '/^gemini-2\.5-flash/', $model ) ) {
			return array( 'thinkingBudget' => $light ? 512 : 0 );
		}
		if ( preg_match( '/^gemini-2\.5-pro/', $model ) ) {
			// Pro cannot disable thinking; 128 is its minimum budget.
			return array( 'thinkingBudget' => 128 );
		}
		if ( preg_match( '/^gemini-[3-9]/', $model ) ) {
			return array( 'thinkingLevel' => 'low' );
		}
		return null;
	}
}
