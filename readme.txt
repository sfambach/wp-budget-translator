# Budget Translator

Affordable WordPress website translation with local segment caching.

## Features

- Choose source and target languages
- Automatic translation via MyMemory or LibreTranslate (free), DeepL or Google (API key)
- Identical text passages are hashed and cached — never sent to the API twice
- Review, edit and confirm translations in wp-admin
- Frontend URLs as `/en/...` language prefixes
- Language switcher (footer + shortcode `[bt_language_switcher]`)

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Copy this folder to `wp-content/plugins/budget-translator`
2. Activate **Budget Translator**
3. Open **Budget Translator → Settings**, choose languages and provider
4. Click **Translate website now**
5. Visit `/en/` (or your target language) on the frontend

## License

GPL-2.0-or-later
