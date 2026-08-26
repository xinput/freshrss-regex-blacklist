# Changelog

## [Unreleased]

### Added

- A rule's Regex Pattern field now accepts multiple patterns, one per line,
  matched with OR semantics — a rule matches if any of its lines match. An
  invalid line is skipped without disabling the rule's other lines.
- Feed scope now accepts multiple feeds (a multi-select), not just one
  specific feed or all feeds. Leave the selection empty for "all feeds".

## [2.0.0] - 2026-08-26

### Added

- Named rules (list, not a single global pattern), each with its own regex
- Per-rule match field: Title only, Content only, Title + Content, or Author
- Per-rule feed scope: a specific subscribed feed, or all feeds
- Inline "Test Regex" tester in the configuration UI (client-side, live match/no-match)
- Per-rule blocked-article counter

### Fixed

- Extension called several methods/functions that don't exist on FreshRSS's real
  API (`getConfig`/`setConfig`/`isPost`/`getPost`, the global `_log()`,
  `$this->includeFile()`, `$entry->getTitle()/getContent()`), and registered its
  hook with a bare method-name string instead of `[$this, 'method']` — meaning
  the block hook likely never actually fired. Rewritten against the real
  `Minz_Extension`/`Minz_Request`/`Minz_Log`/`FreshRSS_Entry` APIs.
- Regex delimiter changed from `/` to `~` so patterns containing a literal `/`
  (e.g. URL patterns) work correctly
- Content matched against a pattern is now capped at 10000 characters to avoid
  catastrophic-backtracking patterns hanging the import worker

### Changed

- Configuration format is not backwards compatible with 1.0.0 — existing
  `global_patterns` are not migrated; re-create them as rules

## [1.0.0] - 2024-01-XX

### Added

- Initial release
- Global regex pattern filtering at import time
- Case-insensitive pattern matching
- Title and content matching
- Statistics tracking
- Configuration UI with form
- Comprehensive documentation

### Features

- **EntryBeforeInsert hook** integration for early filtering
- **Fail-safe design** — invalid patterns don't block all articles
- **JSON statistics** tracking of blocked articles
- **Per-user configuration** storage
- **Development mode logging** for debugging
