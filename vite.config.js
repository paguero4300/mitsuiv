import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament.css',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
    ],
    server: {
        host: 'mitsui.test', // Tu dominio local de Herd
        hmr: {
            host: 'mitsui.test'
        },
    },
});
