# BulkFlow V0.3 Error System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** نگهداری durable status و failed rowها، گزارش قابل‌دانلود و retry انتخابی بدون اجرای مجدد rowهای موفق.

**Architecture:** `RunRepository` و `FailureRepository` قراردادهای اصلی‌اند. هر queue chunk transition و failure را transactional ثبت می‌کند؛ retry یک run فرزند با snapshot definition والد می‌سازد.

**Tech Stack:** V0.2 + Laravel migrations/Eloquent، private Storage، PHPUnit.

**Spec:** [../PRD.md](../PRD.md)

## Tasks

### Task 1: Run state schema و repository

**Files:** Create `database/migrations/*_create_bulkflow_import_runs_table.php`, `src/Run/{ImportRun,RunRepository,DatabaseRunRepository,RunStateMachine}.php`, `tests/Integration/RunStateTest.php`.

- [ ] test transition table بنویسید: `draft→queued→processing→completed_with_errors` مجاز و `completed→processing` ممنوع است.
- [ ] migration و repository optimistic/atomic counter update را implement کنید؛ UUID، definition snapshot، metrics و parent_run_id را ذخیره کنید.
- [ ] test migration/state را PASS کنید.

### Task 2: Failure schema، redaction و partial policies

**Files:** Create `database/migrations/*_create_bulkflow_row_failures_table.php`, `src/Failure/{RowFailureRecord,FailureRepository,PayloadRedactor,ErrorPolicy}.php`, `tests/Integration/FailureStoreTest.php`.

- [ ] test بنویسید که `password` و کلیدهای config شده در payload با `[REDACTED]` ذخیره می‌شوند و row/attribute/error code ثبت است.
- [ ] policy testهای `continue`، `fail-fast` و `stop-on-threshold(2)` را بنویسید.
- [ ] repository/redactor/policy را implement و tests را PASS کنید.

### Task 3: Failure report

**Files:** Create `src/Failure/FailureReportExporter.php`, `tests/Integration/FailureReportTest.php`.

- [ ] test CSV report بنویسید که header شامل source row, error code, message است و فقط failureهای همان run را صادر می‌کند.
- [ ] exporter را با writer contract V0.1 implement کنید؛ XLSX را با همان column schema اضافه کنید.
- [ ] report tests را PASS کنید.

### Task 4: Retry failed rows

**Files:** Create `src/Retry/{RetryFailedRows,RetrySelection}.php`, `tests/Feature/RetryFailedRowsTest.php`.

- [ ] fixture با 3 success و 2 failure بسازید؛ test باید assert کند retry فقط 2 source row را پردازش و parent run را تغییر نمی‌دهد.
- [ ] snapshot definition، retry limit و selection (`all|ids`) را implement کنید؛ resolved failureها را به retry run link کنید.
- [ ] test را PASS و docs/privacy/retention را به‌روز کنید.

### Task 5: V0.3 release gate

- [ ] cleanup command با `--before` و `--dry-run` بسازید و test کنید هرگز run تازه را حذف نمی‌کند.
- [ ] migration guide و changelog `0.3.0-alpha` را منتشر کنید؛ suite کامل PASS باشد.

