<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
  headers: string[];
  destinations: string[];
  previewRows?: Array<Record<string, unknown>>;
}>();

const emit = defineEmits<{ confirm: [mapping: Record<string, string>] }>();
const mapping = ref<Record<string, string>>({});
const step = ref(props.previewRows && props.previewRows.length > 0 ? 'preview' : 'mapping');

function confirm(): void {
  const selected: Record<string, string> = {};

  for (const header of props.headers) {
    const destination = mapping.value[header];
    if (destination) selected[header] = destination;
  }

  emit('confirm', selected);
}
</script>

<template>
  <section v-if="step === 'preview'" aria-label="Import preview">
    <p>Preview {{ previewRows?.length ?? 0 }} sample rows.</p>
    <table>
      <thead>
        <tr><th v-for="header in headers" :key="header" scope="col">{{ header }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in previewRows" :key="index">
          <td v-for="header in headers" :key="header">{{ row[header] ?? '' }}</td>
        </tr>
      </tbody>
    </table>
    <button type="button" @click="step = 'mapping'">Continue to mapping</button>
  </section>
  <form v-else aria-label="Import mapping" @submit.prevent="confirm">
    <label v-for="header in headers" :key="header">
      {{ header }}
      <select v-model="mapping[header]">
        <option value="">Skip</option>
        <option v-for="destination in destinations" :key="destination" :value="destination">{{ destination }}</option>
      </select>
    </label>
    <button type="submit">Confirm mapping</button>
  </form>
</template>
