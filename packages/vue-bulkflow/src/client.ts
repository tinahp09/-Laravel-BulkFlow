export type ImportRun = {
  id: string;
  state: string;
  processedRows: number;
  totalRows: number;
  successfulRows: number;
  failedRows: number;
  revision: number;
};

export type RowFailure = {
  id: string;
  rowNumber: number;
  type: string;
  status: 'pending' | 'resolved';
  errors: Record<string, string[]>;
  payload: Record<string, unknown> | null;
};

export type RetryRun = { id: string; parentRunId: string; state: string };

export type UploadPreview = {
  uploadId: string;
  headers: string[];
  preview: Array<Record<string, unknown>>;
};
export type ImportProfile = { key: string; label: string; attributes: string[]; defaultMapping: Record<string, string> };
export type ImportMappingTemplate = { id: string; name: string; mapping: Record<string, string> };
export type ProfileUploadPreview = UploadPreview;

export type FailurePage = {
  data: RowFailure[];
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

export type RunPage = {
  data: ImportRun[];
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

type Fetcher = typeof fetch;

export class BulkFlowClient {
  constructor(
    private readonly baseUrl: string,
    private readonly fetcher: Fetcher = (input, init) => globalThis.fetch(input, init),
  ) {}

  async getRun(id: string): Promise<ImportRun> {
    const response = await this.fetcher(`${this.baseUrl}/imports/${id}`, {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Unable to load import run: ${response.status}`);
    }

    const payload = await response.json() as {
      id: string;
      state: string;
      processed_rows: number;
      total_rows: number;
      successful_rows?: number;
      failed_rows?: number;
      revision?: number;
    };

    return {
      id: payload.id,
      state: payload.state,
      processedRows: payload.processed_rows ?? 0,
      totalRows: payload.total_rows ?? 0,
      successfulRows: payload.successful_rows ?? 0,
      failedRows: payload.failed_rows ?? 0,
      revision: payload.revision ?? 0,
    };
  }

  async uploadDemoImport(file: File): Promise<UploadPreview> {
    const body = new FormData();
    body.set('file', file);
    const response = await this.fetcher(`${this.baseUrl}/demo-imports/upload`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body,
    });

    if (!response.ok) {
      throw new Error(`Unable to upload import file: ${response.status}`);
    }

    const payload = await response.json() as { upload_id: string; headers: string[]; preview: Array<Record<string, unknown>> };

    return { uploadId: payload.upload_id, headers: payload.headers, preview: payload.preview };
  }

  async listImportProfiles(): Promise<ImportProfile[]> {
    const response = await this.fetcher(`${this.baseUrl}/import-profiles`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Unable to load import profiles: ${response.status}`);
    const payload = await response.json() as { data: Array<{ key: string; label: string; attributes: string[]; default_mapping: Record<string, string> }> };
    return payload.data.map((profile) => ({ key: profile.key, label: profile.label, attributes: profile.attributes, defaultMapping: profile.default_mapping }));
  }

  async listMappingTemplates(profileKey: string): Promise<ImportMappingTemplate[]> {
    const response = await this.fetcher(`${this.baseUrl}/import-profiles/${encodeURIComponent(profileKey)}/mapping-templates`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Unable to load mapping templates: ${response.status}`);
    return (await response.json() as { data: ImportMappingTemplate[] }).data;
  }

  async saveMappingTemplate(profileKey: string, name: string, mapping: Record<string, string>): Promise<ImportMappingTemplate> {
    const response = await this.fetcher(`${this.baseUrl}/import-profiles/${encodeURIComponent(profileKey)}/mapping-templates`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, mapping }),
    });
    if (!response.ok) throw new Error(`Unable to save mapping template: ${response.status}`);
    return await response.json() as ImportMappingTemplate;
  }

  async deleteMappingTemplate(profileKey: string, templateId: string): Promise<void> {
    const response = await this.fetcher(`${this.baseUrl}/import-profiles/${encodeURIComponent(profileKey)}/mapping-templates/${encodeURIComponent(templateId)}`, {
      method: 'DELETE', headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error(`Unable to delete mapping template: ${response.status}`);
  }

  async uploadProfileImport(profileKey: string, file: File): Promise<ProfileUploadPreview> {
    const body = new FormData(); body.set('file', file);
    const response = await this.fetcher(`${this.baseUrl}/import-profiles/${encodeURIComponent(profileKey)}/uploads`, { method: 'POST', headers: { Accept: 'application/json' }, body });
    if (!response.ok) throw new Error(`Unable to upload import file: ${response.status}`);
    const payload = await response.json() as { upload_id: string; headers: string[]; preview: Array<Record<string, unknown>> };
    return { uploadId: payload.upload_id, headers: payload.headers, preview: payload.preview };
  }

  async startProfileImport(profileKey: string, uploadId: string, mapping: Record<string, string>): Promise<ImportRun> {
    const response = await this.fetcher(`${this.baseUrl}/import-profiles/${encodeURIComponent(profileKey)}/imports`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ upload_id: uploadId, mapping }) });
    if (!response.ok) throw new Error(`Unable to start import: ${response.status}`);
    const payload = await response.json() as { id: string; state: string; processed_rows: number; total_rows: number; successful_rows?: number; failed_rows?: number; revision?: number };
    return { id: payload.id, state: payload.state, processedRows: payload.processed_rows ?? 0, totalRows: payload.total_rows ?? 0, successfulRows: payload.successful_rows ?? 0, failedRows: payload.failed_rows ?? 0, revision: payload.revision ?? 0 };
  }

  async startDemoImport(uploadId: string, mapping: Record<string, string>): Promise<ImportRun> {
    const response = await this.fetcher(`${this.baseUrl}/demo-imports`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ upload_id: uploadId, mapping }),
    });

    if (!response.ok) {
      throw new Error(`Unable to start import: ${response.status}`);
    }

    const payload = await response.json() as {
      id: string; state: string; processed_rows: number; total_rows: number; successful_rows?: number; failed_rows?: number; revision?: number;
    };

    return {
      id: payload.id,
      state: payload.state,
      processedRows: payload.processed_rows,
      totalRows: payload.total_rows,
      successfulRows: payload.successful_rows ?? 0,
      failedRows: payload.failed_rows ?? 0,
      revision: payload.revision ?? 0,
    };
  }

  async getFailures(runId: string): Promise<RowFailure[]> {
    return (await this.getFailuresPage(runId)).data;
  }

  async getFailuresPage(runId: string, options: { page?: number; perPage?: number; status?: 'pending' | 'resolved'; type?: string } = {}): Promise<FailurePage> {
    const query = new URLSearchParams();
    if (options.page !== undefined) query.set('page', String(options.page));
    if (options.perPage !== undefined) query.set('per_page', String(options.perPage));
    if (options.status !== undefined) query.set('status', options.status);
    if (options.type !== undefined) query.set('type', options.type);
    const suffix = query.size === 0 ? '' : `?${query.toString()}`;
    const response = await this.fetcher(`${this.baseUrl}/imports/${runId}/failures${suffix}`, {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Unable to load import failures: ${response.status}`);
    }

    const payload = await response.json() as { data: Array<{
      id: string; row_number: number; type: string; status?: 'pending' | 'resolved'; errors: Record<string, string[]>; payload: Record<string, unknown> | null;
    }>; meta?: { current_page: number; last_page: number; per_page: number; total: number } };

    const data = payload.data.map((failure) => ({
      id: failure.id,
      rowNumber: failure.row_number,
      type: failure.type,
      status: failure.status ?? 'pending',
      errors: failure.errors,
      payload: failure.payload,
    }));

    return {
      data,
      currentPage: payload.meta?.current_page ?? 1,
      lastPage: payload.meta?.last_page ?? 1,
      perPage: payload.meta?.per_page ?? data.length,
      total: payload.meta?.total ?? data.length,
    };
  }

  async retryFailures(runId: string, failureIds?: string[]): Promise<RetryRun> {
    const options: RequestInit = {
      method: 'POST',
      headers: { Accept: 'application/json' },
    };

    if (failureIds !== undefined) {
      options.headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
      options.body = JSON.stringify({ failure_ids: failureIds });
    }

    const response = await this.fetcher(`${this.baseUrl}/imports/${runId}/retry-failures`, options);

    if (!response.ok) {
      throw new Error(`Unable to retry import failures: ${response.status}`);
    }

    const payload = await response.json() as { id: string; parent_run_id: string; state: string };

    return { id: payload.id, parentRunId: payload.parent_run_id, state: payload.state };
  }

  async cancelImport(id: string): Promise<ImportRun> {
    const response = await this.fetcher(`${this.baseUrl}/imports/${id}/cancel`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Unable to cancel import: ${response.status}`);
    }

    const payload = await response.json() as {
      id: string;
      state: string;
      processed_rows?: number;
      total_rows?: number;
      successful_rows?: number;
      failed_rows?: number;
      revision?: number;
    };

    return {
      id: payload.id,
      state: payload.state,
      processedRows: payload.processed_rows ?? 0,
      totalRows: payload.total_rows ?? 0,
      successfulRows: payload.successful_rows ?? 0,
      failedRows: payload.failed_rows ?? 0,
      revision: payload.revision ?? 0,
    };
  }

  failureReportUrl(runId: string, format: 'csv' | 'xlsx' = 'csv'): string {
    return `${this.baseUrl}/imports/${encodeURIComponent(runId)}/failures/report?format=${format}`;
  }

  async listRunsPage(options: { page?: number; perPage?: number; state?: string } = {}): Promise<RunPage> {
    const query = new URLSearchParams();
    if (options.page !== undefined) query.set('page', String(options.page));
    if (options.perPage !== undefined) query.set('per_page', String(options.perPage));
    if (options.state !== undefined) query.set('state', options.state);
    const suffix = query.size === 0 ? '' : `?${query.toString()}`;
    const response = await this.fetcher(`${this.baseUrl}/imports${suffix}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Unable to load import history: ${response.status}`);

    const payload = await response.json() as { data: Array<{
      id: string; state: string; processed_rows: number; total_rows: number; successful_rows?: number; failed_rows?: number; revision?: number;
    }>; meta?: { current_page: number; last_page: number; per_page: number; total: number } };
    const data = payload.data.map((run) => ({
      id: run.id,
      state: run.state,
      processedRows: run.processed_rows ?? 0,
      totalRows: run.total_rows ?? 0,
      successfulRows: run.successful_rows ?? 0,
      failedRows: run.failed_rows ?? 0,
      revision: run.revision ?? 0,
    }));

    return {
      data,
      currentPage: payload.meta?.current_page ?? 1,
      lastPage: payload.meta?.last_page ?? 1,
      perPage: payload.meta?.per_page ?? data.length,
      total: payload.meta?.total ?? data.length,
    };
  }

  async listRuns(): Promise<ImportRun[]> {
    return (await this.listRunsPage()).data;
  }
}
