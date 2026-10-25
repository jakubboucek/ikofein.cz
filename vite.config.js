import { defineConfig } from 'vite';
import nette from '@nette/vite-plugin';

export default defineConfig({
    plugins: [
        nette({
            entry: ['front.js', 'admin.js'],
            appUrl: 'http://localhost:8080',
            refresh: ['app/**/*.latte'],
        }),
    ],
    publicDir: false,
    build: {
        emptyOutDir: true,
    },
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        cors: { origin: 'http://localhost:8080' },
    },
});
