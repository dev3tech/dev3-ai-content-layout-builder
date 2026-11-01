<?php
/**
 * REST permissions, nonce and the generate pipeline with a fake Gemini.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

use Dev3\AiContentLayoutBuilder\Core\Settings;

class RestTest extends WP_UnitTestCase {

	private $editor;
	private $subscriber;

	public function set_up() {
		parent::set_up();
		$this->editor     = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Settings::update( array( 'api_key' => 'test-key', 'rate_limit' => 2 ) );
		do_action( 'rest_api_init' );
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) {
				if ( false === strpos( $url, 'generativelanguage' ) ) {
					return $pre;
				}
				$body = json_decode( $args['body'], true );
				$text = isset( $body['generationConfig']['responseSchema'] ) ? '{"items":["One","Two"]}' : '**Fresh** coffee';
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => $text ) ) ), 'finishReason' => 'STOP' ) ), 'usageMetadata' => array( 'totalTokenCount' => 7 ) ) ),
					'response' => array( 'code' => 200 ),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		global $wpdb;
		$wpdb->query( "TRUNCATE {$wpdb->prefix}dev3cl_usage" ); // phpcs:ignore
		$wpdb->query( "TRUNCATE {$wpdb->prefix}dev3cl_history" ); // phpcs:ignore
		parent::tear_down();
	}

	private function call( $method, $route, $params = array(), $nonce = true ) {
		$req = new WP_REST_Request( $method, '/dev3cl/v1/' . $route );
		if ( $nonce ) {
			$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		}
		foreach ( $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		return rest_do_request( $req );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber );
		$this->assertSame( 403, $this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'x' ) )->get_status() );
		wp_set_current_user( $this->editor );
		$this->assertSame( 403, $this->call( 'GET', 'settings' )->get_status(), 'editors cannot read settings' );
		$this->assertSame( 403, $this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'x' ), false )->get_status(), 'nonce required' );
	}

	public function test_generate_plain_logs_usage_and_history() {
		wp_set_current_user( $this->editor );
		$res = $this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'Coffee', 'format' => 'plain', 'field' => 'heading' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'Fresh coffee', $res->get_data()['output'] );
		$history = $this->call( 'GET', 'history' )->get_data();
		$this->assertSame( 1, $history['total'] );
		$this->assertArrayNotHasKey( 'api_key', Settings::public_settings() );
	}

	public function test_json_task_and_rate_limit() {
		wp_set_current_user( $this->editor );
		$res = $this->call( 'POST', 'generate', array( 'task' => 'headline_variations', 'prompt' => 'Coffee' ) );
		$this->assertSame( array( 'One', 'Two' ), $res->get_data()['output'] );
		$this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'Coffee' ) );
		$limited = $this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'Coffee' ) );
		$this->assertSame( 429, $limited->get_status() );
		$this->assertSame( 'dev3cl_rate_limited', $limited->get_data()['code'] );
		$this->assertTrue( $limited->get_data()['retryable'] );
	}

	public function test_history_is_private_per_user() {
		wp_set_current_user( $this->editor );
		$this->call( 'POST', 'generate', array( 'task' => 'generate', 'prompt' => 'Coffee' ) );
		$other = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $other );
		$this->assertSame( 0, $this->call( 'GET', 'history' )->get_data()['total'] );
	}
}
