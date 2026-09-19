# V1.1 Import Profiles Design

## Goal

Turn the fixed User demo import into a reusable, secure Laravel integration:
developers register import profiles in PHP, while administrators choose an
allowed profile in the Vue UI, upload a file, apply or save a mapping template,
and start a queued import.

## Scope

- V1.1 adds profile-driven browser imports to the Laravel and Vue packages.
- A profile determines a stable identifier, display name, Eloquent model,
  allowed destination attributes, validation rules, upsert keys, default
  mapping, and optional per-actor authorization.
- The profile registry is developer-owned. The browser cannot name a model,
  validation rule, source path, or persistence strategy directly.
- Mapping templates are stored with an import-profile identifier and an owner
  reference. A template is private to its owner unless marked as a project
  default by application code.
- CSV and XLSX remain the only accepted source formats. Imports remain queued.

## Non-goals

- User-authored schemas, arbitrary Eloquent model selection, arbitrary PHP
  transforms, and cross-project template sharing are out of scope.
- This release does not alter the existing fluent ImportBuilder API.
- The V1 User demo flow is migrated to a registered `users` profile; it is not
  kept as a parallel special-case API.

## Laravel Architecture

### Profile contract and registry

The package exposes an `ImportProfile` contract and a `BulkFlow::profiles()`
registry. Applications register concrete profile classes in a service provider.
Each profile is immutable and returns only queue-serializable data:

```php
final class UsersImportProfile implements ImportProfile
{
    public function key(): string { return 'users'; }
    public function modelClass(): string { return User::class; }
    public function attributes(): array { return ['name', 'email', 'password']; }
    public function rules(): array { return ['email' => ['required', 'email']]; }
    public function upsertBy(): array { return ['email']; }
    public function defaultMapping(): array { return ['Email' => 'email']; }
}
```

The registry rejects duplicate keys, unknown profiles, profile attribute
mappings outside the whitelist, and non-Eloquent model classes.

### Generic HTTP flow

The package supplies profile-aware endpoints:

1. `GET /bulkflow/import-profiles` lists only profiles authorized for the
   current actor, exposing their key, display name, allowed attributes, and
   application-provided default mapping.
2. `POST /bulkflow/import-profiles/{profile}/uploads` accepts a file and
   materializes it in BulkFlow's safe input location. The cache-backed opaque
   upload token contains the profile key and an actor fingerprint in addition
   to source metadata, headers, and preview rows.
3. `POST /bulkflow/import-profiles/{profile}/imports` consumes that token,
   verifies the same authorized actor and profile, validates its mapping, and
   dispatches the profile's queued `ImportBuilder` definition.

The upload source is only accepted from an opaque token; request data never
contains a filesystem path. Tokens expire after 15 minutes and are consumed
once a job dispatch succeeds.

### Mapping templates

A new `bulkflow_import_mapping_templates` table stores `id`, `profile_key`,
`owner_id` (nullable only for application-managed defaults), `name`, `mapping`,
and timestamps. The package provides read/create/delete endpoints. The
application configures how an actor ID is derived and whether it may create
or delete templates; without an actor resolver, only default mappings supplied
by profile classes are available.

Template mapping is revalidated against the current profile and uploaded
headers before it can be applied or used to dispatch an import. Hidden or
unavailable profiles return 404 rather than leaking metadata.

## Vue Architecture

`BulkFlowClient` gains types and methods for listing profiles, loading mapping
templates, uploading a profile source, saving/deleting a template, and starting
a profile import. `ImportWizard` gains a profile selector and template selector
but remains presentational: it emits decisions and accepts profile/template
data from its host application.

The demo app registers a `users` profile and removes its bespoke upload/start
controller code. Its UI begins with profile selection, proposes matching
default/template mappings after preview, and lets a signed-in host integration
opt into template persistence. The unauthenticated demo demonstrates default
mappings and all other profile behavior without pretending to have ownership.

## Authorization and Error Handling

- `bulkflow.profile_authorize` receives the actor and profile and controls
  profile visibility and every profile-specific operation.
- `bulkflow.template_actor_id` returns the stable template owner key; missing
  values disable personal-template endpoints with 403.
- File type/size, corrupt files, expired tokens, profile mismatch, actor
  mismatch, invalid template names, and invalid mappings return 422 or 403
  with field-level JSON errors.
- Row-level validation and persistence failures remain BulkFlow failed-row
  records, reports, and retry operations.

## Compatibility and Migration

Existing fluent imports, run-history endpoints, and Vue progress/error
components remain backward-compatible. The demo-only `demo-imports` routes
are removed in the V1.1 demo migration; adopters of the released package are
given a migration snippet mapping their current User configuration into an
`ImportProfile` class.

## Verification

- Laravel tests cover registry validation, authorization visibility, upload
  token profile/actor binding, dispatch, template ownership, template mapping
  validation, CSV and XLSX previews, and preservation of existing API routes.
- Vue tests cover profile selection, template application, invalid template
  error display, and dispatch of the selected profile's token/mapping.
- The demo test registers the Users profile and executes the complete queued
  browser API flow. The Laravel package, Vue package, demo tests, static
  analysis, formatting, and production builds pass.

