import type { BulkFlowClient, ImportRun } from './client';

export function isTerminalImportState(state: string): boolean {
  return ['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(state);
}

export type ImportProgressSource = {
  subscribe(runId: string, onProgress: (run: ImportRun) => void): () => void;
};

type EchoPrivateChannel = {
  listen(event: string, callback: (payload: Record<string, unknown>) => void): void;
};

export type LaravelEcho = {
  private(channel: string): EchoPrivateChannel;
  leave(channel: string): void;
};

/** Adapter for Laravel Echo without making Echo an npm dependency. */
export class LaravelEchoProgressSource implements ImportProgressSource {
  constructor(private readonly echo: LaravelEcho) {}

  subscribe(runId: string, onProgress: (run: ImportRun) => void): () => void {
    const channelName = `bulkflow.imports.${runId}`;
    const channel = this.echo.private(channelName);

    channel.listen('.bulkflow.progress.updated', (payload) => {
      onProgress({
        id: String(payload.id),
        state: String(payload.state),
        totalRows: Number(payload.total_rows),
        processedRows: Number(payload.processed_rows),
        successfulRows: Number(payload.successful_rows),
        failedRows: Number(payload.failed_rows),
        revision: Number(payload.revision),
      });
    });

    return () => this.echo.leave(channelName);
  }
}

export class ImportProgressTracker {
  current: ImportRun | null = null;

  constructor(
    private readonly client: Pick<BulkFlowClient, 'getRun'>,
    private readonly runId: string,
  ) {}

  async refresh(): Promise<ImportRun> {
    const run = await this.client.getRun(this.runId);

    this.apply(run);

    return this.current as ImportRun;
  }

  poll(
    intervalMs: number,
    onUpdate: (run: ImportRun) => void = () => undefined,
    onError: (error: unknown) => void = () => undefined,
  ): () => void {
    if (!Number.isFinite(intervalMs) || intervalMs < 1) {
      throw new RangeError('Polling interval must be at least one millisecond.');
    }

    const timer = globalThis.setInterval(() => {
      void this.refresh().then(onUpdate).catch(onError);
    }, intervalMs);

    return () => globalThis.clearInterval(timer);
  }

  apply(run: ImportRun): boolean {
    if (this.current !== null && run.revision < this.current.revision) {
      return false;
    }

    this.current = run;

    return true;
  }

  connect(source: ImportProgressSource): () => void {
    return source.subscribe(this.runId, (run) => this.apply(run));
  }
}
