<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
  headers: string[];
  destinations: string[];
  previewRows?: Array<Record<string, unknown>>;
  profiles?: Array<{ key: string; label: string }>;
  selectedProfile?: string;
  mappingProposal?: Record<string, string>;
  templates?: Array<{ id: string; name: string; mapping: Record<string, string> }>;
  selectedTemplateId?: string;
}>();

const emit = defineEmits<{
  upload: [file: File];
  confirm: [mapping: Record<string, string>];
  'profile-change': [profileKey: string];
  'template-change': [templateId: string];
  'save-template': [name: string, mapping: Record<string, string>];
  'delete-template': [templateId: string];
}>();
const mapping = ref<Record<string, string>>({});
const templateName = ref('');
const templateError = ref<string | null>(null);
const step = ref(props.previewRows && props.previewRows.length > 0 ? 'preview' : 'mapping');

watch(() => props.previewRows, (previewRows) => {
  if (previewRows && previewRows.length > 0) step.value = 'preview';
});
watch(() => props.mappingProposal, (proposal) => { mapping.value = { ...(proposal ?? {}) }; }, { immediate: true });

function confirm(): void {
  const selected: Record<string, string> = {};

  for (const header of props.headers) {
    const destination = mapping.value[header];
    if (destination) selected[header] = destination;
  }

  emit('confirm', selected);
}

function selectFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (file) emit('upload', file);
}

function selectProfile(event: Event): void {
  emit('profile-change', (event.target as HTMLSelectElement).value);
}

function selectTemplate(event: Event): void {
  const templateId = (event.target as HTMLSelectElement).value;
  const template = props.templates?.find((candidate) => candidate.id === templateId);
  const entries = Object.entries(template?.mapping ?? {});
  const matchingEntries = entries.filter(([header]) => props.headers.includes(header));
  mapping.value = Object.fromEntries(matchingEntries);
  templateError.value = entries.length === matchingEntries.length ? null : 'Some template columns are not present in this file and were ignored.';
  emit('template-change', templateId);
}

function saveTemplate(): void {
  const name = templateName.value.trim();
  if (name === '') return;
  emit('save-template', name, { ...mapping.value });
  templateName.value = '';
}
</script>

<template>
  <label v-if="profiles">
    Import profile
    <select aria-label="Import profile" :value="selectedProfile" @change="selectProfile">
      <option value="">Select a profile</option>
      <option v-for="profile in profiles" :key="profile.key" :value="profile.key">{{ profile.label }}</option>
    </select>
  </label>
  <section v-if="headers.length === 0" aria-label="Import file selection">
    <label>
      Select CSV or XLSX file
      <input type="file" accept=".csv,.xlsx" @change="selectFile">
    </label>
  </section>
  <section v-else-if="step === 'preview'" aria-label="Import preview">
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
    <label v-if="templates">
      Mapping template
      <select aria-label="Mapping template" :value="selectedTemplateId" @change="selectTemplate">
        <option value="">No template</option>
        <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
      </select>
    </label>
    <p v-if="templateError" role="alert">{{ templateError }}</p>
    <label v-for="header in headers" :key="header">
      {{ header }}
      <select v-model="mapping[header]">
        <option value="">Skip</option>
        <option v-for="destination in destinations" :key="destination" :value="destination">{{ destination }}</option>
      </select>
    </label>
    <div v-if="templates" class="template-actions">
      <label>
        Save current mapping as
        <input v-model="templateName" aria-label="Template name" type="text" maxlength="100">
      </label>
      <button type="button" :disabled="templateName.trim() === ''" @click="saveTemplate">Save template</button>
      <button v-if="selectedTemplateId" type="button" @click="emit('delete-template', selectedTemplateId)">Delete template</button>
    </div>
    <button type="submit">Confirm mapping</button>
  </form>
</template>
