import { describe, expect, it, vi } from 'vitest';
import { ImportProgressTracker, isTerminalImportState, LaravelEchoProgressSource } from '../src/progress';
import type { BulkFlowClient } from '../src/client';

describe('ImportProgressTracker', () => {
  it('treats cancelled imports as terminal progress states', () => {
    expect(isTerminalImportState('cancelled')).toBe(true);
    expect(isTerminalImportState('processing')).toBe(false);
  });

  it('polls for progress updates until stopped', async () => {
    vi.useFakeTimers();
    const client = { getRun: vi.fn().mockResolvedValue({
      id: 'run-1', state: 'processing', processedRows: 25, totalRows: 100, successfulRows: 25, failedRows: 0, revision: 1,
    }) } as unknown as BulkFlowClient;
    const tracker = new ImportProgressTracker(client, 'run-1');
    const onUpdate = vi.fn();

    const stop = tracker.poll(1_000, onUpdate);
    await vi.advanceTimersByTimeAsync(1_000);

    expect(onUpdate).toHaveBeenCalledWith(expect.objectContaining({ processedRows: 25 }));
    stop();
    await vi.advanceTimersByTimeAsync(1_000);
    expect(client.getRun).toHaveBeenCalledTimes(1);
    vi.useRealTimers();
  });

  it('loads the current run and refreshes it on demand', async () => {
    const client = { getRun: vi.fn()
      .mockResolvedValueOnce({ id: 'run-1', state: 'processing', processedRows: 40, totalRows: 100, revision: 2 })
      .mockResolvedValueOnce({ id: 'run-1', state: 'processing', processedRows: 20, totalRows: 100, revision: 1 }) } as unknown as BulkFlowClient;
    const tracker = new ImportProgressTracker(client, 'run-1');

    const run = await tracker.refresh();

    expect(run.processedRows).toBe(40);
    expect(tracker.current).toEqual(run);
    expect(client.getRun).toHaveBeenCalledWith('run-1');

    await tracker.refresh();
    expect(tracker.current?.processedRows).toBe(40);
    expect(tracker.current?.revision).toBe(2);
  });

  it('accepts realtime updates and ignores an older revision', () => {
    const client = { getRun: vi.fn() } as unknown as BulkFlowClient;
    const tracker = new ImportProgressTracker(client, 'run-1');
    const subscribe = vi.fn((runId: string, callback: (run: { id: string; state: string; processedRows: number; totalRows: number; revision: number }) => void) => {
      expect(runId).toBe('run-1');
      callback({ id: 'run-1', state: 'processing', processedRows: 90, totalRows: 100, revision: 3 });
      callback({ id: 'run-1', state: 'processing', processedRows: 10, totalRows: 100, revision: 1 });
      return vi.fn();
    });

    const disconnect = tracker.connect({ subscribe });

    expect(tracker.current?.processedRows).toBe(90);
    expect(tracker.current?.revision).toBe(3);
    disconnect();
  });

  it('maps the stable BulkFlow broadcast payload from a Laravel Echo private channel', () => {
    const listen = vi.fn();
    const leave = vi.fn();
    const source = new LaravelEchoProgressSource({
      private: vi.fn(() => ({ listen })),
      leave,
    });
    const onProgress = vi.fn();

    const disconnect = source.subscribe('run-1', onProgress);
    const listener = listen.mock.calls[0][1] as (payload: Record<string, unknown>) => void;
    listener({
      id: 'run-1',
      state: 'processing',
      total_rows: 100,
      processed_rows: 25,
      successful_rows: 24,
      failed_rows: 1,
      revision: 3,
    });

    expect(listen).toHaveBeenCalledWith('.bulkflow.progress.updated', expect.any(Function));
    expect(onProgress).toHaveBeenCalledWith({
      id: 'run-1',
      state: 'processing',
      totalRows: 100,
      processedRows: 25,
      successfulRows: 24,
      failedRows: 1,
      revision: 3,
    });

    disconnect();
    expect(leave).toHaveBeenCalledWith('bulkflow.imports.run-1');
  });

  it('loads first, accepts newer realtime progress, and falls back to polling after a transport disconnect', async () => {
    vi.useFakeTimers();
    const client = { getRun: vi.fn()
      .mockResolvedValueOnce({ id: 'run-1', state: 'processing', processedRows: 10, totalRows: 100, successfulRows: 10, failedRows: 0, revision: 1 })
      .mockResolvedValueOnce({ id: 'run-1', state: 'processing', processedRows: 30, totalRows: 100, successfulRows: 29, failedRows: 1, revision: 3 }) } as unknown as BulkFlowClient;
    const tracker = new ImportProgressTracker(client, 'run-1');
    const updates: number[] = [];

    const stop = await tracker.track({
      source: {
        subscribe: (_runId, onProgress, onDisconnect) => {
          onProgress({ id: 'run-1', state: 'processing', processedRows: 20, totalRows: 100, successfulRows: 20, failedRows: 0, revision: 2 });
          onProgress({ id: 'run-1', state: 'processing', processedRows: 5, totalRows: 100, successfulRows: 5, failedRows: 0, revision: 1 });
          onDisconnect?.();

          return () => undefined;
        },
      },
      pollIntervalMs: 1_000,
      onUpdate: (run) => updates.push(run.revision),
    });

    expect(tracker.current?.revision).toBe(2);
    await vi.advanceTimersByTimeAsync(1_000);
    expect(tracker.current?.revision).toBe(3);
    expect(updates).toEqual([1, 2, 3]);
    stop();
    vi.useRealTimers();
  });

  it('does not subscribe or poll when the initial snapshot is terminal', async () => {
    vi.useFakeTimers();
    const client = { getRun: vi.fn().mockResolvedValue({
      id: 'run-1', state: 'completed', processedRows: 1, totalRows: 1, successfulRows: 1, failedRows: 0, revision: 3,
    }) } as unknown as BulkFlowClient;
    const subscribe = vi.fn();
    const tracker = new ImportProgressTracker(client, 'run-1');

    await tracker.track({ source: { subscribe }, pollIntervalMs: 1_000 });
    await vi.advanceTimersByTimeAsync(2_000);

    expect(subscribe).not.toHaveBeenCalled();
    expect(client.getRun).toHaveBeenCalledTimes(1);
    vi.useRealTimers();
  });
});
