<?php
/**
 * Cleans Gemini output before it reaches a page builder.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Strips fences, converts stray Markdown and sanitizes per target.
 */
class Response_Parser {

	/**
	 * Removes ```lang fences and surrounding quotes.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function strip_fences( $text ) {
		$text = trim( (string) $text );
		if ( preg_match( '/^```[a-zA-Z0-9_-]*\s*\n(.*?)\n?```\s*$/s', $text, $m ) ) {
			$text = trim( $m[1] );
		}
		if ( strlen( $text ) > 1 && ( ( '"' === $text[0] && '"' === substr( $text, -1 ) ) || ( '“' === mb_substr( $text, 0, 1 ) && '”' === mb_substr( $text, -1 ) ) ) && false === strpos( mb_substr( $text, 1, -1 ), '"' ) ) {
			$text = mb_substr( $text, 1, -1 );
		}
		return $text;
	}

	/**
	 * Decodes a JSON answer, tolerating fences or leading prose.
	 *
	 * @param string $text Raw text.
	 * @return array|null
	 */
	public static function json( $text ) {
		$text    = self::strip_fences( $text );
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		$start = strcspn( $text, '{[' );
		$end   = max( strrpos( $text, '}' ), strrpos( $text, ']' ) );
		if ( $start < strlen( $text ) && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return null;
	}

	/**
	 * Plain text output: no Markdown, no tags.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = self::strip_fences( $text );
		$text = preg_replace( '/^\s{0,3}#{1,6}\s+/m', '', $text );
		$text = preg_replace( '/(\*\*|__)(.+?)\1/s', '$2', $text );
		$text = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/s', '$1', $text );
		$text = wp_strip_all_tags( $text );
		return sanitize_textarea_field( $text );
	}

	/**
	 * Rich output: Markdown converted when needed, then wp_kses_post.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function html( $text ) {
		$text = self::strip_fences( $text );
		if ( ! preg_match( '/<(p|h[1-6]|ul|ol|li|strong|em|a|br|div|blockquote)\b/i', $text ) ) {
			$text = self::markdown_to_html( $text );
		}
		return trim( wp_kses_post( $text ) );
	}

	/**
	 * Sanitizes according to the target format.
	 *
	 * @param string $text   Raw text.
	 * @param string $format plain|html.
	 * @return string
	 */
	public static function for_format( $text, $format ) {
		return 'html' === $format ? self::html( $text ) : self::plain( $text );
	}

	/**
	 * Small Markdown subset → HTML (headings, lists, bold/italic, links, paragraphs).
	 *
	 * @param string $md Markdown.
	 * @return string
	 */
	public static function markdown_to_html( $md ) {
		$md     = str_replace( array( "\r\n", "\r" ), "\n", (string) $md );
		$blocks = preg_split( '/\n{2,}/', trim( $md ) );
		$html   = array();
		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $block, $m ) && false === strpos( $block, "\n" ) ) {
				$level  = max( 2, min( 4, strlen( $m[1] ) ) );
				$html[] = "<h{$level}>" . self::inline( $m[2] ) . "</h{$level}>";
				continue;
			}
			$lines = explode( "\n", $block );
			if ( count( array_filter( $lines, array( __CLASS__, 'is_bullet' ) ) ) === count( $lines ) ) {
				$items  = array_map(
					static function ( $line ) {
						return '<li>' . self::inline( preg_replace( '/^\s*[-*+]\s+/', '', $line ) ) . '</li>';
					},
					$lines
				);
				$html[] = '<ul>' . implode( '', $items ) . '</ul>';
				continue;
			}
			if ( count( array_filter( $lines, array( __CLASS__, 'is_numbered' ) ) ) === count( $lines ) ) {
				$items  = array_map(
					static function ( $line ) {
						return '<li>' . self::inline( preg_replace( '/^\s*\d+[.)]\s+/', '', $line ) ) . '</li>';
					},
					$lines
				);
				$html[] = '<ol>' . implode( '', $items ) . '</ol>';
				continue;
			}
			$html[] = '<p>' . implode( '<br>', array_map( array( __CLASS__, 'inline' ), $lines ) ) . '</p>';
		}
		return implode( "\n", $html );
	}

	/**
	 * Bullet line test.
	 *
	 * @param string $line Line.
	 * @return bool
	 */
	public static function is_bullet( $line ) {
		return (bool) preg_match( '/^\s*[-*+]\s+/', $line );
	}

	/**
	 * Numbered line test.
	 *
	 * @param string $line Line.
	 * @return bool
	 */
	public static function is_numbered( $line ) {
		return (bool) preg_match( '/^\s*\d+[.)]\s+/', $line );
	}

	/**
	 * Inline Markdown.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function inline( $text ) {
		$text = esc_html( $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/', '<em>$1</em>', $text );
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( $m ) {
				return '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>';
			},
			$text
		);
		return $text;
	}
}
