// Development-only build script. Bundles the React/TypeScript editor and wraps
// it as the AMD module mod_vimipad/editor_lazy in amd/build/. Emitting it as an
// AMD module (rather than injecting a <script> tag at runtime) keeps the load
// inside Moodle's JS tracking, which Behat's wait_for_pending_js relies on.
//
// Architecture: the React/TypeScript source lives in js/src and is bundled by
// esbuild into the distribution artefact amd/build/editor_lazy.min.js (+ .map).
// There is deliberately NO amd/src/editor_lazy.js: amd/src is Moodle's own AMD
// source directory, and Moodle's Grunt (rollup/babel) processes everything it
// finds there. A prebuilt esbuild bundle placed in amd/src would be fed back
// through that pipeline (thirdpartylibs.xml only affects lint ignores, not the
// rollup input set), which is wrong. React cannot be built through Moodle's
// Grunt/Babel pipeline anyway; this prebuilt artefact ships as-is and the
// production server needs no Node/npm.
//
// Developer mode: Moodle's lib/requirejs.php serves the minified build directly
// when a ".map" file sits next to it (only without a map does it fall back to
// amd/src/xxx.js). Because esbuild emits editor_lazy.min.js.map, the module
// loads correctly in both developer and production mode from amd/build.
import {build} from 'esbuild';
import {writeFileSync} from 'fs';

// esbuild has no native AMD format, so build an IIFE that assigns to a global,
// then wrap it in a named define() via banner/footer (keeps the sourcemap
// aligned with the wrapped output).
const result = await build({
    entryPoints: ['js/src/mount.tsx'],
    bundle: true,
    format: 'iife',
    globalName: '__vimipadEditor',
    target: ['es2018'],
    minify: true,
    sourcemap: true,
    write: false,
    outfile: 'amd/build/editor_lazy.min.js',
    jsx: 'automatic',
    define: {'process.env.NODE_ENV': '"production"'},
    banner: {js: 'define("mod_vimipad/editor_lazy", [], function() {'},
    footer: {js: 'return __vimipadEditor.default || __vimipadEditor;\n});'},
    logLevel: 'info',
});

// outputFiles contains both the .js and the .js.map; write each to its path.
for (const file of result.outputFiles) {
    writeFileSync(file.path, file.text);
}
console.log('Built amd/build/editor_lazy.min.js (+ .map)');
