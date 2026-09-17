# UI User Import Design

## Goal

Complete the V1 demo so a user can upload a CSV or XLSX file in the Vue UI,
preview its columns, map them to demo-user attributes, queue an import, and
then inspect its actual progress, history, and row failures.

## Scope

- The demo accepts only CSV and XLSX files and uses BulkFlow's configured
  extension and file-size safeguards.
- The demo import target is `App\\Models\\User`.
- A mapping must be supplied for `name`, `email`, and `password`. The import
  validates `name`, `email`, and `password`; imports upsert by `email`.
- The queue is the execution mechanism. The developer starts a queue worker
  alongside the Laravel and Vite servers.
- This feature is a demo integration. It does not add a generic, model-driven
  import endpoint to the reusable Laravel package.

## API

The Laravel demo will own two endpoints under its existing `/bulkflow` API
prefix:

1. `POST /bulkflow/demo-imports/upload`
   - Accepts multipart field `file`.
   - Validates type and size, stores the upload on the local disk beneath a
     generated `bulkflow-inputs` path, and returns an opaque `upload_id`, the
     parsed headers, and at most five preview rows.
   - The response never exposes an absolute server file path.

2. `POST /bulkflow/demo-imports`
   - Accepts `upload_id` and `mapping`.
   - Resolves only an upload created by the first endpoint, confirms mappings
     are a one-to-one selection of `name`, `email`, and `password`, and queues
     `BulkFlow::import(User::class)` with validation and email upsert.
   - Returns the normal import-run JSON payload.

Upload metadata is stored in Laravel's cache with a short expiry and the
source file is retained in storage only for the queued import. The queued
BulkFlow source hardening remains the authority for safe source paths.

## Frontend Flow

The Vue demo starts with an "Import users" action. The wizard has three
states: select a file, review preview and choose mappings, then submit.
Loading and server-validation states are visible. On a successful response it
refreshes the import list and selects the returned run, reusing the existing
polling and failure-review UI.

The reusable `ImportWizard` remains presentational: it displays preview and
mapping controls. The demo owns upload requests, submission state, and API
errors through additions to `BulkFlowClient`.

## Error Handling

- Unsupported, corrupt, oversized, missing-header, and malformed mappings
  return Laravel validation errors (HTTP 422) and are rendered in the UI.
- An expired or already-consumed upload token returns a clear 422 response.
- Failed row validation is not an API failure: it is persisted by BulkFlow and
  shown through the existing Error Viewer.

## Tests and Verification

- Laravel feature tests cover CSV upload preview, XLSX upload preview,
  rejection of unsupported files, and queueing a mapped import without
  accepting arbitrary source paths.
- Vue tests cover file submission, mapping submission, error display, and
  selection of the returned run.
- Full Laravel, Vue, demo, lint, and build commands pass. A manual smoke test
  imports a small CSV from the UI with a running queue worker.
