// Development-only build script. Bundles the React/TypeScript editor and wraps
// it as the AMD module mod_vimipad/editor_lazy in amd/build/. Emitting it as an
// AMD module (rather than injecting a <script> tag at runtime) keeps the load
// inside Moodle's JS tracking, which Behat's wait_for_pending_js relies on.
// There is deliberately NO matching amd/src file: React cannot be built through
// Moodle's Grunt/Babel pipeline, so this prebuilt artefact ships as-is. The
// production server needs no Node/npm.
import {build} from 'esbuild';
import {readFileSync, writeFileSync} from 'fs';

// esbuild has no native AMD format, so build an IIFE that assigns to a global,
// then wrap that in a named define() ourselves.
const result = await build({
    entryPoints: ['js/src/mount.tsx'],
    bundle: true,
    format: 'iife',
    globalName: '__vimipadEditor',
    target: ['es2018'],
    minify: true,
    sourcemap: false,
    write: false,
    jsx: 'automatic',
    define: {'process.env.NODE_ENV': '"production"'},
    logLevel: 'info',
});

const iife = result.outputFiles[0].text;
// The IIFE assigns the module's default export to window.__vimipadEditor.
// Wrap it so it registers as an AMD module returning that export.
const amd = `define("mod_vimipad/editor_lazy", [], function() {\n`
    + `${iife}\n`
    + `return __vimipadEditor.default || __vimipadEditor;\n`
    + `});\n`;

const outfile = 'amd/build/editor_lazy.min.js';
writeFileSync(outfile, amd);
console.log('Built amd/build/editor_lazy.min.js (AMD module mod_vimipad/editor_lazy)');
