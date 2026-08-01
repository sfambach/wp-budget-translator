# Budget Translator

Affordable WordPress website translation with local segment caching.

**Version 1.3.0**

## Features

- Choose source and target languages
- Automatic translation via MyMemory or LibreTranslate (free), DeepL or Google (API key)
- Identical text passages are hashed and cached — never sent to the API twice
- Admin menu: **Bulk** → **By post** → **One by one** → **Settings** (last)
- Review UX: bulk list, per-post review with auto-fill, and one-by-one confirm-and-advance
- Translation quality pipeline: term/link/number/shortcode guards, punctuation affix, source auto-corrections, technical passthrough
- Auto-confirm when source already looks like the target language; cache cleanup of invalid/duplicate rows on review open
- Sortable review columns; confirmed items hidden from the default “needs work” lists
- Do-not-translate list and per-language glossary
- Code / console prompts are not translated (`code`, `pre`, `kbd`, shell lines, …)
- Exclude post types, IDs and URL paths
- Auto-queue on save; SEO meta (Yoast) and image alts
- JSON export/import of the translation cache
- Frontend URLs as `/en/...` language prefixes
- Language switcher (footer, shortcode `[bt_language_switcher]`, Gutenberg block)

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Copy this folder to `wp-content/plugins/budget-translator`
2. Activate **Budget Translator**
3. Open **Budget Translator → Settings**, choose languages and provider
4. Optionally fill **Do not translate** / **Glossary** and exclusion rules
5. Click **Translate website now**
6. Visit `/en/` (or your target language) on the frontend

## FAQ

### Critical error / blank page on `/en/`

On-the-fly translation calls the provider during page render. Free APIs (especially MyMemory) are slow; many uncached segments can exceed PHP’s max execution time.

**Fix:** Run **Translate website now** in Settings so the cache is filled. Frontend on-the-fly is limited to a few API calls per request; the rest stays in the source language until the next visit or batch run. Admins see a notice when this happens (optional for visitors).

### Settings needed for good free-tier results

- Set a contact email for MyMemory (higher free quota).
- Prefer the batch job over relying only on on-the-fly.
- Use **Do not translate** for brands/codes and **Glossary** for fixed wording.
- For production quality, use DeepL or Google with an API key.

### Source auto-corrections

Under **Settings → Source auto-corrections** (on by default) small source typos are normalized before caching/translation:

1. Spaces before punctuation: `hier :` → `hier:`, `Ende .` → `Ende.`
2. Multiple spaces → one space
3. No space at mid-word line breaks (`el↵ectrolytic` → `electrolytic`; normal breaks between words keep a space)
4. Sentence starts capitalized (first letter and after `.` `!` `?`). Single words without spaces (e.g. link labels like `winnt`) are left unchanged.

This does not change the post content in the WordPress editor — only how segments are matched and sent to the API. Individual rules can be toggled; the master switch disables all of them.

### Confirmed text with punctuation

If `Beschreibung` is confirmed as `Description`, variants like `Beschreibung:` or `(Beschreibung)` reuse that translation, keep the surrounding punctuation (`Description:`, `(Description)`), and are stored as **confirmed** (or edited) — not left as auto. Only confirmed/edited cores are reused this way.

Provider responses are also aligned to the source edges: if the source has no trailing period, a machine result like `Download .` is stored as `Download`.

Numbers (issue numbers, versions, baud rates, units like `2,2uF`) are masked during API calls and reattached if the provider drops them. Provider output is also cleaned (double spaces, spaces before punctuation). The ` + ` joiner is preserved.

Placeholders for links/terms/numbers/shortcodes use ASCII tokens (`__BTL0__`, …). Free APIs often corrupt Unicode brackets (`⟦…⟧`) into broken `[[` / `]]` fragments.

### Code and console prompts

Text inside `code` / `pre` / `kbd` / `samp` and typical shell lines (`$ npm …`, `git …`, `C:\…`) is skipped. Long opaque IDs (hyphenated hex-heavy tokens) are skipped too. Short technical codes such as `R1,R2`, `U1/U2`, `v2.1`, or part numbers are **not sent to the API** — they are stored as confirmed passthrough (`translated = source`, `provider=passthrough`) so `/en/` still resolves them. Opening **Bulk** or a post review upgrades older garbled `auto` rows (e.g. MyMemory turning `R1,R2` into `R1:R2`) the same way. WordPress shortcodes (e.g. `[display-posts …]`) are also left unchanged — attribute values like category slugs must not be translated. Opening **Bulk**, **One by one**, or a **By post** detail also auto-removes leftover invalid API messages and code/console/shortcode cache rows. Manual purge buttons are no longer needed (or shown).

### Review by post

**Budget Translator → By post** lists posts with confirmed %. Open one to see **Needs work**: texts from that post that are not confirmed yet.

Missing texts are **machine-translated automatically** when you open the post. Confirm the suggestions (or use **Retry failed translations** if the provider failed). Identical phrases already confirmed elsewhere are reused automatically.

Opening **Bulk** or a post review also re-applies the shared quality polish to **auto** rows (numbers, punctuation, placeholder cleanup) so both screens match the frontend. Near-duplicates that only differ by ellipsis (`…` vs `...`) or case (MySQL collation twins) are collapsed to one row. Auto rows that only copy English→English (when the site source language is German) are removed and not created again.

Filters: Needs work (default) · Translated, not confirmed · Confirmed · All texts.

### English text in a German site

Budget Translator translates **whatever is currently in the post fields** (title, content, excerpt, Yoast SEO). It does not keep a separate “original German” copy. If another plugin or tool previously wrote English into a German post, that English becomes the “source” segment. BT detects text that already looks like the target language (e.g. `No module named 'serial'` on a de→en site), skips the provider API, and **auto-confirms** a passthrough row (`status=confirmed`, translation = source) so it does not sit in “Needs work”. Opening **Bulk** upgrades older identical/already-target `auto` rows the same way. Restore the German wording in the editor if the post was overwritten.

### Auto-queue on save

When enabled, publishing or updating a post/page (or menu item) appends changed texts to the translation queue. Keep the WP-Cron/`bt_every_minute` schedule active, or click **Process next chunk**.

### Exclusions

Use excluded post types, post IDs, or URL path prefixes (e.g. `/shop`) so those areas stay in the source language and are not queued.

### Export / Import

Export downloads the full segment cache as JSON. Import upserts by source hash; leave “Do not overwrite confirmed” checked to protect reviewed entries.

### Emails and URLs appear translated

They should not. Budget Translator masks emails, `http(s)` links and common website hosts before sending text to providers.

### Language switcher block

Insert the **Language Switcher** block in the editor, or use shortcode `[bt_language_switcher]`.

## Changelog

### 1.3.0

- Review menu: Bulk, By post, One by one, Settings
- Per-post review with on-open machine translation and confirmed-% overview
- Quality pipeline (guards, punctuation, source auto-corrections, technical passthrough)
- Auto-confirm target-language passthrough; cleanup of invalid/duplicate cache rows
- Sortable review lists; frontend timeout protection for free providers
- Language switcher Gutenberg block; German translations (`languages/`)

## License

GPL-2.0-or-later
