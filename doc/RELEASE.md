# Release checklist

This repository intentionally contains **no GitHub Actions workflow**. The maintainer performs these checks locally or in their own CI before a release.

## Preconditions

- [ ] Project owner reviews the public API and signs off on the version number.
- [ ] No secrets, example credentials, or raw PII are included in source, fixtures, reports, or documentation.
- [ ] `CHANGELOG.md` and `doc/UPGRADE.md` describe the release.
- [ ] Package licenses, repository URL, support URL, and author metadata are finalized in both package manifests.
- [ ] `composer.json` at the monorepo root and `packages/laravel-bulkflow/composer.json` pass the manifest synchronization check. The root manifest is the Packagist entry point; the nested manifest remains the package-development manifest.

## Verification

From the monorepo root, run:

```bash
php scripts/verify-composer-manifests.php
composer validate --strict --no-check-lock
(cd packages/laravel-bulkflow && composer test)
npm --workspace @bulkflow/vue run test
npm --workspace @bulkflow/vue run build
composer test --working-dir=demo/laravel-app
```

Also test a fresh Laravel application against the tagged Laravel package. Before
tagging, `composer archive --format=zip` provides a local distribution smoke
test; verify the archive excludes development dependencies and is installable
by a Laravel application. Install the generated Vue tarball with `npm pack
--dry-run` before publishing.

The current internal evidence is recorded in [V1_AUDIT.md](V1_AUDIT.md). For a
live S3-compatible check, run the opt-in MinIO test described in the Laravel
package README.

## Publishing (owner action only)

Then:

1. Create and push an approved, signed `v1.0.0` tag on this monorepo. The root `composer.json` is intentionally the `bulkflow/laravel-bulkflow` package entry point, while its PSR-4 paths target `packages/laravel-bulkflow`.
2. Register this repository in Packagist and verify that the tag and `bulkflow/laravel-bulkflow` metadata are indexed correctly.
3. Run `npm pack --dry-run` in `packages/vue-bulkflow`; inspect the package contents; then publish `@bulkflow/vue` from the owner-controlled npm account with provenance enabled.
4. Never publish an untagged working tree. Do not place GitHub, Packagist, or npm tokens in this repository.

Publishing is deliberately not automated and requires the owner’s external credentials and explicit approval.
