# Virtual Media Folders — Media Cleanup

Add-on for [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) that helps you find and manage unused, duplicate, and oversized media in your WordPress library.

## Features

- **Unused media detection** — deep content scanning across Gutenberg, classic editor, featured images, widgets, and custom meta keys.
- **Duplicate detection** — SHA-256 file hashing with primary/copy management and one-click trash of non-primary copies.
- **Oversized file detection** — configurable per-type thresholds (images, video, audio, documents).
- **Non-destructive actions** — archive to a virtual folder, trash (with restore), or flag for review.
- **Background scanning** — powered by Action Scheduler for large media libraries.
- **Admin dashboard** — React-based UI with tabs for Scan, Unused, Duplicates, Oversized, Flagged, Trash, and Settings.
- **WP-CLI support** — scan, list, archive, trash, flag, and manage duplicates from the command line.
- **Internationalization** — fully translatable; ships with Norwegian Bokmål (nb_NO).

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.8+ |
| PHP | 8.3+ |
| [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) | active |

## Installation


1. Download [`vmfa-media-cleanup.zip`](https://github.com/soderlind/vmfa-media-cleanup/releases/latest/download/vmfa-media-cleanup.zip)
2. Upload via `Plugins → Add New → Upload Plugin`
3. Activate via `WordPress Admin → Plugins`

Plugin [updates are handled automatically](https://github.com/soderlind/wordpress-plugin-github-updater#readme) via GitHub. No need to manually download and install updates.

## Usage

### Admin Dashboard

Navigate to **Media → Virtual Folders → Media Cleanup**. The dashboard has seven tabs:

| Tab | Purpose |
|-----|---------|
| **Scan** | Start/monitor scans, view progress |
| **Unused** | Media not referenced in any post content or featured image |
| **Duplicates** | Groups of files sharing the same hash — set primary, trash copies |
| **Oversized** | Files exceeding your configured size thresholds |
| **Flagged** | Items you've manually flagged for later review |
| **Trash** | Trashed items with restore/permanent-delete options |
| **Settings** | Thresholds, scan depth, batch size, auto-scan, archive folder |

### WP-CLI

```bash
wp vmfa-cleanup scan              # Run a full scan
wp vmfa-cleanup scan --async      # Run scan in background
wp vmfa-cleanup list --type=unused
wp vmfa-cleanup list --type=duplicate --format=csv
wp vmfa-cleanup stats             # Show scan statistics
wp vmfa-cleanup archive --type=unused --yes
wp vmfa-cleanup trash --type=unused --yes
wp vmfa-cleanup flag 42 56 78
wp vmfa-cleanup unflag 42 56
wp vmfa-cleanup duplicates        # List duplicate groups
wp vmfa-cleanup rehash            # Recompute file hashes
```

## Developer Documentation

REST API reference, hooks/filters, project structure, and build instructions are in [docs/DEVELOPER.md](docs/DEVELOPER.md).

## License

GPL-2.0-or-later
