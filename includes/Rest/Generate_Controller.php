<?php
/**
 * Content generation, layout restore and media write-back.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Rest;

use Dev3\AiContentLayoutBuilder\AI\Generator;
use Dev3\AiContentLayoutBuilder\AI\Layout_Schema;
use Dev3\AiContentLayoutBuilder\AI\Prompt_Orchestrator;
use Dev3\AiContentLayoutBuilder\AI\Vision;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * /generate, /layout/convert, /media/{id}
 */
class Generate_Controller extends Base_Controller {

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_generate' ),
				'args'                => $this->generate_args(),
			)
		);
		register_rest_route(
			self::NS,
			'/layout/convert',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'convert_layout' ),
				'permission_callback' => array( $this, 'can_generate' ),
			)
		);
		register_rest_route(
			self::NS,
			'/media/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_media' ),
				'permission_callback' => array( $this, 'can_generate' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Argument schema with sanitizers.
	 *
	 * @return array
	 */
	private function generate_args() {
		$text = static function ( $max ) {
			return static function ( $value ) use ( $max ) {
				return Prompt_Orchestrator::clip( sanitize_textarea_field( (string) $value ), $max );
			};
		};
		return array(
			'task'           => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			),
			'prompt'         => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => $text( 8000 ),
			),
			'text'           => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					return Prompt_Orchestrator::clip( wp_kses_post( (string) $value ), 40000 );
				},
			),
			'tone'           => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'language'       => array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return substr( sanitize_text_field( (string) $value ), 0, 60 );
				},
			),
			'format'         => array(
				'type' => 'string',
				'enum' => array( 'plain', 'html', '' ),
			),
			'field'          => array(
				'type'              => 'string',
				'default'           => 'text',
				'sanitize_callback' => 'sanitize_key',
			),
			'template_id'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'values'         => array(
				'type'              => 'object',
				'sanitize_callback' => static function ( $value ) {
					$out = array();
					foreach ( (array) $value as $k => $v ) {
						if ( is_scalar( $v ) ) {
							$out[ sanitize_key( $k ) ] = Prompt_Orchestrator::clip( sanitize_textarea_field( (string) $v ), 2000 );
						}
					}
					return $out;
				},
			),
			'model'          => array(
				'type'              => 'string',
				'sanitize_callback' => array( Settings::class, 'sanitize_model' ),
			),
			'temperature'    => array(
				'type'    => 'number',
				'minimum' => 0,
				'maximum' => 1,
			),
			'variations'     => array(
				'type'    => 'integer',
				'minimum' => 2,
				'maximum' => 10,
			),
			'context'        => array(
				'type'              => 'string',
				'default'           => 'admin',
				'sanitize_callback' => 'sanitize_key',
			),
			'builder'        => array(
				'type' => 'string',
				'enum' => array( 'elementor', 'wpbakery', 'gutenberg', '' ),
			),
			'elementor_mode' => array(
				'type' => 'string',
				'enum' => array( 'container', 'section', '' ),
			),
			'attachment_id'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'image_url'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'vision_fields'  => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'string',
					'enum' => Vision::FIELDS,
				),
			),
		);
	}

	/**
	 * POST /generate.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function generate( \WP_REST_Request $request ) {
		$keys = array( 'task', 'prompt', 'text', 'tone', 'language', 'format', 'field', 'template_id', 'values', 'model', 'temperature', 'variations', 'context', 'builder', 'elementor_mode', 'attachment_id', 'image_url', 'vision_fields' );
		$req  = array();
		foreach ( $keys as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$req[ $key ] = $value;
			}
		}
		$generator = new Generator();
		return $this->respond( $generator->run( $req ) );
	}

	/**
	 * POST /layout/convert — re-renders a stored tree (history restore, mode switch).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function convert_layout( \WP_REST_Request $request ) {
		$tree = Layout_Schema::normalize( $request->get_param( 'tree' ) );
		if ( is_wp_error( $tree ) ) {
			return $this->respond( $tree );
		}
		return $this->respond( Generator::layout_payload( $tree, (string) $request->get_param( 'elementor_mode' ) ) );
	}

	/**
	 * POST /media/{id} — writes alt/caption/title/description to an attachment.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply_media( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return $this->respond( new \WP_Error( 'dev3cl_forbidden', __( 'You cannot edit this attachment.', 'dev3-ai-content-layout-builder' ), array( 'status' => 403 ) ) );
		}
		$data   = Vision::clean( (array) $request->get_json_params(), array() );
		$update = array( 'ID' => $id );
		if ( isset( $data['title'] ) ) {
			$update['post_title'] = $data['title'];
		}
		if ( isset( $data['caption'] ) ) {
			$update['post_excerpt'] = $data['caption'];
		}
		if ( isset( $data['description'] ) ) {
			$update['post_content'] = $data['description'];
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		if ( isset( $data['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $data['alt'] ) );
		}
		return $this->respond(
			array(
				'id'      => $id,
				'updated' => array_keys( $data ),
			)
		);
	}
}
