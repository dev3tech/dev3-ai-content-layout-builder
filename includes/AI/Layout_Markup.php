<?php
/**
 * Small HTML fragments shared by the layout converters.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Billing-period toggle and price markup. The toggle is powered by assets/frontend/toggle.js,
 * which the plugin enqueues only on pages that contain it; without the script the first period shows.
 */
class Layout_Markup {

	/**
	 * Period toggle buttons.
	 *
	 * @param array $labels Two labels.
	 * @return string
	 */
	public static function toggle( array $labels ) {
		$labels = array_values( $labels );
		if ( count( $labels ) < 2 ) {
			$labels = array( __( 'Monthly', 'dev3-ai-content-layout-builder' ), __( 'Yearly', 'dev3-ai-content-layout-builder' ) );
		}
		return sprintf(
			'<div class="dev3cl-period-toggle" data-dev3cl-toggle="1" role="group"><button type="button" class="is-active" data-dev3cl-set="0" aria-pressed="true">%1$s</button><button type="button" data-dev3cl-set="1" aria-pressed="false">%2$s</button></div>',
			esc_html( $labels[0] ),
			esc_html( $labels[1] )
		);
	}

	/**
	 * Price line with optional alternate period.
	 *
	 * @param array $p Price.
	 * @return string
	 */
	public static function price( array $p ) {
		$main = sprintf(
			'<span data-dev3cl-period="0"><span class="dev3cl-price__amount" style="font-size:44px;font-weight:700;line-height:1.1">%1$s%2$s</span> <span class="dev3cl-price__period">%3$s</span></span>',
			esc_html( $p['currency'] ),
			esc_html( $p['amount'] ),
			esc_html( $p['period'] )
		);
		$alt  = '';
		if ( '' !== $p['amount_alt'] ) {
			$alt = sprintf(
				'<span data-dev3cl-period="1" style="display:none"><span class="dev3cl-price__amount" style="font-size:44px;font-weight:700;line-height:1.1">%1$s%2$s</span> <span class="dev3cl-price__period">%3$s</span></span>',
				esc_html( $p['currency'] ),
				esc_html( $p['amount_alt'] ),
				esc_html( '' !== $p['period_alt'] ? $p['period_alt'] : $p['period'] )
			);
		}
		return '<p class="dev3cl-price">' . $main . $alt . '</p>';
	}

	/**
	 * "Title — text" for list items, without repeating identical title/text.
	 *
	 * @param array $item { title, text }.
	 * @return string Plain text.
	 */
	public static function item_label( array $item ) {
		$title = isset( $item['title'] ) ? trim( wp_strip_all_tags( $item['title'] ) ) : '';
		$text  = isset( $item['text'] ) ? trim( wp_strip_all_tags( $item['text'] ) ) : '';
		if ( '' === $title || 0 === strcasecmp( $title, $text ) || 0 === stripos( $text, $title ) ) {
			return '' !== $text ? $text : $title;
		}
		return '' !== $text ? $title . ' — ' . $text : $title;
	}
}
