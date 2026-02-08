import { defineConfig } from 'vite';
import { resolve } from 'path';
import { copyFileSync, mkdirSync } from 'fs';

// Copy vanilla JS to dist after build
const copyJsPlugin = {
    name: 'copy-js',
    closeBundle() {
        mkdirSync('client/dist/js', { recursive: true });
        copyFileSync('client/src/js/asset-icons.js', 'client/dist/js/asset-icons.js');
    },
};

export default defineConfig({
    plugins: [copyJsPlugin],
    base: './',  // Use relative paths in CSS
    build: {
        outDir: 'client/dist',
        emptyOutDir: false,
        // Don't inline SVGs - keep them as external files for caching
        assetsInlineLimit: 0,
        rollupOptions: {
            input: resolve(__dirname, 'client/src/styles/asset-icons.scss'),
            output: {
                // Keep original filenames for SVGs (no hash) for better caching
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith('.svg')) {
                        return 'icons/[name][extname]';
                    }
                    return 'styles/[name][extname]';
                },
            },
        },
        cssMinify: true,
    },
});
