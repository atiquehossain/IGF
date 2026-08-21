import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/scss/app.scss', 'resources/js/app.js'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json', '.vue'],
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  ssr: {
    noExternal: ['vuetify', 'bootstrap-vue-next'],
  },
  css: {
    preprocessorOptions: {
      scss: {
        quietDeps: true,
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined;
          if (id.includes('vuetify') || id.includes('@mdi')) return 'ui-vendor';
          if (id.includes('@inertiajs') || id.includes('/vue/')) return 'vue-vendor';
          if (id.includes('bootstrap') || id.includes('@popperjs')) return 'bootstrap-vendor';
          if (id.includes('@fullcalendar')) return 'calendar-vendor';
          if (id.includes('pdfjs-dist')) return 'pdf-vendor';
          return 'vendor';
        },
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/test.setup.js'],
    include: ['resources/js/__tests__/**/*.spec.js'],
    css: true,
    alias: {
      '@inertiajs/vue3': fileURLToPath(
        new URL('./resources/js/__mocks__/inertia-vue3.js', import.meta.url),
      ),
    },
  },
});
