# UI User Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete V1 by allowing the Vue demo to upload CSV/XLSX user files, preview and map them, queue imports, and show their real runs.

**Architecture:** A demo-only Laravel controller stores uploads behind short-lived opaque cache tokens and delegates parsing and queue execution to BulkFlow. `BulkFlowClient` gains typed upload/start methods; `ImportWizard` remains presentational and `App.vue` connects it to the existing history/progress UI.

**Tech Stack:** Laravel 12, PHP 8.2, SQLite/cache, BulkFlow, Vue 3, TypeScript, Vitest, PHPUnit, Vite.

**Spec:** `doc/superpowers/specs/2026-09-17-ui-user-import-design.md`

## Global Constraints

- Accept only CSV/XLSX and enforce `bulkflow.input.allowed_extensions` and `bulkflow.input.max_bytes`.
- Target `App\\Models\\User`; require a unique mapping to `name`, `email`, and `password`, validate values, and upsert by email.
- Queue every UI import and never pass a browser-provided file path to BulkFlow.
- Return only opaque upload tokens; retain sources under BulkFlow's safe local input directory.
- Persist validation row failures through BulkFlow instead of treating them as API errors.

## Review Focus

- Invented or expired upload tokens return 422 and never queue work.
- Two source columns cannot map to the same destination.
- Corrupt XLSX and missing-header files produce useful 422 errors.
- Upload/start failures leave the UI ready for another attempt.
- A successful start refreshes history and selects the returned run.

---

### Task 1: Demo upload-preview API

**Files:**
- Create: `demo/laravel-app/app/Http/Controllers/DemoImportController.php`
- Create: `demo/laravel-app/app/Support/DemoImportUploadStore.php`
- Modify: `demo/laravel-app/routes/api.php`
- Test: `demo/laravel-app/tests/Feature/BulkFlowDemoTest.php`

**Interfaces:** Produces `DemoImportUploadStore::store(UploadedFile): array{upload_id:string,headers:list<string>,preview:list<array<string,mixed>>}` and `POST /api/bulkflow/demo-imports/upload` returning that payload with HTTP 201.

- [ ] **Step 1: Write failing upload tests**

```php
$response = $this->postJson('/api/bulkflow/demo-imports/upload', [
    'file' => UploadedFile::fake()->createWithContent('users.csv', "full_name,email_address,password\nNeda,neda@example.test,secret\n"),
]);
$response->assertCreated()->assertJsonPath('headers', ['full_name', 'email_address', 'password']);
$this->postJson('/api/bulkflow/demo-imports/upload', ['file' => UploadedFile::fake()->createWithContent('users.txt', 'x')])
    ->assertUnprocessable()->assertJsonValidationErrors('file');
```

- [ ] **Step 2: Verify RED**

Run: `php demo/laravel-app/artisan test --filter=upload_preview`
Expected: FAIL because the endpoint does not exist.

- [ ] **Step 3: Implement store and endpoint**

Use `UploadedFile::store('bulkflow-inputs')`, `FormatRegistry`, and `ReadOptions` to obtain heading keys and at most five rows. Cache disk/path/headers under a UUID for 15 minutes, return the UUID token, delete unreadable uploads, and return validation errors for unsupported/missing-header files.

- [ ] **Step 4: Verify GREEN**

Run: `php demo/laravel-app/artisan test --filter=upload_preview`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add demo/laravel-app/app/Http/Controllers/DemoImportController.php demo/laravel-app/app/Support/DemoImportUploadStore.php demo/laravel-app/routes/api.php demo/laravel-app/tests/Feature/BulkFlowDemoTest.php
git commit -m "feat: add demo import upload preview"
```

### Task 2: Queued demo import start API

**Files:**
- Modify: `demo/laravel-app/app/Http/Controllers/DemoImportController.php`
- Modify: `demo/laravel-app/app/Support/DemoImportUploadStore.php`
- Modify: `demo/laravel-app/routes/api.php`
- Test: `demo/laravel-app/tests/Feature/BulkFlowDemoTest.php`

**Interfaces:** Consumes `DemoImportUploadStore::consume(string): array{disk:string,path:string,headers:list<string>}`. Produces `POST /api/bulkflow/demo-imports` accepting `{upload_id,mapping}` and returning `ImportRun` JSON.

- [ ] **Step 1: Write failing queue and safety tests**

```php
Queue::fake();
$upload = $this->postJson('/api/bulkflow/demo-imports/upload', ['file' => UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nNeda,neda@example.test,secret\n")])->json();
$this->postJson('/api/bulkflow/demo-imports', ['upload_id' => $upload['upload_id'], 'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password']])
    ->assertCreated()->assertJsonPath('state', 'queued');
Queue::assertPushed(ProcessImport::class);
$this->postJson('/api/bulkflow/demo-imports', ['upload_id' => (string) Str::uuid(), 'mapping' => ['name' => 'email', 'email' => 'email', 'password' => 'password']])
    ->assertUnprocessable();
```

- [ ] **Step 2: Verify RED**

Run: `php demo/laravel-app/artisan test --filter='starting_a_previewed|start_rejects'`
Expected: FAIL because the start endpoint does not exist.

- [ ] **Step 3: Implement token consumption and dispatch**

Consume the token before dispatch. Require each destination exactly once, mapped headers to exist, and no duplicate destination. Queue `BulkFlow::import(User::class)->fromDisk($disk, $path)->map($mapping)->validate(['name'=>['required'], 'email'=>['required','email'], 'password'=>['required']])->upsertBy(['email'])->queue()` and return its normal run payload.

- [ ] **Step 4: Verify GREEN**

Run: `php demo/laravel-app/artisan test --filter='upload_preview|starting_a_previewed|start_rejects'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add demo/laravel-app/app/Http/Controllers/DemoImportController.php demo/laravel-app/app/Support/DemoImportUploadStore.php demo/laravel-app/routes/api.php demo/laravel-app/tests/Feature/BulkFlowDemoTest.php
git commit -m "feat: queue mapped demo user imports"
```

### Task 3: Vue client and wizard

**Files:**
- Modify: `packages/vue-bulkflow/src/client.ts`
- Modify: `packages/vue-bulkflow/src/index.ts`
- Modify: `packages/vue-bulkflow/src/components/ImportWizard.vue`
- Test: `packages/vue-bulkflow/tests/client.test.ts`
- Test: `packages/vue-bulkflow/tests/import-wizard.test.ts`

**Interfaces:** Produces `UploadPreview`, `BulkFlowClient.uploadDemoImport(file)`, `BulkFlowClient.startDemoImport(uploadId,mapping)`, and wizard events `upload(file)` / `confirm(mapping)`.

- [ ] **Step 1: Write failing client and component tests**

```ts
const result = await new BulkFlowClient('/bulkflow', fetcher).uploadDemoImport(new File(['name\nNeda'], 'users.csv'));
expect(result.headers).toEqual(['name']);
expect(fetcher.mock.calls[0][0]).toBe('/bulkflow/demo-imports/upload');
await wrapper.get('input[type=file]').setValue(new File(['x'], 'users.csv'));
expect(wrapper.emitted('upload')?.[0]).toHaveLength(1);
```

- [ ] **Step 2: Verify RED**

Run: `npm --workspace @bulkflow/vue run test -- client.test.ts import-wizard.test.ts`
Expected: FAIL with missing API methods and file control.

- [ ] **Step 3: Implement typed API and UI states**

Use `FormData` without setting a multipart `Content-Type`; map snake-case API payloads into `UploadPreview` and `ImportRun`. Add file selection, supplied error alert, busy disabled states, existing preview, and mapping confirmation to `ImportWizard`.

- [ ] **Step 4: Verify GREEN**

Run: `npm --workspace @bulkflow/vue run test -- client.test.ts import-wizard.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/vue-bulkflow/src/client.ts packages/vue-bulkflow/src/index.ts packages/vue-bulkflow/src/components/ImportWizard.vue packages/vue-bulkflow/tests/client.test.ts packages/vue-bulkflow/tests/import-wizard.test.ts
git commit -m "feat: add typed UI import client flow"
```

### Task 4: Vue demo composition and V1 verification

**Files:**
- Modify: `demo/vue-app/src/App.vue`
- Modify: `demo/vue-app/src/style.css`
- Create: `demo/vue-app/src/App.test.ts`
- Modify: `README.md`
- Modify: `demo/vue-app/README.md`
- Modify: `doc/RELEASE.md`

**Interfaces:** Consumes the client methods and wizard events. Produces an Import users flow that refreshes history and selects the queued run after success.

- [ ] **Step 1: Write a failing app interaction test**

```ts
vi.spyOn(BulkFlowClient.prototype, 'uploadDemoImport').mockResolvedValue(preview);
vi.spyOn(BulkFlowClient.prototype, 'startDemoImport').mockResolvedValue(run);
const wrapper = mount(App);
// choose file, map name/email/password, submit
expect(wrapper.text()).toContain('Import details');
```

- [ ] **Step 2: Verify RED**

Run: `npm --workspace @bulkflow/demo-vue exec vitest run src/App.test.ts`
Expected: FAIL because `App.vue` does not compose the wizard.

- [ ] **Step 3: Implement composition and docs**

On upload, save preview; on confirm, start run, refresh history, and call existing `selectRun(run)`. Show recoverable request errors. Document Laravel server, Vite server, `php demo/laravel-app/artisan queue:work`, CSV fixture, and User-only limitation.

- [ ] **Step 4: Verify full V1 behavior**

Run: `php demo/laravel-app/artisan test && composer --working-dir=packages/laravel-bulkflow test && npm --workspace @bulkflow/vue run test && npm run build:vue && npm run build:demo`
Expected: PASS for every suite and both builds.

- [ ] **Step 5: Commit**

```bash
git add demo/vue-app/src/App.vue demo/vue-app/src/style.css demo/vue-app/src/App.test.ts README.md demo/vue-app/README.md doc/RELEASE.md
git commit -m "feat: complete UI import demo"
```

