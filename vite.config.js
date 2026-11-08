import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/home-motion.js',
                'resources/js/site-background.js',
                'resources/js/cobro.js',
                'resources/js/caja.js',
                'resources/js/ventas.js',
                'resources/js/qr-salida.js',
                'resources/js/tarifas-ingreso.js',
                'resources/js/ingreso-abonado.js',
                'resources/js/mapa-admin.js',
                ...['public', 'admin', 'auth'].flatMap(area => [
                    `resources/css/${area}/base.css`,
                    `resources/css/${area}/theme.css`,
                ]),
                'resources/css/shared/atmosphere.css',
                'resources/css/pages/placas.css',
                'resources/css/pages/ticket.css',
                'resources/css/pages/cobro.css',
                'resources/css/pages/caja.css',
                'resources/css/pages/ingreso-presencial.css',
                'resources/css/pages/operacion-espacio.css',
                'resources/css/pages/abonados.css',
                'resources/css/pages/ticket-entrada.css',
                'resources/css/pages/qr-salida.css',
                'resources/css/pages/impresoras.css',
            ],
            refresh: true,
        }),
    ],
});
