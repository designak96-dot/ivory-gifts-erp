import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // The layouts reference build/assets/app.css and build/assets/app.js
        // as FIXED paths (asset('build/assets/app.css'), not the @vite
        // directive with content-hash manifest lookups) — several other
        // feature-specific CSS/JS files (dashboard.css, delivery.css,
        // delivery.js, order-entry.css, sync.css) live in the same output
        // directory as plain static files outside the Vite pipeline
        // entirely. Both of these mean: never hash the Vite output
        // filenames, and never let Vite clear the output directory (its
        // default `emptyOutDir: true` would silently delete those sibling
        // static files on every build — confirmed the hard way).
        emptyOutDir: false,
        rollupOptions: {
            output: {
                entryFileNames: (chunk) => chunk.name === 'app' ? 'assets/app.js' : 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: (asset) => asset.names?.[0] === 'app.css' ? 'assets/app.css' : 'assets/[name].[ext]',
            },
        },
    },
});
