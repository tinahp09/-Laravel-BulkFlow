<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
  BulkFlowClient,
  ErrorViewer,
  ImportDashboard,
  ImportProgress,
  ImportProgressTracker,
  isTerminalImportState,
  type ImportRun,
  type RowFailure,
} from '@bulkflow/vue';

const client = new BulkFlowClient('/bulkflow');
const runs = ref<ImportRun[]>([]);
const selectedRun = ref<ImportRun | null>(null);
const failures = ref<RowFailure[]>([]);
const failureStatus = ref<'pending' | 'resolved' | undefined>();
const error = ref<string | null>(null);
const loading = ref(false);
let stopPolling: (() => void) | undefined;

const activeRunId = computed(() => selectedRun.value?.id ?? '');
const reportUrl = computed(() => selectedRun.value ? client.failureReportUrl(selectedRun.value.id) : undefined);

async function loadRuns(): Promise<void> {
  loading.value = true;
  error.value = null;

  try {
    runs.value = await client.listRuns();
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to load import history.';
  } finally {
    loading.value = false;
  }
}

async function selectRun(run: ImportRun): Promise<void> {
  stopPolling?.();
  stopPolling = undefined;
  selectedRun.value = run;
  error.value = null;
  const tracker = new ImportProgressTracker(client, run.id);

  try {
    const [latestRun, failurePage] = await Promise.all([
      tracker.refresh(),
      client.getFailuresPage(run.id, { status: failureStatus.value }),
    ]);
    selectedRun.value = latestRun;
    failures.value = failurePage.data;

    if (!isTerminalImportState(latestRun.state)) {
      stopPolling = tracker.poll(3_000, (updatedRun) => {
        selectedRun.value = updatedRun;

        if (isTerminalImportState(updatedRun.state)) {
          stopPolling?.();
          stopPolling = undefined;
        }
      }, (reason) => {
        error.value = reason instanceof Error ? reason.message : 'Unable to refresh import progress.';
      });
    }
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Unable to load import details.';
  }
}

async function filterFailures(status: 'pending' | 'resolved' | undefined): Promise<void> {
  failureStatus.value = status;

  if (selectedRun.value) {
    await selectRun(selectedRun.value);
  }
}

async function retry(runId: string, failureIds?: string[]): Promise<void> {
  try {
    await client.retryFailures(runId, failureIds);
    await Promise.all([loadRuns(), selectedRun.value ? selectRun(selectedRun.value) : Promise.resolve()]);
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

onMounted(loadRuns);
onBeforeUnmount(() => stopPolling?.());
</script>

<template>
  <main>
    <header>
      <p class="eyebrow">Laravel + Vue reference application</p>
      <h1>BulkFlow demo</h1>
      <p>Inspect completed imports, their progress, and selected failed-row retries.</p>
      <button type="button" :disabled="loading" @click="loadRuns">
        {{ loading ? 'Refreshing…' : 'Refresh imports' }}
      </button>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="grid">
      <aside>
        <h2>Import history</h2>
        <ImportDashboard :runs="runs" />
        <button
          v-for="run in runs"
          :key="run.id"
          class="run"
          :class="{ selected: run.id === activeRunId }"
          type="button"
          @click="selectRun(run)"
        >
          View {{ run.id.slice(0, 8) }}
        </button>
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
        <ErrorViewer :run-id="selectedRun.id" :failures="failures" :report-url="reportUrl" @filter="filterFailures" @retry="retry" />
      </section>
      <section v-else class="details empty">
        Select an import to inspect its progress and errors.
      </section>
    </section>
  </main>
</template>
