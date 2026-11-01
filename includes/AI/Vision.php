<?php
/**
 * Image loading and prompts for Gemini Vision.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Loads an attachment or remote URL as an inline image part.
 */
class Vision {

	const MIMES     = array( 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/gif' );
	const MAX_BYTES = 15728640; // 15 MB: inline requests are capped at 20 MB including base64 overhead.

	const FIELDS = array( 'alt', 'caption', 'title', 'description', 'promo' );

	/**
	 * Loads image bytes.
	 *
	 * @param int    $attachment_id Attachment id (0 to use URL).
	 * @param string $url           Remote URL.
	 * @return array|\WP_Error { mime, data(base64), source }
	 */
	public static function load( $attachment_id, $url = '' ) {
		if ( $attachment_id > 0 ) {
			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				return self::error( __( 'That attachment is not an image.', 'dev3-ai-content-layout-builder' ) );
			}
			if ( ! current_user_can( 'edit_post', $attachment_id ) && ! current_user_can( 'upload_files' ) ) {
				return new \WP_Error( 'dev3cl_forbidden', __( 'You cannot read this attachment.', 'dev3-ai-content-layout-builder' ), array( 'status' => 403 ) );
			}
			$path = self::best_local_path( $attachment_id );
			if ( $path && is_readable( $path ) && filesize( $path ) <= self::MAX_BYTES ) {
				$mime = wp_check_filetype( $path );
				$mime = $mime['type'] ? $mime['type'] : get_post_mime_type( $attachment_id );
				if ( ! in_array( $mime, self::MIMES, true ) ) {
					return self::error( __( 'Gemini supports JPEG, PNG, WebP, HEIC and GIF images.', 'dev3-ai-content-layout-builder' ) );
				}
				return array(
					'mime'   => $mime,
					'data'   => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'source' => 'attachment',
				);
			}
			// Offloaded media (S3/CDN): fall back to the public URL.
			$url = (string) wp_get_attachment_image_url( $attachment_id, 'large' );
		}

		$url = esc_url_raw( trim( (string) $url ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return self::error( __( 'Choose an image from the Media Library or enter a valid image URL.', 'dev3-ai-content-layout-builder' ) );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 20,
				'limit_response_size' => self::MAX_BYTES + 1,
				'redirection'         => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::error( __( 'The image URL could not be downloaded.', 'dev3-ai-content-layout-builder' ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || '' === $body ) {
			return self::error( __( 'The image URL did not return an image.', 'dev3-ai-content-layout-builder' ) );
		}
		if ( strlen( $body ) > self::MAX_BYTES ) {
			return self::error( __( 'The image is larger than 15 MB.', 'dev3-ai-content-layout-builder' ) );
		}
		$mime = self::sniff( $body );
		if ( ! $mime ) {
			$header = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
			$mime   = in_array( $header, self::MIMES, true ) ? $header : '';
		}
		if ( ! $mime ) {
			return self::error( __( 'Gemini supports JPEG, PNG, WebP, HEIC and GIF images.', 'dev3-ai-content-layout-builder' ) );
		}
		return array(
			'mime'   => $mime,
			'data'   => base64_encode( $body ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'source' => 'url',
		);
	}

	/**
	 * Prefers the "large" intermediate size to keep requests small.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return string|false
	 */
	private static function best_local_path( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return false;
		}
		$large = image_get_intermediate_size( $attachment_id, 'large' );
		if ( $large && ! empty( $large['path'] ) ) {
			$uploads = wp_get_upload_dir();
			$path    = trailingslashit( $uploads['basedir'] ) . $large['path'];
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		return $file;
	}

	/**
	 * Magic-byte sniffing.
	 *
	 * @param string $bytes File bytes.
	 * @return string
	 */
	public static function sniff( $bytes ) {
		$head = substr( $bytes, 0, 16 );
		if ( "\xFF\xD8\xFF" === substr( $head, 0, 3 ) ) {
			return 'image/jpeg';
		}
		if ( "\x89PNG" === substr( $head, 0, 4 ) ) {
			return 'image/png';
		}
		if ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) ) {
			return 'image/webp';
		}
		if ( 'GIF8' === substr( $head, 0, 4 ) ) {
			return 'image/gif';
		}
		if ( 'ftyp' === substr( $head, 4, 4 ) && in_array( substr( $head, 8, 4 ), array( 'heic', 'heix', 'mif1', 'msf1' ), true ) ) {
			return 'image/heic';
		}
		return '';
	}

	/**
	 * Prompt for the requested fields.
	 *
	 * @param array  $fields Fields wanted.
	 * @param string $extra  Extra user context (product name, page topic…).
	 * @return string
	 */
	public static function prompt( array $fields, $extra = '' ) {
		$fields = array_values( array_intersect( self::FIELDS, $fields ) );
		if ( ! $fields ) {
			$fields = array( 'alt', 'caption', 'title' );
		}
		$spec  = array(
			'alt'         => '"alt": accessible alt text, max 125 characters, describes what matters in the image for a screen-reader user, no "image of"/"picture of".',
			'caption'     => '"caption": one engaging caption sentence suitable under the image.',
			'title'       => '"title": a short, SEO-friendly, context-aware title attribute (max 8 words).',
			'description' => '"description": a 2–3 sentence descriptive paragraph about the image.',
			'promo'       => '"promo": persuasive promotional copy (2–4 sentences) featuring what is shown, with a call to action.',
		);
		$lines = array( 'Analyze the attached image and return JSON with these keys:' );
		foreach ( $fields as $f ) {
			$lines[] = '- ' . $spec[ $f ];
		}
		if ( '' !== trim( $extra ) ) {
			$lines[] = "\nContext from the user (use it, e.g. product or brand names):\n" . $extra;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Response schema.
	 *
	 * @return array
	 */
	public static function response_schema() {
		$descriptions = array(
			'alt'         => 'Accessible alt text, at most 125 characters.',
			'caption'     => 'One caption sentence.',
			'title'       => 'Short title attribute, at most 8 words.',
			'description' => '2-3 sentence description.',
			'promo'       => '2-4 sentence promotional copy with a call to action.',
		);
		// Hard caps stop the runaway repetition flash models sometimes produce in structured output.
		$max_length = array(
			'alt'         => 160,
			'caption'     => 240,
			'title'       => 90,
			'description' => 900,
			'promo'       => 900,
		);
		$props      = array();
		foreach ( self::FIELDS as $f ) {
			$props[ $f ] = array(
				'type'        => 'STRING',
				'description' => $descriptions[ $f ],
				'maxLength'   => (string) $max_length[ $f ],
			);
		}
		return array(
			'type'       => 'OBJECT',
			'properties' => $props,
		);
	}

	/**
	 * Sanitizes the decoded answer.
	 *
	 * @param array $data   Decoded JSON.
	 * @param array $fields Requested fields.
	 * @return array
	 */
	public static function clean( array $data, array $fields ) {
		$out = array();
		foreach ( self::FIELDS as $f ) {
			if ( isset( $data[ $f ] ) && is_string( $data[ $f ] ) && ( ! $fields || in_array( $f, $fields, true ) ) ) {
				$out[ $f ] = 'alt' === $f || 'title' === $f ? sanitize_text_field( $data[ $f ] ) : sanitize_textarea_field( $data[ $f ] );
				if ( 'alt' === $f ) {
					$out[ $f ] = self::clamp( $out[ $f ], 125 );
				} elseif ( 'title' === $f ) {
					$out[ $f ] = self::clamp( $out[ $f ], 70 );
				}
			}
		}
		return $out;
	}

	/**
	 * Shortens at a word boundary, ending on a full stop when one is close.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function clamp( $text, $max ) {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut    = mb_substr( $text, 0, $max );
		$period = mb_strrpos( $cut, '.' );
		if ( false !== $period && $period > $max * 0.6 ) {
			return mb_substr( $cut, 0, $period + 1 );
		}
		$space = mb_strrpos( $cut, ' ' );
		return rtrim( mb_substr( $cut, 0, false !== $space ? $space : $max ), ' ,;:-' );
	}

	/**
	 * Input error.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function error( $message ) {
		return new \WP_Error( 'dev3cl_image', $message, array( 'status' => 400 ) );
	}
}
