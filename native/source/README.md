# Rebuild `native/evaluation-ui.js`

The plugin repository and release ZIP include every first-party source file used by the Moodle native evaluation interface. `native-entry.tsx` and `moodle-http-client.ts` are in the plugin root; the shared interface and API types are vendored under `native/source/packages/` so this build does not depend on the Quessor monorepo.

Requirements: Node.js 20 or later and npm.

From the plugin root, run:

```sh
cd native/source
npm ci
npm run build
```

Webpack writes `native/evaluation-ui.js` and copies the source stylesheet to `native/evaluation-ui.css`. Run `node --check native/evaluation-ui.js` from the plugin root to verify the generated JavaScript syntax. The lockfile pins the build toolchain and runtime library versions; `thirdpartylibs.xml` lists the bundled libraries and their licences.

To update the vendored first-party sources, copy the matching files from `packages/evaluation-ui/src/` and `packages/shared/src/` in the Quessor monorepo into this directory, then rebuild and commit the generated bundle and stylesheet with the sources.
