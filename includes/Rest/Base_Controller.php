<?php
/**
 * Shared REST helpers.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Rest;

use Dev3\AiContentLayoutBuilder\Api\Api_Error;
use Dev3\AiContentLayoutBuilder\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Base controller: namespace, nonce + capability checks, error envelope.
 */
abstract class Base_Controller {

	const NS = 'dev3cl/v1';

	/**
	 * Registers routes.
	 */
	abstract public function register_routes();

	/**
	 * Verifies the wp_rest nonce explicitly (cookie auth already requires it, this makes it strict for every route).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	protected function nonce_ok( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return $nonce && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Permission: generate content.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function can_generate( \WP_REST_Request $request ) {
		return $this->permission( $request, Capabilities::can_generate() );
	}

	/**
	 * Permission: manage settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function can_manage( \WP_REST_Request $request ) {
		return $this->permission( $request, Capabilities::can_manage() );
	}

	/**
	 * Combined check.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param bool             $allowed Capability result.
	 * @return bool|\WP_Error
	 */
	private function permission( \WP_REST_Request $request, $allowed ) {
		if ( ! $this->nonce_ok( $request ) ) {
			return new \WP_Error( 'dev3cl_bad_nonce', __( 'Your session has expired. Reload the editor and try again.', 'dev3-ai-content-layout-builder' ), array( 'status' => 403 ) );
		}
		if ( ! $allowed ) {
			return new \WP_Error( 'dev3cl_forbidden', __( 'You do not have permission to do that.', 'dev3-ai-content-layout-builder' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Wraps a result or WP_Error into a response.
	 *
	 * @param mixed $result Result.
	 * @param int   $status Status for success.
	 * @return \WP_REST_Response
	 */
	protected function respond( $result, $status = 200 ) {
		if ( is_wp_error( $result ) ) {
			return Api_Error::to_response( $result );
		}
		return new \WP_REST_Response( $result, $status );
	}
}
