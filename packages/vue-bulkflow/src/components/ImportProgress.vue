<script setup lang="ts">
const props = defineProps<{
  processedRows: number;
  totalRows: number;
  successfulRows: number;
  failedRows: number;
}>();

const percentage = props.totalRows === 0 ? 0 : Math.min(100, Math.round((props.processedRows / props.totalRows) * 100));
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
