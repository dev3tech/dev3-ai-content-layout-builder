# DEV3 AI Content & Layout Builder for Elementor, WPBakery & Gutenberg

Write, rewrite, translate, generate SEO meta, build whole sections and describe images with DEV3 AI — inside Elementor, WPBakery, the block editor and the Media Library.

## Features

DEV3 AI Content & Layout Builder connects your own Google AI API key to the editors you already use.

* **Copywriting** – generate, expand, shorten, summarize, fix grammar, rewrite and change tone (Professional, Casual, Persuasive, Technical, Friendly, Witty, Formal, Empathetic).
* **Translation** – 20+ languages, keeping HTML and brand names intact.
* **SEO meta** – title, meta description, slug, keywords and Open Graph title, with length counters.
* **Layout generator** – describe a section ("3-column pricing table with monthly/yearly toggle") and insert native, editable Elementor containers or WPBakery rows.
* **Vision** – alt text, captions, title attributes, descriptions and promotional copy from any image; bulk alt text for the Media Library.
* **Template library** – 10 built-in prompt templates plus your own with {placeholders}.
* **History & usage logs** – restore any previous generation; see requests and tokens per task, model and user.

Integrations:

* Elementor 3.x/4.x (free & Pro): a wand on every text, textarea, WYSIWYG and code control, a "Write with DEV3 AI" control on core widgets, "Generate items" for Accordion/Toggle/Tabs, and an AI button in the add-section area.
* Block editor: a toolbar wand on text and image blocks, and an DEV3 AI Assistant sidebar.
* Media Library: "Generate alt text, caption & title" in attachment details, plus a bulk action.

### Privacy
Prompts, the content you ask the AI to work on, and images you choose to analyze are sent to Google's AI API using your API key. Nothing is sent until a user clicks an AI action. The API key is encrypted in your database (AES-256-GCM) and never sent to the browser.

## Requirements

- WordPress 6.0 or newer (tested up to 6.8)
- PHP 7.4 or newer
- Optional: your own Google AI API key (from Google AI Studio) to switch on the DEV3 AI features. The rest of the plugin works without one.

## Installation

### From the release zip

1. Download `dev3-ai-content-layout-builder.zip` from the [latest release](https://github.com/dev3tech/dev3-ai-content-layout-builder/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**.
3. Click **Activate**.

### Build from source

```bash
git clone https://github.com/dev3tech/dev3-ai-content-layout-builder.git
cd dev3-ai-content-layout-builder
composer install --no-dev
npm ci
npm run build
```

Then copy the `dev3-ai-content-layout-builder` folder into `wp-content/plugins/` and activate it.

### Getting started

1. Upload the plugin zip in Plugins → Add New → Upload Plugin, then activate it.
2. Create a key at https://aistudio.google.com/app/apikey.
3. Go to DEV3 AI Builder → API Config, paste the key and click "Validate & save".

## FAQ

### Do I need a paid Google account?
No. Google AI API key from Google AI Studio include a free tier. Heavier use needs billing enabled on your Google Cloud project.

### Which models are supported?
Use "Refresh models" to load the list or type a custom model ID.

### Who can use it?
Users with `edit_posts` can generate; users with `manage_options` change settings. Both can be changed with the `dev3cl_capability` and `dev3cl_manage_capability` filters.

## Documentation

The full HTML guide (installation, settings, hooks and FAQ) is in [`docs/index.html`](docs/index.html). Open it in a browser after cloning or downloading the repository.

## Changelog

### 1.0.1
* Branding: the plugin description and readme now say "DEV3 AI" instead of naming the AI provider.
* Readme tags updated.
* Fixed a typo in the privacy text.

### 1.0.0
* Initial release.

## Licence

Released under the GNU General Public License v2.0 or later (GPL-2.0-or-later). See [LICENSE](LICENSE).

## More from DEV3

- [DEV3 AI Content & Layout Builder for Elementor, WPBakery & Gutenberg on the DEV3 Marketplace](https://www.dev3.tech/item/dev3-ai-content-layout-builder)
- [More WordPress plugins, themes and templates from DEV3](https://www.dev3.tech/marketplace)
