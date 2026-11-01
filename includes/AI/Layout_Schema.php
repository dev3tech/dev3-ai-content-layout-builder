<?php
/**
 * Builder-neutral layout tree: schema sent to Gemini and normalizer for its answer.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Tree shape:
 * { title, sections: [ { name, background, columns: [ { width, featured, widgets: [ widget ] } ] } ] }
 */
class Layout_Schema {

	const WIDGETS = array( 'heading', 'text', 'button', 'image', 'icon_list', 'icon_box', 'price', 'period_toggle', 'accordion', 'tabs', 'testimonial', 'counter', 'divider', 'spacer', 'html' );

	const MAX_SECTIONS = 8;
	const MAX_COLUMNS  = 6;
	const MAX_WIDGETS  = 12;
	const MAX_ITEMS    = 12;

	/**
	 * Gemini responseSchema (OpenAPI subset).
	 *
	 * @return array
	 */
	public static function response_schema() {
		$string = array( 'type' => 'STRING' );
		$item   = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'title' => $string,
				'text'  => $string,
				'icon'  => $string,
			),
		);
		$widget = array(
			'type'             => 'OBJECT',
			'properties'       => array(
				'type'      => array(
					'type' => 'STRING',
					'enum' => self::WIDGETS,
				),
				'text'      => $string,
				'html'      => $string,
				'level'     => array(
					'type' => 'STRING',
					'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ),
				),
				'align'     => array(
					'type' => 'STRING',
					'enum' => array( 'left', 'center', 'right' ),
				),
				'url'       => $string,
				'icon'      => $string,
				'style'     => array(
					'type' => 'STRING',
					'enum' => array( 'primary', 'secondary' ),
				),
				'image_alt' => $string,
				'number'    => array( 'type' => 'NUMBER' ),
				'suffix'    => $string,
				'author'    => $string,
				'role'      => $string,
				'labels'    => array(
					'type'  => 'ARRAY',
					'items' => $string,
				),
				'items'     => array(
					'type'  => 'ARRAY',
					'items' => $item,
				),
				'price'     => array(
					'type'             => 'OBJECT',
					'properties'       => array(
						'plan'       => $string,
						'currency'   => $string,
						'amount'     => $string,
						'period'     => $string,
						'amount_alt' => $string,
						'period_alt' => $string,
						'features'   => array(
							'type'  => 'ARRAY',
							'items' => $string,
						),
						'button'     => $string,
						'featured'   => array( 'type' => 'BOOLEAN' ),
					),
					'required'         => array( 'plan', 'currency', 'amount', 'period', 'features', 'button' ),
					'propertyOrdering' => array( 'plan', 'currency', 'amount', 'period', 'amount_alt', 'period_alt', 'features', 'button', 'featured' ),
				),
			),
			'required'         => array( 'type' ),
			'propertyOrdering' => array( 'type', 'text', 'level', 'html', 'align', 'url', 'style', 'icon', 'image_alt', 'number', 'suffix', 'author', 'role', 'labels', 'items', 'price' ),
		);
		return array(
			'type'       => 'OBJECT',
			'properties' => array(
				'title'    => $string,
				'sections' => array(
					'type'  => 'ARRAY',
					'items' => array(
						'type'       => 'OBJECT',
						'properties' => array(
							'name'       => $string,
							'background' => array(
								'type' => 'STRING',
								'enum' => array( 'none', 'light', 'dark' ),
							),
							'columns'    => array(
								'type'  => 'ARRAY',
								'items' => array(
									'type'       => 'OBJECT',
									'properties' => array(
										'width'    => array( 'type' => 'NUMBER' ),
										'featured' => array( 'type' => 'BOOLEAN' ),
										'widgets'  => array(
											'type'  => 'ARRAY',
											'items' => $widget,
										),
									),
									'required'   => array( 'widgets' ),
								),
							),
						),
						'required'   => array( 'columns' ),
					),
				),
			),
			'required'   => array( 'sections' ),
		);
	}

	/**
	 * Instructions for the layout designer persona.
	 *
	 * @param string $tone     Tone id.
	 * @param string $language Language.
	 * @param string $builder  elementor|wpbakery.
	 * @return string
	 */
	public static function system_instruction( $tone, $language, $builder ) {
		$labels = Prompt_Orchestrator::tone_labels();
		return implode(
			"\n",
			array(
				'You are a senior web designer and copywriter generating page-builder sections as JSON for a WordPress site.',
				'Return a JSON object that matches the response schema. Every section has 1–' . self::MAX_COLUMNS . ' columns; column "width" values are percentages that add up to 100 in each section.',
				'Widget types: heading (text, level), text (html: short paragraphs using <p>, <strong>, <em>, <ul>, <li>, <a> only), button (text, url, style), image (image_alt describing the ideal photo), icon_list (items[].text, items[].icon), icon_box (icon, items[0].title, items[0].text OR text+html), price (price object), period_toggle (labels e.g. ["Monthly","Yearly"]; place once above pricing columns in its own full-width section or column), accordion and tabs (items[].title + items[].text), testimonial (text, author, role), counter (number, suffix, text), divider, spacer, html.',
				'Each pricing plan is ONE "price" widget whose price object holds everything: plan name, currency symbol, amount, period (e.g. "/month"), features (4–6 short strings) and button label. Never split a plan into separate heading/text/icon_list widgets, and never leave price fields empty.',
				'For pricing with a billing toggle, also fill amount_alt and period_alt for the second label (e.g. yearly). Mark exactly one plan as featured and set its column featured=true.',
				'In icon_list items use "text" only (leave "title" empty unless it differs from the text).',
				'Icons are Font Awesome 5 solid class names such as "fas fa-check", "fas fa-bolt", "fas fa-shield-alt".',
				'Write real, specific, persuasive copy for the requested topic — never lorem ipsum or placeholders like [Your Company]. Use "#" for unknown URLs.',
				'Use background "light" or "dark" to alternate sections when there are several. Keep it to at most ' . self::MAX_SECTIONS . ' sections.',
				'Tone of voice: ' . ( isset( $labels[ $tone ] ) ? $labels[ $tone ] : 'Professional' ) . '. Write in ' . $language . '.',
				'Target builder: ' . ( 'wpbakery' === $builder ? 'WPBakery Page Builder' : 'Elementor' ) . '.',
			)
		);
	}

	/**
	 * Validates and sanitizes a decoded tree.
	 *
	 * @param mixed $tree Decoded JSON.
	 * @return array|\WP_Error
	 */
	public static function normalize( $tree ) {
		if ( ! is_array( $tree ) || empty( $tree['sections'] ) || ! is_array( $tree['sections'] ) ) {
			return new \WP_Error(
				'dev3cl_layout_invalid',
				__( 'The AI did not return a usable layout.', 'dev3-ai-content-layout-builder' ),
				array(
					'status'    => 502,
					'hint'      => __( 'Regenerate, or describe the section in more detail.', 'dev3-ai-content-layout-builder' ),
					'retryable' => true,
				)
			);
		}

		$out = array(
			'title'    => isset( $tree['title'] ) ? sanitize_text_field( (string) $tree['title'] ) : '',
			'sections' => array(),
		);
		foreach ( array_slice( $tree['sections'], 0, self::MAX_SECTIONS ) as $section ) {
			if ( ! is_array( $section ) || empty( $section['columns'] ) || ! is_array( $section['columns'] ) ) {
				continue;
			}
			$columns = array();
			foreach ( array_slice( $section['columns'], 0, self::MAX_COLUMNS ) as $col ) {
				if ( ! is_array( $col ) ) {
					continue;
				}
				$widgets = array();
				foreach ( array_slice( isset( $col['widgets'] ) && is_array( $col['widgets'] ) ? $col['widgets'] : array(), 0, self::MAX_WIDGETS ) as $w ) {
					$w = self::widget( $w );
					if ( $w ) {
						$widgets[] = $w;
					}
				}
				$columns[] = array(
					'width'    => isset( $col['width'] ) ? (float) $col['width'] : 0,
					'featured' => ! empty( $col['featured'] ),
					'widgets'  => $widgets,
				);
			}
			if ( ! $columns ) {
				continue;
			}
			$out['sections'][] = array(
				'name'       => isset( $section['name'] ) ? sanitize_text_field( (string) $section['name'] ) : '',
				'background' => isset( $section['background'] ) && in_array( $section['background'], array( 'light', 'dark' ), true ) ? $section['background'] : 'none',
				'columns'    => self::balance_widths( $columns ),
			);
		}

		if ( ! $out['sections'] ) {
			return self::normalize( null );
		}
		return $out;
	}

	/**
	 * Makes column widths positive and sum to 100.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function balance_widths( array $columns ) {
		$count = count( $columns );
		$sum   = 0;
		foreach ( $columns as $c ) {
			$sum += max( 0, $c['width'] );
		}
		foreach ( $columns as $i => $c ) {
			$columns[ $i ]['width'] = ( $sum <= 0 || $c['width'] <= 0 )
				? round( 100 / $count, 3 )
				: round( $c['width'] * 100 / $sum, 3 );
		}
		return $columns;
	}

	/**
	 * Sanitizes one widget.
	 *
	 * @param mixed $w Raw widget.
	 * @return array|null
	 */
	private static function widget( $w ) {
		if ( ! is_array( $w ) || empty( $w['type'] ) || ! in_array( $w['type'], self::WIDGETS, true ) ) {
			return null;
		}
		$text = static function ( $key ) use ( $w ) {
			return isset( $w[ $key ] ) && is_scalar( $w[ $key ] ) ? sanitize_text_field( (string) $w[ $key ] ) : '';
		};
		$out  = array(
			'type'      => $w['type'],
			'text'      => $text( 'text' ),
			'html'      => isset( $w['html'] ) && is_string( $w['html'] ) ? Response_Parser::html( $w['html'] ) : '',
			'level'     => isset( $w['level'] ) && preg_match( '/^h[1-6]$/', (string) $w['level'] ) ? $w['level'] : 'h2',
			'align'     => isset( $w['align'] ) && in_array( $w['align'], array( 'left', 'center', 'right' ), true ) ? $w['align'] : '',
			'url'       => isset( $w['url'] ) && '#' !== $w['url'] ? esc_url_raw( (string) $w['url'] ) : '#',
			'icon'      => self::icon( isset( $w['icon'] ) ? $w['icon'] : '' ),
			'style'     => isset( $w['style'] ) && 'secondary' === $w['style'] ? 'secondary' : 'primary',
			'image_alt' => $text( 'image_alt' ),
			'number'    => isset( $w['number'] ) && is_numeric( $w['number'] ) ? 0 + $w['number'] : 0,
			'suffix'    => $text( 'suffix' ),
			'author'    => $text( 'author' ),
			'role'      => $text( 'role' ),
			'labels'    => array(),
			'items'     => array(),
			'price'     => null,
		);
		if ( '' === $out['url'] ) {
			$out['url'] = '#';
		}
		if ( 'html' === $w['type'] && isset( $w['html'] ) && is_string( $w['html'] ) ) {
			$out['html'] = wp_kses_post( Response_Parser::strip_fences( $w['html'] ) );
		}
		if ( ! empty( $w['labels'] ) && is_array( $w['labels'] ) ) {
			$out['labels'] = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', array_slice( $w['labels'], 0, 2 ) ) ) ) );
		}
		if ( ! empty( $w['items'] ) && is_array( $w['items'] ) ) {
			foreach ( array_slice( $w['items'], 0, self::MAX_ITEMS ) as $item ) {
				if ( is_string( $item ) ) {
					$item = array( 'text' => $item );
				}
				if ( ! is_array( $item ) ) {
					continue;
				}
				$out['items'][] = array(
					'title' => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
					'text'  => isset( $item['text'] ) ? wp_kses_post( (string) $item['text'] ) : '',
					'icon'  => self::icon( isset( $item['icon'] ) ? $item['icon'] : '' ),
				);
			}
		}
		if ( 'price' === $w['type'] && ! empty( $w['price'] ) && is_array( $w['price'] ) ) {
			$p            = $w['price'];
			$s            = static function ( $key ) use ( $p ) {
				return isset( $p[ $key ] ) && is_scalar( $p[ $key ] ) ? sanitize_text_field( (string) $p[ $key ] ) : '';
			};
			$out['price'] = array(
				'plan'       => $s( 'plan' ),
				'currency'   => $s( 'currency' ),
				'amount'     => $s( 'amount' ),
				'period'     => $s( 'period' ),
				'amount_alt' => $s( 'amount_alt' ),
				'period_alt' => $s( 'period_alt' ),
				'features'   => isset( $p['features'] ) && is_array( $p['features'] ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', array_slice( $p['features'], 0, self::MAX_ITEMS ) ) ) ) : array(),
				'button'     => $s( 'button' ),
				'featured'   => ! empty( $p['featured'] ),
			);
		}
		if ( 'price' === $w['type'] && ( null === $out['price'] || '' === $out['price']['amount'] ) ) {
			// A price without an amount is unusable; keep the plan name as a heading instead.
			if ( null !== $out['price'] && '' !== $out['price']['plan'] ) {
				$out['type']  = 'heading';
				$out['text']  = $out['price']['plan'];
				$out['level'] = 'h3';
				$out['price'] = null;
				return $out;
			}
			return null;
		}
		return $out;
	}

	/**
	 * Font Awesome class sanitizer: "fas fa-check".
	 *
	 * @param mixed $icon Raw icon.
	 * @return string
	 */
	public static function icon( $icon ) {
		$icon = strtolower( trim( (string) $icon ) );
		if ( preg_match( '/^(fas|far|fab|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+$/', $icon ) ) {
			return str_replace( array( 'fa-solid', 'fa-regular', 'fa-brands' ), array( 'fas', 'far', 'fab' ), $icon );
		}
		if ( preg_match( '/^fa-[a-z0-9-]+$/', $icon ) ) {
			return 'fas ' . $icon;
		}
		return 'fas fa-check';
	}
}
