<?php
/**
 * Settings, key validation and model list.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Rest;

use Dev3\AiContentLayoutBuilder\Api\Gemini_Client;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * /settings, /settings/validate, /models
 */
class Settings_Controller extends Base_Controller {

	const MODELS_TRANSIENT = 'dev3cl_models';

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/settings/validate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_key' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'api_key' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/models',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'models' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'refresh' => array( 'type' => 'boolean' ),
				),
			)
		);
	}

	/**
	 * GET /settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return $this->respond( Settings::public_settings() );
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ) {
		$input   = (array) $request->get_json_params();
		$old_key = Settings::api_key();
		$saved   = Settings::update( $input );
		if ( Settings::api_key() !== $old_key ) {
			delete_transient( self::MODELS_TRANSIENT );
		}
		return $this->respond( $saved );
	}

	/**
	 * POST /settings/validate — live connection test with the typed key (or the stored one).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function validate_key( \WP_REST_Request $request ) {
		$key = trim( (string) $request->get_param( 'api_key' ) );
		if ( '' === $key || false !== strpos( $key, '•' ) ) {
			$key = Settings::api_key();
		}
		$client = new Gemini_Client( $key );
		$models = $client->list_models();
		if ( is_wp_error( $models ) ) {
			return $this->respond( $models );
		}
		if ( Settings::api_key() === $key ) {
			set_transient( self::MODELS_TRANSIENT, $models, 12 * HOUR_IN_SECONDS );
		}
		return $this->respond(
			array(
				'valid'  => true,
				'models' => $models,
			)
		);
	}

	/**
	 * GET /models — cached for 12h.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function models( \WP_REST_Request $request ) {
		$cached = get_transient( self::MODELS_TRANSIENT );
		if ( is_array( $cached ) && ! $request->get_param( 'refresh' ) ) {
			return $this->respond( $cached );
		}
		$client = new Gemini_Client();
		$models = $client->list_models();
		if ( ! is_wp_error( $models ) ) {
			set_transient( self::MODELS_TRANSIENT, $models, 12 * HOUR_IN_SECONDS );
		}
		return $this->respond( $models );
	}
}
