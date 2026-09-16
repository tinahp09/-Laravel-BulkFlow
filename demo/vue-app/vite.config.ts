import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@bulkflow/vue': fileURLToPath(new URL('../../packages/vue-bulkflow/src/index.ts', import.meta.url)),
    },
  },
  server: {
    proxy: {
      '/bulkflow': 'http://127.0.0.1:8011',
    },
  },
});
