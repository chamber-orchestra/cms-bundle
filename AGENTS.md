# Repository Guidelines

## Project Structure & Module Organization
- Full CMS admin bundle under `ChamberOrchestra\CmsBundle`.
- Autoloading is PSR-4 from the package root (no `src/` directory).
- Key directories: `Controller/` (abstract + operation traits), `Configurator/`, `Contracts/`, `DependencyInjection/`, `EntityRepository/` (wrapper + helpers), `Events/`, `EventSubscriber/`, `Exception/`, `Form/` (DTOs, types, transformers), `Generator/`, `Processor/` (CRUD logic, reflection), `PropertyAccessor/`, `Regex/`, `Resources/` (views, assets, config), `Twig/`.
- Tests belong in `tests/` (autoloaded as `Tests\`); tools are in `bin/` (`bin/phpunit`).
- Requirements: PHP 8.5+, Symfony 8.0 components.

## Build, Test, and Development Commands
- Install dependencies: `composer install`.
- Run the suite: `./bin/phpunit` (uses `phpunit.xml.dist`). Add `--filter ClassNameTest` or `--filter testMethodName` to scope.
- `composer test` is an alias for `vendor/bin/phpunit`.
- Quick lint: `php -l path/to/File.php`; keep code PSR-12 even though no fixer is bundled.

## Coding Style & Naming Conventions
- Follow PSR-12: 4-space indent, one class per file, strict types (`declare(strict_types=1);`).
- Use typed properties and return types; favor `readonly` where appropriate.
- Controllers compose behavior via `Supports*Operation` traits (list, create, update, delete, copy, move, toggle, export, meta).
- DTOs live in `Form/Dto/`; implement `DtoInterface` or extend `AbstractDto`/`EntityDto`.
- Form types live in `Form/Type/`; extend Symfony's `AbstractType`.
- Keep constructors light; prefer small, composable services injected via Symfony DI.

## Frontend Assets
- SCSS in `Resources/assets/scss/` — customized Bootstrap 5.3 source with CMS overrides in `bootstrap/` subdirectory.
- JavaScript uses jQuery, TinyMCE 5, Select2, Flatpickr, nestable.
- Twig templates in `Resources/views/` — base layout, CRUD views, form themes (Bootstrap 5), modals, menus.
- Entry points in `Resources/assets/entry/` — per-page JS/SCSS bundles.

## Testing Guidelines
- Use PHPUnit (13.x). Name files `*Test.php` mirroring the class under test.
- Unit tests live in `tests/Unit/` extending `TestCase`.
- Keep tests deterministic; use data providers where appropriate.
- Cover processors, form types, DTOs, entity repository helpers, and event subscribers.

## Commit & Pull Request Guidelines
- Commit messages: short, action-oriented, optionally bracketed scope (e.g., `[fix] handle missing entity`, `[master] bump version`).
- Keep commits focused; avoid unrelated formatting churn.
- Pull requests should include: purpose summary, key changes, test results.
