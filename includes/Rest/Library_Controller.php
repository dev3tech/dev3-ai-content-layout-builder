<?php
/**
 * Templates, history and usage.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Rest;

use Dev3\AiContentLayoutBuilder\Data\History_Repository;
use Dev3\AiContentLayoutBuilder\Data\Templates;
use Dev3\AiContentLayoutBuilder\Data\Usage_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * /templates, /history, /usage
 */
class Library_Controller extends Base_Controller {

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/templates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'templates' ),
					'permission_callback' => array( $this, 'can_generate' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_template' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/templates/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_template' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/history',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'history' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'task'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_history' ),
					'permission_callback' => array( $this, 'can_generate' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/history/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_history' ),
				'permission_callback' => array( $this, 'can_generate' ),
			)
		);
		register_rest_route(
			self::NS,
			'/usage',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'usage' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'days' => array(
						'type'    => 'integer',
						'default' => 30,
						'minimum' => 1,
						'maximum' => 365,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/usage/recent',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'usage_recent' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'page' => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * GET /templates.
	 *
	 * @return \WP_REST_Response
	 */
	public function templates() {
		return $this->respond( Templates::all() );
	}

	/**
	 * POST /templates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_template( \WP_REST_Request $request ) {
		return $this->respond( Templates::save( (array) $request->get_json_params() ) );
	}

	/**
	 * DELETE /templates/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_template( \WP_REST_Request $request ) {
		$ok = Templates::delete( sanitize_key( $request['id'] ) );
		return $ok ? $this->respond( array( 'deleted' => true ) ) : $this->respond( new \WP_Error( 'dev3cl_not_found', __( 'Template not found (built-in templates cannot be deleted).', 'dev3-ai-content-layout-builder' ), array( 'status' => 404 ) ) );
	}

	/**
	 * GET /history.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function history( \WP_REST_Request $request ) {
		return $this->respond(
			History_Repository::list_for(
				get_current_user_id(),
				(int) $request['page'],
				(int) $request['per_page'],
				(string) $request['task'],
				(string) $request['search']
			)
		);
	}

	/**
	 * DELETE /history/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_history( \WP_REST_Request $request ) {
		return $this->respond( array( 'deleted' => History_Repository::delete( get_current_user_id(), (int) $request['id'] ) ) );
	}

	/**
	 * DELETE /history — clears the current user's history.
	 *
	 * @return \WP_REST_Response
	 */
	public function clear_history() {
		return $this->respond( array( 'deleted' => History_Repository::delete( get_current_user_id() ) ) );
	}

	/**
	 * GET /usage.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function usage( \WP_REST_Request $request ) {
		return $this->respond( Usage_Repository::summary( (int) $request['days'] ) );
	}

	/**
	 * GET /usage/recent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function usage_recent( \WP_REST_Request $request ) {
		return $this->respond( Usage_Repository::recent( (int) $request['page'] ) );
	}
}
