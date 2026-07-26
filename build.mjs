// Development-only build script. Bundles the React/TypeScript editor and wraps
// it as the AMD module mod_vimipad/editor_lazy. It writes TWO copies:
//
//   amd/build/editor_lazy.min.js  (+ .map)  -> served in production
//   amd/src/editor_lazy.js                  -> served by Moodle in developer
//                                              mode (jsrev = -1, cachejs off)
//
// Why both are needed: in developer mode Moodle's lib/requirejs.php serves the
// minified build only if a ".map" sits next to it; otherwise it rewrites
// /amd/build/xxx.min.js to /amd/src/xxx.js and serves that. Shipping BOTH the
// .map and amd/src/editor_lazy.js covers every load path (with or without the
// .map branch, across Moodle point releases), so RequireJS always finds the
// define and never reports "No define call for mod_vimipad/editor_lazy".
//
// Both files are declared in thirdpartylibs.xml, so Moodle's Grunt/ESLint/phpcs
// leave them untouched (Grunt will not rebuild amd/src/editor_lazy.js). React
// cannot be built through Moodle's Grunt/Babel pipeline; this prebuilt artefact
// ships as-is and the production server needs no Node/npm.
import {build} from 'esbuild';
import {writeFileSync} from 'fs';

// esbuild has no native AMD format, so build an IIFE that assigns to a global,
// then wrap it in a named define() via banner/footer (keeps the sourcemap
// aligned with the wrapped output).
const common = {
    entryPoints: ['js/src/mount.tsx'],
    bundle: true,
    format: 'iife',
    globalName: '__vimipadEditor',
    target: ['es2018'],
    write: false,
    jsx: 'automatic',
    define: {'process.env.NODE_ENV': '"production"'},
    banner: {js: 'define("mod_vimipad/editor_lazy", [], function() {'},
    footer: {js: 'return __vimipadEditor.default || __vimipadEditor;\n});'},
    logLevel: 'info',
};

// Production: minified + sourcemap -> amd/build/.
const prod = await build({
    ...common,
    minify: true,
    sourcemap: true,
    outfile: 'amd/build/editor_lazy.min.js',
});
for (const file of prod.outputFiles) {
    writeFileSync(file.path, file.text);
}

// Developer source: unminified, no sourcemap -> amd/src/. Served raw by Moodle
// in developer mode, so readable stack traces without a map file.
const dev = await build({
    ...common,
    minify: false,
    sourcemap: false,
    outfile: 'amd/src/editor_lazy.js',
});
for (const file of dev.outputFiles) {
    writeFileSync(file.path, file.text);
}

console.log('Built amd/build/editor_lazy.min.js (+ .map) and amd/src/editor_lazy.js');
