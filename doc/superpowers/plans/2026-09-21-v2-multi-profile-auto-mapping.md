# V2 Multi-Profile Imports and Auto-Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide real Users, Products and Orders imports in the demo and propose safe column mappings automatically.

**Architecture:** Laravel demo models and immutable `ImportProfile` classes define each resource's schema, rules and upsert key. The Vue package owns a pure proposal function and the demo combines it with profile defaults after upload, leaving the existing wizard editable.

**Tech Stack:** Laravel 12, Eloquent, SQLite, PHPUnit, Vue 3, TypeScript, Vitest, Vite.

**Spec:** `doc/superpowers/specs/2026-09-21-v2-multi-profile-auto-mapping-design.md`

## Global Constraints

- The browser must not select a model class, validation rule or upsert key.
- Auto-mapping must only produce source headings present in the upload and attributes allowed by the selected profile.
- A destination can be selected at most once.
- Explicit wizard edits and selected templates remain editable after proposal.

## Review Focus

- Unknown vendor headings are skipped, not guessed.
- Persian `ایمیل` only maps to an allowed `email` attribute.
- A default mapping wins over an alias for the same source heading.
- An alias never overwrites a destination already used by a default mapping.
- Changing profile clears an upload and its mapping proposal.

### Task 1: Demo Products and Orders profiles

**Files:**
- Create: `demo/laravel-app/app/Models/Product.php`
- Create: `demo/laravel-app/app/Models/Order.php`
- Create: `demo/laravel-app/app/Imports/ProductsImportProfile.php`
- Create: `demo/laravel-app/app/Imports/OrdersImportProfile.php`
- Create: `demo/laravel-app/database/migrations/2026_09_21_000001_create_products_table.php`
- Create: `demo/laravel-app/database/migrations/2026_09_21_000002_create_orders_table.php`
- Modify: `demo/laravel-app/app/Providers/AppServiceProvider.php`
- Test: `demo/laravel-app/tests/Feature/BulkFlowDemoTest.php`

**Interfaces:** Registers `products` and `orders` profile keys in `bulkflow.profiles`; each profile implements `ImportProfile`.

- [ ] **Step 1: Write failing feature tests**

```php
$this->getJson('/bulkflow/import-profiles')->assertJsonPath('data.1.key', 'products');
$this->postJson('/bulkflow/import-profiles/products/uploads', ['file' => $csv]);
```

Add one queued dispatch assertion for Products and Orders.

- [ ] **Step 2: Verify RED**

Run: `php artisan test --filter=BulkFlowDemoTest`
Expected: profiles are absent and profile endpoints return 404.

- [ ] **Step 3: Implement models, migrations and profiles**

Products use `sku` as their unique upsert key and validate numeric `price` plus integer `stock`. Orders use `reference` and validate email, numeric total and a string status. Register all three profile classes in the demo service provider.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test --filter=BulkFlowDemoTest`
Expected: Products and Orders queue through the generic profile endpoints.

- [ ] **Step 5: Commit**

```bash
git add demo/laravel-app
git commit -m "feat: add product and order import profiles"
```

### Task 2: Pure safe auto-mapping proposal

**Files:**
- Create: `packages/vue-bulkflow/src/mapping.ts`
- Modify: `packages/vue-bulkflow/src/index.ts`
- Test: `packages/vue-bulkflow/tests/mapping.test.ts`

**Interfaces:** Produces `proposeMapping(headers: string[], profile: ImportProfile): Record<string, string>`.

- [ ] **Step 1: Write failing unit tests**

```ts
expect(proposeMapping([' product-sku ', 'Product Name', 'Amount'], productProfile))
  .toEqual({ ' product-sku ': 'sku', 'Product Name': 'name', Amount: 'price' });
expect(proposeMapping(['ایمیل'], usersProfile)).toEqual({ ایمیل: 'email' });
```

Test unknown headers, default-mapping precedence and duplicate destination prevention.

- [ ] **Step 2: Verify RED**

Run: `npm --workspace @bulkflow/vue run test -- --run mapping.test.ts`
Expected: FAIL because `proposeMapping` is missing.

- [ ] **Step 3: Implement deterministic proposal**

Normalize lower case Latin headings and separator variants. Use a fixed alias dictionary and check the selected profile's `attributes` plus destinations already used before accepting a candidate.

- [ ] **Step 4: Verify GREEN**

Run: `npm --workspace @bulkflow/vue run test -- --run mapping.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/vue-bulkflow/src packages/vue-bulkflow/tests/mapping.test.ts
git commit -m "feat: add safe import auto-mapping"
```

### Task 3: Apply proposals in the demo wizard

**Files:**
- Modify: `demo/vue-app/src/App.vue`
- Test: `packages/vue-bulkflow/tests/import-wizard.test.ts`

**Interfaces:** The demo calls `proposeMapping(upload.headers, selectedProfile)` after a successful upload and passes its result to `ImportWizard` as `mappingProposal`.

- [ ] **Step 1: Write a failing wizard regression test**

```ts
expect(wrapper.emitted('confirm')).toEqual([[{ 'Product SKU': 'sku', Amount: 'price' }]]);
```

The test must confirm proposal values remain editable through the existing select controls.

- [ ] **Step 2: Verify RED**

Run: `npm --workspace @bulkflow/vue run test -- --run import-wizard.test.ts`
Expected: FAIL because the demo does not pass an auto-mapping proposal.

- [ ] **Step 3: Integrate proposal state**

Reset proposal on profile changes and after a completed import. Use profile defaults only when an upload has not supplied headings.

- [ ] **Step 4: Verify GREEN**

Run: `npm --workspace @bulkflow/vue run test -- --run import-wizard.test.ts && npm run build:demo`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add demo/vue-app/src/App.vue packages/vue-bulkflow/tests/import-wizard.test.ts
git commit -m "feat: propose mappings in demo imports"
```

### Task 4: End-to-end verification and documentation

**Files:**
- Modify: `README.md`
- Modify: `packages/laravel-bulkflow/README.md`

- [ ] **Step 1: Document profiles and auto-mapping**

Explain that profiles define persistence behavior and auto-mapping is a proposal that the operator can change.

- [ ] **Step 2: Run the release suite**

Run: `php scripts/verify-composer-manifests.php && composer --working-dir=packages/laravel-bulkflow test && composer --working-dir=packages/laravel-bulkflow analyse && composer --working-dir=packages/laravel-bulkflow lint && php demo/laravel-app/artisan test && npm --workspace @bulkflow/vue run test && npm run build:vue && npm run build:demo`
Expected: every command passes.

- [ ] **Step 3: Run a Playwright smoke test**

Upload a Products CSV with `product_sku`, `Product Name`, `Amount` and `stock`; verify the wizard proposes `sku`, `name`, `price`, `stock`, dispatch it and observe a completed run.

- [ ] **Step 4: Commit**

```bash
git add README.md packages/laravel-bulkflow/README.md
git commit -m "docs: document V2 auto-mapping"
```
