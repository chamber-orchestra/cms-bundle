# Changes since v8.2.0-BETA1

What the 8.2 line adds to `v8.2.0-BETA1` (`aa8f846`): the new admin front end (Stimulus, Bootstrap 6), dashboard-style lists, bulk update, the journal of the administrators and more. They were made in a real application (saabcity2) first.

## Front end: Stimulus instead of jQuery, Bootstrap 6 (alpha)
- New `src/Resources/assets/controllers/*_controller.js` (Stimulus): `aside` (sidebar, sub menus, cookie `aside-closed`), `toaster`, `bulk` (bulk operations),
  `sort` (move up/down), `values` (fills the delete form), `fields-filter` (columns). They replace `js/plugins/menu.js`, `entry/crud/bulkOperation.js`,
  `component/sort`, `js/plugins/jquery.values.js`, `js/fieldsFilter`, `js/bootstrap/{toast,tooltip}.js`.
- New entry `src/Resources/assets/entry/cms.js` (Bootstrap 6 `dialog`, `menu`, `collapse`, `tab`, `tooltip`; Font Awesome **Free**; registers the controllers) and
  `scss/cms.scss` (`@use 'bootstrap/scss/bootstrap' with (...)`, then the bundle's `theme/_aside`, `theme/_filter` and the new `theme/_modern`).
  One build for the whole admin; the project's Vite config has the input `cms`.
- Removed: the Bootstrap 5 copy (`scss/bootstrap/`, `scss/_bootstrap.scss`, `scss/forms`, `scss/utilities`, `scss/mixins`, `scss/_init.scss`, `scss/_fontawesome.scss`,
  `scss/base.scss`, `assets/component/`), the per-page entries still use jQuery plugins (select2, tinymce, nestable, codemirror, upload, collection, slider,
  phone, schemaField, flatpickr) and are **not ported yet**: port them as Stimulus controllers when a section needs them.
- Icons: Font Awesome Pro classes `far`/`fad` became `fas` (Free).
- Templates: `modal.html.twig` is a native `<dialog class="dialog">` (Bootstrap 6), `data-bs-toggle="modal"` -> `dialog`, `modal-sm` -> `dialog-sm`; the columns
  dropdown of `crud/list.html.twig` is a Bootstrap 6 `menu`; badges are `badge theme-<colour>`; the per-page `vite_entry_*` blocks are gone, `base.html.twig`
  loads the `cms` entry; the menu search (select2) and `logout_path('cms')` -> `logout_path()`; the sidebar menu class is `Admin\Menu\SidebarNavigation`
  (the project's own); `data-controller` hooks on `<aside>`, `<main>`, the toaster and the columns dropdown.
- The project must register the Doctrine filter `ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter` under that name and provide Twig's intl extension
  (`twig/intl-extra`) for `format_datetime` / `format_currency`.

## Translations
- `Resources/translations/cms+intl-icu.ru.yaml` (all keys of the English file, plus `app.signout`). Source language stays English.

## Look
- `scss/theme/_modern.scss`: a flat theme with a dark scheme (CSS variables `--cms-*`).

## Rich text
- TinyMCE is replaced by a Tiptap Stimulus controller (`controllers/lazy/wysiwyg_controller.js`, loaded only on pages with such a field; toolbar, tables, images by URL,
  a source (HTML) toggle). `WysiwygType` sets `data-controller="wysiwyg"` and no longer needs the file manager's image upload route.
- Select2, CodeMirror, flatpickr, jQuery and the old per-page entries are removed: a `<select>` is native, dates are the browser's date/time inputs, JSON/YAML fields are
  monospace textareas. `entry/forms.js` loads the owner's native components (form, upload, collection, colour fields) lazily. `js/nestable`, `js/tree` (jQuery) are not
  ported yet; `js/schemaField` was removed (jQuery) and has to be rewritten when dynamic schema fields are needed.

## Layout
- No fixed sidebar: a sticky top bar (burger, page title, sign-out) and the menu in a Bootstrap 6 `drawer` on the left (closed until the burger is pressed; the same on a
  phone). `views/base.html.twig` is restructured (`.cms-bar`, `#cms-menu.drawer`, `.cms-main`); `controllers/aside_controller.js` became `menu_controller.js`
  (sub menus only); `theme/_aside.scss` holds the new layout; the list's search panel is full width on a phone. Menu icons are read from the item extras
  (`IconExtension` moves the `icon` option there). New translation key `common.menu`.

## Look (revised)
- `scss/theme/_modern.scss` is the bundle's original look on Bootstrap 6: Bootstrap 4 blue (#007bff), the teal main action (#11c2a1), compact 0.8rem text, 2px borders, small
  radii, 900-weight headings, cards with a soft shadow, a dark menu drawer with a teal heading. Light only (`color-scheme: light`), as the old theme had no dark mode.
  Responsive classes in the bundle's templates use the Bootstrap 6 prefix form (`lg:col-2`).

## List cells
- `format_date` and `format_datetime` of `index.fields` accept an ICU pattern (`['format_date' => 'dd.MM.y']`); `true` keeps the locale's default. Dates, datetimes and currency are no longer bold (`fw-bolder` removed).
- `base.html.twig`: block `bar_search` in the top bar (includes `search_box.html.twig`, Stimulus `search_controller.js`: debounced suggestions from the route `cms_search`, keyboard navigation); the application provides the route. `px-2` removed from `.content-section` so the title and the content share one left edge.
- `_modern.scss`: `.cms-search`, `.cms-search-results`, `.import-output`.
- `crud/list.html.twig`: no card header on a list; the column picker moved to the end of the tab row (new block `content_nav_tools` in `base.html.twig`; `.card-nav` no longer clips, so the menu can open).
- `search_box.html.twig`: a third group label `data-users` (key `search.users`).
- `menu/_menu_item.html.twig`: the badge (`badge` option) is a counter at the right end of an item and of a sub-item (`.nav-badge`); `_aside.scss`: sub-menus render under their item.
- `_aside.scss` / `aside_controller.js`: on a wide screen the closed menu is a 3.5rem rail of icons (counters on the icon's corner) with fly-outs on hover/focus; a link in the rail navigates instead of opening the sub-menu.
- `_modern.scss`: in a list the last (action) column is sticky at the right edge (`.table-list`, or `.table-pinned` for other tables) so it stays visible while a wide table scrolls sideways.
- `crud/list.html.twig`: all the actions of a row (edit, move, entries, copy, toggle, delete) are ONE cell (`td.table-actions`, buttons side by side, one `*` heading) instead of one cell each; the cell is pinned to the right edge (`_modern.scss`).
- Note for the tag: `search_box.html.twig` needs the application to provide the route `cms_search` (`GET ?q=`, `?format=json` for suggestions); `base.html.twig` renders `Admin\Menu\SidebarNavigation` of the application. Both are the application's part of the admin (controllers, menu, forms, search), not the bundle's.
- `base.html.twig`: no logout button and user name in the top bar; the menu header shows a user icon and who is signed in (`.cms-aside-user`; the icon only, with the name as its tooltip, in the rail); the logout button stays at the foot of the menu. The bulk checkbox column of a list is a square cell (`#bulk-operation-toggler`, `.bulk-operation-input`).
- `base.html.twig` renders the menu class `Cms\\Menu\\SidebarNavigation` (was `Admin\\Menu\\SidebarNavigation`): the application's admin code now lives in `src/Cms`. `CLAUDE.md` of the bundle got the "Using the bundle in an application" section.
- `base.html.twig`: page title area redone: the trail (breadcrumbs, only when the card has no tabs and has more than one item) sits small above a bigger `h1`; the title is no longer repeated in the top bar or in a card header (`content_header` is empty by default); the missing `</h1>` is closed. `_modern.scss`: a tab row with a single tab is hidden (the bulk bar and the column picker stay); `_aside.scss`: `.cms-title` styles.
- `base.html.twig`: the section menu (`nav`: List / Create / Edit) moved out of the card to its own row under the title (`.cms-tabs`, block `content_tabs`, pills, hidden when it has a single item); the card's `.card-nav` row keeps only the bulk bar and the tools (column picker) and is hidden when empty.
- `base.html.twig`: the card's `.card-nav` strip is gone. One `.cms-subbar` row under the title holds the section tabs, the page tools (`content_nav_tools`, the column picker, at the end) and the bulk bar (`#bulk-nav`, covers the row while rows are selected); no row when empty. The block `content_nav` no longer exists.
- Filters of a list: the side drawer (yellow tab, `aside-filter`) is gone. The filter form renders in the row under the title (`crud/list.html.twig`, block `content_nav_tools`): text fields as inputs, choices as labelled selects, checkboxes inline; Stimulus `filter` applies a select/checkbox change at once, the text with Enter or the button; "Reset" (`filter.reset`, new key) shows while a filter is set; set fields get an accent border. `_filter.scss` is the new styles; the block `content_aside` is no longer used by lists.
- Filters of a list open in a wide dialog (`crud/filter.modal.html.twig`, `dialog-lg`, fields in a grid) from a "Filter" button under the title that shows how many filters are on, with a Reset link; the inline filter row is gone (the Stimulus `filter` controller is removed).
- Quick search of a column: `AbstractCrudController::list()` reads `?col[field]=text` for the columns of `index.fields` that are text or whole-number (or such a column of a to-one association, `user.email`) and passes `searchable`, `col`, `filters_on` to the template; `crud/index.html.twig` puts a magnifier in those headings, which opens a field under the heading (Stimulus `colsearch`, Enter searches, Esc closes). A field with `'search' => false` gets none. New key `crud.index.search_column`.
- Every single `<select>` of the admin is typeable: `entry/cms.js` tags all selects (also those added later) with the Stimulus `combobox` controller (`controllers/combobox_controller.js`, no library): an input in front of the hidden native select, a list that narrows to the options containing the typed text (any case), arrows/Enter/Esc, `change` fires on the select. The wrapper is inserted before the select (the select is NOT moved: Stimulus would reconnect forever). Styles `.cb`, `.cb-list` in `_filter.scss`. Multiple selects and selects with `size` stay native. The filter dialog is the default width; each field is a full-width label with a full-width field under it.
- `_modern.scss`: text inputs, selects (typeable ones too) and buttons share one height (`--cms-h`, 2rem): the global `.form-control`/`.form-select` rule now also resets `min-height` (Bootstrap 6 gave text inputs a taller minimum than selects).

## The core knows nothing of the application (config) and the bar
- **Configuration**: new `DependencyInjection/Configuration.php`, `chamber_orchestra_cms: {sidebar_menu: <FQCN of the navigation>, search_route: <route name>}` (both default `null`), parameters `chamber_orchestra_cms.sidebar_menu|search_route`, Twig global `cms` (`CmsGlobalsExtension`: `cms.sidebar_menu`, `cms.search_route`). `base.html.twig` renders `cms.sidebar_menu` (nothing when it is not set) and shows the search box only when `cms.search_route` is set: the bundle names no class or route of the application any more (this replaces the notes above about `Cms\Menu\SidebarNavigation` and the route `cms_search`).
- **Search answer**: the JSON of the search route is `[{group, title, items: [{label, sub, url}]}]`: the title of a group comes from the application (`search_controller.js` uses `title ?? group`); the `data-orders|products|users` attributes of `search_box.html.twig` are gone. `search.placeholder` has a neutral default in the bundle's own translations ("Search the site" / "Поиск по сайту").
- **Top bar**: on the left the trail (breadcrumbs, small, only when the card has no tabs and the trail has more than one item) over the title of the page (`h1#page-title`, block `page_title`, one line with an ellipsis); on the right the search box (`flex: 0 1 26rem`, rounded, a `/` key hint that focuses it). `.cms-title` under the bar keeps only the row of tabs and tools (it is not shown when empty). The burger of a wide screen is in the header of the menu (`.cms-aside-burger`; the rail shows only it); the one of the bar stays for a phone.
- **Menu groups**: `menu/sidebar.html.twig` renders a section as `<li class="nav-section">` with a label (`.nav-section-label`, normal case; in the rail a thin line); `.nav-item > .nav` is the only indented list; `aside_controller.js` and the rail CSS find the items of a group too. In the navigation: `->add('group', ['label' => ...], false, true)->children()->add(...)->end()`.
- **One radius**: `--cms-radius` (0.4rem) for every block, button, field, menu, badge and card (Bootstrap's `--radius-1..9` are set to it; circles and the chip pill aside); no `text-transform: uppercase` anywhere.
- **Dashboard styles** (`_modern.scss`): `.dash-actions`, `.dash-kpis`/`.dash-kpi`, `.dash-blocks`/`.dash-card`, `.dash-table`, `.dash-status`, `.dash-facts`, `.dash-list` (the page itself is the application's); `.cms-main` is up to 120rem wide.


### White page

`--cms-bg` is white and the content card (`.card.card-content`) has no background, border or shadow: the page no longer alternates between grey and white, blocks are separated by thin borders.

### Forms, copy, media upload (this batch)

- Horizontal forms: `form/horizontal.html.twig` uses `lg:col-2` (label) and `lg:col-10` (field); `.form-group.row` is a flex row again (Bootstrap 6 makes `.form-group` a grid); the actions of create/update are at the left edge.
- Image field (`dev_image_widget`): a grey placeholder while there is no picture (col-3), the input group beside it (col-9); under the group the date, the MIME type and the size of the uploaded file (Twig function `cms_file_info`, `Twig/FileInfoExtension`); the buttons of a file group are squares. The template uses `asset(uri, 'file')`: **the application needs the asset package `file`** (`framework.assets.packages.file: ~`).
- Joined input groups: the single radius would round every button; `_modern.scss` removes the radius where members of a group touch.
- Copy button on every text input and textarea (`entry/cms.js` wraps the field into `.copyable`, `controllers/copy_controller.js`; top right for a textarea; the search of the bar, fields with a controller of their own and fields of an input group are skipped; `data-no-copy` opts out). `<html data-copy-label>` carries the translated title.
- `UploadController` (`POST /upload/image`, route `cms_upload_image`; replaces the unrouted `FileManagerController`): images only (JPEG, PNG, WebP, AVIF, GIF, 10 MB, validated as an image; no SVG), stored in the default storage of the file bundle under `editor/`, answers `{location}` or `{error}` (422). The application imports `@ChamberOrchestraCmsBundle/Resources/config/routing.php` under its admin prefix (`/abc`). `WysiwygType` puts `data-wysiwyg-upload-url` on the field when the route exists; the editor then uploads a picture chosen with the button, pasted or dropped (without the route: a URL prompt as before).
- Quick creation of what a select lists (`QuickCreateExtension`, option `quick_create` of Doctrine `EntityType`): see the section below.

#### Quick creation from a select

`'quick_create' => '<route of the create action of the controller of the listed entity>'` on a Doctrine `EntityType` field (`ProductType::vendor`: `cms_vendor_create`) puts a "+" beside the select (`combobox_controller.js`). It opens `<route>?quick=1` in a dialog: `AbstractCrudController::doCreate` answers a quick request with `crud/create.quick.html.twig` (the form without the page; 422 with errors when invalid) and, for a valid post, saves the entity and answers `{id, label}` (the label is the controller's `entity_label`, else the id); the select gets the new option chosen. For reference books (an id, a name, a few fields); the route is named only in the form type of the application.

- `UploadController` also takes plain files: `POST /upload/file` (route `cms_upload_file`): documents only (pdf, doc(x), xls(x), odt, ods, txt, csv, zip: by extension and by type, 10 MB), stored under `files/`, answers `{location, name}`; the editor has a paperclip button that uploads a file and inserts a link with its name (`data-wysiwyg-file-url`).
- `StringEmptyDataExtension` (Text/Textarea types): an empty field is submitted as null, which a DTO property typed `string` cannot take (a TypeError instead of "must not be blank"); for such a property the empty value is `''`.
- Icons of the field buttons (copy, upload, "+", delete) are solid and use `--cms-icon`, the colour of the toolbar of the editor; only the cross of "delete" is red.
- `AdminUserRepository` extends `ServiceEntityRepository` (doctrine-extensions) and uses `PaginationAwareTrait`, so the admin's list can page the administrators; the application registers it as a service (`config/services/admin.yaml`).

### Lists, the filter and the editor helpers (this batch)

- Page size of a list: `AbstractCrudController::pageSize()` reads `?limit=` (16, 32, 64, 128; `PAGE_SIZES`), the footer of `crud/list.html.twig` has the links (`common.per_page`); the links keep the filter and start from the first page.
- The filter of a list is a button on the dark plate at the bottom of the window (`.cms-list-dock`, hidden while the plate of the selected rows shows, label `filter.open`: "Filter"/"Фильтр"); the column chooser (`#fields_filter_dropdown`) stays in the markup but is hidden.
- Cells: `cms-cell-small` (a small font) and `cms-cell-clip` (one line with an ellipsis) for the fields of a list (`'class' => 'cms-cell-clip'`).
- `controllers/lookup_controller.js`: a text field with suggestions from the server (`data-controller="lookup" data-lookup-url-value=... list=<datalist id>`, answer `[{value, label}]`).
- Administrators: `AbstractAdminUser` (+ `AdminUser`), `RecordLastLogin` (the time of the last sign-in), `AdminUserChecker`, `AdminLoginController`, `cms:admin:create`; the application names the mapping (`config/services/cms.yaml`) and the firewall (`config/packages/security.yaml`).

- Sorting of a list: the sort icon of a heading is `fa-sort` (dim) / `fa-sort-up` / `fa-sort-down` (the arrow-square icons of the old template do not exist in the free Font Awesome: the squares were empty). The list query is cloned per request (`AbstractCrudController`): the options are resolved once per controller instance, so in a long-lived process (FrankenPHP worker, tests) the order of one request was added to the query of the next. A column that is not a field of the entity must be left out of the sorting (`'order_by_mapping' => ['!payment']`).
- Quick search of a column opens a dropdown panel under the heading (`.th-search`, fixed; the field is a `.form-control`, `data-no-copy`).
- The dark theme follows the system (`prefers-color-scheme`): the colours are the `--cms-*` tokens (`--cms-card`, `--cms-field-border`, `--cms-hover`, `--cms-soft`, `--cms-help`, `--cms-warn-bg` besides the old ones); there is no switch.

### Turbo and Early Hints in the admin

- Turbo Drive is on in the admin (`entry/cms.js`: `@hotwired/turbo`; the application needs it in `package.json`): a click on a link and the back button change the page without a full load, the progress bar is teal. Forms are not taken over (`Turbo.config.forms.mode = 'off'`: the form components send them), pages are not cached by Turbo (`<meta name="turbo-cache-control" content="no-cache">`: the controllers that wrap a field would wrap it twice in a restored snapshot), and the Vite tags carry `data-turbo-track="reload"` (a new build of the assets reloads the page). A dialog made by a script is `data-turbo-temporary`. The layout's `<title>` is the title of the page (`block('page_title')`).
- `103 Early Hints` (`EventSubscriber\\AdminEarlyHints`, `Service\\EarlyHints`): for a plain GET of a route named `cms_*` the server sends the `Link` headers of the entry (`cms`): the stylesheet, the scripts (`modulepreload`) and the icon fonts (`woff2` named by the stylesheet, solid and regular), while the page is still built. It needs a server with `headers_send()` (FrankenPHP has it; elsewhere it is skipped) and a build of the assets (not the Vite dev server).
- A list is a card of the style of the cards of the dashboard (`.cms-list-card` with `dash-card dash-card-wide`: border, radius, margin), set by the block `card_class` of the layout.
- Rows of a list can be selected only when there is something to do with them (`bulk_delete` or `index.bulk_nav`): otherwise no checkboxes and no bulk bar.

## Validation messages as tooltips

`_modern.scss`: `.invalid-tooltip` / `.invalid-feedback` are Bootstrap-like tooltips (white on a red plate, `--cms-danger-bg`, a dark-theme value too) placed under the field over the content; the column of a `.form-group.row` is `position: relative`.

## Stimulus `autosubmit`

`assets/controllers/autosubmit_controller.js`: `<form data-controller="autosubmit">` with a field `data-action="change->autosubmit#submit"` submits the form when the field changes (a chosen file).

## Menu: a focused item

`_aside.scss`: a link of the menu that has the focus (after a click, or with the keyboard) kept Bootstrap's light `:focus` background and a blue ring, a white box on the navy menu (most visible in the rail of icons). It now has the hover colours and a thin accent outline.

## Lists the way a dashboard shows a table

- `crud/list.html.twig`: the bar of a list (the filter button, the columns picker) is the first row of the card and **sticks under the header of the page**, the headings of the columns stick under it; the pagination (with "Total: N" and the size of the page) is a **bar at the bottom of the window that is always there** (`.card-footer` sticks); the top pager is gone. The card clips with `overflow: clip` (not `hidden`: that stops everything inside from sticking); on a narrow screen the table scrolls sideways as before, on a wide one it does not (sticky headings need that).
- `base.html.twig`: `#bulk-nav` (rows are selected) leaves the sub-bar: a dark plate, fixed at the bottom of the window, in the middle of the page (`--cms-aside-w`), with the count, the actions and a cross (`bulk_controller.js`: `clear()`); the tooltip over the table that said how many rows were selected is gone.
- A table is light: one thin line between rows, quiet headings (muted, weight 500), quiet row buttons (the badges keep their theme colours); the headings are aligned as their cells (`table_align_controller.js`, `table-align` on the table).
- The search box widens while it is used; with nothing typed it offers hints and the last searches (`search_controller.js`; the JSON of the search route with an empty `q` is a group of hints, items with `fill`: a text that goes into the box; `search.recent` is translated here, the hints are the application's).
- New keys: `common.total`, `search.recent`.

## The plate of the selected rows: "Change" and a confirmed "Delete"

- `Delete` asks first: a dialog (`#modal-bulk-delete`, `crud/bulk.modals.html.twig`) says how many rows will go; the plate's cross is red.
- **Bulk update**: a controller with `use SupportsBulkUpdateOperation` and the option `'bulk_update' => ['form_class' => SomeType::class]` gets "Change" on the plate: a dialog (`#modal-bulk-update`) with the fields of that form (each one optional: what is left empty is not changed) and a POST `{prefix}_bulk_update` that gives the selected rows (or all) the filled values (`CrudProcessor::bulkUpdate`: one transaction, `UpdateEvent` and `PostSyncEvent` for each row, values written to the properties of the entity). New: `BulkUpdateDto`, `BulkUpdateForm`, `SupportsBulkUpdateOperation`; new keys `common.nav.bulk_update`, `crud.bulk.*`, `crud.flash.selected_updated`, `crud.flash.nothing_to_update`.
- The total of the list follows the pagination (which is at the left); the size of the page stays at the right.

## The filter on the bottom plate, 16:9 pictures in lists

- The bar of a list (`.cms-list-bar`) is gone; the Filter button (and "Reset") is on the dark plate `#list-dock` (`.cms-list-dock`) fixed at the bottom of the window; `body:has(#bulk-nav.show)` hides it while the plate of the selected rows is shown. `--cms-list-bar-h` is 0.
- A picture in a list cell is `image.fill(value, 160, 90, {class: 'cms-thumb'})`: 16:9, `aspect-ratio` and `object-fit: cover` in CSS (the generic `height: auto` no longer stretches it).
- The dialog of a bulk update has even gaps between its fields and buttons.

## Stripe-like page head and list bar

- `base.html.twig`: the title left the top bar (burger + search) for `.cms-pagehead` in `<main>`: a big title (with the trail over it) and, opposite it, `block page_actions` = the nav items that carry `data-head` (create and export, set by `createNavigation()`), rendered as buttons by the new `menu/nav_head.html.twig`; `menu/nav.html.twig` skips them, so the tabs under the title are list / edit / meta.
- `crud/list.html.twig`: the bar of the list (sticky, `--cms-list-bar-h`) holds the search of the entities and the Filter button again (the bottom plate of the filter is gone). The search input is the `search` field of the filter form moved to the bar: `<input form="cms-filter-form" name="filter[search]">` (the form of `crud/filter.modal.html.twig` has that id and does not render the field twice). New key `filter.search_placeholder`.

## Indigo primary

- The admin's primary colour is indigo (`--primary-*` and `--cms-accent` in `_modern.scss`: `#4f46e5`, links `#4338ca`, subtle `#e0e7ff`; the row hover is `#f1f0fd`) instead of Bootstrap's blue `#007bff`; it goes with the teal of the menu and the graphite of the plates. Hard-coded blues in `_aside.scss` and `_filter.scss` follow.

## The choice of rows lives through searches, filters and pages

- `bulk_controller.js` keeps the chosen ids in `sessionStorage` (`cms-bulk:<path>`) and paints them on every page of the list; the heading checkbox is nothing -> this page (indeterminate) -> every row of the filter (checked). The sending always carries the ids (the `all` field stays `0`: "all" no longer means the whole table, so a filtered list cannot delete what it does not show).
- `AbstractCrudController::list()`: `?format=ids` answers `{ids, truncated}` for the whole filter (and the column search), 5000 at most, only for a list that has a bulk action. The list route also takes `POST` with `ids` ("show selected"): the ids wait in the session and the answer is a 303 to `?selected=1`, which shows only those rows.
- `BulkOperationDto` / `BulkUpdateDto` / their forms have `query` (the address query of the list): with `all=1` the server finds the ids of the filter itself (`idsOfFilter()`: the filter form submitted from the query, the column search, `FilterHelper`), so "all" never means the whole table and the browser never sends a list of thousands of ids.
- The plate has "Show selected" / "Show all" (`crud.index.show_selected`, `crud.index.show_all`); the heading checkbox carries `data-total` (the number of rows of the filter).
- The title of a page is 1.2rem.

- The search input of the list bar and the Filter button are one height (`--cms-list-control-h`).

## Export of the filtered list, `filteredQuery()`

- `AbstractCrudController::filteredQuery($resolved, $query)` (protected) is the query of a list with its filter and its column search applied, from the address query of the page; the bulk operations on "all" and the export use it. `resolveAll()` is protected (a controller that changes rows its own way, such as the orders' status, calls it first).
- `doExport()` no longer dumps the whole table through the CSV generator: it streams the rows of the list **as it is filtered now** (the address query of the page; the "CSV" button of `menu/nav_head.html.twig` carries it), the plain columns of `index.fields`, UTF-8 with a mark and `;` for a spreadsheet, 200000 rows at most.

## The journal of the administrators (`audit`, off by default)

- Config: `chamber_orchestra_cms.audit: {enabled: false, path_prefix: '/', record_ip: false, anonymize_ip: true, keyed_hash_secret: null, silent_failures: true, integrity: {enabled: false}}` (what is journalled is chosen with `#[Auditable]`, see the last section). On: `AuditLog` (table `audit_log`) is mapped, its migration (`Resources/migrations`, namespace `ChamberOrchestra\CmsBundle\Migrations`) is registered for `doctrine_migrations`, `Audit\AuditWriter` and `Audit\AuditSubscriber` are defined (they write one line per row of an `#[Auditable]` entity created, changed, deleted or restored after every flush of a request under `path_prefix` made by an `admin_user_class` user: `{field: [before, after]}`, secrets as `***`). Off: none of that exists; the parameter `chamber_orchestra_cms.audit.enabled` lets the application hide its menu item.
- `Controller\AuditController` (`audit_index` `/audit`, `audit_restore` `POST /audit/{id}/restore` under the admin prefix, `Resources/views/audit/index.html.twig`, translations `audit.*` of the `cms` domain): the journal with a filter and "Restore" for a soft-deleted row (`SoftDeleteInterface`); 404 while the journal is off. The application names the kinds of rows with `audit.kind.<ShortClassName>`.

## The journal: chosen with attributes, with more settings
- **`#[Auditable]` opt-in** (`Audit\Attribute\Auditable`, on a class or its parent; `actions: [...]` limits the actions): the journal writes only these entities. The configuration `audit.ignore` is **gone**.
  `#[AuditIgnore]` (a property is never written; a change of it alone writes no line) and `#[AuditTransform(HIDDEN|HASH|KEYED_HASH)]` (a property is written as `***`, a short
  digest or a keyed digest). `AuditMetadata` reads them by reflection once per class. A field named like password / token / secret / hash is still masked without the attribute.
  `AbstractAdminUser` has `#[Auditable]` and a hidden password.
- **New settings** of `chamber_orchestra_cms.audit`: `record_ip`, `anonymize_ip` (default true), `keyed_hash_secret` (default: the kernel secret), `silent_failures` (default true),
  `integrity: {enabled, secret}` (a seal, an HMAC, on every line; `secret` is required when it is enabled).
- **Table `audit_log`**: new columns `ip` and `signature` (migration `Version20261125000001`, `IF NOT EXISTS`). The page shows the address and, with the seals on, whether the line fits its seal;
  `cms:audit:check` checks every line (exit 1 when one does not fit). `AuditWriter::write()` takes the address and makes the id in PHP (`Uuid::v7()`), so that it can be sealed.
- New translation keys `audit.seal`, `audit.seal_ok`, `audit.seal_broken`, `audit.seal_none`.
