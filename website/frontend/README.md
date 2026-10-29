# Harbor Safe website

The public website: home, about, events and news, resources, get support, the
contact forms, and the civilian lethality assessment (`src/app/assessment/`,
linked from Get Support). Next.js 16 (App Router), exported fully static.

Setup, the backend it talks to and the gotchas are in the repo's
[README](../../README.md) (§3 and §4). In short:

```bash
npm install
npm run dev      # http://localhost:3000
npm run build    # static export to out/
npm run lint     # eslint, clean as of 2026-10-25
```

- **No server at runtime.** Everything the site needs from the backend (the
  contact forms, Events & News and the civilian assessment) is fetched from the
  visitor's browser through the narrow `/api/public/*` routes. The site never
  touches a database.
- **`NEXT_PUBLIC_API_URL` is baked in at build time.** It defaults to
  `http://127.0.0.1:8000`; set it (in `.env.local`, or on the build machine)
  before `npm run build` for anywhere else.
- **Keep the `turbopack.root` pin in `next.config.mjs`.** Without it a
  `npm install` at the repo root can break `npm run dev`; see
  [CLAUDE.md](../../CLAUDE.md).
- **The 11 assessment questions** in `src/app/lib/lethality-questions.js` must
  match the officer wizard's; a backend test fails if they differ.
- **Tests:** Playwright, run from the repo root (`npx playwright test`).
