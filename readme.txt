=== Budget Translator ===
Contributors: sfambach
Tags: translation, multilingual, deepl, google translate, mymemory
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Affordable WordPress website translation with local segment caching.

== Description ==

Budget Translator translates your site into another language using free or paid providers, with hashed segment caching so identical text is never sent to the API twice.

Full documentation (features, installation, FAQ, changelog) is in README.md on GitHub:
https://github.com/sfambach/wp-budget-translator

== Installation ==

1. Copy this folder to `wp-content/plugins/budget-translator`
2. Activate Budget Translator
3. Open Budget Translator → Settings, choose languages and provider
4. Optionally fill Do not translate / Glossary and exclusion rules
5. Click Translate website now
6. Visit /en/ (or your target language) on the frontend

== Frequently Asked Questions ==

= Settings needed for good free-tier results =

* Set a contact email for MyMemory (higher free quota).
* Prefer the batch job over relying only on on-the-fly.
* Use Do not translate for brands/codes and Glossary for fixed wording.
* For production quality, use DeepL or Google with an API key.

= Critical error / blank page on /en/ =

Run Translate website now in Settings so the cache is filled. Free on-the-fly APIs can time out when many segments are uncached.

== Changelog ==

= 1.3.0 =

* Review menu: Bulk, By post, One by one, Settings
* Per-post review with on-open machine translation and confirmed-% overview
* Quality pipeline (guards, punctuation, source auto-corrections, technical passthrough)
* Auto-confirm target-language passthrough; cleanup of invalid/duplicate cache rows
* Sortable review lists; frontend timeout protection for free providers
* Language switcher Gutenberg block; German translations (languages/)
