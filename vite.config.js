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
    build: {
        outDir: 'client/dist',
        emptyOutDir: false,
        rollupOptions: {
            input: resolve(__dirname, 'client/src/styles/asset-icons.scss'),
            output: {
                assetFileNames: 'styles/[name][extname]',
            },
        },
        cssMinify: true,
    },
});
