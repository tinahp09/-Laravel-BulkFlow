# ADR-002 — Spreadsheet adapter

- Status: Accepted
- Date: 2026-09-16

## Decision

BulkFlow uses OpenSpout 4 as its CSV/XLSX streaming adapter. Public import and
export builders depend on BulkFlow reader/writer boundaries rather than exposing
OpenSpout objects.

## Rationale

OpenSpout reads and writes rows incrementally, which keeps peak memory bounded
for the core use case of large CSV/XLSX files. It covers the V1 format scope
without taking responsibility for Excel formulas, charts, macros or full
workbook editing.

## Consequences

- CSV and XLSX are the only V1 formats.
- XLSX queue chunks are spooled as temporary streamed files when byte ranges are
  not available; CSV queue chunks use byte ranges directly.
- An adapter replacement is possible behind BulkFlow's format boundary, but is
  a compatibility-sensitive change and requires benchmark evidence.
- The 100k CSV benchmark is documented in
  [v02.md](../benchmarks/v02.md); XLSX round-trip integration tests protect the
  supported worksheet path.
