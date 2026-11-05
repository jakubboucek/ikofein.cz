import { defineConfig } from 'vite';

export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'www/assets',
        emptyOutDir: false,
        minify: true,
        lib: {
            entry: 'assets/monitoring.js',
            formats: ['iife'],
            name: 'KofeinMonitoring',
            fileName: () => 'monitoring.js',
        },
    },
});
