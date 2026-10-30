import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/digital.js',
                // Telegram Mini App (dedicated mobile shell, /mini-app).
                'resources/css/mini-app.css',
                'resources/css/digital.css',
                'resources/css/product-details.css',
                'resources/js/mini-app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Sora', { weights: [400, 500, 600] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
