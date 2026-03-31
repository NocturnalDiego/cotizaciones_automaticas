# Project Guidelines

## Special Rules
- Icon system standard: use **Tabler Icons** as the default icon set for the project (`free`, `complete`, and consistent for UI actions/states).
- In Blade views, prefer reusable icon components/partials so icon usage stays consistent and easy to replace globally.
- Never use emojis in UI copy, code comments, commit messages, generated docs, or assistant responses for this project.
- If an icon is needed, always use an icon from the established icon set instead of emoji characters.
- App language quality rule: all user-facing text in the app must use correct Spanish (including accents and the letter ñ), with proper spelling and grammar.
- English is allowed only in code-level identifiers and technical artifacts (variables, classes, methods, routes, config keys, commit tooling), not in visible UI copy.


## Build and Test
- Use `composer run dev` for local development (server, queue listener, logs, and Vite).
- Use `composer run test` to run the test suite.
- Use `npm run build` for production assets.
- If dependencies are missing in a fresh clone, run `composer run setup`.

## Architecture
- This is a Laravel 12 app with Breeze auth scaffolding.
- Authorization uses Spatie Laravel Permission (roles and permissions).
- HTTP routes are defined in `routes/web.php` and `routes/auth.php`.
- Controllers are in `app/Http/Controllers`; request validation belongs in `app/Http/Requests`.
- Domain models are in `app/Models` (currently centered around `User`).
- UI uses Blade templates in `resources/views`.
- Frontend assets are built with Vite from `resources/js/app.js` and `resources/css/app.css`.

## Conventions
- Treat Laravel 12 + Breeze as the default baseline for auth, routing, and Blade flows unless a task explicitly requests a different stack.
- Use Spatie permission APIs (`assignRole`, `hasRole`, `can`) for authorization checks and role assignments.
- Keep controllers thin; put validation in Form Requests.
- Follow existing Laravel/Breeze patterns before introducing new abstractions.
- Prefer Pest style tests in `tests/Feature` and `tests/Unit`.
- For app behavior changes, add or update tests with the same PR.

## Blade Structure and Clean Usage
- For each new CRUD module, organize views under `resources/views/{module}/` using this baseline: `view.blade.php`, `create.blade.php`, `edit.blade.php`.
- If a module needs a listing screen, keep it explicit as `index.blade.php`; detail page remains `view.blade.php`.
- Extract repeated form fields into `resources/views/{module}/partials/form.blade.php` and reuse in `create`/`edit`.
- Keep Blade focused on presentation; do not place business logic, data queries, or complex transformations in views.
- Prefer Blade components (`x-*`) for repeated UI blocks (inputs, buttons, badges, cards, icons).
- Use named routes and route model binding; avoid hardcoded URLs in templates.
- Keep conditional rendering simple (`@if`, `@can`, `@auth`) and move heavy branching to controllers/view models.
- Prefer localization helpers (`__()`) for user-facing text to keep templates translation-ready.
- Use slot-based layouts and section composition to avoid duplicated page structure.
- For authorization in templates, use policies/gates or Spatie permissions, not custom inline permission logic.
- Avoid inline CSS/JS in Blade files; use Vite assets and reusable classes/components.

## CSS Guidelines (Keep It Clean)
- Keep styles layered and predictable: design tokens in `:root` (colors, spacing, radii, shadows), base/utility classes in `resources/css/app.css`, and component-specific styles in small scoped blocks.
- Prefer reusable semantic class names for custom CSS (`.quote-card`, `.status-badge`) and avoid one-off selectors tied to page structure.
- Avoid `!important` unless there is no viable alternative; fix specificity and source order first.
- Keep selectors shallow (max 3 levels) and avoid styling by IDs.
- Use a naming convention consistently for custom classes (BEM-lite or component-prefix style).
- Group CSS by component sections with short headers and keep each section focused.
- Reuse existing spacing and color tokens instead of hardcoded values.
- For responsive behavior, use mobile-first breakpoints and keep them consistent with the existing Tailwind/Vite setup.
- Before adding new styles, check if the result can be achieved with existing Breeze/Tailwind utilities.

## SOLID in Laravel
- **Single Responsibility Principle (SRP):** controllers orchestrate, Form Requests validate, services/actions contain business rules, and models handle persistence concerns.
- **Open/Closed Principle (OCP):** prefer extending behavior via new classes (strategies, policies, actions) instead of editing stable core flows.
- **Liskov Substitution Principle (LSP):** child classes must preserve parent behavior contracts; avoid surprising side effects in overrides.
- **Interface Segregation Principle (ISP):** use small, focused interfaces for domain behaviors; avoid large "god" contracts.
- **Dependency Inversion Principle (DIP):** depend on interfaces/contracts and bind implementations in service providers.
- For non-trivial business flows, create application services in `app/Services` or action classes in `app/Actions` and inject them into controllers.
- Keep authorization decisions in Policies/Gates or Spatie permissions, not inline conditionals spread across controllers/views.
- Prefer constructor injection over facades/static access for core domain logic to improve testability.
- When introducing abstractions, add focused Pest tests to lock expected behavior.

## Environment Notes
- Local development uses MySQL from `.env`.
- Sessions, cache, and queues use database drivers in `.env`; ensure migrations are up to date.
- If you see missing table errors for sessions, cache, or jobs, run `php artisan migrate`.
- Tests run on SQLite in-memory as configured in `phpunit.xml`.

## Links
- See `composer.json` scripts for the source of truth on dev/test workflows.
- See `phpunit.xml` for test environment behavior.
- See `vite.config.js` and `package.json` for frontend build setup.
- See `config/permission.php` and `database/migrations/*create_permission_tables.php` for role/permission setup.