=== VMFA Media Cleanup ===
Contributors: suspended
Tags: media, cleanup, unused, duplicates, virtual-media-folders
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detect unused, duplicate, and oversized media items in your WordPress library. Add-on for Virtual Media Folders.

== Description ==

VMFA Media Cleanup is an add-on for the [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders) plugin. It scans your media library to find:

* **Unused media** — Attachments not referenced in any post content, featured image, widget, or site option.
* **Duplicates** — Files with identical content (SHA-256 hashes) regardless of filename.
* **Oversized files** — Media exceeding configurable size thresholds per MIME type.

= Features =

* **Non-destructive actions** — Archive to a virtual folder, trash (reversible), or flag for review.
* **Deep content scanning** — Detects references in Gutenberg blocks, classic editor, page builder meta (Elementor, Beaver Builder, Fusion Builder), featured images, widgets, and site options.
* **Async processing** — Uses Action Scheduler for background scanning of large libraries.
* **WP-CLI support** — Full command-line interface for scan, list, archive, trash, flag, duplicates, rehash.
* **Integration hooks** — Extensive filters and actions for other add-ons and custom workflows.
* **React-based UI** — Modern dashboard integrated into Virtual Media Folders settings or standalone.

= Requirements =

* WordPress 6.8+
* PHP 8.3+
* Virtual Media Folders plugin (active)

== Installation ==

1. Upload the `vmfa-media-cleanup` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Navigate to Media → Virtual Folders → Media Cleanup tab.

== Frequently Asked Questions ==

= Does this delete files automatically? =

No. The plugin only detects issues. Actions (archive, trash, flag) are always user-initiated and require confirmation.

= How does archiving work? =

Archiving moves a media item to an "Archive" virtual folder. The file itself stays in its original location on disk. This uses the Virtual Media Folders taxonomy system.

= Can I un-trash media? =

Yes. Trashed media goes to the standard WordPress trash and can be restored.

= What about page builders? =

The reference scanner checks known meta keys for Elementor, Beaver Builder, Fusion Builder, and similar page builders.

== Changelog ==

= 0.2.0 =
* Added: Reference count display on duplicate group members.
* Added: Warning confirmation when trashing in-use duplicate media.
* Fixed: PHP 8.x error from `array_values()` around `get_post_types()` spread.
* Fixed: Scan status mismatch preventing UI from showing completion.
* Fixed: Results response parsing — extract items array from REST response.
* Fixed: Dashboard stats key mismatch with backend response.
* Fixed: Duplicate groups not rendering from REST response.
* Fixed: DuplicateGroup component data shape for attachment_id/is_primary fields.

= 0.1.0 =
* Initial release.
* Unused media detection with deep content scanning (Gutenberg, classic editor, page builders).
* Duplicate detection via SHA-256 hashing.
* Oversized file detection with configurable per-MIME thresholds.
* Non-destructive actions: archive to virtual folder, trash, or flag for review.
* Action Scheduler background scanning for large libraries.
* WP-CLI commands: scan, list, archive, trash, flag, duplicates, rehash.
* React-based admin dashboard.
* REST API endpoints for scan, results, actions, and settings.
* Integration hooks (filters and actions) for custom workflows.
