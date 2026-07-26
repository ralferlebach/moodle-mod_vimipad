// Development-only build script. Bundles the React/TypeScript editor into a
// single self-contained IIFE placed in js/build/. The production Moodle server
// ships this prebuilt asset and requires no Node/npm.
import {build} from 'esbuild';

await build({
    entryPoints: ['js/src/mount.tsx'],
    bundle: true,
    format: 'iife',
    target: ['es2018'],
    minify: true,
    sourcemap: false,
    outfile: 'js/build/vimipad-editor.js',
    jsx: 'automatic',
    define: {'process.env.NODE_ENV': '"production"'},
    logLevel: 'info',
});

console.log('Built js/build/vimipad-editor.js');
