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

## FAQ

### Critical error / blank page on `/en/`

On-the-fly translation calls the provider during page render. Free APIs (especially MyMemory) are slow; many uncached segments can exceed PHP’s max execution time.

**Fix:** Run **Translate website now** in Settings so the cache is filled. Frontend on-the-fly is limited to a few API calls per request; the rest stays in the source language until the next visit or batch run.

### Settings needed for good free-tier results

- Set a contact email for MyMemory (higher free quota).
- Prefer the batch job over relying only on on-the-fly.
- For production quality, use DeepL or Google with an API key.

### Emails and URLs appear translated

They should not. Budget Translator masks emails, `http(s)` links and common website hosts before sending text to providers.

## License

GPL-2.0-or-later
