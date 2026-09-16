<script setup lang="ts">
import { ref } from 'vue';
import type { RowFailure } from '../client';

const props = defineProps<{
  runId: string;
  failures: RowFailure[];
  reportUrl?: string;
}>();

const emit = defineEmits<{
  retry: [runId: string, failureIds?: string[]];
  filter: [status: 'pending' | 'resolved' | undefined];
}>();
const selectedFailureIds = ref<string[]>([]);

function retry(): void {
  emit('retry', props.runId, selectedFailureIds.value.length > 0 ? selectedFailureIds.value : undefined);
}

function updateFilter(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  emit('filter', value === '' ? undefined : value as 'pending' | 'resolved');
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
    <a v-if="reportUrl" :href="reportUrl" download>Download CSV report</a>
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
  </section>
</template>
