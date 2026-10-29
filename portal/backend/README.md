# Harbor Safe backend

Laravel 13 on PHP 8.4.1 or newer. Two jobs in one app:

- the **public API** (`/api/public/*`) behind the website's contact forms,
  Events & News and civilian assessment, through restricted database users;
- the **staff panel** (Filament 5, served from the site root, sign-in at
  `/login`), where every staff and officer screen lives.

Full setup is in the repo's [README](../../README.md) (§2) and, for Windows,
[PORTAL_SETUP.md](../../PORTAL_SETUP.md). In short:

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:all          # creates the databases, then migrates
npm install && npm run build     # the staff panel's theme
php artisan serve
php artisan test                 # should pass in full; any failure is real
```

- **Always migrate with `--database=Portal`**, whatever a migration targets.
- **Run `npm run build` again** after changing the panel's theme or Blade views.
  Node is needed only to build; the running app never calls it (PDFs are
  printed in PHP by dompdf).
- **Production must be https**, or the officer wizard can't keep an unfinished
  assessment through the idle logout.

Architecture, access rules and conventions: [CLAUDE.md](../../CLAUDE.md),
[Filament_CMS_Design.md](../../Filament_CMS_Design.md) and
[Schema_Reference.md](../../Schema_Reference.md).
