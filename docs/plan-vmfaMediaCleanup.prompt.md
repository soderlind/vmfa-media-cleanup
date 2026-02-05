## Plan: Virtual Media Cleanup Add-on

A non-AI utility add-on for Virtual Media Folders that detects unused, duplicate, and oversized media items — with batch actions to archive, trash, or flag files for review. Follows the established VMFA add-on architecture (singleton `Plugin.php`, REST controllers, React UI in settings tab, Action Scheduler for background scanning, WP-CLI commands, GitHub auto-updater). All detection is review-first: scan results are presented for human review before any action is taken.

**Steps**

1. **Scaffold the project structure** following the established add-on convention from both reference implementations:

   ```
   vmfa-media-cleanup/
   ├── build/
   ├── languages/
   ├── src/
   │   ├── js/
   │   │   ├── __tests__/
   │   │   ├── components/
   │   │   │   ├── CleanupDashboard.jsx       # Main dashboard (stats + scan controls)
   │   │   │   ├── ScanProgress.jsx           # Real-time scan progress bar
   │   │   │   ├── ResultsPanel.jsx           # Tabbed results list (Unused / Duplicates / Oversized / Flagged)
   │   │   │   ├── MediaItemRow.jsx           # Single result row with thumbnail, info, actions
   │   │   │   ├── DuplicateGroup.jsx         # Grouped duplicates with primary selector
   │   │   │   ├── BulkActionBar.jsx          # Sticky bulk-action toolbar (Archive / Trash / Flag)
   │   │   │   └── ConfirmModal.jsx           # Confirmation dialog for destructive actions
   │   │   ├── hooks/
   │   │   │   ├── useScanStatus.js           # Polls scan progress endpoint
   │   │   │   └── useResults.js              # Fetches/paginates scan results
   │   │   ├── styles/
   │   │   │   └── admin.scss
   │   │   └── index.js                       # React entry point → mounts into #vmfa-media-cleanup-app
   │   └── php/
   │       ├── Detectors/
   │       │   ├── DetectorInterface.php       # Contract: scan(), get_type(), get_label()
   │       │   ├── UnusedDetector.php          # Finds unattached + unreferenced media
   │       │   ├── DuplicateDetector.php       # Groups by file hash (SHA-256)
   │       │   └── OversizedDetector.php       # Flags above configurable threshold
   │       ├── REST/
   │       │   ├── ScanController.php          # Start/status/cancel/reset scan
   │       │   ├── ResultsController.php       # List/filter/paginate results
   │       │   ├── ActionsController.php       # Archive/trash/flag/unflag operations
   │       │   └── SettingsController.php      # GET/POST settings
   │       ├── Services/
   │       │   ├── ScanService.php             # Orchestrates batch scanning via Action Scheduler
   │       │   ├── ReferenceIndex.php          # Builds + caches reverse-lookup of attachment usage
   │       │   └── HashService.php             # Computes + stores file hashes in attachment meta
   │       ├── CLI/
   │       │   └── Commands.php                # WP-CLI: scan, list, archive, trash, stats
   │       ├── Update/
   │       │   └── GitHubPluginUpdater.php      # PUC wrapper (same pattern as existing add-ons)
   │       └── Plugin.php                      # Singleton: init services, hooks, REST, CLI
   ├── tests/
   │   ├── php/
   │   └── js/
   ├── vendor/
   ├── vmfa-media-cleanup.php                  # Bootstrap file
   ├── composer.json
   ├── package.json
   ├── phpcs.xml
   ├── phpunit.xml.dist
   ├── vitest.config.js
   ├── i18n-map.json
   └── readme.txt
   ```

2. **Create the bootstrap file** `vmfa-media-cleanup.php` with:
   - Plugin header including `Requires Plugins: virtual-media-folders`
   - Constants: `VMFA_MEDIA_CLEANUP_VERSION`, `_FILE`, `_PATH`, `_URL`, `_BASENAME`
   - Composer autoloader (`vendor/autoload.php`)
   - Early Action Scheduler load (same pattern as AI Organizer)
   - `plugins_loaded` hook at priority 20 → VMFO_VERSION check → `Plugin::get_instance()`
   - Activation hook: set default `vmfa_media_cleanup_settings` option (threshold sizes, hash algorithm, archive folder slug)
   - Deactivation hook: unschedule all Action Scheduler jobs (`vmfa_cleanup_scan_batch`, `vmfa_cleanup_finalize_scan`), clear transients

3. **Implement `Plugin.php`** as a singleton (namespace `VmfaMediaCleanup`):
   - `init_services()` — instantiate `ScanService`, `ReferenceIndex`, `HashService`, all detectors, all REST controllers
   - `init_hooks()` — register tab via `vmfo_settings_tabs` filter (slug: `media-cleanup`), enqueue via `vmfo_settings_enqueue_scripts`, register REST routes, register Action Scheduler callbacks, add fallback standalone menu
   - `init_cli()` — register `wp vmfa-cleanup` command group
   - `load_textdomain()` for `vmfa-media-cleanup` text domain
   - Render callback outputs `<div id="vmfa-media-cleanup-app"></div>` (all-React UI, no sub-tabs initially)
   - `wp_localize_script` → `vmfaMediaCleanup` object with `restUrl`, `nonce`, `settings`, `folders`

4. **Build the Reference Index** (`Services/ReferenceIndex.php`):
   - Core method `build_index()` — runs as batched Action Scheduler job:
     - Query all posts (all public post types) in batches of 200
     - Parse `post_content` for attachment IDs: `wp-image-{id}`, `wp:image {"id":{id}}`, `wp:cover {"id":{id}}`, `wp:media-text {"mediaId":{id}}`, `wp:gallery` inner images, raw `?attachment_id=`, and `wp-content/uploads/` URL matches resolved via `attachment_url_to_postid()`
     - Collect `_thumbnail_id` post meta (featured images)
     - Scan common page builder meta keys: `_elementor_data`, `_fl_builder_data`, `panels_data`, `_fusion_builder_data` — extract attachment IDs via regex
     - Provide filter: `vmfa_cleanup_reference_meta_keys` to let third-party plugins register additional meta keys
     - Provide filter: `vmfa_cleanup_reference_sources` to let plugins add entirely custom reference sources (callback returning array of attachment IDs)
   - Store results in a custom DB table `{prefix}vmfa_media_references` (attachment_id, source_type, source_id) for fast lookups
   - Alternatively (simpler): store as a transient/option with a serialized map — but a table scales better for 10K+ media libraries
   - Method `is_referenced( int $attachment_id ): bool`
   - Method `get_references( int $attachment_id ): array` — returns where the media is used

5. **Implement the three Detectors** (Strategy pattern via `DetectorInterface`):

   **a) `UnusedDetector`** — finds media not referenced anywhere:
   - Query all attachments not in the reference index
   - Also check: no `_thumbnail_id` pointing to them, not used as site icon (`site_icon` option), not used as custom logo (`custom_logo` theme mod), not used in widgets
   - Provide filter: `vmfa_cleanup_is_unused` — allows third-party plugins to exclude specific attachments

   **b) `DuplicateDetector`** — groups by file hash:
   - Compute SHA-256 hash of original file (not thumbnails) via `HashService`
   - Store hash in `_vmfa_file_hash` attachment meta (compute once, reuse)
   - Group attachments sharing the same hash
   - Identify primary: oldest upload date (first `post_date`) is the "original"
   - Skip already-trashed attachments

   **c) `OversizedDetector`** — flags files above threshold:
   - Configurable threshold (default: 2 MB for images, 10 MB for other media)
   - Read from `_wp_attached_file` + `filesize()`, or `wp_filesize()` (WP 6.0+)
   - Separate thresholds for images vs. video vs. audio vs. documents (all configurable)
   - Report file size and dimensions (from `_wp_attachment_metadata`)

6. **Implement the Scan Service** (`Services/ScanService.php`):
   - Uses Action Scheduler for async batch processing (same pattern as AI Organizer's `MediaScannerService`)
   - Scan workflow: `start_scan()` → schedules `vmfa_cleanup_build_index` → on completion schedules `vmfa_cleanup_run_detectors` → on completion schedules `vmfa_cleanup_finalize_scan`
   - Scan progress stored in `vmfa_cleanup_scan_progress` option: `{ status, phase, total, processed, started_at, completed_at }`
   - Results stored in `vmfa_cleanup_results` option (or custom table for scale): grouped by detector type
   - Supports cancel/reset
   - Scan is idempotent — re-running clears previous results

7. **Implement REST Controllers** (namespace `vmfa-cleanup/v1`, all require `manage_options`):

   **`ScanController`** — routes:
   | Method | Route | Purpose |
   |--------|-------|---------|
   | POST | `/scan` | Start a scan (optionally limit to specific detector types) |
   | GET | `/scan/status` | Get current scan progress |
   | POST | `/scan/cancel` | Cancel running scan |
   | POST | `/scan/reset` | Clear all scan results |
   | GET | `/stats` | Dashboard statistics (total media, unused count, duplicate groups, oversized count, flagged count) |

   **`ResultsController`** — routes:
   | Method | Route | Purpose |
   |--------|-------|---------|
   | GET | `/results` | List results (filter by `type`: unused/duplicate/oversized/flagged; paginated) |
   | GET | `/results/{id}` | Single result detail (where used, duplicates, file info) |
   | GET | `/duplicates` | List duplicate groups with member attachments |

   **`ActionsController`** — routes:
   | Method | Route | Purpose |
   |--------|-------|---------|
   | POST | `/actions/archive` | Move selected attachment IDs to the `/Archive` virtual folder |
   | POST | `/actions/trash` | Trash selected attachment IDs (WordPress soft-delete) |
   | POST | `/actions/flag` | Add `_vmfa_flagged_for_review` meta to selected attachments |
   | POST | `/actions/unflag` | Remove the review flag |
   | POST | `/actions/set-primary` | Mark a specific duplicate as the primary/keep item |

   **`SettingsController`** — routes:
   | Method | Route | Purpose |
   |--------|-------|---------|
   | GET | `/settings` | Get current settings |
   | POST | `/settings` | Update settings |

8. **Implement Bulk Actions** in the `ActionsController`:
   - **Archive**: Auto-create a top-level `Archive` folder via `wp_insert_term('Archive', 'vmfo_folder')` if it doesn't exist. Move selected attachments with `wp_set_object_terms()`. Fire action `vmfa_cleanup_media_archived` for each.
   - **Trash**: Use `wp_trash_post()` — WordPress soft-delete, recoverable from trash. Fire action `vmfa_cleanup_media_trashed`.
   - **Flag for review**: Set `_vmfa_flagged_for_review` post meta to current timestamp. Fire action `vmfa_cleanup_media_flagged`.
   - All batch actions accept an array of attachment IDs, process in chunks of 50, return success/failure counts.
   - All destructive actions require a `confirm` parameter set to `true` (rejected without it).

9. **Build the React UI** mounted in the settings tab:

   **Dashboard view** (`CleanupDashboard.jsx`):
   - Stats card (4-column grid): Total Media | Unused | Duplicate Groups | Oversized | Flagged
   - "Run Scan" button → shows `ScanProgress` component with phase indicator and progress bar
   - Settings card: threshold inputs, archive folder name

   **Results view** (`ResultsPanel.jsx`):
   - Tab navigation: Unused | Duplicates | Oversized | Flagged for Review
   - Each tab shows a paginated list of `MediaItemRow` components
   - Each row: thumbnail (64×64), filename, file size, upload date, status badge, usage info, checkbox
   - Duplicates tab uses `DuplicateGroup` — expandable groups showing all copies with "Set as Primary" toggle
   - `BulkActionBar`: sticky bar appears when items are checked — buttons: Archive, Trash, Flag for Review, Clear Selection
   - `ConfirmModal`: displayed before Archive or Trash with item count and clear warning text

   **React hooks**:
   - `useScanStatus` — polls `/scan/status` every 2s during active scan, stops when complete
   - `useResults` — fetches paginated results with type filter, supports "Load More"

10. **Expose integration hooks** for third-party plugins:

    **Filters:**
    | Filter | Parameters | Purpose |
    |--------|-----------|---------|
    | `vmfa_cleanup_reference_meta_keys` | `array $meta_keys` | Add custom meta keys to scan for attachment references |
    | `vmfa_cleanup_reference_sources` | `array $sources` | Register custom reference source callbacks |
    | `vmfa_cleanup_is_unused` | `bool $is_unused, int $attachment_id` | Override unused detection for specific items |
    | `vmfa_cleanup_oversized_thresholds` | `array $thresholds` | Override size thresholds per MIME type |
    | `vmfa_cleanup_hash_algorithm` | `string $algo` | Change hash algorithm (default: sha256) |
    | `vmfa_cleanup_scan_batch_size` | `int $size` | Change batch size (default: 200) |
    | `vmfa_cleanup_archive_folder_name` | `string $name` | Change archive folder name (default: "Archive") |

    **Actions:**
    | Action | Parameters | Purpose |
    |--------|-----------|---------|
    | `vmfa_cleanup_scan_complete` | `array $results` | Fired when a full scan finishes |
    | `vmfa_cleanup_media_archived` | `int $attachment_id, int $folder_id` | After archiving an item |
    | `vmfa_cleanup_media_trashed` | `int $attachment_id` | After trashing an item |
    | `vmfa_cleanup_media_flagged` | `int $attachment_id` | After flagging for review |
    | `vmfa_cleanup_before_bulk_action` | `string $action, array $ids` | Before processing a bulk action |
    | `vmfa_cleanup_optimize_folder` | `int $folder_id, array $attachment_ids` | Hook for optimization plugins to process a folder's contents |

11. **Implement WP-CLI commands** (registered as `wp vmfa-cleanup`):

    | Command | Description |
    |---------|-------------|
    | `wp vmfa-cleanup scan [--type=<unused\|duplicate\|oversized\|all>]` | Run scan (foreground, with progress bar) |
    | `wp vmfa-cleanup list <type> [--format=<table\|json\|csv>]` | List results by type |
    | `wp vmfa-cleanup stats` | Show dashboard statistics |
    | `wp vmfa-cleanup archive <ids...> [--folder=<name>]` | Move items to archive folder |
    | `wp vmfa-cleanup trash <ids...> [--yes]` | Trash items (requires `--yes` for >10 items) |
    | `wp vmfa-cleanup flag <ids...>` | Flag items for review |
    | `wp vmfa-cleanup unflag <ids...>` | Remove review flag |
    | `wp vmfa-cleanup duplicates [--format=<table\|json>]` | List duplicate groups |
    | `wp vmfa-cleanup rehash [--force]` | Recompute file hashes (useful after migration) |

12. **Configure build tooling**:
    - `composer.json`: PSR-4 autoload `VmfaMediaCleanup\` → `src/php/`, require `woocommerce/action-scheduler ^3.7` + `yahnis-elsts/plugin-update-checker ^5.6`, dev-require `brain/monkey` + `pestphp/pest` + `wpcs`
    - `package.json`: single entry point `src/js/index.js`, dependencies `@wordpress/api-fetch`, `@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/icons`; build via `wp-scripts build src/js/index.js --output-path=build`
    - No custom webpack config needed (use `@wordpress/scripts` defaults)
    - `vitest.config.js` for JS tests, `phpunit.xml.dist` for PHP tests

13. **Settings storage**: single option `vmfa_media_cleanup_settings` containing:
    ```json
    {
      "image_size_threshold": 2097152,
      "video_size_threshold": 52428800,
      "audio_size_threshold": 10485760,
      "document_size_threshold": 10485760,
      "hash_algorithm": "sha256",
      "scan_batch_size": 200,
      "archive_folder_name": "Archive",
      "extra_meta_keys": []
    }
    ```

**Verification**

- Activate plugin with Virtual Media Folders active → no errors, tab appears in Folder Settings
- Activate without VMF → admin notice shown, plugin does not initialize
- Run scan on a library with ~100 items → scan completes, results show in each category
- Archive 3 items → "Archive" folder created, items appear in it
- Trash 2 items → items moved to WordPress trash (recoverable)
- Flag items → `_vmfa_flagged_for_review` meta set, items appear in Flagged tab
- Upload two identical files → duplicate detection groups them after scan
- `wp vmfa-cleanup scan --type=all` completes with progress bar in terminal
- `wp vmfa-cleanup stats` shows correct counts
- Deactivate → Action Scheduler jobs cleared
- PHP tests: `composer test` — detectors return expected results for mock data
- JS tests: `npm run test` — components render, scan progress updates, bulk actions fire

**Decisions**

- **SHA-256 over MD5**: SHA-256 for file hashing — MD5 has collision risks and is deprecated for integrity checks. Stored in `_vmfa_file_hash` attachment meta (computed once, cached).
- **Custom table for reference index**: `{prefix}vmfa_media_references` table over serialized options — scales to 50K+ media libraries without memory issues. Created on activation, dropped on uninstall.
- **All-React UI**: No WordPress Settings API forms — the scanning/review workflow is better served by a single React app with real-time updates, matching the Rules Engine pattern.
- **Action Scheduler over WP-Cron**: Reliable async processing with retry, logging, and CLI compatibility. Same dependency as AI Organizer — if both add-ons are active, Action Scheduler is loaded once.
- **Review-first safety**: No scan result is acted upon automatically. All destructive actions require explicit user selection + confirmation modal. WP-CLI trash requires `--yes` flag for batches >10.
- **Extensible content scanning**: Deep scan covers `post_content` block markup, `_thumbnail_id`, site icon, custom logo, widgets, and major page builder meta keys — with `vmfa_cleanup_reference_meta_keys` and `vmfa_cleanup_reference_sources` filters for third-party extensibility.
