# ADR-001 — Package identity and compatibility

- Status: Accepted
- Date: 2026-09-16

## Decision

The Laravel package name is `bulkflow/laravel-bulkflow` and its public PHP
namespace is `BulkFlow`. The Vue package name is `@bulkflow/vue`.

The Laravel package requires PHP `^8.2` and supports Laravel's `illuminate/*`
components `^12.0 || ^13.0`. Laravel 11 is intentionally excluded because the
fresh Composer installation used for V1 verification is security-blocked by its
published advisories. It is a package, not an application: it
uses Laravel's public contracts for filesystem, queue, bus, validation, events
and notifications. No Laravel application class is part of the public API.

## Rationale

The names are short enough for fluent imports, leave room for adapters under the
`BulkFlow` namespace, and make the backend and Vue distribution independently
installable. The component constraint supports a Laravel app without coupling
the package to a single full-framework version.

## Release verification

Before a public tag, the maintainer runs the package suite against every listed
Laravel version in a fresh temporary application and records the commands and
results in the release checklist. This repository deliberately has no hosted CI
workflow; local, reproducible verification is the release gate.
