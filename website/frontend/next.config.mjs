import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
PIN TURBOPACK'S PROJECT ROOT TO THIS DIRECTORY. Do not remove this.

The repo has three package-lock.json files - one here, one in the other
frontend, one at the repo root - and Turbopack picks the outermost as its
project root when it finds several. That makes Node resolution for this app's
build tooling able to reach the REPO ROOT's node_modules, which is a different
dependency set entirely.

Concretely, what it broke: the repo root's package.json still declares `next`
as a dependency (a leftover from before the repo was split - its dev/build
scripts do not work either). `next` brings postcss 8.4.31 with it. So running
`npm install` at the repo root - which README.md and CLAUDE.md both instruct,
because Playwright and the shared eslint config live there - plants an old
postcss above this app, Tailwind v4's @tailwindcss/postcss resolves that
instead of the 8.5.x here, and `npm run dev` dies with

    ./src/app/globals.css
    Error evaluating Node.js code
    Error: Module [turbopack-node]/transforms/postcss.ts ... was instantiated
    because it was required from ... but the module factory is not available.

which blames a stale browser cache and is nothing of the kind. `npm run build`
is unaffected, so the two disagree, which makes it harder still to place.

Pinning the root fixes the resolution and silences Next's "inferred your
workspace root" warning at the same time.
*/
const projectRoot = dirname(fileURLToPath(import.meta.url));

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',
  trailingSlash: true,
  images: {
      unoptimized: true,
  },
  turbopack: {
    root: projectRoot,
  },
};
export default nextConfig;