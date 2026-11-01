<?php
/**
 * Layout tree → WPBakery shortcodes.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Uses core WPBakery elements only (vc_row, vc_column, vc_custom_heading, vc_column_text,
 * vc_btn, vc_single_image, vc_tta_*, vc_separator, vc_empty_space, vc_raw_html, vc_icon).
 */
class Layout_WPBakery {

	/**
	 * WPBakery column fractions and their percentages.
	 */
	const FRACTIONS = array(
		'1/12'  => 8.333,
		'1/6'   => 16.667,
		'1/5'   => 20,
		'1/4'   => 25,
		'1/3'   => 33.333,
		'5/12'  => 41.667,
		'1/2'   => 50,
		'7/12'  => 58.333,
		'2/3'   => 66.667,
		'3/4'   => 75,
		'4/5'   => 80,
		'5/6'   => 83.333,
		'11/12' => 91.667,
		'1/1'   => 100,
	);

	/**
	 * Converts a normalized tree.
	 *
	 * @param array $tree Normalized tree.
	 * @return string Shortcode markup.
	 */
	public function convert( array $tree ) {
		$out = array();
		foreach ( $tree['sections'] as $section ) {
			$out[] = $this->row( $section );
		}
		return implode( "\n", $out );
	}

	/**
	 * Nearest WPBakery fraction for a percentage.
	 *
	 * @param float $percent Width in %.
	 * @return string
	 */
	public static function fraction( $percent ) {
		$best  = '1/1';
		$delta = PHP_FLOAT_MAX;
		foreach ( self::FRACTIONS as $fraction => $value ) {
			$d = abs( $value - (float) $percent );
			if ( $d < $delta ) {
				$delta = $d;
				$best  = $fraction;
			}
		}
		return $best;
	}

	/**
	 * Row.
	 *
	 * @param array $section Section.
	 * @return string
	 */
	private function row( array $section ) {
		$css  = 'padding-top: 80px !important;padding-bottom: 80px !important;';
		$dark = 'dark' === $section['background'];
		if ( 'light' === $section['background'] ) {
			$css .= 'background-color: ' . Layout_Elementor::LIGHT_BG . ' !important;';
		} elseif ( $dark ) {
			$css .= 'background-color: ' . Layout_Elementor::DARK_BG . ' !important;';
		}
		$attrs = array(
			'css'      => '.vc_custom_' . self::css_id() . '{' . $css . '}',
			'el_class' => $dark ? 'dev3cl-dark' : '',
		);
		if ( '' !== $section['name'] ) {
			$attrs['el_id'] = sanitize_title( $section['name'] );
		}
		$cols = array();
		foreach ( $section['columns'] as $col ) {
			$cols[] = $this->column( $col, $dark );
		}
		return self::tag( 'vc_row', $attrs, "\n" . implode( "\n", $cols ) . "\n" );
	}

	/**
	 * Column.
	 *
	 * @param array $col  Column.
	 * @param bool  $dark Dark row.
	 * @return string
	 */
	private function column( array $col, $dark ) {
		$attrs = array( 'width' => self::fraction( $col['width'] ) );
		if ( $col['featured'] && ! $dark ) {
			$attrs['css'] = '.vc_custom_' . self::css_id() . '{padding: 24px !important;background-color: ' . Layout_Elementor::FEATURED_BG . ' !important;border: 2px solid ' . Layout_Elementor::ACCENT . ' !important;border-radius: 12px !important;}';
		}
		$inner = array();
		foreach ( $col['widgets'] as $w ) {
			$inner[] = $this->widget( $w, $dark );
		}
		return self::tag( 'vc_column', $attrs, implode( '', $inner ) );
	}

	/**
	 * Widget.
	 *
	 * @param array $w    Widget.
	 * @param bool  $dark Dark row.
	 * @return string
	 */
	private function widget( array $w, $dark ) {
		$align = '' !== $w['align'] ? $w['align'] : 'left';
		switch ( $w['type'] ) {
			case 'heading':
				return self::heading( $w['text'], $w['level'], $align, $dark );
			case 'text':
				return self::text( '' !== $w['html'] ? $w['html'] : '<p>' . esc_html( $w['text'] ) . '</p>', $align, $dark );
			case 'button':
				return self::button( $w['text'], $w['url'], $align, $w['style'] );
			case 'image':
				return self::tag(
					'vc_single_image',
					array(
						'source'     => 'external_link',
						'custom_src' => Layout_Elementor::placeholder(),
						'alignment'  => 'left' === $align ? '' : $align,
						'title'      => $w['image_alt'],
					)
				);
			case 'icon_list':
				return self::text( self::list_html( $w['items'] ), $align, $dark );
			case 'icon_box':
				$item = isset( $w['items'][0] ) ? $w['items'][0] : array(
					'title' => $w['text'],
					'text'  => $w['html'],
				);
				$box  = 'left' === $align ? 'center' : $align;
				return self::tag(
					'vc_icon',
					array(
						'type'             => 'fontawesome',
						'icon_fontawesome' => $w['icon'],
						'color'            => 'custom',
						'custom_color'     => Layout_Elementor::ACCENT,
						'size'             => 'lg',
						'align'            => $box,
					)
				)
					. self::heading( '' !== $item['title'] ? $item['title'] : $w['text'], 'h3', $box, $dark )
					. self::text( '<p>' . wp_strip_all_tags( '' !== $item['text'] ? $item['text'] : $w['html'] ) . '</p>', $box, $dark );
			case 'price':
				return $this->price( $w['price'], 'left' === $align ? 'center' : $align, $dark );
			case 'period_toggle':
				return self::raw_html( Layout_Markup::toggle( $w['labels'] ) );
			case 'accordion':
			case 'tabs':
				$sections = '';
				foreach ( $w['items'] as $item ) {
					$sections .= self::tag(
						'vc_tta_section',
						array(
							'title'  => $item['title'],
							'tab_id' => 'dev3cl-' . Layout_Elementor::id(),
						),
						self::text( '<p>' . $item['text'] . '</p>', 'left', false )
					);
				}
				return self::tag( 'accordion' === $w['type'] ? 'vc_tta_accordion' : 'vc_tta_tabs', array( 'active_section' => '1' ), $sections );
			case 'testimonial':
				$quote = '' !== $w['text'] ? $w['text'] : wp_strip_all_tags( $w['html'] );
				return self::text(
					'<blockquote><p>' . esc_html( $quote ) . '</p></blockquote><p><strong>' . esc_html( $w['author'] ) . '</strong>' . ( '' !== $w['role'] ? '<br>' . esc_html( $w['role'] ) : '' ) . '</p>',
					'left' === $align ? 'center' : $align,
					$dark
				);
			case 'counter':
				return self::heading( $w['number'] . $w['suffix'], 'h2', 'center', $dark ) . self::text( '<p>' . esc_html( $w['text'] ) . '</p>', 'center', $dark );
			case 'divider':
				return self::tag( 'vc_separator', array() );
			case 'spacer':
				return self::tag( 'vc_empty_space', array( 'height' => '40px' ) );
			case 'html':
				return self::raw_html( $w['html'] );
		}
		return '';
	}

	/**
	 * Price card.
	 *
	 * @param array  $p     Price.
	 * @param string $align Alignment.
	 * @param bool   $dark  Dark row.
	 * @return string
	 */
	private function price( array $p, $align, $dark ) {
		$out = '';
		if ( $p['featured'] ) {
			$out .= self::text( '<p><strong style="color:' . Layout_Elementor::ACCENT . '">' . esc_html__( 'Most popular', 'dev3-ai-content-layout-builder' ) . '</strong></p>', $align, $dark );
		}
		$out .= self::heading( $p['plan'], 'h3', $align, $dark );
		$out .= self::text( Layout_Markup::price( $p ), $align, $dark );
		if ( $p['features'] ) {
			$items = array();
			foreach ( $p['features'] as $f ) {
				$items[] = array(
					'title' => '',
					'text'  => $f,
				);
			}
			$out .= self::text( self::list_html( $items ), 'left', $dark );
		}
		if ( '' !== $p['button'] ) {
			$out .= self::button( $p['button'], '#', $align, $p['featured'] ? 'primary' : 'secondary', true );
		}
		return $out;
	}

	/**
	 * Heading.
	 *
	 * @param string $text  Text.
	 * @param string $level h1..h6.
	 * @param string $align Alignment.
	 * @param bool   $dark  White text.
	 * @return string
	 */
	private static function heading( $text, $level, $align, $dark ) {
		$font = 'tag:' . $level . '|text_align:' . $align . ( $dark ? '|color:%23ffffff' : '' );
		return self::tag(
			'vc_custom_heading',
			array(
				'text'            => $text,
				'font_container'  => $font,
				'use_theme_fonts' => 'yes',
			)
		);
	}

	/**
	 * Text block.
	 *
	 * @param string $html  HTML.
	 * @param string $align Alignment.
	 * @param bool   $dark  Light text.
	 * @return string
	 */
	private static function text( $html, $align, $dark ) {
		$style = array();
		if ( 'left' !== $align ) {
			$style[] = 'text-align:' . $align;
		}
		if ( $dark ) {
			$style[] = 'color:#D1D5DB';
		}
		if ( $style ) {
			$html = '<div style="' . esc_attr( implode( ';', $style ) ) . '">' . $html . '</div>';
		}
		return self::tag( 'vc_column_text', array(), self::content( $html ) );
	}

	/**
	 * Button.
	 *
	 * @param string $text  Label.
	 * @param string $url   URL.
	 * @param string $align Alignment.
	 * @param string $style primary|secondary.
	 * @param bool   $block Full width.
	 * @return string
	 */
	private static function button( $text, $url, $align, $style, $block = false ) {
		$attrs = array(
			'title' => $text,
			'align' => $align,
			'link'  => 'url:' . rawurlencode( $url ) . '|title:' . rawurlencode( $text ),
		);
		if ( 'secondary' === $style ) {
			$attrs['style']                           = 'outline-custom';
			$attrs['outline_custom_color']            = Layout_Elementor::ACCENT;
			$attrs['outline_custom_hover_background'] = Layout_Elementor::ACCENT;
			$attrs['outline_custom_hover_text']       = '#ffffff';
		} else {
			$attrs['style']             = 'custom';
			$attrs['custom_background'] = Layout_Elementor::ACCENT;
			$attrs['custom_text']       = '#ffffff';
		}
		if ( $block ) {
			$attrs['button_block'] = 'true';
		}
		return self::tag( 'vc_btn', $attrs );
	}

	/**
	 * Raw HTML block (WPBakery stores it base64 + urlencoded).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function raw_html( $html ) {
		return self::tag( 'vc_raw_html', array(), base64_encode( rawurlencode( $html ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * UL from items.
	 *
	 * @param array $items Items.
	 * @return string
	 */
	private static function list_html( array $items ) {
		$li = '';
		foreach ( $items as $item ) {
			$label = Layout_Markup::item_label( $item );
			$title = isset( $item['title'] ) ? trim( wp_strip_all_tags( $item['title'] ) ) : '';
			// Bold the title part when the label is "Title — text".
			$li .= '' !== $title && 0 === strpos( $label, $title . ' — ' )
				? '<li><strong>' . esc_html( $title ) . '</strong> ' . esc_html( substr( $label, strlen( $title ) + 5 ) ) . '</li>'
				: '<li>' . esc_html( $label ) . '</li>';
		}
		return '<ul>' . $li . '</ul>';
	}

	/**
	 * Shortcode tag with escaped attributes.
	 *
	 * @param string      $name    Tag.
	 * @param array       $attrs   Attributes.
	 * @param string|null $content Enclosed content, null for self-closing.
	 * @return string
	 */
	public static function tag( $name, array $attrs, $content = null ) {
		$parts = '';
		foreach ( $attrs as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$parts .= ' ' . $key . '="' . self::attr( (string) $value ) . '"';
		}
		$open = '[' . $name . $parts . ']';
		return null === $content ? $open : $open . $content . '[/' . $name . ']';
	}

	/**
	 * Shortcode attribute escaping: WPBakery stores a double quote as ``, and brackets would break parsing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function attr( $value ) {
		return str_replace( array( '"', '[', ']', "\n", "\r" ), array( '``', '&#91;', '&#93;', ' ', '' ), $value );
	}

	/**
	 * Enclosed content cannot contain raw brackets.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function content( $html ) {
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $html );
	}

	/**
	 * Numeric id for vc_custom_ CSS classes.
	 *
	 * @return string
	 */
	private static function css_id() {
		return (string) ( time() * 1000 + wp_rand( 0, 999 ) ) . wp_rand( 10, 99 );
	}
}
