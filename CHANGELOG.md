# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-09

### Changed

- Bump to stable 1.0.0 release

## [0.5.1] - 2025-06-25

### Fixed

- Removed stray character from main plugin file
- Added `defined( 'ABSPATH' ) || exit` guards to all PHP source files

### Added

- `uninstall.php` for clean plugin removal (drops custom tables and deletes options)

## [0.5.0] - 2026-02-07

### Changed

- Integrated as subtab within parent plugin's settings page
- Dashboard tabs (Scan, Duplicates, etc.) now appear under Media Cleanup subtab
- Fixed version constant mismatch (was 0.3.0, now matches header 0.5.0)

## [0.4.0] - 2026-02-06

### Added

- StatsCard component displaying Total Media, Unused (highlighted), Duplicates, and Oversized counts.

### Changed

- Dashboard layout: tabs now appear above the stats card (consistent with AI Organizer).
- Replaced inline header stats with dedicated StatsCard component.

## [0.3.0] - 2026-02-06

### Added

- Settings tab with UI for oversized thresholds, scan depth, batch size, auto-scan toggle, and archive folder name.
- `useSettings` hook for fetching/saving plugin settings via REST API.
- "Start Scan" button on empty state that triggers scan and navigates to Scan tab.
- Full i18n build pipeline: `npm run i18n` (make-pot, update-po, make-mo, make-json, make-php).
- Norwegian Bokmål (nb_NO) translation with ~75 translated strings.
- `i18n-map.json` mapping source JSX files to script handle for JSON translation generation.
- Translators comments on all `__()` calls containing `%d` placeholders.

### Fixed

- Settings panel error state when fetch fails (null settings hiding error Notice).
- `SelectControl` uppercase label issue — replaced with native `<select>` elements.
- Settings UI spacing/styling — added SCSS sections with headings, 2-column grid for thresholds, and proper field spacing.
- `make-pot` warnings for missing translators comments on sprintf-style strings.

## [0.2.0] - 2026-02-06

### Added

- Reference count display on duplicate group members ("Used in N post(s)").
- Warning confirmation when trashing in-use duplicate media, showing which items are referenced and by how many posts.

### Fixed

- Wrap `array_values()` around `get_post_types()` in `ReferenceIndex::get_total_posts()` to fix PHP 8.x "Cannot use positional argument after named argument" error.
- Scan status mismatch: frontend checked for `'done'` but backend returns `'complete'`, causing polling to never stop.
- Results response parsing: extract `items` array from REST response instead of using full wrapper object.
- Dashboard stats key mismatch: use `unused_count`/`duplicate_count`/`oversized_count` to match backend response.
- Duplicate groups not rendering: extract `groups` array from REST response.
- `DuplicateGroup` component data shape: use `attachment_id`/`is_primary` fields instead of `id`/`primary`.

### Changed

- Code style: consistent array bracket spacing across PHP tests and source files.

## [0.1.0] - 2026-02-05

### Added

- Unused media detection with deep content scanning (Gutenberg, classic editor, page builders).
- Duplicate detection via SHA-256 hashing.
- Oversized file detection with configurable per-MIME type thresholds.
- Non-destructive actions: archive to virtual folder, trash, or flag for review.
- Action Scheduler background scanning for large libraries.
- WP-CLI commands: `scan`, `list`, `archive`, `trash`, `flag`, `duplicates`, `rehash`.
- React-based admin dashboard.
- REST API endpoints for scan, results, actions, and settings.
- Integration hooks (filters and actions) for custom workflows.
