# V1 internal release audit

Date: 2026-09-16

This audit records verified internal readiness. It is not a public release
announcement and does not authorize publishing.

## Verified capabilities

| Requirement | Evidence |
| --- | --- |
| CSV/XLSX import/export, mapping, validation and upsert | Laravel package suite: 62 tests / 170 assertions (one opt-in S3 test skipped in ordinary local execution) |
| Upload safety | `from(UploadedFile)` validates extension and configured byte limit, then materializes the request file into private application storage before sync or queued processing |
| Large queued imports | Fresh isolated 100k CSV run: 100,000 successful, 0 failed, 26.924 seconds, 35,651,584-byte peak memory; queued CSV and XLSX runs persist their total row count before chunk processing; [benchmark](benchmarks/v02.md) |
| Failed rows, filtered review, retry and reports | Feature tests for API/filter/retry/download; live Vue demo displayed a validation failure, filter selector and CSV report link |
| S3-compatible storage | Live MinIO test: import from disk, export to disk and temporary URL, 3 assertions |
| Scheduled exports, notifications, history, retention and scope | Integration/feature tests; imports can notify configured Laravel notifiables when complete; pruning requires `--before` and supports non-destructive `--dry-run` |
| Vue client and demo | 38 Vue tests; package build and independent Vite demo build passed; demo Laravel suite has 13 tests / 43 assertions and uses per-run random passwords rather than reusable credentials |
| Polling, realtime and cancellation | `ImportProgressTracker` has tested stoppable polling, terminal cancellation, revision ordering and a first-party Laravel Echo adapter. Laravel broadcasts a stable full snapshot on an authorized private channel using the same scope/authorizer hooks as HTTP; an authorized API call cancels a queued batch or pre-dispatch run. A live demo `processing` run was cancelled and then returned as `cancelled` from the show endpoint |
| Compatibility | Fresh Laravel 12 package discovery and live Laravel 13 demo route discovery; [matrix](COMPATIBILITY.md) |
| Release artifacts | Root Composer manifest validates and is automatically checked against the Laravel package manifest; an archive smoke test excluded vendors, temporary files and frontend/docs, then installed successfully in the Laravel demo with package discovery. NPM dry-run produces `@bulkflow/vue@1.0.0` with only README, dist and package manifest. Both manifests include the verified canonical repository URL, issue tracker, and maintainer metadata |
| Release source hygiene | Targeted source/config scan found only null `.env.example` password placeholders and runtime-generated demo passwords; no committed credential value is used by the demo |

## Deliberate release boundaries

- This repository contains no GitHub Actions workflow by project decision.
- Laravel 11 is not advertised as supported because Composer security advisories
  block a fresh secure installation. V1 supports Laravel 12 and 13.
- A public `1.0.0` release requires an owner-approved signed tag, Packagist registration and NPM owner credentials. Packagist
  reads the root Laravel manifest in this monorepo; the checked path mappings
  deliberately load the source under `packages/laravel-bulkflow`. These are
  listed in [RELEASE.md](RELEASE.md) and are not actions that the package
  implementation can perform autonomously.
