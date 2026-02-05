# VMFA Media Cleanup

Add-on for [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders) — detect and manage unused, duplicate, and oversized media items.

## Requirements

- WordPress 6.8+
- PHP 8.3+
- Virtual Media Folders plugin (active)

## Development

### Install dependencies

```bash
composer install
npm install
```

### Build assets

```bash
npm run build
```

### Watch mode

```bash
npm start
```

### PHP linting

```bash
composer lint
```

### PHP tests

```bash
composer test
```

### JS tests

```bash
npm test
```

## WP-CLI Commands

```bash
# Run a full scan
wp vmfa-cleanup scan

# Run async scan
wp vmfa-cleanup scan --async

# List detected issues
wp vmfa-cleanup list --type=unused
wp vmfa-cleanup list --type=duplicate --format=csv
wp vmfa-cleanup list --type=oversized --format=json

# Show scan statistics
wp vmfa-cleanup stats

# Archive unused media
wp vmfa-cleanup archive --type=unused --yes

# Trash unused media
wp vmfa-cleanup trash --type=unused --yes

# Flag/unflag media
wp vmfa-cleanup flag 42 56 78
wp vmfa-cleanup unflag 42 56

# List duplicate groups
wp vmfa-cleanup duplicates

# Recompute file hashes
wp vmfa-cleanup rehash
```

## Hooks & Filters

### Actions

| Action | Description |
|--------|-------------|
| `vmfa_cleanup_scan_complete` | Fires after a scan finishes |
| `vmfa_cleanup_before_bulk_action` | Fires before any bulk action |
| `vmfa_cleanup_media_archived` | Fires after archiving a media item |
| `vmfa_cleanup_media_trashed` | Fires after trashing a media item |
| `vmfa_cleanup_media_flagged` | Fires after flagging a media item |
| `vmfa_cleanup_settings_updated` | Fires after settings are saved |

### Filters

| Filter | Description |
|--------|-------------|
| `vmfa_cleanup_is_unused` | Override whether an attachment is considered unused |
| `vmfa_cleanup_oversized_thresholds` | Modify per-MIME-type size thresholds |
| `vmfa_cleanup_archive_folder_name` | Change the archive folder name |
| `vmfa_cleanup_hash_algorithm` | Change the hash algorithm (default: sha256) |
| `vmfa_cleanup_reference_meta_keys` | Add meta keys for reference scanning |
| `vmfa_cleanup_reference_sources` | Add custom reference sources |

## License

GPLv2 or later
