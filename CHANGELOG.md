# Changelog

All notable changes to this monorepo are documented here.

## Unreleased

### Added

- Error viewer pagination controls and separate CSV/XLSX failure-report links in the Vue demo.
- Client-side mapping validation that requires every source column to map to a unique destination before an import can start.
- Optional versioned progress publisher, Laravel Echo transport with polling fallback, and server-paginated, filterable import dashboard history.

## 1.0.0 — 2026-09-26

### Added

- Laravel CSV/XLSX streaming imports and exports, mapping, validation, transforms and Eloquent upserts.
- Import runs, progress events, durable failed rows, CSV/XLSX failure reports and retry-failures endpoint.
- Queue dispatch, correct CSV/XLSX total-row progress metrics, chunk metrics and partial-import policies: `continue`, `fail-fast`, `stop-on-threshold`.
- Vue headless API client plus import wizard, progress, dashboard and error-viewer components.
- Scheduled CSV/XLSX exports with Laravel scheduler registration, filesystem disks and mail notifications, plus configurable import-completion notifications.
- Local Laravel demo application and package-level automated tests.

### Security

- Failed-row payloads redact common secret fields before persistence and export.
- Import APIs pass through a configurable authorization callback.
- Laravel 11 support was removed before the first public release because current
  Composer security advisories block a fresh Laravel 11 installation; V1 supports
  Laravel 12 and 13.

This is the first stable release of the Laravel and Vue packages.

## Release policy

The first public tag will be `1.0.0` only after the release checklist in [doc/RELEASE.md](doc/RELEASE.md) is signed off by the project owner.
