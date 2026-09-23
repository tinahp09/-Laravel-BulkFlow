<script setup lang="ts">
import { ref } from 'vue';
import type { RowFailure } from '../client';

const props = defineProps<{
  runId: string;
  failures: RowFailure[];
  reportUrl?: string;
  xlsxReportUrl?: string;
  currentPage?: number;
  lastPage?: number;
  totalFailures?: number;
}>();

const emit = defineEmits<{
  retry: [runId: string, failureIds?: string[]];
  filter: [status: 'pending' | 'resolved' | undefined];
  'page-change': [page: number];
}>();
const selectedFailureIds = ref<string[]>([]);

function retry(): void {
  emit('retry', props.runId, selectedFailureIds.value.length > 0 ? selectedFailureIds.value : undefined);
}

function updateFilter(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  emit('filter', value === '' ? undefined : value as 'pending' | 'resolved');
}

function changePage(page: number): void {
  if (page >= 1 && page <= (props.lastPage ?? 1)) emit('page-change', page);
}
</script>

<template>
  <section aria-label="Import errors">
    <label>
      Status
      <select aria-label="Failure status" @change="updateFilter">
        <option value="">All failures</option>
        <option value="pending">Pending</option>
        <option value="resolved">Resolved</option>
      </select>
    </label>
    <div v-if="reportUrl || xlsxReportUrl">
      <a v-if="reportUrl" :href="reportUrl" download>Download CSV report</a>
      <a v-if="xlsxReportUrl" :href="xlsxReportUrl" download aria-label="Download XLSX failure report">Download XLSX report</a>
    </div>
    <p v-if="failures.length === 0">No failed rows.</p>
    <ul v-else>
      <li v-for="failure in failures" :key="failure.id">
        <label>
          <input v-model="selectedFailureIds" type="checkbox" :value="failure.id" :aria-label="`Select row ${failure.rowNumber} for retry`">
          Select
        </label>
        <strong>Row {{ failure.rowNumber }}</strong>
        <span> · {{ failure.type }} · {{ failure.status }} · </span>
        <span>{{ Object.values(failure.errors).flat().join(', ') }}</span>
      </li>
    </ul>
    <button type="button" :disabled="failures.length === 0" @click="retry">
      Retry failed rows
    </button>
    <nav v-if="(lastPage ?? 1) > 1" aria-label="Failure pagination">
      <button type="button" aria-label="Previous failure page" :disabled="(currentPage ?? 1) <= 1" @click="changePage((currentPage ?? 1) - 1)">Previous</button>
      <span>Page {{ currentPage ?? 1 }} of {{ lastPage ?? 1 }} · {{ totalFailures ?? failures.length }} failures</span>
      <button type="button" aria-label="Next failure page" :disabled="(currentPage ?? 1) >= (lastPage ?? 1)" @click="changePage((currentPage ?? 1) + 1)">Next</button>
    </nav>
  </section>
</template>
