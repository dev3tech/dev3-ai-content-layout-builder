<?php
/**
 * Prompt template library: built-in + user-defined.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Template shape: { id, name, category, task, prompt (with {placeholders}), fields[{key,label}], format, builtin }.
 */
class Templates {

	const OPTION    = 'dev3cl_templates';
	const TRANSIENT = 'dev3cl_templates_list';

	/**
	 * Built-in templates.
	 *
	 * @return array
	 */
	public static function builtin() {
		$t = array(
			array(
				'id'       => 'hero',
				'name'     => __( 'Landing Page Hero Copy', 'dev3-ai-content-layout-builder' ),
				'category' => 'landing',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => "Write landing-page hero copy for {product}, aimed at {audience}.\nInclude: a headline (max 10 words) as <h2>, a one-sentence subheadline as <p>, three short benefit bullets as <ul>, and a call-to-action phrase as <p><strong>.",
				'fields'   => array(
					array(
						'key'   => 'product',
						'label' => __( 'Product or service', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'audience',
						'label' => __( 'Target audience', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
			array(
				'id'       => 'product_description',
				'name'     => __( 'Product Description', 'dev3-ai-content-layout-builder' ),
				'category' => 'ecommerce',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => "Write a compelling product description for {product}. Key features: {features}.\nStructure: an opening paragraph about the main benefit, a bullet list of features turned into benefits, and a closing line with a call to action.",
				'fields'   => array(
					array(
						'key'   => 'product',
						'label' => __( 'Product name', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'features',
						'label' => __( 'Key features', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
			array(
				'id'       => 'faq',
				'name'     => __( 'FAQ List', 'dev3-ai-content-layout-builder' ),
				'category' => 'content',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => 'Write {count} frequently asked questions with concise, helpful answers about {topic}. Format each question as <h3> followed by the answer as <p>.',
				'fields'   => array(
					array(
						'key'   => 'topic',
						'label' => __( 'Topic / business', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'     => 'count',
						'label'   => __( 'Number of questions', 'dev3-ai-content-layout-builder' ),
						'default' => '6',
					),
				),
			),
			array(
				'id'       => 'feature_grid',
				'name'     => __( 'Feature Grid', 'dev3-ai-content-layout-builder' ),
				'category' => 'landing',
				'task'     => 'layout',
				'format'   => 'json',
				'prompt'   => 'A section introducing {product} with a heading, a short intro, and a grid of {count} feature icon boxes (icon, title, one-sentence description), followed by a call-to-action button.',
				'fields'   => array(
					array(
						'key'   => 'product',
						'label' => __( 'Product or service', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'     => 'count',
						'label'   => __( 'Number of features', 'dev3-ai-content-layout-builder' ),
						'default' => '6',
					),
				),
			),
			array(
				'id'       => 'pricing',
				'name'     => __( 'Pricing Table', 'dev3-ai-content-layout-builder' ),
				'category' => 'landing',
				'task'     => 'layout',
				'format'   => 'json',
				'prompt'   => 'A {count}-column pricing table for {product} with a Monthly/Yearly billing toggle, a heading and intro above it, one featured plan, feature lists and buttons.',
				'fields'   => array(
					array(
						'key'   => 'product',
						'label' => __( 'Product or service', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'     => 'count',
						'label'   => __( 'Number of plans', 'dev3-ai-content-layout-builder' ),
						'default' => '3',
					),
				),
			),
			array(
				'id'       => 'seo_meta',
				'name'     => __( 'SEO Meta', 'dev3-ai-content-layout-builder' ),
				'category' => 'seo',
				'task'     => 'seo_meta',
				'format'   => 'json',
				'prompt'   => 'Page about {topic}. Primary keyword: {keyword}.',
				'fields'   => array(
					array(
						'key'   => 'topic',
						'label' => __( 'Page topic', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'keyword',
						'label' => __( 'Primary keyword', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
			array(
				'id'       => 'about',
				'name'     => __( 'About Us', 'dev3-ai-content-layout-builder' ),
				'category' => 'content',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => 'Write an About Us section for {company}, which {what}. Founded {founded}. Cover the mission, what makes them different, and a warm closing line. 2–3 short paragraphs.',
				'fields'   => array(
					array(
						'key'   => 'company',
						'label' => __( 'Company name', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'what',
						'label' => __( 'What the company does', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'founded',
						'label' => __( 'Founded (year/place)', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
			array(
				'id'       => 'testimonials',
				'name'     => __( 'Testimonials Section', 'dev3-ai-content-layout-builder' ),
				'category' => 'landing',
				'task'     => 'layout',
				'format'   => 'json',
				'prompt'   => 'A testimonials section for {product}: heading, short intro and {count} testimonial cards in columns with realistic first names and roles. Mark them as sample copy in the intro.',
				'fields'   => array(
					array(
						'key'   => 'product',
						'label' => __( 'Product or service', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'     => 'count',
						'label'   => __( 'Number of testimonials', 'dev3-ai-content-layout-builder' ),
						'default' => '3',
					),
				),
			),
			array(
				'id'       => 'cta',
				'name'     => __( 'Call to Action', 'dev3-ai-content-layout-builder' ),
				'category' => 'landing',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => 'Write a short call-to-action block for {offer}: an <h3> headline, one supporting <p> sentence, and a button label in <p><strong>.',
				'fields'   => array(
					array(
						'key'   => 'offer',
						'label' => __( 'Offer', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
			array(
				'id'       => 'blog_intro',
				'name'     => __( 'Blog Post Intro', 'dev3-ai-content-layout-builder' ),
				'category' => 'content',
				'task'     => 'generate',
				'format'   => 'html',
				'prompt'   => 'Write a hooking 2-paragraph introduction for a blog post titled "{title}" for {audience}.',
				'fields'   => array(
					array(
						'key'   => 'title',
						'label' => __( 'Post title', 'dev3-ai-content-layout-builder' ),
					),
					array(
						'key'   => 'audience',
						'label' => __( 'Audience', 'dev3-ai-content-layout-builder' ),
					),
				),
			),
		);
		foreach ( $t as $i => $tpl ) {
			$t[ $i ]['builtin'] = true;
		}
		/**
		 * Filters built-in templates.
		 *
		 * @param array $templates Templates.
		 */
		return apply_filters( 'dev3cl_builtin_templates', $t );
	}

	/**
	 * All templates (cached).
	 *
	 * @return array
	 */
	public static function all() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && get_locale() === ( isset( $cached['locale'] ) ? $cached['locale'] : '' ) ) {
			return $cached['items'];
		}
		$custom = get_option( self::OPTION, array() );
		$items  = array_merge( self::builtin(), is_array( $custom ) ? array_values( $custom ) : array() );
		set_transient(
			self::TRANSIENT,
			array(
				'locale' => get_locale(),
				'items'  => $items,
			),
			DAY_IN_SECONDS
		);
		return $items;
	}

	/**
	 * Saves a user template (create or update).
	 *
	 * @param array $input Raw input.
	 * @return array|\WP_Error Saved template.
	 */
	public static function save( array $input ) {
		$name   = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
		$prompt = isset( $input['prompt'] ) ? sanitize_textarea_field( (string) $input['prompt'] ) : '';
		if ( '' === $name || '' === $prompt ) {
			return new \WP_Error( 'dev3cl_template_invalid', __( 'A template needs a name and a prompt.', 'dev3-ai-content-layout-builder' ), array( 'status' => 400 ) );
		}
		$custom = get_option( self::OPTION, array() );
		$custom = is_array( $custom ) ? $custom : array();
		$id     = isset( $input['id'] ) ? sanitize_key( (string) $input['id'] ) : '';
		foreach ( self::builtin() as $b ) {
			if ( $b['id'] === $id ) {
				$id = '';
			}
		}
		if ( '' === $id ) {
			$id = 'custom_' . strtolower( wp_generate_password( 8, false ) );
		}
		$task = isset( $input['task'] ) ? sanitize_key( (string) $input['task'] ) : 'generate';
		if ( ! in_array( $task, array( 'generate', 'layout', 'seo_meta', 'headline_variations' ), true ) ) {
			$task = 'generate';
		}
		preg_match_all( '/\{([a-z0-9_]{1,30})\}/', $prompt, $m );
		$fields = array();
		foreach ( array_unique( $m[1] ) as $key ) {
			$fields[] = array(
				'key'   => $key,
				'label' => ucfirst( str_replace( '_', ' ', $key ) ),
			);
		}
		$tpl           = array(
			'id'       => $id,
			'name'     => $name,
			'category' => isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : 'custom',
			'task'     => $task,
			'format'   => 'layout' === $task || 'seo_meta' === $task ? 'json' : ( isset( $input['format'] ) && 'plain' === $input['format'] ? 'plain' : 'html' ),
			'prompt'   => $prompt,
			'fields'   => $fields,
			'builtin'  => false,
		);
		$custom[ $id ] = $tpl;
		update_option( self::OPTION, $custom, false );
		delete_transient( self::TRANSIENT );
		return $tpl;
	}

	/**
	 * Deletes a user template.
	 *
	 * @param string $id Template id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$custom = get_option( self::OPTION, array() );
		if ( ! is_array( $custom ) || ! isset( $custom[ $id ] ) ) {
			return false;
		}
		unset( $custom[ $id ] );
		update_option( self::OPTION, $custom, false );
		delete_transient( self::TRANSIENT );
		return true;
	}

	/**
	 * Fills {placeholders}.
	 *
	 * @param string $prompt Template prompt.
	 * @param array  $values Values.
	 * @return string
	 */
	public static function render( $prompt, array $values ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]{1,30})\}/',
			static function ( $m ) use ( $values ) {
				return isset( $values[ $m[1] ] ) && '' !== trim( (string) $values[ $m[1] ] ) ? sanitize_textarea_field( (string) $values[ $m[1] ] ) : $m[0];
			},
			(string) $prompt
		);
	}
}
