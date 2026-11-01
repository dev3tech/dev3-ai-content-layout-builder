<?php
/**
 * Media Library integration: AI alt text, captions and titles.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Integrations\Media;

use Dev3\AiContentLayoutBuilder\AI\Generator;
use Dev3\AiContentLayoutBuilder\Assets;
use Dev3\AiContentLayoutBuilder\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Generate with Gemini" field to attachment details and a bulk action in list mode.
 */
class Media {

	const BULK_ACTION = 'dev3cl_generate_alt';
	const BULK_LIMIT  = 10;

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_filter( 'attachment_fields_to_edit', array( $this, 'field' ), 20, 2 );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_on_attachment_screen' ) );
		add_filter( 'bulk_actions-upload', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );
	}

	/**
	 * Attachment details field.
	 *
	 * @param array    $fields Fields.
	 * @param \WP_Post $post   Attachment.
	 * @return array
	 */
	public function field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post ) || ! Capabilities::can_generate() ) {
			return $fields;
		}
		$fields['dev3cl_ai'] = array(
			'label' => __( 'DEV3 AI Assistant', 'dev3-ai-content-layout-builder' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button dev3cl-media-launch" data-id="%1$d"><span class="dev3cl-wand-icon" aria-hidden="true"></span> %2$s</button>',
				(int) $post->ID,
				esc_html__( 'Generate alt text, caption & title', 'dev3-ai-content-layout-builder' )
			),
		);
		return $fields;
	}

	/**
	 * Loads the bridge wherever the media modal is used.
	 */
	public function enqueue() {
		Assets::enqueue_editor( 'media' );
	}

	/**
	 * The classic "Edit Media" screen does not call wp_enqueue_media.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_on_attachment_screen( $hook ) {
		if ( 'post.php' === $hook && 'attachment' === get_post_type() ) {
			Assets::enqueue_editor( 'media' );
		}
		if ( 'upload.php' === $hook ) {
			Assets::enqueue_editor( 'media' );
		}
	}

	/**
	 * Bulk action entry.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		if ( Capabilities::can_generate() ) {
			$actions[ self::BULK_ACTION ] = __( 'Generate missing alt text (AI)', 'dev3-ai-content-layout-builder' );
		}
		return $actions;
	}

	/**
	 * Processes up to BULK_LIMIT images that have no alt text.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $ids      Attachment ids.
	 * @return string
	 */
	public function handle_bulk( $redirect, $action, $ids ) {
		if ( self::BULK_ACTION !== $action || ! Capabilities::can_generate() ) {
			return $redirect;
		}
		$done      = 0;
		$skipped   = 0;
		$failed    = 0;
		$generator = new Generator();
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) || '' !== (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) || $done + $failed >= self::BULK_LIMIT ) {
				++$skipped;
				continue;
			}
			$result = $generator->run(
				array(
					'task'          => 'vision',
					'attachment_id' => $id,
					'vision_fields' => array( 'alt' ),
					'context'       => 'media_bulk',
				)
			);
			if ( is_wp_error( $result ) || empty( $result['output']['alt'] ) ) {
				++$failed;
				continue;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $result['output']['alt'] ) );
			++$done;
		}
		return add_query_arg(
			array(
				'dev3cl_alt_done'    => $done,
				'dev3cl_alt_skipped' => $skipped,
				'dev3cl_alt_failed'  => $failed,
			),
			$redirect
		);
	}

	/**
	 * Result notice after the bulk action.
	 */
	public function bulk_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of counts.
		if ( ! isset( $_GET['dev3cl_alt_done'] ) ) {
			return;
		}
		$done    = absint( $_GET['dev3cl_alt_done'] );
		$skipped = isset( $_GET['dev3cl_alt_skipped'] ) ? absint( $_GET['dev3cl_alt_skipped'] ) : 0;
		$failed  = isset( $_GET['dev3cl_alt_failed'] ) ? absint( $_GET['dev3cl_alt_failed'] ) : 0;
		// phpcs:enable
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			$failed ? 'warning' : 'success',
			esc_html(
				sprintf(
					/* translators: 1: images updated, 2: skipped, 3: failed, 4: per-run limit. */
					__( 'AI wrote alt text for %1$d image(s). Skipped %2$d (already had alt text, not images, or over the %4$d-per-run limit). Failed %3$d.', 'dev3-ai-content-layout-builder' ),
					$done,
					$skipped,
					$failed,
					self::BULK_LIMIT
				)
			)
		);
	}
}
