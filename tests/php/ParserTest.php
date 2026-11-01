<?php
/**
 * Response parsing, prompts and templates.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

use Dev3\AiContentLayoutBuilder\AI\Prompt_Orchestrator;
use Dev3\AiContentLayoutBuilder\AI\Response_Parser;
use Dev3\AiContentLayoutBuilder\AI\Vision;
use Dev3\AiContentLayoutBuilder\Data\Templates;

class ParserTest extends WP_UnitTestCase {

	public function test_strip_fences_and_quotes() {
		$this->assertSame( 'Hello', Response_Parser::strip_fences( "```text\nHello\n```" ) );
		$this->assertSame( 'Hello world', Response_Parser::strip_fences( '"Hello world"' ) );
	}

	public function test_json_tolerates_prose() {
		$this->assertSame( array( 'a' => 1 ), Response_Parser::json( "Sure! Here it is:\n```json\n{\"a\":1}\n```" ) );
		$this->assertSame( array( 'a' => 1 ), Response_Parser::json( 'Result: {"a":1} done' ) );
		$this->assertNull( Response_Parser::json( '{"a":' ) );
	}

	public function test_plain_strips_markdown_and_tags() {
		$this->assertSame( 'Big bold idea', Response_Parser::plain( '## **Big** bold <b>idea</b>' ) );
	}

	public function test_html_converts_markdown_and_kses() {
		$html = Response_Parser::html( "## Title\n\n- one\n- **two**\n\n<script>alert(1)</script>text" );
		$this->assertStringContainsString( '<h2>Title</h2>', $html );
		$this->assertStringContainsString( '<li><strong>two</strong></li>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<script', Response_Parser::html( '<p>ok</p><script>x</script><img src=x onerror=alert(1)>' ) );
		$this->assertStringNotContainsString( 'onerror', Response_Parser::html( '<p>ok</p><img src=x onerror=alert(1)>' ) );
	}

	public function test_orchestrator_validation_and_prompts() {
		$this->assertSame( 'dev3cl_missing_text', Prompt_Orchestrator::build( array( 'task' => 'rewrite' ) )->get_error_code() );
		$this->assertSame( 'dev3cl_missing_prompt', Prompt_Orchestrator::build( array( 'task' => 'generate' ) )->get_error_code() );
		$this->assertSame( 'dev3cl_unknown_task', Prompt_Orchestrator::build( array( 'task' => 'hack' ) )->get_error_code() );

		$t = Prompt_Orchestrator::build( array( 'task' => 'translate', 'text' => 'Hi', 'language' => 'German' ) );
		$this->assertStringContainsString( 'into German', $t['prompt'] );
		$this->assertSame( 'translate', $t['group'] );

		$s = Prompt_Orchestrator::build( array( 'task' => 'seo_meta', 'prompt' => 'Coffee' ) );
		$this->assertSame( 'json', $s['output'] );
		$this->assertStringContainsString( 'JSON', $s['system'] );
		$this->assertStringNotContainsString( 'plain text without Markdown or HTML', $s['system'] );

		$h = Prompt_Orchestrator::build( array( 'task' => 'tone', 'text' => 'x', 'tone' => 'witty', 'field' => 'heading' ) );
		$this->assertStringContainsString( 'witty tone', $h['prompt'] );
		$this->assertStringContainsString( 'heading', $h['system'] );
	}

	public function test_template_render_and_save() {
		$this->assertSame( 'Hero for Acme and {audience}', Templates::render( 'Hero for {product} and {audience}', array( 'product' => 'Acme' ) ) );
		$saved = Templates::save( array( 'name' => 'Tagline', 'prompt' => 'Tagline for {brand_name}', 'task' => 'evil' ) );
		$this->assertSame( 'generate', $saved['task'] );
		$this->assertSame( 'brand_name', $saved['fields'][0]['key'] );
		$this->assertContains( $saved['id'], wp_list_pluck( Templates::all(), 'id' ) );
		$this->assertTrue( Templates::delete( $saved['id'] ) );
		$this->assertFalse( Templates::delete( 'hero' ), 'built-ins cannot be deleted' );
		$this->assertWPError( Templates::save( array( 'name' => '' ) ) );
	}

	public function test_vision_clean_and_sniff() {
		$out = Vision::clean( array( 'alt' => str_repeat( 'word ', 60 ), 'title' => '<b>T</b>', 'evil' => 'x' ), array() );
		$this->assertLessThanOrEqual( 125, mb_strlen( $out['alt'] ) );
		$this->assertSame( 'T', $out['title'] );
		$this->assertArrayNotHasKey( 'evil', $out );
		$this->assertSame( 'image/png', Vision::sniff( "\x89PNG\r\n\x1a\n...." ) );
		$this->assertSame( 'image/jpeg', Vision::sniff( "\xFF\xD8\xFF\xE0" ) );
		$this->assertSame( '', Vision::sniff( '<html>' ) );
	}
}
