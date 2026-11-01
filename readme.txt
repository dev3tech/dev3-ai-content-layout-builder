=== DEV3 AI Content & Layout Builder for Elementor, WPBakery & Gutenberg ===
Contributors: dev3tech
Tags: ai, gemini, elementor, wpbakery, content generator
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write, rewrite, translate, generate SEO meta, build whole sections and describe images with Google Gemini — inside Elementor, WPBakery, the block editor and the Media Library.

== Description ==

DEV3 AI Content & Layout Builder connects your own Google Gemini API key to the editors you already use.

* **Copywriting** – generate, expand, shorten, summarize, fix grammar, rewrite and change tone (Professional, Casual, Persuasive, Technical, Friendly, Witty, Formal, Empathetic).
* **Translation** – 20+ languages, keeping HTML and brand names intact.
* **SEO meta** – title, meta description, slug, keywords and Open Graph title, with length counters.
* **Layout generator** – describe a section ("3-column pricing table with monthly/yearly toggle") and insert native, editable Elementor containers or WPBakery rows.
* **Vision** – alt text, captions, title attributes, descriptions and promotional copy from any image; bulk alt text for the Media Library.
* **Template library** – 10 built-in prompt templates plus your own with {placeholders}.
* **History & usage logs** – restore any previous generation; see requests and tokens per task, model and user.

Integrations:

* Elementor 3.x/4.x (free & Pro): a wand on every text, textarea, WYSIWYG and code control, a "Write with DEV3 AI" control on core widgets, "Generate items" for Accordion/Toggle/Tabs, and an AI button in the add-section area.
* WPBakery Page Builder 6.x+: a `gemini_ai_prompt` param on core elements, a wand on every text param in the edit popup, and a navbar button that inserts generated rows.
* Block editor: a toolbar wand on text and image blocks, and an DEV3 AI Assistant sidebar.
* Media Library: "Generate alt text, caption & title" in attachment details, plus a bulk action.

= Privacy =

Prompts, the content you ask the AI to work on, and images you choose to analyze are sent to Google's Gemini API using your API key. Nothing is sent until a user clicks an AI action. The API key is encrypted in your database (AES-256-GCM) and never sent to the browser.tech.

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload Plugin, then activate it.
2. Create a key at https://aistudio.google.com/app/apikey.
3. Go to DEV3 AI Builder → API Config, paste the key and click "Validate & save".

You can also define the key in wp-config.php: `define( 'DEV3CL_GEMINI_API_KEY', 'your-key' );`

== Frequently Asked Questions ==

= Do I need a paid Google account? =
No. Gemini API keys from Google AI Studio include a free tier. Heavier use needs billing enabled on your Google Cloud project.

= Which models are supported? =
Any Gemini text model available to your key. Defaults: gemini-2.5-flash (fast) and gemini-2.5-pro (layouts). Use "Refresh models" to load the list or type a custom model ID.

= Who can use it? =
Users with `edit_posts` can generate; users with `manage_options` change settings. Both can be changed with the `dev3cl_capability` and `dev3cl_manage_capability` filters.

== Changelog ==

= 1.0.0 =
* Initial release.
