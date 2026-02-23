# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony bundle providing a full CMS admin panel with CRUD operations, form handling, entity management, and admin UI components. Built around trait-based operation composition — controllers opt into list, create, update, delete, copy, move, toggle, export, and meta operations by using corresponding `Supports*Operation` traits.

**Package:** `chamber-orchestra/cms-bundle`
**Requirements:** PHP ^8.5, Symfony 8.0 components
**Namespace:** `ChamberOrchestra\CmsBundle` (PSR-4 from `src/`)
**Bundle class:** `ChamberOrchestraCmsBundle`
**Main branch:** `main`

## Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test                           # or: ./bin/phpunit

# Run specific test file
./bin/phpunit tests/Unit/SomeTest.php

# Run tests in specific directory
./bin/phpunit tests/Unit/

# Run single test method
./bin/phpunit --filter testMethodName

# Check code style (dry-run)
vendor/bin/php-cs-fixer fix --dry-run

# Auto-fix code style
vendor/bin/php-cs-fixer fix

# Quick syntax lint
php -l path/to/File.php

# Check Twig code style (dry-run)
composer twig-check                     # or: vendor/bin/twig-cs-fixer lint

# Auto-fix Twig code style
composer twig-fix                       # or: vendor/bin/twig-cs-fixer fix
```

## Architecture

### CRUD Controller System

1. `AbstractCrudController` extends `AbstractController` and implements `CrudControllerInterface`
2. Controllers compose operations via traits: `SupportsListOperation`, `SupportsCreateOperation`, `SupportsUpdateOperation`, `SupportsDeleteOperation`, `SupportsCopyOperation`, `SupportsMoveOperation`, `SupportsToggleOperation`, `SupportsExportOperation`, `SupportsUpdateMetaOperation`
3. Shortcut traits: `SupportsCudOperation` (create+update+delete), `SupportsLcudOperation` (list+create+update+delete)
4. `CrudControllerConfigurator` resolves the `$options` array (via `OptionsResolver`) and wires up dependencies

### Controller Options

The `$options` array passed to `AbstractCrudController::__construct()` drives all behavior. Key options resolved by `CrudControllerConfigurator`:

- `class` *(required)* — FQCN of the managed entity
- `form_class` — Symfony FormType FQCN
- `data_class` — DTO class string or `callable(string $entityClass, array $options): DtoInterface`; string classes are instantiated via `Instantiator` with `entityClass` and `parent` keys
- `route_prefix` — auto-detected from `#[Route]` attribute name or derived as `cms_{snake_entity}` if unset
- `view_prefix` — base path for Twig templates; defaults to `@ChamberOrchestraCms/crud/`
- `parent_controller_class` — FQCN of a parent `AbstractCrudController` for hierarchical CRUD (auto-resolves `_parent` relationship via reflection)
- `index.fields` — `['field' => ['format' => ...]]` or `['field']`; callable under `'transform'` key applies row transforms
- `index.order_by_mapping` — fields allowed for column sorting; fields named `image`, `video`, `preview`, or prefixed `!`, or containing `.` without a callable, are excluded automatically
- `index.filter` — FilterType class for search form; `index.filter_mapping` maps form fields to DQL expressions
- `index.query_builder` — QueryBuilder or `Closure(EntityRepository): QueryBuilder` for custom list queries
- `nav`, `breadcrumbs` — `false` disables, callable receives the builder for customisation

Actions (`create`, `update`, `view`, `meta`, `delete`, `copy`, `move`, `toggle`, `export`, `bulk_delete`) each accept `null` to disable, or an array with `route`, `route_params`, `view`, `view_params`, `title` overrides.

### Request Flow

1. Controller action (from `Supports*Operation` trait) receives request and calls the matching `do*()` method on `AbstractCrudController`
2. `CrudProcessor` handles entity lifecycle: `create()`, `update()`, `delete()`, `bulkDelete()`, `toggle()`, `copy()`, `move()`
3. Each mutating operation wraps the DB work in an explicit transaction
4. Events dispatched in sequence: `CreateEvent` / `UpdateEvent`, then `SyncEvent`, then `PostSyncEvent`
5. `EntityRepositoryWrapper::filterBy()` applies `FilterHelper`, `SortHelper`, and optional pagination for list queries

### DTO ↔ Entity Sync

`CrudUtils::sync(object $source, object $target)` copies values between any two objects by **property-name intersection** (using `RuntimeReflectionClass`). This is the canonical way DTOs transfer data to/from entities:

- Simple scalar properties: copied directly when different
- `Collection` / association properties: items added/removed to reconcile source into target; `DtoCollection` triggers deep (recursive) sync of nested DTOs into nested entities
- `DtoInterface` typed properties: recursively synced
- `MetaInterface` targets: auto-populates empty `title`, `metaTitle`, `metaDescription`, `metaImage` from sibling properties

### Instantiator

`Instantiator::instantiate(string $class, array|object $data)` constructs objects by matching constructor parameter names against `$data` keys (via `PropertyAccessor`). Falls back to type-matching when a key is absent. Results are cached per class name.

### Key Layers

- **src/Controller/**: Abstract controllers + operation traits; `OriginPathTrait` for redirect-back logic
- **src/Configurator/**: `CrudControllerConfigurator` — OptionsResolver-based wiring of all controller options
- **src/Processor/**: `CrudProcessor` (core CRUD logic + transactions), `SortProcessorTrait` (move up/down for `SortInterface` entities), `Instantiator`, `Reflector`, `RuntimeReflectionClass`
- **src/Form/**: DTOs in `Dto/` (`EntityDto`, `DeleteDto`, `BulkOperationDto`, `DtoCollection`, `AbstractDto`); form types in `Type/` (`EntityType`, `DeleteType`, `BulkOperationForm`, `JsonType`, `YamlType`, `WysiwygType`, `AbstractFilterType`); transformers and normalizers
- **src/EntityRepository/**: `EntityRepositoryWrapper` + `FilterHelper`, `SortHelper`
- **src/Events/**: `CreateEvent`, `UpdateEvent`, `DeleteEvent`, `SyncEvent`, `PostSyncEvent` — all extend `AbstractEvent(ClassMetadata, object)`
- **src/EventSubscriber/**: `InversedCollectionSubscriber`, `OwningCollectionSubscriber`, `SetVersionSubscriber`
- **src/Twig/**: `CmsExtension`/`CmsRuntime`, `CoreExtension`, `EmailExtension`, `PhoneExtension`
- **src/Generator/**: `CsvGenerator` for export operations
- **src/Resources/**: Twig templates (`views/`), SCSS/JS assets (`assets/`), service configs (`config/`)

### Frontend Assets

- SCSS in `src/Resources/assets/scss/` — customized Bootstrap 5.3 source files with CMS-specific overrides in `bootstrap/` subdirectory
- JavaScript: jQuery, TinyMCE 5, Select2, Flatpickr, nestable, Chart.js
- Twig templates in `src/Resources/views/` — base layout, CRUD views, form themes (Bootstrap 5), modals, menus
- Entry points in `src/Resources/assets/entry/` — per-page JS/SCSS bundles

### Twig Templates

- `base.html.twig` — admin layout shell
- `crud/` — index, list, create, update, view, delete (modal + fragment variants), meta
- `form/` — `horizontal.html.twig`, `inline.html.twig`, `layout.html.twig` form themes
- `menu/` — sidebar navigation, action menus
- `modal.html.twig` — reusable modal component

### Service Configuration

- `src/Resources/config/services.yaml` — main service definitions
- `src/Resources/config/dev/services.yaml` — dev-only services

## Testing

- PHPUnit 13.x with `failOnRisky` and `failOnWarning`; tests autoloaded as `Tests\`
- Two test suites: `Unit` (`tests/Unit/`) and `Integrational` (`tests/Integrational/`)
- Use PHPUnit 13 attributes (`#[Test]`)
- CI: GitHub Actions on `main` / `8.0` branches, PHP 8.5, PostgreSQL 17
- The `tests/` directory is currently empty — no existing tests to follow as precedent

### Testing Conventions

- Use music thematics for test fixtures and naming (e.g., entity names like `Composition`, `Instrument`, `Rehearsal`, `Score`; file names like `symphony_no_5.pdf`, `violin_concerto.mp3`, `moonlight_sonata.jpg`; prefixes like `scores`, `recordings`)

## Code Style

- PHP 8.5+ with strict types (`declare(strict_types=1);`)
- PSR-4 autoloading: `ChamberOrchestra\CmsBundle\` → `src/`
- `@PER-CS` + `@Symfony` PHP-CS-Fixer rulesets
- Native function invocations must be backslash-prefixed (e.g., `\array_merge()`, `\sprintf()`, `\count()`)
- No global namespace imports — never use `use function` or `use const`
- Ordered imports (alpha), no unused imports, single quotes, trailing commas in multiline
- 4-space indent
- Typed properties and return types; favor `readonly`
- Constructor injection only; autowiring and autoconfiguration
- Commit style: short, action-oriented with optional bracketed scope — `[fix] ...`, `[8.0] ...`

## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests — then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First**: Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan**: Check in before starting implementation
3. **Track Progress**: Mark items complete as you go
4. **Explain Changes**: High-level summary at each step
5. **Document Results**: Add review section to `tasks/todo.md`
6. **Capture Lessons**: Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact**: Changes should only touch what's necessary. Avoid introducing bugs.

## Dependencies

- Requires PHP 8.5, Symfony 8.0 components (`event-dispatcher`, `form`, `framework-bundle`, `http-foundation`, `http-kernel`, `options-resolver`, `property-access`, `routing`, `string`, `twig-bundle`, `uid`, `yaml`), Doctrine ORM 3.x, Doctrine Collections 2.x
- Chamber Orchestra bundles: `doctrine-extensions-bundle`, `doctrine-sort-bundle`, `file-bundle`, `form-bundle`, `menu-bundle`, `meta`, `metadata-bundle`, `pagination-bundle`
- Dev: PHPUnit 13, `doctrine-bundle`, `symfony/test-pack`
- Suggests: `studio-42/elfinder` for file manager integration
