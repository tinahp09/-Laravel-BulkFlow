# BulkFlow V0.6–V1.0 Advanced and Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** قابلیت‌های production (storage، schedule، notification، history، permissions) و انتشار پایدار Laravel/Vue packageها.

**Architecture:** storage، scheduler، notifier و authorization همگی contract/adapter هستند. core import pipeline به providerهای cloud یا UI dependency ندارد؛ demo app تنها integration reference است.

**Tech Stack:** Laravel Filesystem/Scheduler/Notifications/Policies، S3-compatible test service، Packagist و NPM.

**Spec:** [../PRD.md](../PRD.md)

## Tasks

### Task 1: Storage drivers و S3

**Files:** Create `src/Storage/*`, integration tests, config docs.

- [x] test local private disk و S3-compatible disk برای input/export و temporary download URL بنویسید.
- [x] storage locator/driver contract را implement کنید؛ public URL تنها با explicit opt-in باشد.
- [x] tests را PASS کنید.

### Task 2: Scheduled exports و notifications

**Files:** Create `src/Schedule/*`, `src/Notification/*`, tests.

- [x] test schedule fake بنویسید که definition در cron مناسب dispatch و completion notification با artifact metadata می‌فرستد.
- [x] scheduler/notification adapterها و failure notification policy را implement کنید.
- [x] tests را PASS کنید.

### Task 3: History، retention و permissions

**Files:** Create `src/Authorization/*`, `src/History/*`, migrations/tests.

- [x] test actor/tenant isolation برای run، failure و report و cleanup `--dry-run` بنویسید.
- [x] resolver/policy bridge، audit fields و retention command را implement کنید.
- [x] tests را PASS کنید.

### Task 4: Demo، docs و release

**Files:** Create `demo/`, `docs/` و package publish config.

- [x] demo 100k users import، upsert، intentional validation failures، retry و report flow را بسازید.
- [x] checkout تازه را به‌صورت local install، test و build کنید؛ PHP/Laravel compatibility matrix را دستی اجرا کنید.
- [x] راهنمای انتشار Packagist/NPM با tag و provenance/secret guidance بنویسید؛ publish دستی بدون tag مستند و ممنوع باشد.
- [x] security/PII review، upgrade guide، API docs، changelog و `1.0.0-rc.1` smoke test را کامل کنید.

### Task 5: V1.0 acceptance gate

- [x] 100k queued import، CSV/XLSX export، retry failed rows، S3 storage، polling و realtime را در demo end-to-end اجرا کنید.
- [x] full suite، static analysis، lint، build و release dry-run را PASS کنید.
- [ ] maintainer checklist را sign off و `1.0.0` tag را فقط پس از تأیید مالک منتشر کنید.
