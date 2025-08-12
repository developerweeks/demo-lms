// eslint-disable-next-line
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => {
  return {
    build: {
      manifest: true,
      rollupOptions: {
        external: ['jQuery', 'Backbone', 'Drupal', 'drupalSettings', 'once'],
        input: 'js/tour.js',
        output: {
          assetFileNames: (assetInfo) => {
            return assetInfo.name;
          },
          entryFileNames: (assetInfo) => {
            return `${assetInfo.name}.js`;
          },
        },
      },
    },
    css: { devSourcemap: true },
    define: {
      'process.env.NODE_ENV':
        mode === 'production' ? '"production"' : '"development"',
    },
  };
});
