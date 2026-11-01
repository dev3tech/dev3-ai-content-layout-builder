<?php
/**
 * Settings, encryption and capabilities.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

use Dev3\AiContentLayoutBuilder\Core\Encryption;
use Dev3\AiContentLayoutBuilder\Core\Settings;

class CoreTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		Settings::flush();
	}

	public function test_encryption_round_trip_and_tamper() {
		$cipher = Encryption::encrypt( 'AIza-secret-key' );
		$this->assertStringStartsWith( 'dev3cl1:', $cipher );
		$this->assertStringNotContainsString( 'secret', $cipher );
		$this->assertSame( 'AIza-secret-key', Encryption::decrypt( $cipher ) );
		$this->assertNotSame( $cipher, Encryption::encrypt( 'AIza-secret-key' ), 'random IV' );
		$tampered = substr( $cipher, 0, -2 ) . ( 'A' === substr( $cipher, -2, 1 ) ? 'B' : 'A' ) . substr( $cipher, -1 );
		$this->assertSame( '', Encryption::decrypt( $tampered ) );
		$this->assertSame( '', Encryption::decrypt( '' ) );
	}

	public function test_api_key_is_encrypted_masked_and_kept_on_placeholder() {
		$public = Settings::update( array( 'api_key' => 'AIzaABCDEF1234' ) );
		$this->assertTrue( $public['api_key_set'] );
		$this->assertArrayNotHasKey( 'api_key', $public );
		$this->assertSame( '••••••••1234', $public['api_key_masked'] );
		$this->assertStringStartsWith( 'dev3cl1:', get_option( Settings::OPTION )['api_key'] );
		Settings::update( array( 'api_key' => '••••••••1234' ) );
		$this->assertSame( 'AIzaABCDEF1234', Settings::api_key(), 'masked value means unchanged' );
		Settings::update( array( 'api_key' => '' ) );
		$this->assertSame( '', Settings::api_key() );
	}

	public function test_sanitize_clamps_and_rejects() {
		Settings::update(
			array(
				'temperature'   => 3,
				'max_tokens'    => 10,
				'default_model' => 'models/Gemini-2.5-Flash',
				'pro_model'     => 'bad model!',
				'default_tone'  => 'evil',
				'rate_limit'    => -5,
				'task_models'   => array( 'layout' => 'default', 'vision' => 'gemini-2.5-flash-lite' ),
				'integrations'  => array( 'elementor' => 0, 'unknown' => 1 ),
			)
		);
		$s = Settings::all();
		$this->assertSame( 1.0, $s['temperature'] );
		$this->assertSame( 64, $s['max_tokens'] );
		$this->assertSame( 'gemini-2.5-flash', $s['default_model'] );
		$this->assertSame( 'gemini-2.5-pro', $s['pro_model'] );
		$this->assertSame( 'professional', $s['default_tone'] );
		$this->assertSame( 0, $s['rate_limit'] );
		$this->assertFalse( $s['integrations']['elementor'] );
		$this->assertArrayNotHasKey( 'unknown', $s['integrations'] );
		$this->assertSame( 'gemini-2.5-flash', Settings::model_for( 'layout' ) );
		$this->assertSame( 'gemini-2.5-flash-lite', Settings::model_for( 'vision' ) );
		$this->assertSame( 'gemini-2.5-flash', Settings::model_for( 'copy' ) );
	}
}
