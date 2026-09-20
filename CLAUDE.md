# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

"goods" ist eine Lagerverwaltung (Warehouse Management) als Laravel-Monolith mit Inertia.js v3 + Vue 3 SPA-Frontend. Die gesamte Domäne, UI-Texte, Flash-Messages und Code-Kommentare sind auf Deutsch — neue Strings und Kommentare ebenfalls auf Deutsch schreiben.

## Befehle

```bash
composer run dev          # Alles parallel: artisan serve + queue:listen + pail (Logs) + vite
npm run dev               # nur Vite Dev-Server
npm run build             # Production-Build (bei "Unable to locate file in Vite manifest")

php artisan test --compact                                   # gesamte Suite
php artisan test --compact tests/Feature/AuthenticationTest.php
php artisan test --compact --filter=testName                 # einzelner Test

vendor/bin/pint --dirty --format agent   # Pflicht nach jeder PHP-Änderung
```

Es gibt kein JS-Linting/Formatting-Setup — Vue-Dateien am Stil der Nachbardateien orientieren.

## Datenmodell

Die Lagerstruktur ist eine strikte 4-stufige Kette, jede Ebene `belongsTo` der darüberliegenden mit `onDelete('cascade')`:

```
Warehouse → Rack → Shelf → StorageLocation
```

Quer dazu:

- `Article` — Stammdaten (`sku` und `barcode` unique, `minimum_stock` für Unterbestand-Warnungen).
- `Stock` — Pivot zwischen `Article` und `StorageLocation` mit `quantity`; unique auf `(article_id, storage_location_id)`. Kein Soft-Delete.
- `StockMovement` — unveränderliches Bewegungsjournal (`type`: `in`/`out`/`transfer`/`correction`, `from_storage_location_id`/`to_storage_location_id`, `user_id`). Kein Soft-Delete.

Wichtig: Der Bestand wird **redundant** geführt — `stocks.quantity` ist der aktuelle Stand, `stock_movements` die Historie. Jede Bestandsänderung muss beides schreiben, in einer `DB::transaction()` (Vorbild: `StockMovementController::update()`). `Article::getTotalQuantityAttribute()` ist ein `$appends`-Accessor, der pro Zugriff eine eigene Query absetzt — bei Listen vorher `withSum('stocks', 'quantity')` nutzen.

Das gesamte Schema liegt in einer einzigen Migration: `database/migrations/2024_12_08_145642_create_datastructure.php`.

## Controller-Konventionen

Alle Management-Controller (`WarehouseManagementController`, `RackManagementController`, `ShelfManagementController`, `StorageLocationManagementController`, `ArticleManagementController`) folgen demselben Muster:

- `index`/`create`/`edit` geben `Inertia::render('<Ordner>/<Komponente>', [...])` zurück.
- Schreibende Actions validieren inline per `$request->validate([...])` (keine Form Requests) und antworten mit `redirect()->route(...)->with('message', '<deutscher Text>')`.
- Soft-Delete-Workflow: `destroy` (soft), `trashed` (Papierkorb-Seite), `restore`, `forceDelete` — für Warehouse, Rack, Shelf, StorageLocation und Article jeweils vorhanden.
- Es gibt keine Policies/Gates; Autorisierung erfolgt allein über die Route-Middleware.

`StorageLocationManagementController::generateQrCode()` rendert per `endroid/qr-code` ein PNG mit der reinen Lagerplatz-ID als Inhalt und liefert es als Download aus. Gegenstück im Frontend ist `Components/QrScanner.vue` (html5-qrcode), genutzt in `Pages/StockMovement/Index.vue`.

## Routing

`routes/web.php` besteht aus mehreren Gruppen, die alle dieselbe Middleware-Kette verwenden:

```php
['auth:sanctum', config('jetstream.auth_session'), 'verified']
```

Öffentlich sind nur `/` (Welcome), `/impressum` und `/privacy`. `routes/api.php` enthält nur den Sanctum-`/user`-Endpunkt; die "API" für das Frontend liegt unter `/stock/api/*` in der Web-Gruppe und antwortet mit `response()->json()`.

Zwei bekannte Defekte in `routes/web.php`, die beim Arbeiten dort auffallen: `Route::get('/articles/trashed', ...)` steht **hinter** `Route::get('/articles/{article}', ...)` und ist dadurch nicht erreichbar; und die Route `storage-locations.get` hat einen Tippfehler im Pfad (`/stouri: rage-locations/...`). Statische Segmente grundsätzlich vor parametrisierte Routen setzen.

Im Frontend werden URLs über Ziggy erzeugt (`route('warehouses.index')`, global via `ZiggyVue` in `resources/js/app.js`).

## Frontend-Struktur

- `resources/js/Layouts/AppLayout.vue` — eingeloggter Bereich; `WebLayout.vue` — öffentliche Seiten (Welcome, Impressum, PrivacyPolicy).
- `resources/js/Pages/**` — Inertia-Seiten, aufgelöst über `import.meta.glob`. Ordnerstruktur spiegelt die Domäne (`Warehouses/`, `Articles/`, `StockMovement/`, …).
- Upsert-Pattern: Anlegen und Bearbeiten teilen sich eine Komponente (`UpsertWarehouse.vue`, `UpsertRack.vue`, `UpsertShelf.vue`, `UpsertStorageLocation.vue`, `UpsertArticle.vue`) — Edit-Modus wird am übergebenen Model-Prop erkannt.
- `Pages/Warehouses/Index.vue` ist die zentrale Verwaltungsseite und bekommt Warehouses, Racks, Shelves und Locations in **einem** Render geliefert; die Tabellen liegen in `Pages/Warehouses/Components/`.
- Charts: ApexCharts, global registriert in `resources/js/app.js`, verwendet in `Pages/Articles/Show.vue`.
  Wichtig: Der Import läuft über `vue3-apexcharts/core`, **nicht** über `vue3-apexcharts` — der
  Default-Einstieg liefert eine fest einkompilierte Kopie von ApexCharts 5.10.0 mit und ignoriert
  die installierte Version. Da `apexcharts/core` ohne Chart-Typen kommt, sind die benötigten Typen
  per Seiteneffekt zu importieren (`import "apexcharts/bar"`); für einen neuen Diagrammtyp ist der
  passende Import zu ergänzen, sonst bricht das Rendern mit `chart type ... is not registered`.
- Jetstream-Standardkomponenten liegen unverändert in `resources/js/Components/` — vor dem Bauen neuer UI-Bausteine dort nachsehen.

## Authentifizierung

Jetstream (Inertia-Stack) + Fortify. Beachten, was in der Config **abgeschaltet** ist:

- `config/fortify.php`: `registration()` und `emailVerification()` sind auskommentiert — es gibt also keine Registrierungs-Route, obwohl `Welcome.vue` `canRegister` auswertet.
- Da `emailVerification` deaktiviert ist, aber alle App-Routen `verified` verlangen, brauchen Benutzer trotzdem ein gesetztes `email_verified_at`; neue Test- und Seed-Benutzer entsprechend anlegen.
- `config/jetstream.php`: nur `profilePhotos()` aktiv — keine Teams, keine API-Tokens, kein Account-Deletion. Mehrere Tests in `tests/Feature/` (ApiToken*, DeleteAccount) laufen deshalb übersprungen bzw. gegen deaktivierte Features.

## Tests

Neben den Jetstream-Standardtests decken `StorageLocationQrCodeTest` den QR-Endpunkt und `InertiaSeitenSmokeTest` alle Inertia-Seiten auf Statuscode und Komponente ab.

Factories gibt es für `User`, `Warehouse`, `Rack`, `Shelf` und `StorageLocation`. Für `Article`, `Stock` und `StockMovement` fehlen sie noch — beim Testen dieser Modelle zuerst anlegen (`php artisan make:factory --no-interaction`).

Tests laufen über `phpunit.xml` fest gegen die MySQL-Datenbank `goods_test`; die Zugangsdaten kommen weiterhin aus `.env`. Die Entwicklungsdatenbank `goods` bleibt dadurch unangetastet — wichtig, weil `RefreshDatabase` die Zieldatenbank leert. Testfälle immer mit `RefreshDatabase` schreiben.

## Hinweis zu den Boost-Guidelines unten

Der folgende Block wird von `php artisan boost:install` generiert und ist teilweise veraltet. Tatsächlich installiert sind: **Laravel 13.32** (nicht 11), **PHPUnit 13** (nicht 11), **Jetstream 5.5 + Fortify 1.39**, PHP 8.5, **Inertia 3** (`inertiajs/inertia-laravel` 3.3 und `@inertiajs/vue3` 3.7 — die Hinweise zu Inertia v1/v2 weiter unten sind überholt), **Tailwind 4** (CSS-first konfiguriert in `resources/css/app.css`, es gibt keine `tailwind.config.js` mehr), **Vite 8** (Rolldown-basiert), Vue 3. Bei Widersprüchen gelten `composer.json`/`composer.lock`. Der MCP-Server `laravel-boost` ist in `.mcp.json` konfiguriert, war zuletzt aber nicht erreichbar — dann auf `php artisan`-Befehle direkt ausweichen.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v11
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA_VUE) - v1
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `inertia-vue-development` — Develops Inertia.js v1 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using Link or router; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/v11 rules ===

# Laravel 11

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Laravel 11 brought a new streamlined file structure which this project now uses.

## Laravel 11 Structure

- In Laravel 11, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- No app\Console\Kernel.php - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Commands auto-register - files in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11:
    - `php artisan make:enum`
    - `php artisan make:class`
    - `php artisan make:interface`

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
