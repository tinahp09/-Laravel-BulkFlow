<script setup lang="ts">
import type { ImportRun } from '../client';

defineProps<{ runs: ImportRun[] }>();
</script>

<template>
  <section aria-label="Import dashboard">
    <p v-if="runs.length === 0">No imports yet.</p>
    <ul v-else>
      <li v-for="run in runs" :key="run.id">
        <strong>{{ run.state }}</strong>
        <span> · {{ run.processedRows }} / {{ run.totalRows }}</span>
      </li>
    </ul>
  </section>
</template>
