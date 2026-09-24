import { shallowRef } from 'vue';
import type { BulkFlowClient, ImportRun } from '../client';
import { ImportProgressTracker, type ImportProgressSource } from '../progress';

export function useImportProgress(client: Pick<BulkFlowClient, 'getRun'>) {
  const run = shallowRef<ImportRun | null>(null);
  const error = shallowRef<unknown>(null);
  let release: (() => void) | undefined;

  async function start(runId: string, options: {
    source?: ImportProgressSource;
    pollIntervalMs?: number;
  } = {}): Promise<void> {
    release?.();
    error.value = null;
    const tracker = new ImportProgressTracker(client, runId);

    release = await tracker.track({
      ...options,
      onUpdate: (updatedRun) => { run.value = updatedRun; },
      onError: (reason) => { error.value = reason; },
    });
    run.value = tracker.current;
  }

  function stop(): void {
    release?.();
    release = undefined;
  }

  return { run, error, start, stop };
}
