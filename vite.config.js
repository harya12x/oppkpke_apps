import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Paksa IPv4 127.0.0.1 — di Windows Vite bisa bind ke [::1] (IPv6), dan
        // CSP tidak mendukung alamat IPv6 literal ("invalid source, ignored"),
        // sehingga aset dev (@vite) terblokir. Dengan host tetap 127.0.0.1, file
        // `public/hot` menulis http://127.0.0.1:5173 yang valid untuk CSP.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
