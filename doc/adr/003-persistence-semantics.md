# ADR-003 — Persistence semantics and idempotency

- Status: Accepted
- Date: 2026-09-16

## Decision

`upsertBy()` is the idempotent persistence mode for queued and retried imports.
BulkFlow persists valid records in bounded chunks. It applies model casts and
timestamps before bulk persistence; if a bulk write fails, it falls back to
individual records so that actionable failed-row records are retained.

`create`/insert-style persistence is available for workflows that require it,
but it is not safe to treat as exactly-once across queue retries. Consumers must
choose a stable unique key and call `upsertBy()` when duplicate prevention is a
requirement.

## Consequences

- Retries only replay selected failed rows, never successful rows from a parent
  run.
- Eloquent per-model events are not guaranteed for bulk `insert`/`upsert`.
- Global, all-file transactions are intentionally not the default; the
  transaction and recovery boundary is a chunk.
- Null handling and database uniqueness semantics remain those of the consumer's
  database driver and schema.
