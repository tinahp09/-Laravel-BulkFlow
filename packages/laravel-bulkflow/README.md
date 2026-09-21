# Laravel BulkFlow

Bulk imports and exports for Laravel with CSV/XLSX, validation, queue dispatch, durable run history and failed-row reports.

## Install

```bash
composer require bulkflow/laravel-bulkflow
php artisan migrate
```

## Browser import profiles

Register an immutable profile in your application configuration. The browser can only select these developer-defined models, rules and attributes.

```php
'profiles' => [App\Imports\UsersImportProfile::class],
'profile_authorize' => fn ($actor, $profile) => true,
```

Each profile implements `BulkFlow\Import\Profiles\ImportProfile` and supplies a key, label, Eloquent model, allowed attributes, validation rules, upsert keys and default mapping. The package exposes `GET /bulkflow/import-profiles`, profile-scoped upload and dispatch endpoints, and consumes a short-lived upload token rather than a client-supplied path.

The companion Vue package can generate a safe mapping proposal from uploaded headings and the profile's allowed attributes. This is a UI convenience only: the profile remains the server-side authority, and operators can edit every proposed mapping before dispatch.

Set `bulkflow.template_actor_id` to a stable actor identifier to enable private mapping-template endpoints. Without it, templates return 403 while code-provided default mappings remain available.

The prior demo-only `/bulkflow/demo-imports` endpoints were replaced by `/bulkflow/import-profiles/{profile}/uploads` and `/bulkflow/import-profiles/{profile}/imports`.

## Import

```php
use BulkFlow\Facades\BulkFlow;
use App\Models\User;

$result = BulkFlow::import(User::class)
    ->from($pathToCsv)
    ->map(['نام' => 'name', 'ایمیل' => 'email'])
    ->validate(['email' => ['required', 'email']])
    ->upsertBy(['email'])
    ->chunkSize(1_000)
    ->onError('continue')
    ->run();
```

Import a source stored on any configured Laravel disk (including S3-compatible disks) with `fromDisk()`:

```php
BulkFlow::import(User::class)
    ->fromDisk('s3', 'uploads/users.xlsx')
    ->map($mapping)
    ->upsertBy(['email'])
    ->queue();
```

BulkFlow materializes the source in private application storage before parsing so readers have a seekable local file. Run history retains the materialized path for a queued worker; use your application's normal storage-retention policy for `storage/app/bulkflow-inputs`.

`from()` also accepts Laravel's `UploadedFile`. Uploaded files are validated before dispatch, materialized into the same private directory, and can therefore safely outlive the request when queued. By default only `csv` and `xlsx` extensions are accepted and the limit is 100 MiB; configure `bulkflow.input.allowed_extensions` and `bulkflow.input.max_bytes` for your application.

Use `->queue()` for a serializable background job. It immediately returns a durable run in `queued` state; a dispatcher streams the source and creates a Laravel Bus batch of chunk jobs. Queue definitions cannot contain closure transforms.

Run a worker and Laravel scheduler in production:

```bash
php artisan queue:work
php artisan schedule:work
```

Package migrations provision `job_batches` when it does not already exist. Configure each queue job through `bulkflow.queue.timeout`, `bulkflow.queue.tries`, and `bulkflow.queue.backoff`; the defaults are a 120-second timeout, three attempts, and 5- and 30-second delays. Use `upsertBy()` for queued imports so retrying a chunk does not create duplicates.

For partial imports, use `->onError('continue')`, `->onError('fail-fast')`, or stop safely after a bounded number of bad rows:

```php
->onError('stop-on-threshold')
->stopOnErrorCount(100)
```

## Export

```php
$file = BulkFlow::export(User::query()->cursor())
    ->columns(['name' => 'نام', 'email' => 'ایمیل'])
    ->asXlsx()
    ->storeOnDisk('s3', 'exports/users.xlsx');

$downloadUrl = $file->temporaryUrl(now()->addMinutes(15));
```

`storeOnDisk()` uses Laravel's filesystem abstraction, so it works with a private local disk, S3, or any configured compatible disk. It never makes an artifact public by itself.

## Scheduled exports

Persist a declarative export definition, then make sure the application's normal Laravel scheduler is running (`php artisan schedule:work` in development or a server cron in production). BulkFlow registers `bulkflow:run-scheduled-exports` every minute and runs only definitions whose cron expression is due.

```php
BulkFlow::scheduledExport(User::class)
    ->named('daily-users')
    ->columns(['name' => 'Name', 'email' => 'Email'])
    ->asCsv()
    ->storeOnDisk('s3', 'exports/users.csv')
    ->cron('0 2 * * *')
    ->notifyTo('ops@example.com')
    ->save();
```

Use `php artisan bulkflow:run-scheduled-exports` to run due definitions manually. Saving a definition with the same name updates it.

## Import completion notifications

Set `bulkflow.notify` to a callback that receives the completed `ImportRun` and
returns a Laravel notifiable object or collection. The callback is invoked for
sync, queued and retry runs. Notification delivery failures are reported but do
not change an already-completed import state.

```php
'notify' => static fn (\BulkFlow\Run\ImportRun $run) =>
    \Illuminate\Support\Facades\Notification::route('mail', 'ops@example.test'),
```

## API

- `GET /bulkflow/imports`
- `GET /bulkflow/imports/{run}`
- `GET /bulkflow/imports/{run}/failures`
- `GET /bulkflow/imports/{run}/failures/report?format=csv|xlsx`
- `POST /bulkflow/imports/{run}/cancel`
- `POST /bulkflow/imports/{run}/retry-failures`

The failure-report endpoint streams a private download and applies the same run authorization and tenant scope as the other endpoints. The retry endpoint accepts an optional JSON body such as `{"failure_ids": ["uuid-1", "uuid-2"]}`. A successful retry marks those original failure records as `resolved`; omitting the list retries every pending failure for that run.

`POST /bulkflow/imports/{run}/cancel` cancels an active Laravel Bus batch and marks the run `cancelled`. A cancellation received before the dispatcher starts also prevents that worker from reading the source. Finished runs cannot be cancelled.

## S3-compatible integration verification

The normal suite skips the live-storage test unless `BULKFLOW_S3_ENDPOINT` is set. It uses standard Laravel disk configuration and has been verified against a local MinIO bucket. Run it in an environment where the endpoint is reachable:

```bash
BULKFLOW_S3_ENDPOINT=http://host.docker.internal:9009 \
  composer test -- --filter S3CompatibleStorageTest
```

## Authorization and tenant scope

`bulkflow.authorize` decides whether the current actor may use an endpoint. `bulkflow.scope` receives the import-run Eloquent query and the current user, and must return the query with the application's tenant/actor constraints. BulkFlow applies that scope to history, run details, failure reports, and retry endpoints, so hidden runs return `404` rather than leaking metadata.

```php
'scope' => static fn (\Illuminate\Database\Eloquent\Builder $runs, ?User $user) =>
$runs->where('tenant_id', $user?->tenant_id),
```

## Realtime broadcasting

BulkFlow broadcasts a full, revisioned run snapshot on the private channel
`bulkflow.imports.{runId}` as `.bulkflow.progress.updated`. Configure a Laravel
broadcasting driver such as Reverb, Pusher, or Redis-backed broadcasting in the
host application, and register Laravel's broadcasting authentication route with
the application's normal authentication middleware:

```php
Broadcast::routes(['middleware' => ['auth']]);
```

The package registers the channel callback and applies the same
`bulkflow.scope` and `bulkflow.authorize` hooks used by its HTTP endpoints. A
run outside the actor's scope is never authorized for the private channel.
When broadcasting is unavailable, the Vue tracker can continue using polling.

Use `bulkflow:prune-runs --before=YYYY-MM-DD` to remove expired run history and failed rows. Preview the exact affected run count without deleting anything with `--dry-run`.
