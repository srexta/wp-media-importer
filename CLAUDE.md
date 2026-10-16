# WP Media Importer

WordPress plugin: search free stock photo sites and import photos into the media library.
Pexels, Pixabay, Unsplash and Openverse ship today; the code is built around pluggable providers so more sources are one class each.
(Pexels paused new API key issuance in Oct 2026, so test with Pixabay.)
Modelled on `../react-settings-plugin` (same PHP class layout, `@wordpress/scripts` build, `window.*` boot data).

## Layout

```
wp-media-importer.php                 bootstrap, WPMI_* constants
includes/class-plugin.php             requires + wires modules
includes/class-settings.php           option `wpmi_settings` = { keys: { <slug>: key }, image: { max_width, max_height,
                                      quality, format, max_selection } } (not autoloaded)
includes/class-providers.php          provider registry, `wpmi_providers` filter, enabled() = has a key
includes/providers/class-provider.php abstract Provider + shared request() helper
includes/providers/class-pexels.php   Pexels (key in Authorization header)
includes/providers/class-pixabay.php  Pixabay (key in ?key= query param; tags used as alt)
includes/providers/class-unsplash.php Unsplash (Client-ID header; UTM credit links; on_import pings download_location)
includes/providers/class-openverse.php Openverse (no key: requires_key() false; license_type=commercial; CC attribution as caption)
includes/class-admin-page.php         Settings → Media Importer page
includes/class-rest-controller.php    REST routes (below); all import logic lives here
includes/class-assets.php             build/settings.* on our page; build/importer.* on `wp_enqueue_media` (every media modal) + upload.php
src/settings/                         React settings screen (one key field per provider)
src/importer/                         "From <Provider>" buttons + ImportModal. Extends wp.media.view.AttachmentsBrowser so the
                                      button sits in every media browser toolbar (Library grid, Add Media, block editor,
                                      featured image); list-mode Library gets header buttons instead
```

## Commands

- `npm install` once, then `npm run start` (watch) or `npm run build`. `build/` is gitignored — always build before testing.
- `npx wp-scripts lint-js src` and `npx wp-scripts lint-style 'src/**/*.scss'` must pass.
- `php -l` on changed PHP files.

## REST — namespace `wp-media-importer/v1`

| Route | Method | Capability | Notes |
|---|---|---|---|
| `/settings` | POST `{ keys: { slug: key } }` | `manage_options` | `""` clears a key; returns providers with masked keys |
| `/{provider}/search` | GET `query`, `page` | `upload_files` | proxied to the provider, 24 per page |
| `/{provider}/import` | POST `{ id }` | `upload_files` | re-fetches the photo server-side, sideloads it, dedupes via `_wpmi_source` + `_wpmi_source_id` meta |

## Rules

- Namespace `WPMediaImporter`, prefix `wpmi`, text domain `wp-media-importer`, PHP 7.4 compatible.
- API keys never reach the browser: the settings screen only gets a masked value, and every provider call goes through PHP.
- Never download a URL sent by the client. Import takes an id and asks the provider for the download URL (prevents SSRF).
- Import settings: photos are resized to fit max_width × max_height (never upscaled), re-encoded at `quality`, optionally converted to WebP, all via `wp_get_image_editor()` in `Rest_Controller::process_image()`. Editor failures keep the original file. `max_selection` caps how many photos can be selected per import (UI only); it never limits search results.
- Every imported attachment gets alt text, a credit caption ("Photo by X on Pexels") and the source meta. Providers require attribution.
- Escape output, check capabilities in every REST `permission_callback`, translator comments on every `sprintf( __() )`.

## Adding a provider

1. Create `includes/providers/class-<slug>.php` extending `Provider`: `slug()`, `label()`, `site_url()`, `key_url()`, `search()`, `get()`, and `on_import()` if the API needs it. `search()`/`get()` return the normalised shape documented in `class-provider.php`.
2. `require_once` it in `class-plugin.php` and add `'<slug>' => Class::class` to the list in `Providers::all()`.
3. That's it: the settings field, REST routes, media library button and modal all pick it up.

Keyless sources override `requires_key()` to return false: they are always enabled and the settings page shows "No API key needed" instead of a key field.

## Testing by hand

Local site "vibe". Settings → Media Importer: save a Pixabay key (free, instant at pixabay.com/api/docs once logged in). "From Pixabay" (and "From Pexels" if it has a key) should appear in the media modal toolbar everywhere: Media → Library grid, classic Add Media, block editor Image block → Media Library, featured image. In list view it sits beside "Add New Media File". Search, select up to the limit, Add photos. In a media modal the photos appear at the top of the library already selected; in list view the page reloads. Importing the same photo again does not create a duplicate.
