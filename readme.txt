=== Virtual Media Folders — Media Cleanup ===
Contributors: PerS
Tags: media, cleanup, unused, duplicates, virtual-media-folders
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find and manage unused, duplicate, and oversized media in your WordPress library. Add-on for Virtual Media Folders.

== Description ==

Media Cleanup is an add-on for [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/). It scans your media library and gives you tools to deal with:

* **Unused media** — attachments not referenced in any post content, featured image, widget, or site option.
* **Duplicates** — files with identical content (SHA-256 hashes), regardless of filename.
* **Oversized files** — media exceeding configurable size thresholds per type (images, video, audio, documents).

**Nothing is deleted automatically.** Every action requires explicit user confirmation.

= What you can do =

* **Archive** items to a virtual folder (file stays on disk, just categorised).
* **Trash** items (reversible — restore from the Trash tab).
* **Flag** items for later review.
* **Set primary** in a duplicate group and trash the copies in one click.

= How it works =

* Deep content scanning — Gutenberg blocks, classic editor, page builder meta (Elementor, Beaver Builder, Fusion Builder), featured images, widgets, and site options.
* Background scanning via Action Scheduler for large libraries.
* React-based admin dashboard with seven tabs: Scan, Unused, Duplicates, Oversized, Flagged, Trash, Settings.
* Full WP-CLI support for scripted workflows.
* Hooks and filters for developers — see [Developer Guide](https://github.com/soderlind/vmfa-media-cleanup/blob/main/docs/DEVELOPER.md).
* Translation-ready — ships with Norwegian Bokmål (nb_NO).

== Installation ==

1. Install and activate [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/).
2. Upload or install this plugin and activate it.
3. Go to **Media → Virtual Folders → Media Cleanup**.

== Frequently Asked Questions ==

= Does this delete files automatically? =

No. The plugin only detects issues. All actions (archive, trash, delete, flag) are user-initiated and require confirmation.

= How does archiving work? =

Archiving moves a media item into an "Archive" virtual folder. The actual file stays in its original location on disk — only the taxonomy term changes. The folder name is configurable in Settings.

= Can I restore trashed media? =

Yes. Trashed media appears in the Trash tab and can be restored or permanently deleted.

= What about page builders? =

The reference scanner checks known meta keys for Elementor, Beaver Builder, Fusion Builder, and similar page builders. You can add custom meta keys with the `vmfa_cleanup_reference_meta_keys` filter.

= Does it work with large libraries? =

Yes. Scanning runs in the background via Action Scheduler with configurable batch sizes.

== Screenshots ==

1. Dashboard with scan progress and statistics.
2. Unused media list with bulk actions.
3. Duplicate groups with primary selection.
4. Settings panel with threshold configuration.

== Changelog ==

= 0.5.0 =
* Changed: Integrated as subtab within parent plugin's settings page
* Changed: Dashboard tabs (Scan, Duplicates, etc.) now appear under Media Cleanup subtab
* Fixed: Version constant mismatch (was 0.3.0, now matches header 0.5.0)

= 0.4.0 =
* Added: StatsCard component showing Total Media, Unused, Duplicates, and Oversized counts at dashboard top.
* Changed: Tabs now appear above the stats card (consistent with AI Organizer).
* Removed: Inline stats from dashboard header (replaced by StatsCard).

= 0.3.0 =
* Added: Settings tab — oversized thresholds, scan depth, batch size, auto-scan, archive folder name.
* Added: "Start Scan" button on empty result tabs.
* Added: Full i18n pipeline and Norwegian Bokmål (nb_NO) translation.
* Fixed: Settings panel error when fetch fails.
* Fixed: SelectControl uppercase label — replaced with native select elements.
* Fixed: Settings UI spacing and section styling.

= 0.2.0 =
* Added: Reference count display on duplicate group members.
* Added: Warning confirmation when trashing in-use duplicate media.
* Fixed: PHP 8.x error in ReferenceIndex::get_total_posts().
* Fixed: Scan status mismatch preventing UI completion.
* Fixed: Results/duplicates response parsing from REST API.
* Fixed: Dashboard stats key mismatch with backend response.

= 0.1.0 =
* Initial release.
* Unused, duplicate, and oversized media detection.
* Archive, trash, and flag actions.
* Action Scheduler background scanning.
* WP-CLI commands and REST API.
* React admin dashboard.
