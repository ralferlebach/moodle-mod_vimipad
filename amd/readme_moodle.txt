Third-party libraries bundled in the prebuilt editor AMD module
===============================================================

The editor is shipped as a prebuilt AMD module, amd/build/editor_lazy.min.js,
which bundles React and its runtime dependencies for Moodle versions without a
native React runtime. This file documents exactly what is bundled and how the
artefact is reproduced (see also thirdpartylibs.xml in the plugin root).

Bundled components
------------------

* react
  - npm package:   react
  - version:       18.3.1
  - upstream:      https://github.com/facebook/react
  - license:       MIT (https://github.com/facebook/react/blob/main/LICENSE)

* react-dom
  - npm package:   react-dom
  - version:       18.3.1
  - upstream:      https://github.com/facebook/react
  - license:       MIT

* scheduler
  - npm package:   scheduler (a react-dom runtime dependency)
  - version:       0.23.2
  - upstream:      https://github.com/facebook/react
  - license:       MIT

Local modifications
-------------------

None. The libraries are bundled unmodified from their published npm packages.
Only the plugin's own source under js/src is authored here; the third-party code
is included verbatim by the bundler. The generated bundle carries a visible
license banner listing every third-party component it contains.

How the bundle is produced (reproducible)
-----------------------------------------

Prerequisites: Node.js and the pinned dependencies from package-lock.json.

    npm ci          # install the exact, locked dependency tree
    npm run build   # runs build.mjs (esbuild) -> amd/build/editor_lazy.min.js

npm ci (not npm install) is required so the versions above are reproduced
exactly from package-lock.json. The build is deterministic: re-running it
produces a byte-identical bundle. The init.min.js and revision.min.js modules
are built by Moodle's own Grunt/rollup pipeline (npx grunt amd), not by esbuild;
editor_lazy.min.js is the esbuild artefact and is not reprocessed by Grunt.

node_modules is a development dependency only and is never distributed with the
plugin.
