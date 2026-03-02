# Content System — Usage Guide

## Overview

The Content System allows attaching dynamic blocks to any entity (Post, Page, etc.) or using them as standalone collections. It consists of two entities: **ContentType** (a template — name + field schema) and **ContentEntry** (the actual data).

---

## Setup

### 1. Doctrine mapping

Register the bundle entities in your `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            ChamberOrchestraCmsBundleEntity:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/packages/cms-bundle/src/Entity'
                prefix: ChamberOrchestra\CmsBundle\Entity
```

### 2. Routes

#### CMS admin for ContentType management

Create `config/routes/content.yaml` (or add to an existing routes file):

```yaml
cms_content_type:
    resource: "../../packages/cms-bundle/src/Controller/ContentTypeController.php"
    type: attribute
    prefix: '%cms.prefix%'
    defaults: { _locale: '%cms.locale%' }
```

This registers the ContentType CRUD at `{cms_prefix}/content-type/`.

#### Bundle file-manager routes (if used)

```yaml
cms_file:
    resource: "@ChamberOrchestraCmsBundle/Resources/config/routing.php"
    prefix: '%cms.prefix%'
    defaults: { _locale: '%cms.locale%' }
```

### 3. Migrations

Generate and run migrations for the two new tables:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Tables created: `content_type`, `content_entry`.

### 4. ContentType admin nav link (optional)

To add a sidebar link to the ContentType list, add it to your CMS navigation:

```php
$nav->add('content_types', [
    'label' => 'Content Types',
    'route' => 'cms_content_type_index',
    'icon'  => 'fa-layer-group',
]);
```

---

## Entities

### ContentType
Defines the structure of a block.

```php
$contentType->getName();   // "Spring Collection"
$contentType->getSchema(); // array of field definitions
```

Example schema (stored as JSON):
```json
[
  {"name": "title",  "type": "text",       "required": true},
  {"name": "text",   "type": "wysiwyg",    "required": false},
  {"name": "image",  "type": "image",      "required": false},
  {"name": "file",   "type": "file",       "required": false},
  {"name": "photos", "type": "collection", "required": false,
    "schema": [
      {"name": "image",   "type": "image", "required": false},
      {"name": "caption", "type": "text",  "required": false}
    ]
  }
]
```

### ContentEntry
Stores the data for a specific block.

```php
$entry->getData();         // ['title' => '...', 'photos' => [...]]
$entry->getContentType();  // ContentType
$entry->getOwnerClass();   // 'Blog\Entity\Post' (nullable)
$entry->getOwnerId();      // 'uuid' (nullable)
$entry->getSortOrder();    // int
```

Data is stored as JSON:
```json
{
  "title": "Spring Collection",
  "photos": [
    {"image": "/uploads/abc.jpg", "caption": "Morning light", "sortOrder": 1},
    {"image": "/uploads/def.jpg", "caption": "Golden hour",   "sortOrder": 2}
  ]
}
```

---

## Repositories

```php
// All entries of a type by name
$repo->findByTypeName('gallery');

// Entries attached to an owner entity
$repo->findByOwner($post);

// Combined: type + owner
$repo->findByOwnerAndTypeName($post, 'gallery');

// By ownerId (UUID) — used in the API
$repo->findByOwnerId($ownerId);
```

---

## CMS Admin: attaching blocks to an entity

Add the trait to the controller:

```php
// src/Cms/Blog/Controller/PostController.php
class PostController extends AbstractCrudController
{
    use SupportsLcudOperation;
    use SupportsContentEntriesOperation; // ← add this

    // add to nav options:
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Post::class,
            'nav' => function (NavBuilder $nav, array $options) {
                $nav->add('entries', ['route' => 'cms_post_entries_index', ...]);
            }
        ]);
    }
}
```

The trait registers the following routes:
- `GET  /post/{id}/entries` — list entries
- `GET  /post/{id}/entries/{contentType}/create` — create entry
- `GET  /post/{id}/entries/{entryId}/update` — edit entry
- `POST /post/{id}/entries/{entryId}/move/{direction}` — reorder
- `POST /post/{id}/entries/delete` — delete entry

---

## Twig: rendering blocks on the frontend

### Functions

| Function | Description |
|----------|-------------|
| `content_entries_for(owner)` | All entries attached to an owner entity |
| `content_entries_for(owner, {type: 'gallery'})` | Filtered by ContentType name |
| `content_entries_for(owner, {orderBy: {sortOrder: 'DESC'}})` | Custom order (default: `sortOrder ASC`) |
| `content_render_entry(entry)` | Renders an entry via its template |
| `content_entry(id)` | Load a single entry by UUID string |

### Rendering entries:
```twig
{% for entry in content_entries_for(post) %}
    {{ content_render_entry(entry) }}
{% endfor %}

{# specific type only #}
{% for entry in content_entries_for(post, {type: 'gallery'}) %}
    {{ content_render_entry(entry) }}
{% endfor %}
```

### Custom template
For a ContentType named `gallery`, create:
`templates/content/blocks/gallery.html.twig`

If the file does not exist, the generic fallback is used: `@ChamberOrchestraCms/content/blocks/_generic.html.twig`

Inside the block template:
```twig
{# entry.data — full data map #}
{{ entry.data.title }}
{{ entry.data.photos[0].image }}

{# Collections are pre-sorted by sortOrder — no manual sort needed #}
{% for photo in entry.data.photos %}
    <img src="{{ image(photo.image) }}">
{% endfor %}
```

---

## Field Types

| type         | stored in data as                         | in Twig                                |
|--------------|-------------------------------------------|----------------------------------------|
| `text`       | `string`                                  | `{{ val }}`                            |
| `textarea`   | `string`                                  | `{{ val }}`                            |
| `wysiwyg`    | `string` (HTML)                           | `{{ val\|raw }}`                       |
| `image`      | `string` (URI `/uploads/...`)             | `{{ image(val) }}`                     |
| `file`       | `string` (URI `/uploads/...`)             | `<a href="{{ val }}">download</a>`     |
| `collection` | `array` of objects (each has `sortOrder`) | `{% for item in val %}...{% endfor %}` |

---

## File uploads

Handled by `ContentEntryUploadSubscriber` (Doctrine pre-persist/pre-update event). Automatically moves `UploadedFile` to storage and saves the URI string.

---

## API: serving blocks to the frontend

A subscriber-based approach: any View implementing `HasContentEntriesInterface`
gets its `content` property populated automatically before serialization.

```php
class DetailedPostView extends BindView implements HasContentEntriesInterface
{
    use HasContentEntriesTrait; // provides: $content, getOwnerId(), setContent()

    public Uuid $id;
    public string $name;
    // ...
}
```

`ContentEntriesViewSubscriber` (priority 10) intercepts `kernel.view`, checks
`instanceof HasContentEntriesInterface`, loads entries by `getOwnerId()`, and calls
`setContent()`. Works with any View in any application.

> **BindView note:** if the source entity has a property with the same name as
> `$content` (e.g. `Post::$content: string`), initialize `$this->content = new IterableView()`
> before `parent::__construct()` so `BindUtils` skips it.
