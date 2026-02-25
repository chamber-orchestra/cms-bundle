# ChamberOrchestra CMS Bundle

A Symfony 8 bundle that provides a full-featured CMS admin panel built around **trait-based CRUD composition**. Controllers opt into exactly the operations they need — list, create, update, delete, copy, move, toggle, export, and meta — by mixing in the corresponding `Supports*Operation` traits.

## Requirements

- PHP ^8.5
- Symfony ^8.0
- Doctrine ORM ^3.0

## Installation

```bash
composer require chamber-orchestra/cms-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    ChamberOrchestra\CmsBundle\ChamberOrchestraCmsBundle::class => ['all' => true],
];
```

## Core Concepts

### Trait-based CRUD composition

Every admin controller extends `AbstractCrudController` and mixes in the operations it needs:

```php
use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Controller\SupportsListOperation;
use ChamberOrchestra\CmsBundle\Controller\SupportsCreateOperation;
use ChamberOrchestra\CmsBundle\Controller\SupportsUpdateOperation;
use ChamberOrchestra\CmsBundle\Controller\SupportsDeleteOperation;

#[Route('/cms/articles', name: 'cms_article_')]
class ArticleController extends AbstractCrudController
{
    use SupportsListOperation;
    use SupportsCreateOperation;
    use SupportsUpdateOperation;
    use SupportsDeleteOperation;

    public function __construct()
    {
        parent::__construct([
            'class'      => Article::class,
            'form_class' => ArticleType::class,
            'data_class' => ArticleDto::class,
        ]);
    }
}
```

Shortcut traits are available for common combinations:

| Trait | Includes |
|-------|----------|
| `SupportsCudOperation` | Create + Update + Delete |
| `SupportsLcudOperation` | List + Create + Update + Delete |

### Available operations

| Trait | Route suffix | Description |
|-------|-------------|-------------|
| `SupportsListOperation` | `index` | Paginated, filterable, sortable list |
| `SupportsCreateOperation` | `create` | Create new entity via DTO form |
| `SupportsUpdateOperation` | `update` | Edit existing entity |
| `SupportsDeleteOperation` | `delete` | Hard or soft delete |
| `SupportsCopyOperation` | `copy` | Clone entity (resets id, slug, enabled) |
| `SupportsMoveOperation` | `move` | Reorder sortable entities |
| `SupportsToggleOperation` | `toggle` | Toggle enabled/disabled flag |
| `SupportsExportOperation` | `export` | Export filtered list as CSV |
| `SupportsUpdateMetaOperation` | `meta` | Edit SEO meta fields |

### Controller options

The `$options` array passed to the constructor drives all behaviour. Every option is resolved through `CrudControllerConfigurator` (powered by Symfony's `OptionsResolver`), so defaults are applied, types validated, and values normalised automatically.

```php
parent::__construct([
    // Required
    'class'       => Article::class,

    // Form & DTO
    'form_class'  => ArticleType::class,
    'data_class'  => ArticleDto::class,   // or a callable

    // Routing
    'route_prefix' => 'cms_article_',     // auto-detected from #[Route] if omitted

    // Templates
    'view_prefix' => '@ChamberOrchestraCms/crud/', // default

    // List configuration
    'index' => [
        'fields'         => ['title', 'enabled', 'createdAt'],
        'order_by'       => ['createdAt' => 'DESC'],
        'filter'         => ArticleFilterType::class,
        'filter_mapping' => ['category' => 'category.id'],
        'query_builder'  => fn($repo) => $repo->createQueryBuilder('a')
                                              ->leftJoin('a.category', 'c'),
    ],

    // Disable specific actions
    'copy'   => null,
    'export' => null,
]);
```

### Options reference

#### Core options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `class` | `string` (class-string) | **required** | FQCN of the managed entity |
| `controller_class` | `string` (class-string) | **required** | FQCN of the controller. Auto-set by `AbstractCrudController::resolve()` to `static::class` |
| `translation_domain` | `string` | `'cms'` | Symfony translation domain for all labels |

#### Parent / hierarchy options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `parent_controller_class` | `?string` | `null` | FQCN of a parent `AbstractCrudController` for hierarchical CRUD |
| `parent_controller` | `?object` | `null` | Auto-resolved from `parent_controller_class` via the controllers service locator. Do not set manually |
| `parent` | `?object` | `null` | The parent entity instance (for nested CRUD views). When set, its `getId()` is added to `route_params` |
| `_parent` | `null\|object\|callable\|string` | `null` | Determines how to extract the parent entity from a child. **string** — name of a property on the child entity (resolved via reflection). **callable** — `fn(object $entity): ?object`. **object** — used directly. **null** — auto-detected by matching the parent controller's entity class against the child entity's property types |

#### Entity label options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `entity_label` | `?callable` | auto | A `callable(object $entity): ?string` that returns the display label for an entity. Default tries `__toString()`, then reads `name`, `title`, or `id` properties via reflection. The result is passed through `strip_tags()` |
| `label_format` | `?string` | `null` | Symfony form label format passed to form types |

#### Form & DTO options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `form_class` | `?string` | `''` | Symfony FormType FQCN for create/update forms |
| `data_class` | `callable\|string\|null` | `null` | DTO class or factory. **string** — class-string implementing `DtoInterface`; instantiated via `Instantiator` with `['entityClass' => ..., 'parent' => ...]`. **callable** — `fn(string $entityClass, array $options): DtoInterface`. **null** — no DTO |
| `form_themes` | `array` | `['@ChamberOrchestraCms/form/horizontal.html.twig', '@ChamberOrchestraFile/cms/form/horizontal.html.twig']` | Twig form themes applied to all forms |

#### Routing options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `route_prefix` | `?string` | auto | Base route name for all actions. Auto-detected from `#[Route]` attribute's `name` parameter, or derived as `cms_{snake_entity_name}` if unset. Trailing `_` is stripped |
| `route_params` | `array` | `[]` | Additional route parameters merged into every action's `route_params`. When `parent` is set, `['parent' => $parent->getId()]` is appended automatically |

#### View / template options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `view_prefix` | `?string` | `null` | Base path for Twig templates. When `null`, defaults to `@ChamberOrchestraCms/crud/`. When set, templates are resolved as `{view_prefix}/{action}.html.twig` |
| `view_params` | `array` | `[]` | Extra template variables merged into every action's view params. Auto-populated with `class`, `parent`, `title`, `label_format`, `translation_domain`, `form_themes` |
| `title` | `?string` | auto | Human-readable title for the managed entity (used in breadcrumbs, nav). Default: short class name extracted from `class` (e.g. `App\Entity\Article` becomes `Article`) |

#### Navigation options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `nav` | `null\|bool\|callable\|string` | `null` | Sidebar navigation customisation. **`false`** — disables nav entirely. **callable** — `fn(MenuBuilder $builder, ?object $entity, array $options)` for custom items. **string** — FQCN of a navigation class (instantiated automatically). **null** — default nav (index, export, create + update/meta for existing entities) |
| `breadcrumbs` | `null\|bool\|callable\|string` | `null` | Breadcrumb customisation. **`false`** — disables breadcrumbs. **callable** — `fn(Breadcrumbs $crumbs, array $options)`. **string** — FQCN instantiated automatically. **null** — default breadcrumbs based on current action |

#### Index (list) options

The `index` key accepts an array of sub-options that configure the list page:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `index.fields` | `?array` | `[]` | Column definitions for the list table. Accepts several formats (see [Fields format](#fields-format) below) |
| `index.actions` | `?callable` | `null` | Row-level action buttons. `fn(MenuBuilder $builder, object $entity, ?object $parent): void` |
| `index.filter` | `?string` | `null` | FQCN of a `FormTypeInterface` subclass for the list filter form |
| `index.filter_mapping` | `array` | `[]` | Maps filter form field names to DQL expressions. String values rename the field; callables receive `(QueryBuilder $qb, mixed $value)` for full control |
| `index.query_builder` | `QueryBuilder\|Closure\|null` | `null` | Custom query builder for the list. **Closure** — `fn(EntityRepository $repo): QueryBuilder`. **QueryBuilder** — used directly. **null** — default `$repo->createQueryBuilder('r')` |
| `index.order_by` | `array` | `[]` | Default sort order, e.g. `['createdAt' => 'DESC']` |
| `index.order_by_mapping` | `?array` | `[]` | Fields allowed for column sorting. Auto-generated from `index.fields` by default; fields named `image`, `video`, `preview`, prefixed with `!`, or containing `.` without a callable are excluded. Set `false` for a field to explicitly exclude it |
| `index.export` | `?array` | `[]` | Export field configuration |
| `index.alias` | `?array` | `[]` | Field aliases for the list view |
| `index.bulk_operation` | `bool` | `true` | Whether to show bulk operation checkboxes |
| `index.bulk_nav` | `?callable` | `null` | Custom bulk operation navigation. `fn(MenuBuilder $builder, array $options): void`. Auto-disabled when `bulk_operation` is `false` |
| `index.view` | `string` | `'{view_prefix}/index.html.twig'` | Template for the list page |
| `index.view_params` | `array` | `[]` | Extra template variables (auto-merged with `fields`, `actions`, `alias`) |
| `index.route` | `string` | `'{route_prefix}_index'` | Route name |
| `index.route_params` | `array` | inherited | Route parameters |
| `index.title` | `?string` | `null` | Title override for breadcrumbs/nav |

##### Fields format

The `index.fields` option supports multiple formats:

```php
'fields' => [
    // Simple list — field name only
    'title',
    'enabled',

    // Named field with format options
    'title' => ['format' => 'truncate:50'],

    // Named field with sub-formats array
    'createdAt' => ['format' => 'date:d.m.Y'],

    // Transform callbacks — applied to each row
    'fullName' => [fn($entity) => $entity->getFirst().' '.$entity->getLast()],

    // Mixed — simple and configured in one array
    'title',
    'category' => ['format' => 'relation'],
    'enabled',
],
```

When processed, each field is normalised to `['field_name' => ['format' => ..., 'transform' => [...]]]`. Callable values in a numeric-keyed position are collected under the `transform` key.

#### Action options

Actions are divided into two groups: **actions with views** (have template + route) and **actions without views** (route only).

**Set any action to `null` to disable it entirely.**

##### Actions with views

`create`, `update`, `view`, `meta`, `export` — each accepts `null` to disable, or an array with these sub-options:

| Sub-option | Type | Default | Description |
|------------|------|---------|-------------|
| `title` | `?string` | `null` | Title override. Can contain nested keys for context: `['breadcrumbs' => '...', 'nav' => '...']` |
| `view` | `string` | `'{view_prefix}/{action}.html.twig'` | Twig template path |
| `view_params` | `array` | inherited from top-level `view_params` | Template variables |
| `route` | `string` | `'{route_prefix}_{action}'` | Route name |
| `route_params` | `array` | inherited from top-level `route_params` | Route parameters |

##### Actions without views

`delete`, `bulk_delete`, `move`, `toggle`, `copy` — each accepts `null` to disable, or an array:

| Sub-option | Type | Default | Description |
|------------|------|---------|-------------|
| `route` | `string` | `'{route_prefix}_{action}'` | Route name |
| `route_params` | `array` | inherited from top-level `route_params` | Route parameters |

`bulk_delete` is automatically disabled when `index.bulk_operation` is `false`.

#### Full example

```php
#[Route('/cms/articles', name: 'cms_article_')]
class ArticleController extends AbstractCrudController
{
    use SupportsLcudOperation;
    use SupportsCopyOperation;
    use SupportsToggleOperation;
    use SupportsExportOperation;
    use SupportsUpdateMetaOperation;

    public function __construct(CrudProcessor $processor)
    {
        parent::__construct($processor, [
            'class'                  => Article::class,
            'form_class'             => ArticleType::class,
            'data_class'             => ArticleDto::class,
            'parent_controller_class' => CategoryController::class,
            'translation_domain'     => 'admin',

            'index' => [
                'fields' => [
                    'title',
                    'category' => ['format' => 'relation'],
                    'enabled',
                    'createdAt' => ['format' => 'date:d.m.Y'],
                ],
                'order_by'       => ['createdAt' => 'DESC'],
                'filter'         => ArticleFilterType::class,
                'filter_mapping' => [
                    'category' => 'category.id',
                    'search'   => fn ($qb, $v) => $qb->andWhere('LOWER(r.title) LIKE :search')
                                                      ->setParameter('search', '%'.mb_strtolower($v).'%'),
                ],
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('r')
                                                      ->leftJoin('r.category', 'c'),
            ],

            'nav' => function (MenuBuilder $builder, ?object $entity, array $options): void {
                $builder->add('custom_link', [
                    'label' => 'Dashboard',
                    'route' => 'cms_dashboard',
                ]);
            },

            // Disable unused actions
            'move'   => null,
            'export' => null,
        ]);
    }
}
```

### DTO ↔ Entity sync

`CrudUtils::sync(object $source, object $target)` copies values between two objects by **property-name intersection** using reflection:

- **Scalar properties** — copied when source ≠ target value
- **Collections / associations** — items added/removed to reconcile source into target
- **`DtoInterface`-typed properties** — recursively synced
- **`MetaInterface` targets** — auto-populates empty `metaTitle`, `metaDescription`, `metaImage` from sibling properties

### Events

Each mutating operation dispatches events in order:

| Operation | Events dispatched |
|-----------|------------------|
| `create()` | `CreateEvent` → `SyncEvent` → `PostSyncEvent` |
| `update()` | `UpdateEvent` → `SyncEvent` → `PostSyncEvent` |
| `delete()` | `DeleteEvent` → `SyncEvent` → `PostSyncEvent` |

All events extend `AbstractEvent` and expose `getEntity()` and `getClassMetadata()`. `SyncEvent` also exposes `getDto()`.

Every mutating operation is wrapped in an explicit `beginTransaction()` / `commit()` / `rollback()`.

### Soft delete

If an entity implements `SoftDeleteInterface`, `CrudProcessor::delete()` calls `$entity->delete()` instead of `EntityManager::remove()`, leaving the row in the database:

```php
class Article implements SoftDeleteInterface
{
    private bool $deleted = false;

    public function delete(): void  { $this->deleted = true; }
    public function isDeleted(): bool { return $this->deleted; }
}
```

## DTOs

DTOs must implement `DtoInterface` (provides `getId(): ?Uuid` and `getEntityClass(): string`). The abstract `AbstractDto` handles the entity class resolution automatically:

```php
class ArticleDto extends AbstractDto
{
    public string $title = '';
    public bool $enabled = true;
    public ?string $slug = null;

    public function __construct()
    {
        parent::__construct(Article::class);
    }
}
```

## List filtering & sorting

`FilterHelper` and `SortHelper` translate simple arrays into Doctrine `QueryBuilder` clauses:

```php
// FilterHelper
['title' => 'news']      // → LOWER(r.title) LIKE '%news%'
['enabled' => true]      // → r.enabled = :p1
['id' => [1, 2, 3]]     // → r.id IN (:p1)
['category' => null]     // → skipped

// SortHelper
['title' => 'ASC']       // → ORDER BY r.title ASC
['name' => null]         // → skipped
```

Both helpers support callable and string mappings to rename or override fields:

```php
'filter_mapping' => [
    'category' => 'category.id',             // string → renames field
    'search'   => fn($qb, $v) => ...,        // callable → full control
],
```

## Frontend assets (Vite)

The bundle ships SCSS + JS source files under `Resources/assets/`. You build them yourself so you can customise variables and override styles.

### Setup with `pentatrion/vite-bundle`

Install the Symfony integration and the required plugins:

```bash
composer require pentatrion/vite-bundle
npm install --save-dev vite vite-plugin-symfony @rollup/plugin-inject vite-plugin-static-copy
```

Register a dedicated CMS build in `config/packages/pentatrion_vite.yaml`:

```yaml
pentatrion_vite:
    default_config: default
    configs:
        default:
            build_directory: build     # public-facing frontend
        cms:
            build_directory: cms       # CMS admin panel
```

Create `vite.cms.config.js` in your project root:

```js
import { defineConfig } from 'vite';
import symfony from 'vite-plugin-symfony';
import inject from '@rollup/plugin-inject';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import path from 'path';

// Path to the bundle's assets (adjust if using a path repository)
const cmsAssets = 'vendor/chamber-orchestra/cms-bundle/Resources/assets';

export default defineConfig({
    plugins: [
        symfony({ buildDirectory: 'cms' }),
        // Make jQuery available globally (required by Select2, nestable, etc.)
        inject({
            $: 'jquery',
            jQuery: 'jquery',
        }),
        // Copy bundle images to the output directory
        viteStaticCopy({
            targets: [
                { src: `${cmsAssets}/img/*`, dest: 'img' },
            ],
        }),
    ],
    root: '.',
    base: '/cms/',
    build: {
        outDir: 'public/cms',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                // Base admin layout (Bootstrap 5 + sidebar + navigation)
                'base':                   `${cmsAssets}/entry/base.js`,
                // CRUD pages
                'crud/index':             `${cmsAssets}/entry/crud/index.js`,
                'crud/edit':              `${cmsAssets}/entry/crud/edit.js`,
                'crud/view':              `${cmsAssets}/entry/crud/view.js`,
                'crud/block':             `${cmsAssets}/entry/crud/block.js`,
                'crud/tree':              `${cmsAssets}/entry/crud/tree.js`,
                'crud/update.fragment':   `${cmsAssets}/entry/crud/update.fragment.js`,
                'crud/bulkOperation':     `${cmsAssets}/entry/crud/bulkOperation.js`,
                // Dashboard
                'dashboard/index':        `${cmsAssets}/entry/dashboard/index.js`,
                // Auth
                'security/login':         `${cmsAssets}/entry/security/login.js`,
                // FontAwesome (separate chunk — load only where needed)
                'fontawesome':            `${cmsAssets}/entry/fontawesome.js`,
            },
        },
    },
    resolve: {
        alias: {
            // Allows importing from node_modules with ~ prefix inside SCSS
            '~': path.resolve(__dirname, 'node_modules'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
                quietDeps: true,
            },
        },
    },
    server: {
        host: '127.0.0.1',
        port: 9001,
        cors: true,
    },
});
```

Add scripts to `package.json`:

```json
{
    "scripts": {
        "dev-cms":   "vite --config vite.cms.config.js",
        "build-cms": "vite build --config vite.cms.config.js"
    }
}
```

Include assets in the admin base template using the `vite()` Twig helper:

```twig
{# templates/cms/base.html.twig #}
{% extends '@ChamberOrchestraCms/base.html.twig' %}

{% block stylesheets %}
    {{ vite_entry_link_tags('base', configName='cms') }}
    {{ vite_entry_link_tags('crud/index', configName='cms') }}
{% endblock %}

{% block javascripts %}
    {{ vite_entry_script_tags('base', configName='cms') }}
    {{ vite_entry_script_tags('crud/index', configName='cms') }}
{% endblock %}
```

> **Customising Bootstrap 5 variables** — create your own SCSS file that overrides variables before importing the bundle's `_init.scss`:
>
> ```scss
> // assets/scss/cms-overrides.scss
> $primary: #3d5a80;
> $sidebar-width: 260px;
>
> @import 'vendor/chamber-orchestra/cms-bundle/Resources/assets/scss/_init';
> ```

## Twig integration

The bundle registers Twig extensions under the `@ChamberOrchestraCms` namespace. Admin views extend `@ChamberOrchestraCms/base.html.twig` and use the Bootstrap 5 form theme at `@ChamberOrchestraCms/form/layout.html.twig`.

## CSV export

When `SupportsExportOperation` is used, the filtered list can be exported as CSV via `CsvGenerator`. The export respects the same filter and mapping configuration as the list action.

## Testing

```bash
# Full suite (Unit + Integrational)
php8.5 ../../vendor/bin/phpunit

# Unit tests only
php8.5 ../../vendor/bin/phpunit --testsuite Unit

# Integration tests only
php8.5 ../../vendor/bin/phpunit --testsuite Integrational
```

Integration tests require a PostgreSQL database. By default the test kernel connects to a local socket database named `cms_bundle_test`. Override with:

```bash
export CMS_BUNDLE_TEST_DB_URL="postgresql://user:pass@host/dbname?serverVersion=17"
```

## License

Apache License 2.0. See [LICENSE](LICENSE) for details.
