# Developer Guide

Technical reference for extending and integrating with the Media Cleanup plugin.

## REST API

All endpoints use the `vmfa-cleanup/v1` namespace and require the `manage_options` capability.

### Scan

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/scan` | Start a new scan. Optional body: `{ "types": ["unused","duplicate"] }` |
| `GET` | `/scan/status` | Get scan progress (`status`, `phase`, `progress`, `total`) |
| `POST` | `/scan/cancel` | Cancel a running scan |
| `POST` | `/scan/reset` | Reset all scan results |
| `GET` | `/stats` | Dashboard statistics (`unused_count`, `duplicate_count`) |

### Results

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/results` | Paginated list. Params: `type`, `page`, `per_page`, `orderby`, `order` |
| `GET` | `/results/{id}` | Detail for a single attachment |
| `GET` | `/duplicates` | Paginated duplicate groups. Params: `page`, `per_page` |

### Actions

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/actions/trash` | Trash items. Body: `{ "ids": [...], "confirm": true }` |
| `POST` | `/actions/restore` | Restore trashed items. Body: `{ "ids": [...] }` |
| `POST` | `/actions/delete` | Permanently delete. Body: `{ "ids": [...], "confirm": true }` |
| `POST` | `/actions/archive` | Archive to virtual folder. Body: `{ "ids": [...], "confirm": true }` |
| `POST` | `/actions/flag` | Flag for review. Body: `{ "ids": [...] }` |
| `POST` | `/actions/unflag` | Remove flag. Body: `{ "ids": [...] }` |
| `POST` | `/actions/set-primary` | Set primary in duplicate group. Body: `{ "id": ..., "group_ids": [...] }` |

### Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/settings` | Get current settings |
| `POST` | `/settings` | Update settings (partial merge) |

## Hooks & Filters

### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `vmfa_cleanup_scan_complete` | `$results` | Fires after a scan finishes. `$results` is an array of scan results grouped by type. |
| `vmfa_cleanup_before_bulk_action` | `$action`, `$ids` | Fires before any bulk action |
| `vmfa_cleanup_media_archived` | `$attachment_id`, `$folder_id` | Fires after archiving a media item. `$folder_id` is the archive folder term ID. |
| `vmfa_cleanup_media_trashed` | `$attachment_id` | Fires after trashing a media item |
| `vmfa_cleanup_media_flagged` | `$attachment_id` | Fires after flagging a media item |
| `vmfa_cleanup_settings_updated` | `$updated`, `$current` | Fires after settings are saved. `$updated` is the new settings, `$current` is the previous settings. |

### Filters

| Filter | Default | Description |
|--------|---------|-------------|
| `vmfa_cleanup_is_unused` | `true` | Override whether an attachment is unused. Return `false` to skip. |
| `vmfa_cleanup_archive_folder_name` | `"Archive"` | Change the archive virtual folder name |
| `vmfa_cleanup_hash_algorithm` | `"sha256"` | Change the file hash algorithm |
| `vmfa_cleanup_reference_meta_keys` | `[]` | Add custom meta keys to scan for attachment references |
| `vmfa_cleanup_reference_sources` | `[]` | Add custom reference sources beyond post content and featured images |

### Examples

#### Skip certain post types from unused detection

```php
add_filter( 'vmfa_cleanup_is_unused', function ( $is_unused, $attachment_id ) {
    // Never flag WooCommerce product images as unused.
    $product_ids = get_posts( [
        'post_type'  => 'product',
        'meta_key'   => '_thumbnail_id',
        'meta_value' => $attachment_id,
        'fields'     => 'ids',
    ] );

    return empty( $product_ids ) ? $is_unused : false;
}, 10, 2 );
```

#### Run custom logic after a scan

```php
add_action( 'vmfa_cleanup_scan_complete', function ( $results ) {
    // $results contains scan results grouped by type.
    wp_mail( 'admin@example.com', 'Media scan complete', count( $results ) . ' issues found.' );
} );
```

## Project Structure

```
vmfa-media-cleanup/
├── src/
│   ├── js/
│   │   ├── components/     # React components (Dashboard, Panels, Modals)
│   │   ├── hooks/          # Custom hooks (useScanStatus, useResults, useSettings)
│   │   └── index.js        # Entry point
│   ├── php/
│   │   ├── CLI/            # WP-CLI command registration
│   │   ├── Detectors/      # UnusedDetector, DuplicateDetector
│   │   ├── REST/           # ScanController, ResultsController, ActionsController, SettingsController
│   │   ├── Services/       # ScanService, ReferenceIndex, HashService
│   │   ├── Update/         # GitHubPluginUpdater
│   │   └── Plugin.php      # Bootstrap class
│   └── styles/
│       └── admin.scss      # Admin dashboard styles
├── build/                  # Compiled assets (generated)
├── languages/              # Translation files (POT, PO, MO, JSON, PHP)
├── tests/
│   ├── js/                 # Vitest component & hook tests
│   └── unit/               # Pest PHP unit tests
├── docs/                   # Developer documentation
├── i18n-map.json           # Source → script handle map for JSON translations
├── package.json            # Node dependencies & scripts
├── composer.json           # PHP dependencies
└── vmfa-media-cleanup.php  # Plugin entry file
```

## Development Setup

### Prerequisites

- Node.js 18+
- PHP 8.3+
- Composer 2+

### Install dependencies

```bash
composer install
npm install
```

### Build

```bash
npm run build        # Production build
npm start            # Watch mode with hot reload
```

### Tests

```bash
npm test             # JS tests (Vitest)
vendor/bin/pest       # PHP tests (Pest)
```

### Linting

```bash
npm run lint:js       # ESLint
composer lint         # PHP_CodeSniffer
```

### Internationalization

```bash
npm run i18n          # Full pipeline: POT → update PO → MO → JSON → PHP
npm run i18n:make-pot # Extract strings only
```

Individual steps: `i18n:make-pot`, `i18n:update-po`, `i18n:make-mo`, `i18n:make-json`, `i18n:make-php`.

#### `i18n-map.json`

The `make-json` step converts PO translations into JSON files that WordPress loads for JavaScript via `wp_set_script_translations()`. It needs to know which script handle each source file belongs to, so it can generate the correct filename hash.

`i18n-map.json` maps every source JSX file that contains translatable strings to the script handle (`vmfa-media-cleanup-admin`):

```json
{
  "src/js/components/BulkActionBar.jsx": "vmfa-media-cleanup-admin",
  "src/js/components/CleanupDashboard.jsx": "vmfa-media-cleanup-admin",
  ...
}
```

The keys must use **source file paths** (not `build/index.js`), because the PO file's `#:` reference comments point to source files. WordPress computes the JSON filename as `{textdomain}-{locale}-{md5(handle)}.json`, so the map ensures the hash matches the handle registered with `wp_set_script_translations()`.

When adding a new component that uses `__()` or `_n()`, add its path to this file.
