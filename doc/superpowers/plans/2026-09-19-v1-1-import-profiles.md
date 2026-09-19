# V1.1 Import Profiles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make browser imports reusable through developer-registered, authorized import profiles and safe mapping templates.

**Architecture:** The Laravel package owns the profile contract, registry, profile-aware upload/dispatch API, token binding, and optional mapping-template persistence. The Vue package adds typed profile/template API methods and presentational selectors; the demo registers a Users profile and uses only the generic endpoints.

**Tech Stack:** PHP 8.2, Laravel 12, Eloquent, cache, queue, PHPUnit, Vue 3, TypeScript, Vitest, Vite.

**Spec:** `doc/superpowers/specs/2026-09-19-v1-1-import-profiles-design.md`

## Global Constraints

- Registered profile classes are the only authority for model class, fields, rules, upsert keys, and defaults.
- Browser requests never contain a filesystem path or arbitrary model/rule.
- Tokens bind to profile key and actor fingerprint, expire in 15 minutes, and are consumed once dispatch succeeds.
- CSV/XLSX remain the supported inputs and jobs remain queued.
- Templates are private to an owner unless application code supplies a default mapping.

## Review Focus

- A token uploaded for one profile or actor cannot dispatch a different profile/actor import.
- A profile's default/template mapping cannot include a field outside its attribute whitelist.
- A stale template cannot map a header absent from the newly uploaded source.
- A missing actor resolver cannot expose personal-template data.
- Existing fluent imports and run-history endpoints continue to behave unchanged.

---

### Task 1: Profile contract, registry, and configuration

**Files:**
- Create: `packages/laravel-bulkflow/src/Import/Profiles/ImportProfile.php`
- Create: `packages/laravel-bulkflow/src/Import/Profiles/ImportProfileRegistry.php`
- Modify: `packages/laravel-bulkflow/src/BulkFlowManager.php`
- Modify: `packages/laravel-bulkflow/config/bulkflow.php`
- Modify: `packages/laravel-bulkflow/src/BulkFlowServiceProvider.php`
- Test: `packages/laravel-bulkflow/tests/Unit/ImportProfileRegistryTest.php`

**Interfaces:** Produces `ImportProfile::key(): string`, `label(): string`, `modelClass(): string`, `attributes(): list<string>`, `rules(): array<string,mixed>`, `upsertBy(): list<string>`, and `defaultMapping(): array<string,string>`. Produces `ImportProfileRegistry::find(string): ImportProfile` and `all(): list<ImportProfile>`.

- [ ] **Step 1: Write failing registry tests**

```php
$registry = new ImportProfileRegistry([new UsersProfile]);
expect($registry->find('users')->attributes())->toBe(['name', 'email']);
expect(fn () => new ImportProfileRegistry([new UsersProfile, new UsersProfile]))
    ->toThrow(InvalidArgumentException::class);
```

Also test that an unknown profile key, non-Eloquent model class, and default mapping to an attribute outside `attributes()` fail with an `InvalidArgumentException`.

- [ ] **Step 2: Verify RED**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportProfileRegistryTest`
Expected: FAIL because profile types and registry do not exist.

- [ ] **Step 3: Implement the minimal registry**

Make the registry validate at construction. Configure profile class strings in `bulkflow.profiles`, instantiate them through the container in the service provider, and expose the registry through `BulkFlowManager::profiles()`.

- [ ] **Step 4: Verify GREEN**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportProfileRegistryTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel-bulkflow/src/Import/Profiles packages/laravel-bulkflow/src/BulkFlowManager.php packages/laravel-bulkflow/config/bulkflow.php packages/laravel-bulkflow/src/BulkFlowServiceProvider.php packages/laravel-bulkflow/tests/Unit/ImportProfileRegistryTest.php
git commit -m "feat: add import profile registry"
```

### Task 2: Profile-aware upload and queued import API

**Files:**
- Create: `packages/laravel-bulkflow/src/Http/Controllers/ImportProfileController.php`
- Create: `packages/laravel-bulkflow/src/Import/Profiles/ProfileUploadStore.php`
- Modify: `packages/laravel-bulkflow/routes/api.php`
- Modify: `packages/laravel-bulkflow/config/bulkflow.php`
- Test: `packages/laravel-bulkflow/tests/Feature/ImportProfileApiTest.php`

**Interfaces:** Produces `GET /bulkflow/import-profiles`, `POST /bulkflow/import-profiles/{profile}/uploads`, and `POST /bulkflow/import-profiles/{profile}/imports`. Consumes `bulkflow.profile_authorize(?Authenticatable, ImportProfile): bool` and `ProfileUploadStore`.

- [ ] **Step 1: Write failing feature tests**

```php
config()->set('bulkflow.profiles', [UsersProfile::class]);
$this->getJson('/bulkflow/import-profiles')->assertOk()->assertJsonPath('data.0.key', 'users');
$upload = $this->postJson('/bulkflow/import-profiles/users/uploads', ['file' => $csv])->assertCreated()->json();
$this->postJson('/bulkflow/import-profiles/users/imports', [
  'upload_id' => $upload['upload_id'],
  'mapping' => ['Email' => 'email', 'Name' => 'name'],
])->assertCreated()->assertJsonPath('state', 'queued');
```

Add tests proving a profile mismatch and changed actor fingerprint return 422, while an unauthorized profile returns 404.

- [ ] **Step 2: Verify RED**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportProfileApiTest`
Expected: FAIL because routes and controller do not exist.

- [ ] **Step 3: Implement generic endpoints**

Use `FileSource::fromUploadedFile` and the profile registry reader to return heading keys and five preview rows. Store source path, headers, profile key, and actor fingerprint in a cache token. Validate mapping headers and destination whitelist before building `BulkFlow::import($profile->modelClass())` with profile rules/upsert keys and `queue()`. Preserve existing run endpoints.

- [ ] **Step 4: Verify GREEN**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportProfileApiTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel-bulkflow/src/Http/Controllers/ImportProfileController.php packages/laravel-bulkflow/src/Import/Profiles/ProfileUploadStore.php packages/laravel-bulkflow/routes/api.php packages/laravel-bulkflow/config/bulkflow.php packages/laravel-bulkflow/tests/Feature/ImportProfileApiTest.php
git commit -m "feat: add profile-aware import API"
```

### Task 3: Mapping template persistence and authorization

**Files:**
- Create: `packages/laravel-bulkflow/database/migrations/2026_09_19_000008_create_bulkflow_import_mapping_templates_table.php`
- Create: `packages/laravel-bulkflow/src/Import/Profiles/ImportMappingTemplate.php`
- Create: `packages/laravel-bulkflow/src/Import/Profiles/MappingTemplateRepository.php`
- Modify: `packages/laravel-bulkflow/src/Http/Controllers/ImportProfileController.php`
- Modify: `packages/laravel-bulkflow/routes/api.php`
- Test: `packages/laravel-bulkflow/tests/Feature/ImportMappingTemplateApiTest.php`

**Interfaces:** Produces `GET|POST|DELETE /bulkflow/import-profiles/{profile}/mapping-templates`. Consumes `bulkflow.template_actor_id(?Authenticatable): ?string`.

- [ ] **Step 1: Write failing ownership and validation tests**

```php
config()->set('bulkflow.template_actor_id', fn () => 'actor-a');
$template = $this->postJson('/bulkflow/import-profiles/users/mapping-templates', [
  'name' => 'Vendor export',
  'mapping' => ['Email Address' => 'email'],
])->assertCreated()->json();
$this->getJson('/bulkflow/import-profiles/users/mapping-templates')->assertJsonPath('data.0.id', $template['id']);
```

Add tests that another actor cannot list/delete it, a missing actor resolver returns 403, and a mapping outside the profile whitelist returns 422.

- [ ] **Step 2: Verify RED**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportMappingTemplateApiTest`
Expected: FAIL because the table and endpoints do not exist.

- [ ] **Step 3: Implement storage and routes**

Store owner ID, profile key, name, and JSON mapping. Scope every query by the resolved owner ID. Do not persist application defaults; profiles remain their source. Reuse the same profile mapping validator before create and before template application in import dispatch.

- [ ] **Step 4: Verify GREEN**

Run: `composer --working-dir=packages/laravel-bulkflow test -- --filter=ImportMappingTemplateApiTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel-bulkflow/database/migrations packages/laravel-bulkflow/src/Import/Profiles packages/laravel-bulkflow/src/Http/Controllers/ImportProfileController.php packages/laravel-bulkflow/routes/api.php packages/laravel-bulkflow/tests/Feature/ImportMappingTemplateApiTest.php
git commit -m "feat: add profile mapping templates"
```

### Task 4: Vue profile and template client flow

**Files:**
- Modify: `packages/vue-bulkflow/src/client.ts`
- Modify: `packages/vue-bulkflow/src/index.ts`
- Modify: `packages/vue-bulkflow/src/components/ImportWizard.vue`
- Test: `packages/vue-bulkflow/tests/client.test.ts`
- Test: `packages/vue-bulkflow/tests/import-wizard.test.ts`

**Interfaces:** Produces `ImportProfile`, `ImportMappingTemplate`, `ProfileUploadPreview`, and client methods `listImportProfiles()`, `listMappingTemplates(profileKey)`, `uploadProfileImport(profileKey,file)`, and `startProfileImport(profileKey,uploadId,mapping)`.

- [ ] **Step 1: Write failing client and wizard tests**

```ts
expect(await client.listImportProfiles()).toEqual([{
  key: 'users', label: 'Users', attributes: ['name', 'email'], defaultMapping: { Email: 'email' },
}]);
await wrapper.get('select[aria-label="Import profile"]').setValue('users');
expect(wrapper.emitted('profile-change')?.[0]).toEqual(['users']);
```

Add a test that choosing a template fills only matching header mappings and leaves nonexistent header mappings unset.

- [ ] **Step 2: Verify RED**

Run: `npm --workspace @bulkflow/vue run test -- client.test.ts import-wizard.test.ts`
Expected: FAIL with missing profile methods and selector.

- [ ] **Step 3: Implement typed methods and presentational controls**

Normalize snake-case payloads, use profile keys in endpoint URLs, and retain current demo methods only until Task 5 removes their host usage. Add profile/template selects, mapping proposal props, and an alert for template errors; keep network work in the host app.

- [ ] **Step 4: Verify GREEN**

Run: `npm --workspace @bulkflow/vue run test -- client.test.ts import-wizard.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/vue-bulkflow/src/client.ts packages/vue-bulkflow/src/index.ts packages/vue-bulkflow/src/components/ImportWizard.vue packages/vue-bulkflow/tests/client.test.ts packages/vue-bulkflow/tests/import-wizard.test.ts
git commit -m "feat: add Vue import profile flow"
```

### Task 5: Migrate the demo to Users profile and document V1.1

**Files:**
- Create: `demo/laravel-app/app/Imports/UsersImportProfile.php`
- Modify: `demo/laravel-app/app/Providers/AppServiceProvider.php`
- Delete: `demo/laravel-app/app/Http/Controllers/DemoImportController.php`
- Delete: `demo/laravel-app/app/Support/DemoImportUploadStore.php`
- Modify: `demo/laravel-app/routes/web.php`
- Modify: `demo/vue-app/src/App.vue`
- Modify: `README.md`
- Modify: `packages/laravel-bulkflow/README.md`
- Test: `demo/laravel-app/tests/Feature/BulkFlowDemoTest.php`

**Interfaces:** Consumes generic profile endpoints and registers key `users`. Produces the same browser User import flow without `demo-imports` routes.

- [ ] **Step 1: Write the failing demo migration test**

```php
$this->getJson('/bulkflow/import-profiles')->assertOk()->assertJsonPath('data.0.key', 'users');
$this->postJson('/bulkflow/demo-imports/upload', ['file' => $csv])->assertNotFound();
```

- [ ] **Step 2: Verify RED**

Run: `php demo/laravel-app/artisan test --filter=BulkFlowDemoTest`
Expected: FAIL because Users profile is not registered and legacy endpoints still exist.

- [ ] **Step 3: Implement migration and docs**

Register `UsersImportProfile` through package config, replace the demo host calls with profile client calls, remove legacy controller/routes, and document profile registration, authorization hooks, actor resolver, templates, and migration from V1 User demo API.

- [ ] **Step 4: Verify full release suite**

Run: `php scripts/verify-composer-manifests.php && composer --working-dir=packages/laravel-bulkflow test && composer --working-dir=packages/laravel-bulkflow analyse && composer --working-dir=packages/laravel-bulkflow lint && php demo/laravel-app/artisan test && npm --workspace @bulkflow/vue run test && npm run build:vue && npm run build:demo`
Expected: every command passes.

- [ ] **Step 5: Commit**

```bash
git add demo/laravel-app packages/laravel-bulkflow/README.md README.md
git commit -m "feat: complete V1.1 import profiles"
```

