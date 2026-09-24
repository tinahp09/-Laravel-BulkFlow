<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
  BulkFlowClient,
  ErrorViewer,
  ImportDashboard,
  ImportProgress,
  ImportProgressTracker,
  ImportWizard,
  LaravelEchoProgressSource,
  isTerminalImportState,
  proposeMapping,
  type ImportRun,
  type ImportProfile,
  type ImportMappingTemplate,
  type RowFailure,
  type ProfileUploadPreview,
  type LaravelEcho,
} from '@bulkflow/vue';

const client = new BulkFlowClient('/bulkflow');
const runs = ref<ImportRun[]>([]);
const runCurrentPage = ref(1);
const runLastPage = ref(1);
const runTotal = ref(0);
const runState = ref<string | undefined>();
const selectedRun = ref<ImportRun | null>(null);
const failures = ref<RowFailure[]>([]);
const failureStatus = ref<'pending' | 'resolved' | undefined>();
const failureCurrentPage = ref(1);
const failureLastPage = ref(1);
const failureTotal = ref(0);
const error = ref<string | null>(null);
const loading = ref(false);
const upload = ref<ProfileUploadPreview | null>(null);
const profiles = ref<ImportProfile[]>([]);
const selectedProfileKey = ref('');
const templates = ref<ImportMappingTemplate[]>([]);
const selectedTemplateId = ref('');
const importBusy = ref(false);
const importError = ref<string | null>(null);
let stopPolling: (() => void) | undefined;

const activeRunId = computed(() => selectedRun.value?.id ?? '');
const reportUrl = computed(() => selectedRun.value ? client.failureReportUrl(selectedRun.value.id, 'csv') : undefined);
const xlsxReportUrl = computed(() => selectedRun.value ? client.failureReportUrl(selectedRun.value.id, 'xlsx') : undefined);

function realtimeProgressSource(): LaravelEchoProgressSource | undefined {
  const echo = (window as Window & { Echo?: LaravelEcho }).Echo;

  return echo ? new LaravelEchoProgressSource(echo) : undefined;
}

async function loadRuns(page = 1): Promise<void> {
  loading.value = true;
  error.value = null;

  try {
    const result = await client.listRunsPage({ page, state: runState.value });
    runs.value = result.data;
    runCurrentPage.value = result.currentPage;
    runLastPage.value = result.lastPage;
    runTotal.value = result.total;
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to load import history.';
  } finally {
    loading.value = false;
  }
}

async function filterRuns(state: string | undefined): Promise<void> {
  runState.value = state;
  await loadRuns(1);
}

async function changeRunPage(page: number): Promise<void> {
  await loadRuns(page);
}

async function selectDashboardRun(runId: string): Promise<void> {
  const run = runs.value.find((candidate) => candidate.id === runId);
  if (run) await selectRun(run);
}

async function loadProfiles(): Promise<void> {
  try {
    profiles.value = await client.listImportProfiles();
    selectedProfileKey.value = profiles.value[0]?.key ?? '';
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to load import profiles.';
  }
}

async function loadTemplates(): Promise<void> {
  if (!selectedProfileKey.value) {
    templates.value = [];
    return;
  }

  try {
    templates.value = await client.listMappingTemplates(selectedProfileKey.value);
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to load mapping templates.';
  }
}

const selectedProfile = computed(() => profiles.value.find((profile) => profile.key === selectedProfileKey.value));
const mappingProposal = computed(() => {
  if (!selectedProfile.value) return {};

  return upload.value
    ? proposeMapping(upload.value.headers, selectedProfile.value)
    : selectedProfile.value.defaultMapping;
});

async function selectRun(run: ImportRun, page = 1): Promise<void> {
  stopPolling?.();
  stopPolling = undefined;
  selectedRun.value = run;
  error.value = null;
  const tracker = new ImportProgressTracker(client, run.id);

  try {
    const [release, failureResult] = await Promise.all([
      tracker.track({
        source: realtimeProgressSource(),
        pollIntervalMs: 3_000,
        onUpdate: (updatedRun) => {
          selectedRun.value = updatedRun;

          if (isTerminalImportState(updatedRun.state)) {
            stopPolling?.();
            stopPolling = undefined;
          }
        },
        onError: (reason) => {
          error.value = reason instanceof Error ? reason.message : 'Unable to refresh import progress.';
        },
      }),
      client.getFailuresPage(run.id, { page, status: failureStatus.value }),
    ]);
    stopPolling = release;
    selectedRun.value = tracker.current;
    failures.value = failureResult.data;
    failureCurrentPage.value = failureResult.currentPage;
    failureLastPage.value = failureResult.lastPage;
    failureTotal.value = failureResult.total;

  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to load import details.';
  }
}

async function filterFailures(status: 'pending' | 'resolved' | undefined): Promise<void> {
  failureStatus.value = status;

  if (selectedRun.value) {
    await selectRun(selectedRun.value, 1);
  }
}

async function changeFailurePage(page: number): Promise<void> {
  if (selectedRun.value) await selectRun(selectedRun.value, page);
}

async function retry(runId: string, failureIds?: string[]): Promise<void> {
  try {
    await client.retryFailures(runId, failureIds);
    await Promise.all([loadRuns(), selectedRun.value ? selectRun(selectedRun.value, 1) : Promise.resolve()]);
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to retry failed rows.';
  }
}

async function cancelSelectedRun(): Promise<void> {
  if (!selectedRun.value) return;

  try {
    stopPolling?.();
    stopPolling = undefined;
    selectedRun.value = await client.cancelImport(selectedRun.value.id);
    await loadRuns();
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to cancel import.';
  }
}

async function uploadFile(file: File): Promise<void> {
  if (!selectedProfileKey.value) return;
  importBusy.value = true;
  importError.value = null;

  try {
    upload.value = await client.uploadProfileImport(selectedProfileKey.value, file);
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to preview import file.';
  } finally {
    importBusy.value = false;
  }
}

async function startImport(mapping: Record<string, string>): Promise<void> {
  if (!upload.value || !selectedProfileKey.value) return;

  importBusy.value = true;
  importError.value = null;

  try {
    const run = await client.startProfileImport(selectedProfileKey.value, upload.value.uploadId, mapping);
    upload.value = null;
    await loadRuns();
    await selectRun(run);
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to start import.';
  } finally {
    importBusy.value = false;
  }
}

async function changeProfile(profileKey: string): Promise<void> {
  selectedProfileKey.value = profileKey;
  selectedTemplateId.value = '';
  upload.value = null;
  await loadTemplates();
}

async function saveTemplate(name: string, mapping: Record<string, string>): Promise<void> {
  if (!selectedProfileKey.value) return;
  importBusy.value = true;
  importError.value = null;

  try {
    const template = await client.saveMappingTemplate(selectedProfileKey.value, name, mapping);
    await loadTemplates();
    selectedTemplateId.value = template.id;
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to save mapping template.';
  } finally {
    importBusy.value = false;
  }
}

async function deleteTemplate(templateId: string): Promise<void> {
  if (!selectedProfileKey.value) return;
  importBusy.value = true;
  importError.value = null;

  try {
    await client.deleteMappingTemplate(selectedProfileKey.value, templateId);
    selectedTemplateId.value = '';
    await loadTemplates();
  } catch (reason) {
    importError.value = reason instanceof Error ? reason.message : 'Unable to delete mapping template.';
  } finally {
    importBusy.value = false;
  }
}

onMounted(async () => { await Promise.all([loadRuns(), loadProfiles()]); await loadTemplates(); });
onBeforeUnmount(() => stopPolling?.());
</script>

<template>
  <main>
    <header>
      <p class="eyebrow">Laravel + Vue reference application</p>
      <h1>BulkFlow demo</h1>
      <p>Inspect completed imports, their progress, and selected failed-row retries.</p>
      <button type="button" :disabled="loading" @click="() => loadRuns()">
        {{ loading ? 'Refreshing…' : 'Refresh imports' }}
      </button>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="import-panel" aria-label="Import data">
      <h2>Import data</h2>
      <p>Choose a registered profile, upload CSV or XLSX, map its columns, then queue an import.</p>
      <p v-if="importError" class="error" role="alert">{{ importError }}</p>
      <p v-if="importBusy">Preparing import…</p>
      <ImportWizard
        :headers="upload?.headers ?? []"
        :destinations="selectedProfile?.attributes ?? []"
        :preview-rows="upload?.preview"
        :profiles="profiles"
        :selected-profile="selectedProfileKey"
        :mapping-proposal="mappingProposal"
        :templates="templates"
        :selected-template-id="selectedTemplateId"
        @profile-change="changeProfile"
        @template-change="(templateId: string) => { selectedTemplateId = templateId; }"
        @save-template="saveTemplate"
        @delete-template="deleteTemplate"
        @upload="uploadFile"
        @confirm="startImport"
      />
    </section>

    <section class="grid">
      <aside>
        <h2>Import history</h2>
        <ImportDashboard
          :runs="runs"
          :current-page="runCurrentPage"
          :last-page="runLastPage"
          :total-runs="runTotal"
          @filter="filterRuns"
          @page-change="changeRunPage"
          @select="selectDashboardRun"
        />
      </aside>

      <section v-if="selectedRun" class="details">
        <h2>Import details</h2>
        <button v-if="!isTerminalImportState(selectedRun.state)" type="button" @click="cancelSelectedRun">
          Cancel import
        </button>
        <ImportProgress
          :processed-rows="selectedRun.processedRows"
          :total-rows="selectedRun.totalRows"
          :successful-rows="selectedRun.successfulRows"
          :failed-rows="selectedRun.failedRows"
        />
        <ErrorViewer
          :run-id="selectedRun.id"
          :failures="failures"
          :report-url="reportUrl"
          :xlsx-report-url="xlsxReportUrl"
          :current-page="failureCurrentPage"
          :last-page="failureLastPage"
          :total-failures="failureTotal"
          @filter="filterFailures"
          @page-change="changeFailurePage"
          @retry="retry"
        />
      </section>
      <section v-else class="details empty">
        Select an import to inspect its progress and errors.
      </section>
    </section>
  </main>
</template>
