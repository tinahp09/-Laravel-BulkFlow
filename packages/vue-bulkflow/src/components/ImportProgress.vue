<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  processedRows: number;
  totalRows: number;
  successfulRows: number;
  failedRows: number;
}>();

const totalRows = computed(() =>
  Number.isFinite(props.totalRows) && props.totalRows > 0 ? props.totalRows : 0,
);
const processedRows = computed(() =>
  Number.isFinite(props.processedRows) && props.processedRows > 0 ? props.processedRows : 0,
);
const percentage = computed(() =>
  totalRows.value === 0
    ? 0
    : Math.min(100, Math.round((processedRows.value / totalRows.value) * 100)),
);
</script>

<template>
  <section aria-label="Import progress">
    <div
      role="progressbar"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="percentage"
    >
      {{ percentage }}%
    </div>
    <p>{{ processedRows }} / {{ totalRows }} rows</p>
    <p>{{ successfulRows }} successful</p>
    <p>{{ failedRows }} failed</p>
  </section>
</template>
