# BulkFlow 1.0.0

## Highlights

- Streaming CSV/XLSX imports and exports with mapping, validation, transforms, and idempotent upserts.
- Queued chunk processing, retry policies, progress snapshots, and bounded-memory processing.
- Durable failed-row storage, filtered review, CSV/XLSX reports, and selective retries.
- `@bulkflow/vue`: import wizard, mapping templates, progress tracking, realtime Echo updates with polling fallback, and a filterable dashboard.
- Private storage, S3-compatible drivers, scheduled exports, notifications, retention, and scoped authorization hooks.

## Architecture

### Problem

Laravel applications need to process large spreadsheet imports without reimplementing parsing, validation, queueing, error recovery, and user-facing progress for every workflow.

### Challenge

Large files must stay memory-bounded, retries must not duplicate records, and realtime UI must keep working when broadcasting infrastructure is unavailable.

### Decision

BulkFlow keeps the import pipeline, run repository, failure repository, storage, and authorization behind Laravel contracts. The Vue package consumes only the typed HTTP and progress contracts.

### Architecture

Readers stream source rows into mapping, transformation, validation, and persistence stages. Run snapshots carry a monotonic revision; the browser first fetches a snapshot, accepts only newer Echo events, and falls back to polling after transport failure. Private-channel authorization uses the same scope and authorization hooks as HTTP endpoints.

### Trade-offs

Realtime broadcasting is intentionally optional. Applications without Redis, Reverb, Pusher, or Echo retain correct progress through polling at the cost of lower update frequency.

## Compatibility and upgrade notes

- PHP `^8.2`; Laravel `^12.0 || ^13.0`.
- Laravel 11 is not supported because the supported dependency set has unresolved security advisories.
- This is the first stable release; no migration from an earlier stable version is required.
