# Compatibility matrix

## Supported runtime

| Component | Supported versions | V1 verification evidence |
| --- | --- | --- |
| PHP | `^8.2` | Package suite on PHP 8.5.10 |
| Laravel | `^12.0` | Fresh Laravel 12.12.2 application installed the local path package and discovered all five BulkFlow routes on 2026-09-16 |
| Laravel | `^13.0` | `demo/laravel-app` on Laravel 13.17 runs package integration tests and the local API on 2026-09-16 |
| Vue | `^3.4.0` peer dependency | Vue 3.5 package test suite and independent Vite demo build on 2026-09-16 |

The optional live storage integration test was verified against MinIO's
S3-compatible API on 2026-09-16 (import, export and a temporary URL).

Laravel 11 is not a V1 supported version. During fresh-install verification,
Composer blocked its available framework versions using published security
advisories. V1 does not require consumers to disable Composer's security checks.

## Repeat the Laravel 12 smoke test

From an empty temporary Laravel 12 app, add the local package as a path
repository and inspect package discovery:

```bash
composer config repositories.bulkflow path /absolute/path/to/packages/laravel-bulkflow
composer require bulkflow/laravel-bulkflow:@dev
php artisan route:list --path=bulkflow
```

The command must show the import history, run detail, failures, failure-report
download and retry-failures routes. Run the same check against every Laravel
minor version before a release tag.
