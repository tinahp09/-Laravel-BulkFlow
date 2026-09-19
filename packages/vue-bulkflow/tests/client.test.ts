import { describe, expect, it, vi } from 'vitest';
import { BulkFlowClient } from '../src/client';

describe('BulkFlowClient', () => {
  it('calls the browser fetch implementation with globalThis as its receiver', async () => {
    const fetcher = vi.fn(function (this: unknown) {
      expect(this).toBe(globalThis);
      return Promise.resolve(new Response(JSON.stringify({
        id: 'run-1', state: 'processing', processed_rows: 0, total_rows: 1,
      }), { status: 200 }));
    });
    vi.stubGlobal('fetch', fetcher);

    await new BulkFlowClient('/bulkflow').getRun('run-1');

    vi.unstubAllGlobals();
  });

  it('requests a run and returns its progress summary', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      id: 'run-1', state: 'processing', processed_rows: 20, total_rows: 100, successful_rows: 18, failed_rows: 2,
    }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    const run = await client.getRun('run-1');

    expect(run).toEqual({ id: 'run-1', state: 'processing', processedRows: 20, totalRows: 100, successfulRows: 18, failedRows: 2, revision: 0 });
    expect(fetcher).toHaveBeenCalledWith('/bulkflow/imports/run-1', { headers: { Accept: 'application/json' } });
  });

  it('loads row failures for a run', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      data: [{ id: 'failure-1', row_number: 7, type: 'validation', errors: { email: ['Invalid'] }, payload: { email: 'bad' } }],
    }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    const failures = await client.getFailures('run-1');

    expect(failures[0]).toMatchObject({ id: 'failure-1', rowNumber: 7, type: 'validation' });
  });

  it('loads a filtered failure page with pagination metadata', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      data: [{ id: 'failure-1', row_number: 7, type: 'validation', status: 'pending', errors: {}, payload: null }],
      meta: { current_page: 2, last_page: 3, per_page: 1, total: 3 },
    }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    const page = await client.getFailuresPage('run-1', { page: 2, perPage: 1, status: 'pending' });

    expect(page).toMatchObject({ currentPage: 2, lastPage: 3, perPage: 1, total: 3 });
    expect(fetcher).toHaveBeenCalledWith('/bulkflow/imports/run-1/failures?page=2&per_page=1&status=pending', { headers: { Accept: 'application/json' } });
  });

  it('starts a retry for selected failed rows', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ id: 'retry-1', parent_run_id: 'run-1', state: 'completed' }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    const retry = await client.retryFailures('run-1', ['failure-1']);

    expect(retry).toEqual({ id: 'retry-1', parentRunId: 'run-1', state: 'completed' });
    expect(fetcher).toHaveBeenCalledWith('/bulkflow/imports/run-1/retry-failures', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ failure_ids: ['failure-1'] }),
    });
  });

  it('cancels an active import and returns its updated state', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      id: 'run-1', state: 'cancelled', processed_rows: 4, total_rows: 10, successful_rows: 4, failed_rows: 0, revision: 5,
    }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    expect(await client.cancelImport('run-1')).toEqual({
      id: 'run-1', state: 'cancelled', processedRows: 4, totalRows: 10, successfulRows: 4, failedRows: 0, revision: 5,
    });
    expect(fetcher).toHaveBeenCalledWith('/bulkflow/imports/run-1/cancel', {
      method: 'POST',
      headers: { Accept: 'application/json' },
    });
  });

  it('builds a failure-report URL for a requested format', () => {
    const client = new BulkFlowClient('/bulkflow');

    expect(client.failureReportUrl('run-1', 'xlsx')).toBe('/bulkflow/imports/run-1/failures/report?format=xlsx');
  });

  it('lists import runs for the dashboard', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [{ id: 'run-1', state: 'completed', processed_rows: 3, total_rows: 3, successful_rows: 3, failed_rows: 0 }] }), { status: 200 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    expect(await client.listRuns()).toEqual([{ id: 'run-1', state: 'completed', processedRows: 3, totalRows: 3, successfulRows: 3, failedRows: 0, revision: 0 }]);
  });

  it('uploads a selected file and returns its preview', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      upload_id: 'run-upload-1', headers: ['name'], preview: [{ name: 'Neda' }],
    }), { status: 201 }));
    const client = new BulkFlowClient('/bulkflow', fetcher);

    expect(await client.uploadDemoImport(new File(['name\nNeda'], 'users.csv'))).toEqual({
      uploadId: 'run-upload-1', headers: ['name'], preview: [{ name: 'Neda' }],
    });
    expect(fetcher.mock.calls[0][0]).toBe('/bulkflow/demo-imports/upload');
    expect(fetcher.mock.calls[0][1].body).toBeInstanceOf(FormData);
  });

  it('starts a previewed import with its token and mapping', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      id: 'run-1', state: 'queued', processed_rows: 0, total_rows: 0, successful_rows: 0, failed_rows: 0,
    }), { status: 201 }));

    expect(await new BulkFlowClient('/bulkflow', fetcher).startDemoImport('upload-1', {
      name: 'name', email: 'email', password: 'password',
    })).toMatchObject({ id: 'run-1', state: 'queued' });
    expect(fetcher).toHaveBeenCalledWith('/bulkflow/demo-imports', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ upload_id: 'upload-1', mapping: { name: 'name', email: 'email', password: 'password' } }),
    });
  });
});
