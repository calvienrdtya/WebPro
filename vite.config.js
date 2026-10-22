import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/store.css',
                'resources/js/store.js',
                'resources/js/forgot-password.js',
                'resources/admin/app.tsx',
            ],
            refresh: true,
            fonts: [
                google('Space Grotesk', {
                    weights: [400, 500, 600, 700],
                }),
                google('Inter', { weights: [400, 500, 600, 700] }),
                google('Manrope', { weights: [400, 500, 600, 700] }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/admin', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
