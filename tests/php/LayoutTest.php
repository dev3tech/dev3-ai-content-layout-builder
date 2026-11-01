<?php
/**
 * Layout normalisation and the Elementor / WPBakery converters.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

use Dev3\AiContentLayoutBuilder\AI\Layout_Elementor;
use Dev3\AiContentLayoutBuilder\AI\Layout_Schema;
use Dev3\AiContentLayoutBuilder\AI\Layout_WPBakery;

class LayoutTest extends WP_UnitTestCase {

	private function tree() {
		return Layout_Schema::normalize(
			array(
				'title'    => 'Pricing',
				'sections' => array(
					array(
						'name'       => 'Header',
						'background' => 'dark',
						'columns'    => array(
							array(
								'width'   => 100,
								'widgets' => array(
									array( 'type' => 'heading', 'text' => 'Plans "that" [fit]', 'level' => 'h2', 'align' => 'center' ),
									array( 'type' => 'period_toggle', 'labels' => array( 'Monthly', 'Yearly' ) ),
									array( 'type' => 'evil', 'text' => 'x' ),
								),
							),
						),
					),
					array(
						'background' => 'light',
						'columns'    => array(
							array( 'width' => 1, 'widgets' => array( array( 'type' => 'price', 'price' => array( 'plan' => 'Basic', 'currency' => '$', 'amount' => '9', 'period' => '/mo', 'amount_alt' => '90', 'period_alt' => '/yr', 'features' => array( 'A', 'B' ), 'button' => 'Buy' ) ) ) ),
							array( 'width' => 1, 'featured' => true, 'widgets' => array( array( 'type' => 'price', 'price' => array( 'plan' => 'Pro', 'amount' => '' ) ) ) ),
							array( 'width' => 1, 'widgets' => array( array( 'type' => 'icon_list', 'items' => array( array( 'title' => 'Fast', 'text' => 'Fast' ), array( 'title' => 'Safe', 'text' => 'Really safe' ) ) ), array( 'type' => 'html', 'html' => '<div>ok</div><script>bad()</script>' ) ) ),
						),
					),
				),
			)
		);
	}

	public function test_normalize() {
		$tree = $this->tree();
		$this->assertCount( 2, $tree['sections'] );
		$this->assertCount( 2, $tree['sections'][0]['columns'][0]['widgets'], 'unknown widget dropped' );
		$widths = wp_list_pluck( $tree['sections'][1]['columns'], 'width' );
		$this->assertEqualsWithDelta( 100, array_sum( $widths ), 0.01 );
		$this->assertSame( 'heading', $tree['sections'][1]['columns'][1]['widgets'][0]['type'], 'price without amount becomes a heading' );
		$this->assertStringNotContainsString( 'script', $tree['sections'][1]['columns'][2]['widgets'][1]['html'] );
		$this->assertWPError( Layout_Schema::normalize( array( 'sections' => array() ) ) );
		$this->assertSame( 'fas fa-check', Layout_Schema::icon( 'javascript:alert(1)' ) );
		$this->assertSame( 'fab fa-github', Layout_Schema::icon( 'fa-brands fa-github' ) );
	}

	public function test_elementor_container_and_section_modes() {
		$tree = $this->tree();
		$c    = ( new Layout_Elementor( 'container' ) )->convert( $tree );
		$this->assertSame( 'container', $c[0]['elType'] );
		$this->assertSame( '#111827', $c[0]['settings']['background_color'] );
		$inner = $c[1]['elements'][0];
		$this->assertTrue( $inner['isInner'] );
		$this->assertStringStartsWith( 'calc(33.333% - 16px)', $inner['settings']['width']['size'] );
		$types = wp_list_pluck( $inner['elements'], 'widgetType' );
		$this->assertSame( array( 'heading', 'text-editor', 'icon-list', 'button' ), $types );
		$price_html = $inner['elements'][1]['settings']->editor;
		$this->assertStringContainsString( 'data-dev3cl-period="1"', $price_html );
		$list = $c[1]['elements'][2]['elements'][0]['settings']->icon_list;
		$this->assertSame( 'Fast', $list[0]['text'], 'identical title/text not repeated' );
		$this->assertSame( 'Safe — Really safe', $list[1]['text'] );

		$s = ( new Layout_Elementor( 'section' ) )->convert( $tree );
		$this->assertSame( 'section', $s[0]['elType'] );
		$this->assertSame( 'column', $s[1]['elements'][0]['elType'] );
		$this->assertSame( 33, $s[1]['elements'][0]['settings']['_column_size'] );
		$ids = array();
		array_walk_recursive(
			$c,
			function ( $v, $k ) use ( &$ids ) {
				if ( 'id' === $k ) {
					$ids[] = $v;
				}
			}
		);
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'unique element ids' );
		$this->assertJson( wp_json_encode( ( new Layout_Elementor() )->template( $tree ) ) );
	}

	public function test_wpbakery_shortcodes() {
		$this->assertSame( '1/3', Layout_WPBakery::fraction( 33.333 ) );
		$this->assertSame( '1/4', Layout_WPBakery::fraction( 24 ) );
		$this->assertSame( '2/3', Layout_WPBakery::fraction( 66 ) );

		$sc = ( new Layout_WPBakery() )->convert( $this->tree() );
		$this->assertSame( substr_count( $sc, '[vc_row' ), substr_count( $sc, '[/vc_row]' ) );
		$this->assertSame( substr_count( $sc, '[vc_column ' ), substr_count( $sc, '[/vc_column]' ) );
		$this->assertStringContainsString( '[vc_column width="1/3"', $sc );
		$this->assertStringContainsString( 'text="Plans ``that`` &#91;fit&#93;"', $sc, 'quotes and brackets escaped' );
		$this->assertStringContainsString( 'font_container="tag:h2|text_align:center|color:%23ffffff"', $sc );
		preg_match( '/\[vc_raw_html\]([^\[]+)\[\/vc_raw_html\]/', $sc, $m );
		$this->assertStringContainsString( 'dev3cl-period-toggle', rawurldecode( base64_decode( $m[1] ) ) );
		$this->assertStringContainsString( 'link="url:%23|title:Buy"', $sc );
		// The output must parse as shortcodes WordPress understands.
		$this->assertSame( 4, preg_match_all( '/' . get_shortcode_regex( array( 'vc_row' ) ) . '/', $sc . '[vc_row][/vc_row][vc_row][/vc_row]' ) );
	}
}
