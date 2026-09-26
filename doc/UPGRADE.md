# Upgrade guide

## Pre-1.0 to 1.0

There is no previous public stable release. `1.0.0` establishes the first stable API baseline.

Before adopting a release candidate:

1. Run `php artisan migrate`; it creates the `bulkflow_import_runs`, `bulkflow_row_failures`, `bulkflow_scheduled_exports`, and any required `job_batches` table. Current V1 migrations also add `revision` and `batch_id` to import runs for ordered progress and cancellation.
2. Publish configuration only if the default authorization behaviour is unsuitable: `php artisan vendor:publish --tag=bulkflow-config`.
3. Configure Laravel queue and scheduler workers in production. Scheduled definitions do not run unless Laravel's normal scheduler is running.
4. Keep exports on private disks by default. Grant public access only through application-level, time-limited URLs.

## Compatibility

The Laravel package declares PHP 8.2+ and Illuminate 12 or 13 support. The Vue package requires Vue 3.4+ and Node 20+ for local development.

## Data safety

- `upsertBy()` is the recommended strategy for retryable imports because it makes duplicate execution idempotent for the selected keys.
- `stop-on-threshold` must specify `stopOnErrorCount()`; it intentionally leaves later source rows unprocessed.
- Scheduled-export definitions are updated by their unique `name`; changing the name creates a separate definition.
