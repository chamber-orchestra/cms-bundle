# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony bundle providing a full CMS admin panel with CRUD operations, form handling, entity management, and admin UI components. Built around trait-based operation composition — controllers opt into list, create, update, delete, copy, move, toggle, export, and meta operations by using corresponding `Supports*Operation` traits.

**Requirements:** PHP ^8.5, Symfony 8.0 components

**Namespace:** `ChamberOrchestra\CmsBundle` (PSR-4 from package root — no `src/` directory)

## Commands

```bash
composer install                        # Install dependencies
./bin/phpunit                           # Run all tests
./bin/phpunit --filter ClassName        # Run a specific test class
./bin/phpunit --filter testMethodName   # Run a specific test method
composer test                           # Alias for vendor/bin/phpunit
vendor/bin/php-cs-fixer fix             # Fix code style (php-cs-fixer.dist.php)
php -l path/to/File.php                 # Quick syntax lint
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

- **Controller/**: Abstract controllers + operation traits; `OriginPathTrait` for redirect-back logic
- **Configurator/**: `CrudControllerConfigurator` — OptionsResolver-based wiring of all controller options
- **Processor/**: `CrudProcessor` (core CRUD logic + transactions), `SortProcessorTrait` (move up/down for `SortInterface` entities), `Instantiator`, `Reflector`, `RuntimeReflectionClass`
- **Form/**: DTOs in `Dto/` (`EntityDto`, `DeleteDto`, `BulkOperationDto`, `DtoCollection`, `AbstractDto`); form types in `Type/` (`EntityType`, `DeleteType`, `BulkOperationForm`, `JsonType`, `YamlType`, `WysiwygType`, `AbstractFilterType`); transformers and normalizers
- **EntityRepository/**: `EntityRepositoryWrapper` + `FilterHelper`, `SortHelper`
- **Events/**: `CreateEvent`, `UpdateEvent`, `DeleteEvent`, `SyncEvent`, `PostSyncEvent` — all extend `AbstractEvent(ClassMetadata, object)`
- **EventSubscriber/**: `InversedCollectionSubscriber`, `OwningCollectionSubscriber`, `SetVersionSubscriber`
- **Twig/**: `CmsExtension`/`CmsRuntime`, `CoreExtension`, `EmailExtension`, `PhoneExtension`
- **Generator/**: `CsvGenerator` for export operations
- **Resources/**: Twig templates (`views/`), SCSS/JS assets (`assets/`), service configs (`config/`)

### Frontend Assets

- SCSS in `Resources/assets/scss/` — customized Bootstrap 5.3 source files with CMS-specific overrides in `bootstrap/` subdirectory
- JavaScript: jQuery, TinyMCE 5, Select2, Flatpickr, nestable, Chart.js
- Twig templates in `Resources/views/` — base layout, CRUD views, form themes (Bootstrap 5), modals, menus
- Entry points in `Resources/assets/entry/` — per-page JS/SCSS bundles

### Twig Templates

- `base.html.twig` — admin layout shell
- `crud/` — index, list, create, update, view, delete (modal + fragment variants), meta
- `form/` — `horizontal.html.twig`, `inline.html.twig`, `layout.html.twig` form themes
- `menu/` — sidebar navigation, action menus
- `modal.html.twig` — reusable modal component

### Service Configuration

- `Resources/config/services.yaml` — main service definitions
- `Resources/config/dev/services.yaml` — dev-only services

## Testing

- PHPUnit 13.x; tests in `tests/` autoloaded as `Tests\`
- Unit tests go in `tests/Unit/` extending `TestCase`
- The `tests/` directory is currently empty — no existing tests to follow as precedent

## Code Conventions

- PSR-12, `declare(strict_types=1)`, 4-space indent; CS Fixer enforces `@PER-CS`, `@Symfony`, and `native_function_invocation` (backslash-prefixed PHP functions in all scopes)
- Typed properties and return types; favor `readonly`
- Constructor injection only; autowiring and autoconfiguration
- All PHP native functions must be called with a leading `\` (enforced by CS Fixer)
- Commit style: short, action-oriented with optional bracketed scope — `[fix] ...`, `[master] ...`
