<?php
/**
 * Layout tree → Elementor element JSON.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Produces native, editable Elementor free widgets (no Pro dependency).
 */
class Layout_Elementor {

	const LIGHT_BG    = '#F5F6FA';
	const DARK_BG     = '#111827';
	const FEATURED_BG = '#EEF2FF';
	const ACCENT      = '#4F46E5';

	/**
	 * Container (flexbox) or legacy section/column mode.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Constructor.
	 *
	 * @param string $mode container|section.
	 */
	public function __construct( $mode = 'container' ) {
		$this->mode = 'section' === $mode ? 'section' : 'container';
	}

	/**
	 * Detects the active Elementor layout mode.
	 *
	 * @return string
	 */
	public static function detect_mode() {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) ) {
			return \Elementor\Plugin::$instance->experiments->is_feature_active( 'container' ) ? 'container' : 'section';
		}
		return 'container';
	}

	/**
	 * Converts a normalized tree.
	 *
	 * @param array $tree Normalized tree.
	 * @return array List of top-level elements.
	 */
	public function convert( array $tree ) {
		$elements = array();
		foreach ( $tree['sections'] as $section ) {
			$elements[] = $this->section( $section );
		}
		return $elements;
	}

	/**
	 * Elementor template export (Templates → Import).
	 *
	 * @param array $tree Normalized tree.
	 * @return array
	 */
	public function template( array $tree ) {
		return array(
			'version'       => '0.4',
			'title'         => '' !== $tree['title'] ? $tree['title'] : __( 'AI layout', 'dev3-ai-content-layout-builder' ),
			'type'          => 'section',
			'content'       => $this->convert( $tree ),
			'page_settings' => array(),
		);
	}

	/**
	 * Random 7-char hex id like Elementor's own.
	 *
	 * @return string
	 */
	public static function id() {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	/**
	 * Top-level section / container.
	 *
	 * @param array $section Section.
	 * @return array
	 */
	private function section( array $section ) {
		$settings = array(
			'padding' => self::box( 80, 20 ),
		);
		if ( '' !== $section['name'] ) {
			$settings['_title'] = $section['name'];
		}
		if ( 'light' === $section['background'] ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = self::LIGHT_BG;
		} elseif ( 'dark' === $section['background'] ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = self::DARK_BG;
			$settings['heading_color']         = '#FFFFFF';
			$settings['color_text']            = '#D1D5DB';
			$settings['color_link']            = '#A5B4FC';
		}

		$children = array();
		foreach ( $section['columns'] as $col ) {
			$children[] = $this->column( $col, count( $section['columns'] ), 'dark' === $section['background'] );
		}

		if ( 'section' === $this->mode ) {
			$settings['gap'] = 'extended';
			return array(
				'id'       => self::id(),
				'elType'   => 'section',
				'settings' => $settings,
				'elements' => $children,
				'isInner'  => false,
			);
		}

		$settings += array(
			'content_width'  => 'boxed',
			'flex_direction' => 'row',
			'flex_wrap'      => 'wrap',
			'flex_gap'       => array(
				'unit'   => 'px',
				'size'   => 24,
				'column' => '24',
				'row'    => '24',
			),
		);
		return array(
			'id'       => self::id(),
			'elType'   => 'container',
			'settings' => $settings,
			'elements' => $children,
			'isInner'  => false,
		);
	}

	/**
	 * Column / inner container.
	 *
	 * @param array $col   Column.
	 * @param int   $count Columns in the section.
	 * @param bool  $dark  Dark section.
	 * @return array
	 */
	private function column( array $col, $count, $dark ) {
		$widgets = array();
		foreach ( $col['widgets'] as $w ) {
			foreach ( $this->widget( $w ) as $el ) {
				$widgets[] = $el;
			}
		}
		$settings = array();
		if ( $col['featured'] && ! $dark ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = self::FEATURED_BG;
			$settings['border_border']         = 'solid';
			$settings['border_width']          = self::box( 2, 2 );
			$settings['border_color']          = self::ACCENT;
			$settings['border_radius']         = self::box( 12, 12 );
		}

		if ( 'section' === $this->mode ) {
			$settings['_column_size'] = (int) round( $col['width'] );
			$settings['_inline_size'] = null;
			if ( $col['featured'] ) {
				$settings['padding'] = self::box( 24, 24 );
			}
			return array(
				'id'       => self::id(),
				'elType'   => 'column',
				'settings' => $settings,
				'elements' => $widgets,
				'isInner'  => false,
			);
		}

		$gap       = $count > 1 ? 24 * ( $count - 1 ) / $count : 0;
		$settings += array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'width'          => array(
				'unit' => 'custom',
				'size' => sprintf( 'calc(%s%% - %spx)', self::num( $col['width'] ), self::num( $gap ) ),
			),
			'width_mobile'   => array(
				'unit' => '%',
				'size' => 100,
			),
			'padding'        => $col['featured'] ? self::box( 24, 24 ) : self::box( 0, 0 ),
		);
		return array(
			'id'       => self::id(),
			'elType'   => 'container',
			'settings' => $settings,
			'elements' => $widgets,
			'isInner'  => true,
		);
	}

	/**
	 * One neutral widget may expand to several Elementor widgets (price, icon box).
	 *
	 * @param array $w Widget.
	 * @return array
	 */
	private function widget( array $w ) {
		$align = '' !== $w['align'] ? $w['align'] : '';
		switch ( $w['type'] ) {
			case 'heading':
				return array(
					self::el(
						'heading',
						array_filter(
							array(
								'title'       => $w['text'],
								'header_size' => $w['level'],
								'align'       => $align,
							)
						)
					),
				);
			case 'text':
				return array(
					self::el(
						'text-editor',
						array_filter(
							array(
								'editor' => '' !== $w['html'] ? $w['html'] : '<p>' . esc_html( $w['text'] ) . '</p>',
								'align'  => $align,
							)
						)
					),
				);
			case 'button':
				return array( self::button( $w['text'], $w['url'], $align, $w['style'] ) );
			case 'image':
				return array(
					self::el(
						'image',
						array(
							'image'          => array(
								'url' => self::placeholder(),
								'id'  => '',
								'alt' => $w['image_alt'],
							),
							'align'          => '' !== $align ? $align : 'center',
							'caption_source' => 'none',
						)
					),
				);
			case 'icon_list':
				return array( self::el( 'icon-list', array( 'icon_list' => self::icon_items( $w['items'] ) ) ) );
			case 'icon_box':
				$item = isset( $w['items'][0] ) ? $w['items'][0] : array(
					'title' => $w['text'],
					'text'  => wp_strip_all_tags( $w['html'] ),
				);
				return array(
					self::el(
						'icon-box',
						array(
							'selected_icon'    => self::fa( $w['icon'] ),
							'title_text'       => '' !== $item['title'] ? $item['title'] : $w['text'],
							'description_text' => wp_strip_all_tags( '' !== $item['text'] ? $item['text'] : $w['html'] ),
							'position'         => 'top',
							'primary_color'    => self::ACCENT,
							'text_align'       => '' !== $align ? $align : 'center',
						)
					),
				);
			case 'price':
				return $this->price( $w['price'], '' !== $align ? $align : 'center' );
			case 'period_toggle':
				return array( self::el( 'html', array( 'html' => Layout_Markup::toggle( $w['labels'] ) ) ) );
			case 'accordion':
			case 'tabs':
				$tabs = array();
				foreach ( $w['items'] as $item ) {
					$tabs[] = array(
						'_id'         => self::id(),
						'tab_title'   => $item['title'],
						'tab_content' => '<p>' . $item['text'] . '</p>',
					);
				}
				return array( self::el( 'accordion' === $w['type'] ? 'accordion' : 'tabs', array( 'tabs' => $tabs ) ) );
			case 'testimonial':
				return array(
					self::el(
						'testimonial',
						array(
							'testimonial_content'   => '' !== $w['text'] ? $w['text'] : wp_strip_all_tags( $w['html'] ),
							'testimonial_name'      => $w['author'],
							'testimonial_job'       => $w['role'],
							'testimonial_alignment' => '' !== $align ? $align : 'center',
						)
					),
				);
			case 'counter':
				return array(
					self::el(
						'counter',
						array(
							'starting_number' => 0,
							'ending_number'   => $w['number'],
							'suffix'          => $w['suffix'],
							'title'           => $w['text'],
						)
					),
				);
			case 'divider':
				return array( self::el( 'divider', array() ) );
			case 'spacer':
				return array(
					self::el(
						'spacer',
						array(
							'space' => array(
								'unit' => 'px',
								'size' => 40,
							),
						)
					),
				);
			case 'html':
				return array( self::el( 'html', array( 'html' => $w['html'] ) ) );
		}
		return array();
	}

	/**
	 * Price card from native widgets.
	 *
	 * @param array  $p     Price.
	 * @param string $align Alignment.
	 * @return array
	 */
	private function price( array $p, $align ) {
		$out = array();
		if ( $p['featured'] ) {
			$out[] = self::el(
				'text-editor',
				array(
					'editor'     => '<p><strong>' . esc_html__( 'Most popular', 'dev3-ai-content-layout-builder' ) . '</strong></p>',
					'align'      => $align,
					'text_color' => self::ACCENT,
				)
			);
		}
		$out[] = self::el(
			'heading',
			array(
				'title'       => $p['plan'],
				'header_size' => 'h3',
				'align'       => $align,
			)
		);
		$out[] = self::el(
			'text-editor',
			array(
				'editor' => Layout_Markup::price( $p ),
				'align'  => $align,
			)
		);
		if ( $p['features'] ) {
			$items = array();
			foreach ( $p['features'] as $f ) {
				$items[] = array(
					'text' => $f,
					'icon' => 'fas fa-check',
				);
			}
			$out[] = self::el( 'icon-list', array( 'icon_list' => self::icon_items( $items ) ) );
		}
		if ( '' !== $p['button'] ) {
			$out[] = self::button( $p['button'], '#', 'justify', $p['featured'] ? 'primary' : 'secondary' );
		}
		return $out;
	}

	/**
	 * Button widget.
	 *
	 * @param string $text  Label.
	 * @param string $url   URL.
	 * @param string $align Alignment.
	 * @param string $style primary|secondary.
	 * @return array
	 */
	private static function button( $text, $url, $align, $style ) {
		$settings = array(
			'text'  => $text,
			'link'  => array(
				'url'         => $url,
				'is_external' => '',
				'nofollow'    => '',
			),
			'align' => $align,
		);
		if ( 'secondary' === $style ) {
			$settings['background_color']  = 'transparent';
			$settings['button_text_color'] = self::ACCENT;
			$settings['border_border']     = 'solid';
			$settings['border_width']      = self::box( 2, 2 );
			$settings['border_color']      = self::ACCENT;
		} else {
			$settings['background_color'] = self::ACCENT;
		}
		return self::el( 'button', array_filter( $settings ) );
	}

	/**
	 * Icon list repeater items.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	private static function icon_items( array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			$label = Layout_Markup::item_label( $item );
			$out[] = array(
				'_id'           => self::id(),
				'text'          => $label,
				'selected_icon' => self::fa( isset( $item['icon'] ) ? $item['icon'] : 'fas fa-check' ),
			);
		}
		return $out;
	}

	/**
	 * Elementor icon control value.
	 *
	 * @param string $icon Class.
	 * @return array
	 */
	private static function fa( $icon ) {
		$icon    = Layout_Schema::icon( $icon );
		$library = 0 === strpos( $icon, 'fab' ) ? 'fa-brands' : ( 0 === strpos( $icon, 'far' ) ? 'fa-regular' : 'fa-solid' );
		return array(
			'value'   => $icon,
			'library' => $library,
		);
	}

	/**
	 * Widget element.
	 *
	 * @param string $type     Widget type.
	 * @param array  $settings Settings.
	 * @return array
	 */
	private static function el( $type, array $settings ) {
		return array(
			'id'         => self::id(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => (object) $settings,
			'elements'   => array(),
			'isInner'    => false,
		);
	}

	/**
	 * Dimensions control value.
	 *
	 * @param int $vertical   Top/bottom.
	 * @param int $horizontal Left/right.
	 * @return array
	 */
	private static function box( $vertical, $horizontal ) {
		return array(
			'unit'     => 'px',
			'top'      => (string) $vertical,
			'right'    => (string) $horizontal,
			'bottom'   => (string) $vertical,
			'left'     => (string) $horizontal,
			'isLinked' => $vertical === $horizontal,
		);
	}

	/**
	 * Formats a number without trailing zeros.
	 *
	 * @param float $n Number.
	 * @return string
	 */
	private static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * Placeholder image.
	 *
	 * @return string
	 */
	public static function placeholder() {
		if ( class_exists( '\Elementor\Utils' ) ) {
			return \Elementor\Utils::get_placeholder_image_src();
		}
		return DEV3CL_URL . 'assets/img/placeholder.svg';
	}
}
