<script setup lang="ts">
import type { ImportRun } from '../client';

const props = defineProps<{
  runs: ImportRun[];
  currentPage?: number;
  lastPage?: number;
  totalRuns?: number;
}>();

const emit = defineEmits<{
  filter: [state: string | undefined];
  'page-change': [page: number];
  select: [runId: string];
}>();

function updateFilter(event: Event): void {
  const state = (event.target as HTMLSelectElement).value;
  emit('filter', state === '' ? undefined : state);
}

function changePage(page: number): void {
  if (page >= 1 && page <= (props.lastPage ?? 1)) emit('page-change', page);
}
</script>

<template>
  <section aria-label="Import dashboard">
    <label>
      Status
      <select aria-label="Import status" @change="updateFilter">
        <option value="">All imports</option>
        <option value="queued">Queued</option>
        <option value="processing">Processing</option>
        <option value="completed">Completed</option>
        <option value="completed_with_errors">Completed with errors</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </label>
    <p v-if="runs.length === 0">No imports yet.</p>
    <ul v-else>
      <li v-for="run in runs" :key="run.id">
        <button type="button" :aria-label="`Select import ${run.id}`" @click="emit('select', run.id)">
          <strong>{{ run.state }}</strong>
        </button>
        <span> · {{ run.processedRows }} / {{ run.totalRows }}</span>
      </li>
    </ul>
    <nav v-if="(lastPage ?? 1) > 1" aria-label="Import pagination">
      <button type="button" aria-label="Previous import page" :disabled="(currentPage ?? 1) <= 1" @click="changePage((currentPage ?? 1) - 1)">Previous</button>
      <span>Page {{ currentPage ?? 1 }} of {{ lastPage ?? 1 }} · {{ totalRuns ?? runs.length }} imports</span>
      <button type="button" aria-label="Next import page" :disabled="(currentPage ?? 1) >= (lastPage ?? 1)" @click="changePage((currentPage ?? 1) + 1)">Next</button>
    </nav>
  </section>
</template>
