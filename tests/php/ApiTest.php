<?php
/**
 * Gemini client parsing, error mapping and HTTP behaviour (no network).
 *
 * @package Dev3\AiContentLayoutBuilder
 */

use Dev3\AiContentLayoutBuilder\Api\Api_Error;
use Dev3\AiContentLayoutBuilder\Api\Gemini_Client;

class ApiTest extends WP_UnitTestCase {

	private $last_request = null;

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function fake( $status, $body ) {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $status, $body ) {
				$this->last_request = array( 'url' => $url, 'args' => $args );
				return array(
					'headers'  => array(),
					'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
					'response' => array( 'code' => $status, 'message' => '' ),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
	}

	public function test_generate_sends_key_in_header_and_parses_usage() {
		$this->fake(
			200,
			array(
				'candidates'    => array( array( 'content' => array( 'parts' => array( array( 'text' => 'thinking…', 'thought' => true ), array( 'text' => 'Hello' ) ) ), 'finishReason' => 'STOP' ) ),
				'usageMetadata' => array( 'promptTokenCount' => 10, 'candidatesTokenCount' => 3, 'thoughtsTokenCount' => 2, 'totalTokenCount' => 15 ),
				'modelVersion'  => 'gemini-2.5-flash',
			)
		);
		$client = new Gemini_Client( 'KEY123' );
		$res    = $client->generate( array( 'model' => 'gemini-2.5-flash', 'prompt' => 'Hi', 'system' => 'Be nice' ) );
		$this->assertSame( 'Hello', $res['text'] );
		$this->assertSame( array( 'prompt' => 10, 'output' => 5, 'total' => 15 ), $res['usage'] );
		$this->assertSame( 'KEY123', $this->last_request['args']['headers']['x-goog-api-key'] );
		$this->assertStringNotContainsString( 'KEY123', $this->last_request['url'], 'key never in URL' );
		$body = json_decode( $this->last_request['args']['body'], true );
		$this->assertSame( 0, $body['generationConfig']['thinkingConfig']['thinkingBudget'] );
		$this->assertSame( 'Be nice', $body['systemInstruction']['parts'][0]['text'] );
	}

	public function test_missing_key() {
		$client = new Gemini_Client( '' );
		$this->assertSame( 'dev3cl_no_api_key', $client->generate( array( 'prompt' => 'x' ) )->get_error_code() );
	}

	/**
	 * @dataProvider error_cases
	 */
	public function test_error_mapping( $http, $body, $code, $status ) {
		$err = Api_Error::from_response( $http, $body );
		$this->assertSame( $code, $err->get_error_code() );
		$this->assertSame( $status, $err->get_error_data()['status'] );
		$this->assertNotEmpty( $err->get_error_data()['hint'] );
	}

	public function error_cases() {
		return array(
			'invalid key' => array( 400, array( 'error' => array( 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT', 'details' => array( array( 'reason' => 'API_KEY_INVALID' ) ) ) ), 'dev3cl_invalid_key', 401 ),
			'quota'       => array( 429, array( 'error' => array( 'message' => 'Quota exceeded for metric per day', 'status' => 'RESOURCE_EXHAUSTED' ) ), 'dev3cl_quota', 429 ),
			'forbidden'   => array( 403, array( 'error' => array( 'status' => 'PERMISSION_DENIED' ) ), 'dev3cl_forbidden', 403 ),
			'model'       => array( 404, array( 'error' => array( 'status' => 'NOT_FOUND' ) ), 'dev3cl_model_not_found', 400 ),
			'upstream'    => array( 503, array(), 'dev3cl_upstream', 503 ),
		);
	}

	public function test_transport_timeout_is_retryable() {
		$err = Api_Error::from_transport( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 90000 milliseconds' ) );
		$this->assertSame( 'dev3cl_timeout', $err->get_error_code() );
		$this->assertTrue( $err->get_error_data()['retryable'] );
	}

	public function test_parse_blocked_and_max_tokens() {
		$this->assertSame( 'dev3cl_blocked', Gemini_Client::parse( array( 'promptFeedback' => array( 'blockReason' => 'SAFETY' ) ), 'm' )->get_error_code() );
		$this->assertSame( 'dev3cl_max_tokens', Gemini_Client::parse( array( 'candidates' => array( array( 'finishReason' => 'MAX_TOKENS', 'content' => array( 'parts' => array() ) ) ) ), 'm' )->get_error_code() );
	}

	public function test_thinking_config() {
		$this->assertSame( array( 'thinkingBudget' => 0 ), Gemini_Client::thinking_config( 'gemini-2.5-flash', 'low' ) );
		$this->assertSame( array( 'thinkingBudget' => 512 ), Gemini_Client::thinking_config( 'gemini-2.5-flash', 'low', true ) );
		$this->assertSame( array( 'thinkingBudget' => 128 ), Gemini_Client::thinking_config( 'gemini-2.5-pro', 'low' ) );
		$this->assertSame( array( 'thinkingLevel' => 'low' ), Gemini_Client::thinking_config( 'gemini-3-flash', 'low' ) );
		$this->assertNull( Gemini_Client::thinking_config( 'gemini-2.5-flash', 'auto' ) );
	}

	public function test_list_models_filters_non_text_models() {
		$this->fake(
			200,
			array(
				'models' => array(
					array( 'name' => 'models/gemini-2.5-flash', 'displayName' => 'Flash', 'supportedGenerationMethods' => array( 'generateContent' ) ),
					array( 'name' => 'models/gemini-2.5-flash-image', 'supportedGenerationMethods' => array( 'generateContent' ) ),
					array( 'name' => 'models/text-embedding-004', 'supportedGenerationMethods' => array( 'embedContent' ) ),
				),
			)
		);
		$models = ( new Gemini_Client( 'k' ) )->list_models();
		$this->assertSame( array( 'gemini-2.5-flash' ), wp_list_pluck( $models, 'id' ) );
	}
}
