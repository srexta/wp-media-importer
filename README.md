# WP Media Importer

Search free stock photo sites from inside WordPress and import photos straight into the media library, resized, optimised and credited.

## Supported sources

| Source | API key | Get a key | Notes |
|---|---|---|---|
| [Pixabay](https://pixabay.com) | Required (free) | [pixabay.com/api/docs](https://pixabay.com/api/docs/#api_search_images), shown once you are logged in | Pixabay has no alt text, so its keyword tags are used |
| [Unsplash](https://unsplash.com) | Required (free) | [unsplash.com/oauth/applications](https://unsplash.com/oauth/applications), create an app and copy its **Access Key** | New apps are limited to 50 requests/hour until Unsplash approves them |
| [Pexels](https://www.pexels.com) | Required (free) | [pexels.com/api](https://www.pexels.com/api/new/) | Pexels has paused new key sign-ups (Oct 2026); existing keys still work |
| [Openverse](https://openverse.org) | Not needed | — | Always on. Only images licensed for commercial use are shown |

## Requirements

- WordPress 6.4+
- PHP 7.4+
- For WebP conversion: a server image library (GD or Imagick) with WebP support. The settings page tells you if it's missing.

## Installation

1. Copy the `wp-media-importer` folder to `wp-content/plugins/`.
2. Build the scripts (the `build/` folder is not committed):
   ```bash
   npm install
   npm run build
   ```
3. Activate **WP Media Importer** under Plugins.
4. Go to **Settings → Media Importer** and paste the API keys for the sources you want.

## Using it

A **From &lt;Source&gt;** button appears for every source that is ready to use (key saved, or no key needed):

- **Media → Library** in grid view: in the media toolbar
- **Media → Library** in list view: next to "Add New Media File"
- **Classic editor**: in the Add Media window
- **Block editor**: in the Media Library window (Image block, Gallery, Cover, etc.)
- **Featured image** window

Click the button, search, click photos to select them, then **Add photos**.

- Inside a media window, the imported photos appear at the top of the library already selected, ready to insert or set as the featured image.
- In list view, the page reloads to show them.
- Importing a photo you already imported does not create a duplicate; the existing one is reused.

## Settings (Settings → Media Importer)

### API keys

One field per source. After saving, only the last four characters are shown. Use **Remove** to disconnect a source.

### Import settings

Applied to each photo as it is imported. Photos already in the library are not changed.

| Setting | Default | What it does |
|---|---|---|
| Photos per import | 5 | The most photos that can be selected and added in one go. Search results are never limited. |
| Maximum size | 1920 × 1920 px | Larger photos are scaled down to fit inside this box. Smaller ones are never enlarged. 0 = no limit. |
| Quality | 82 | Compression level, 1–100. Lower means smaller files. 82 is WordPress's own default. |
| Format | Keep original | Or **Convert to WebP**, usually about a third smaller than JPEG at the same quality. |

If resizing or conversion fails on your server, the original file is imported instead. An import never fails because of optimisation.

## What gets saved with each photo

- **File name**: `<source>-<photo id>.<ext>`, e.g. `pixabay-8568913.jpg`
- **Title and alt text**: the photo's description (Pixabay: keyword tags; Openverse: image title)
- **Caption**: the credit the source asks for, e.g. "Photo by Jane Doe on Unsplash". Openverse captions carry the full Creative Commons attribution and licence.
- Hidden meta `_wpmi_source` and `_wpmi_source_id`, used to spot duplicates.

Keep the captions when you publish photos. Most of these licences and API terms require credit.

## Privacy and security

- API keys are stored in your database and never sent to the browser after saving. Every search and download goes through your server.
- Only administrators (`manage_options`) can change keys and settings. Anyone who can upload media (`upload_files`) can search and import.
- The browser only sends a photo **id**. The server looks up the real download link from the source's API, so the import endpoint can't be used to fetch arbitrary URLs.

## REST API

Namespace `wp-media-importer/v1`, cookie + nonce authentication.

| Route | Method | Who | Body / params |
|---|---|---|---|
| `/settings` | POST | Admins | `{ keys: { pixabay: "…" }, image: { max_width, max_height, quality, format, max_selection } }`. `""` clears a key |
| `/{source}/search` | GET | Uploaders | `query`, `page` |
| `/{source}/import` | POST | Uploaders | `{ id }`, returns `{ id: <attachment id>, existing: bool }` |

## Adding another source

Each source is one PHP class in `includes/providers/` extending `Provider`. Register it in `Providers::all()` or from another plugin:

```php
add_filter( 'wpmi_providers', function ( $providers ) {
	return $providers + array( 'flickr' => My_Flickr::class );
} );
```

The settings field, REST routes, media buttons and search window pick it up automatically. See `CLAUDE.md` for the full developer guide.

## Development

```bash
npm run start                          # rebuild on change
npm run build                          # production build
npx wp-scripts lint-js src             # JS lint
npx wp-scripts lint-style 'src/**/*.scss'
```

## License

GPL-2.0-or-later
