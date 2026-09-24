import { describe, expect, it, vi } from 'vitest';
import { useImportProgress } from '../src/composables/useImportProgress';
import type { BulkFlowClient } from '../src/client';

describe('useImportProgress', () => {
  it('exposes the initial server snapshot and releases its tracker', async () => {
    const client = { getRun: vi.fn().mockResolvedValue({
      id: 'run-1', state: 'processing', processedRows: 10, totalRows: 100, successfulRows: 10, failedRows: 0, revision: 1,
    }) } as unknown as BulkFlowClient;
    const progress = useImportProgress(client);

    await progress.start('run-1', { pollIntervalMs: 1_000 });

    expect(progress.run.value).toMatchObject({ id: 'run-1', revision: 1 });
    progress.stop();
  });
});
