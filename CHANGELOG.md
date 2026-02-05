# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
