import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                ...['public', 'admin', 'auth'].flatMap(area => [
                    `resources/css/${area}/base.css`,
                    `resources/css/${area}/theme.css`,
                ]),
                'resources/css/shared/atmosphere.css',
                'resources/css/pages/placas.css',
                'resources/css/pages/ticket.css',
            ],
            refresh: true,
        }),
    ],
});
