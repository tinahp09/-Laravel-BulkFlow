# Laravel BulkFlow Implementation Roadmap

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ساخت تدریجی Laravel BulkFlow تا یک پکیج پایدار برای import/export حجیم به‌همراه Vue companion.

**Architecture:** هسته یک Laravel package مستقل است که فایل را به‌صورت streaming به pipeline ردیفی می‌فرستد. وضعیت اجرا و failureها پشت repository contract نگهداری می‌شوند؛ queue، realtime و Vue فقط consumer همین قراردادها هستند.

**Tech Stack:** PHP 8.2+، Laravel 11/12 (پس از ADR سازگاری)، Orchestra Testbench، PHPUnit، OpenSpout برای CSV/XLSX streaming، Laravel Queue/Bus، Vue 3، TypeScript و Vite.

**Spec:** [../PRD.md](../PRD.md)

## Global Constraints

- CSV و XLSX تنها فرمت‌های V0.1 هستند؛ reader/writerها باید پشت contract باقی بمانند.
- مصرف حافظه باید تابع `chunkSize` باشد، نه کل ردیف‌ها.
- هیچ فایل ورودی یا payload حاوی PII public-by-default نیست.
- `create` نباید برای retry قابل‌اعتماد تلقی شود؛ idempotency با `upsertBy` صریح است.
- transaction پیش‌فرض در مرز هر chunk است.
- HTTP route و Vue UI نباید منطق domain را کپی کنند.
- هر مرحله فقط پس از تست unit/integration سبز، benchmark متناسب و changelog تکمیل‌شده وارد مرحله بعد می‌شود.

---

## ترتیب اجرا و وابستگی‌ها

| Plan | فیچرها | وابسته به | خروجی قابل انتشار |
| --- | --- | --- | --- |
| [01](2026-09-16-v01-core.md) | skeleton، CSV/XLSX، import/export، mapping، validation | — | `0.1.0-alpha` |
| [02](2026-09-16-v02-large-data.md) | chunking، queue، batch، retry، memory | 01 | `0.2.0-alpha` |
| [03](2026-09-16-v03-error-system.md) | failure rows، report، partial import، retry failure | 02 | `0.3.0-alpha` |
| [04](2026-09-16-v04-vue-ui.md) | wizard، mapping UI، progress، error viewer | 03 | `0.4.0-alpha` |
| [05](2026-09-16-v05-realtime.md) | Redis، broadcast، live dashboard | 04 | `0.5.0-alpha` |
| [06](2026-09-16-v06-v10-advanced-release.md) | schedule، storage، S3، notifications، history، permissions، release | 05 | `1.0.0` |

## تصمیم‌های معماری که باید پیش از Task 1 قفل شوند

- [ ] **ADR-001 — package identity and compatibility:** نام Composer (`bulkflow/laravel-bulkflow` مگر آن‌که مالک نام دیگری انتخاب کند)، namespace (`BulkFlow`)، حداقل PHP و Laravel matrix را ثبت کنید. خروجی: `doc/adr/001-package-identity.md`.
- [ ] **ADR-002 — spreadsheet adapter:** OpenSpout را با fixtureهای 10k و 100k ردیف در برابر نیازهای XLSX بررسی و به‌عنوان adapter اولیه تثبیت کنید. خروجی: `doc/adr/002-spreadsheet-adapter.md` و benchmark قابل تکرار.
- [ ] **ADR-003 — persistence semantics:** تفاوت `create`، `insert` و `upsert`، eventهای Eloquent، null handling و کلید idempotency را تعریف کنید. خروجی: `doc/adr/003-persistence-semantics.md`.
- [ ] **ADR-004 — stored failure privacy:** کلیدهای redact، retention و access control پیش‌فرض را تعیین کنید. خروجی: `doc/adr/004-failure-privacy.md`.

## Feature-by-feature review

### V0.1 — Core

1. **Package skeleton:** boundary و قراردادها را پیش از هر integration می‌سازد. پایان کار: `BulkFlowServiceProvider` در Testbench بالا می‌آید و config/migration publishable است.
2. **CSV reader/writer:** parsing و writing streaming، با encoding/delimiter/header قابل‌پیکربندی. پایان کار: fixture چند delimiter و UTF-8 تست می‌شود.
3. **XLSX reader/writer:** adapter XLSX streaming، انتخاب sheet و header. پایان کار: XLSX واقعی round-trip می‌شود.
4. **Import builder + mapping:** definition immutable و header-to-attribute resolver. پایان کار: mapping missing/duplicate با خطای domain مشخص می‌شود.
5. **Transform + validation:** هر row بعد از mapping تبدیل و با Laravel Validator بررسی می‌شود. پایان کار: validation error به source row وصل است.
6. **Persistence:** strategyهای create/insert/upsert با transaction chunk. پایان کار: رفتار duplicate به‌صورت تست‌شده و مستند است.
7. **Export:** Builder/query/iterable به CSV/XLSX streaming صادر می‌شود. پایان کار: export در حافظهٔ محدود fixture بزرگ را می‌نویسد.

### V0.2 — Large Data

8. **Chunking:** iterator را به chunkهای bounded تقسیم و شمارش را به runner منتقل می‌کند. پایان کار: با 10k ردیف، peak memory با افزایش row count به‌صورت خطی رشد نمی‌کند.
9. **Queue + batch:** definition snapshot به job serializable تبدیل می‌شود و batch state قابل query است. پایان کار: Queue fake همهٔ jobها و metadata را تأیید می‌کند.
10. **Retry:** failure infrastructure و transient job errors با backoff Laravel retry می‌شوند. پایان کار: job idempotent با upsert دوبار اجرا شود و duplicate نسازد.
11. **Memory benchmarks:** command benchmark و CI workflow شبانه. پایان کار: خروجی زمان/peak-memory برای fixtureها artifact می‌شود.

### V0.3 — Error System

12. **Run/failure persistence:** migrations و repositoryها برای `ImportRun` و `RowFailure`. پایان کار: state machine transitionهای نامعتبر را رد می‌کند.
13. **Partial-import policy:** `continue`، `fail-fast` و `stop-on-threshold`. پایان کار: هر policy با همان fixture summary مورد انتظار می‌دهد.
14. **Error reports:** CSV اول، XLSX دوم، با payload redact شده. پایان کار: report فقط failureهای run انتخابی را دارد.
15. **Retry failed rows:** definition snapshot و selection failureها، بدون replay موفق‌ها. پایان کار: retry run به parent run متصل و آمارش مستقل است.

### V0.4 — UI

16. **Headless API client:** typeهای transport مستقل از component. پایان کار: mock-server contract test.
17. **Wizard:** file → preview → mapping → confirm → progress. پایان کار: تعامل keyboard و validation state تست می‌شود.
18. **Error viewer:** pagination/filter/export/retry. پایان کار: permission-denied و empty states نمایش داده می‌شوند.

### V0.5 — Realtime

19. **Progress state store:** state قابل polling با version/revision. پایان کار: UI با reconnect بدون overcount همگام می‌شود.
20. **Broadcast adapter:** eventهای run را broadcast می‌کند و null adapter fallback دارد. پایان کار: بدون Redis UI polling می‌کند؛ با adapter eventها اعمال می‌شوند.
21. **Dashboard:** لیست runها با health/status و refresh state. پایان کار: pagination و tenant/actor scope اعمال شده‌اند.

### V0.6–V1.0 — Advanced and release

22. **Storage drivers + S3:** disk contract و private download URL. پایان کار: local و S3-compatible integration test.
23. **Scheduled export:** schedule definition و notification. پایان کار: schedule fake job را dispatch می‌کند و artifact می‌نویسد.
24. **History/retention:** cleanup command و audit fields. پایان کار: retention فقط runهای منقضی و مجاز را حذف می‌کند.
25. **Permissions:** resolver contract و Laravel policy bridge. پایان کار: actor نمی‌تواند run/failure tenant دیگر را ببیند.
26. **Release hardening:** docs/demo/CI/Packagist/NPM. پایان کار: release candidate از checkout تازه install و demo end-to-end اجرا می‌شود.

## Milestone gates

- [ ] **Gate A — پیش از V0.2:** Core API، driver contract و persistence semantics را API review کنید؛ breaking change پس از این gate فقط با major bump.
- [ ] **Gate B — پیش از V0.4:** schema run/failure، retention و authorization hook را review کنید؛ UI نباید schema داخلی را bypass کند.
- [ ] **Gate C — پیش از V1.0:** 100k import demo، release-from-scratch، security review PII، upgrade guide و compatibility matrix همگی پاس شوند.

## Shared Definition of Done

- تست‌های مرتبط با feature سبز هستند و یک test failure قبلی را بازتولید کرده‌اند.
- PHPStan/formatter و lint مربوطه سبز است.
- public API، config key، migration یا event جدید در docs ثبت شده است.
- errorها دارای کد/پیام قابل‌عمل و بدون PII ناخواسته هستند.
- changelog و release note حاوی behavior change و migration note هستند.

