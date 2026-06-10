---
name: frontend-asset
description: Add or modify a frontend asset (JS module, SCSS override, static file, or Bootstrap component) using Webpack Encore + Bootstrap 5. Use when the user asks to add JS behaviour, style overrides, static images/fonts, Bootstrap components, or new Encore entry points.
disable-model-invocation: false
argument-hint: [asset description]
allowed-tools: Read Write Edit Glob Grep Bash(ls *) Bash(cat *)
---

Add or modify a frontend asset for: `$ARGUMENTS`

## Live context

Existing JS modules in `assets/js/`:
```!
ls assets/js/
```

Existing SCSS files in `assets/styles/`:
```!
ls assets/styles/
```

Current `assets/app.js` entry point:
```!
cat assets/app.js
```

Current `assets/styles/bootstrap_override.scss`:
```!
cat assets/styles/bootstrap_override.scss
```

Current `webpack.config.js`:
```!
cat webpack.config.js
```

---

## Rules — read before writing anything

### Project conventions
- **All source assets live in `assets/`** — never put source JS, SCSS, fonts, or images directly in `public/`
- **All JS must be bundled via Webpack Encore** — no CDN `<script>` tags, no inline `<script>` blocks in Twig
- **jQuery is the preferred JS approach** — use `$()` over `document.querySelector` unless there is a compelling reason
- Use `$(document).ready()` / `$(function() { ... })` consistently with the rest of the JS in `assets/js/`
- New JS modules export an object with an `init()` method and are registered in `assets/app.js`
- Build with `npm run dev` / `npm run build` / `npm run watch` — never yarn

### Bootstrap conventions
- **Bootstrap 5.3.3 only** — no other CSS framework; no mixing Bootstrap 4 class names
- Bootstrap 4 → 5 class name changes to remember: `mr-*`→`me-*`, `ml-*`→`ms-*`, `float-left`→`float-start`, `float-right`→`float-end`, `text-left`→`text-start`, `text-right`→`text-end`
- **All style overrides go in `assets/styles/bootstrap_override.scss`** — never inline styles, never a new separate `.css`/`.scss` file
- Use **Bootstrap Icons** for all icons (`bi bi-*`) — never Font Awesome or other icon libraries
- Prefer Bootstrap components (cards, modals, alerts, badges, tables, forms, offcanvas) over custom-built equivalents
- Use Bootstrap utility classes for spacing (`p-*`, `m-*`, `gap-*`), shadows (`shadow-sm`, `shadow`), borders (`rounded`, `rounded-3`), and display/flex utilities

---

## Sass customization — correct import order

Bootstrap requires a strict import order. The existing `bootstrap_override.scss` follows this pattern — **do not change the order**:

```scss
// 1. Variable overrides FIRST — before Bootstrap variables are imported
$primary: #cc995a;
$border-radius: 0.5rem;
// ... other overrides ...

// 2. Import Bootstrap (all-in-one) — after variable overrides
@import "~bootstrap/scss/bootstrap";

// 3. Custom rules AFTER the import — can use Bootstrap variables and mixins here
.my-component {
    color: $primary;
}
```

When adding new variable overrides, **always place them above the `@import` line**. When adding custom rules, **always place them below the `@import` line**. Never split overrides across the file.

### Extending theme colours
```scss
// Above the @import line:
$primary: #cc995a;
$custom-colors: (
    "brand-dark": #8a6035,
    "brand-light": #e8c99a,
);
$theme-colors: map-merge($theme-colors, $custom-colors);

@import "~bootstrap/scss/bootstrap";
```

### Useful Bootstrap Sass functions (usable below the @import line)
- `tint-color($color, $weight)` — mix with white
- `shade-color($color, $weight)` — mix with black
- `color-contrast($color)` — returns accessible contrast colour

---

## Grid system

Always use the `container → row → col` hierarchy — never place `.col-*` directly inside a `.container`.

```html
{# Standard responsive layout #}
<div class="container">
    <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">...</div>
        <div class="col-12 col-md-6 col-lg-4">...</div>
        <div class="col-12 col-md-12 col-lg-4">...</div>
    </div>
</div>

{# Uniform card grid — prefer row-cols over individual col classes #}
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
    <div class="col"><div class="card h-100">...</div></div>
    <div class="col"><div class="card h-100">...</div></div>
</div>

{# Full-width fluid layout #}
<div class="container-fluid px-4">
    <div class="row">...</div>
</div>
```

### Breakpoint reference

| Breakpoint | Class infix | Min-width |
|---|---|---|
| Extra small | *(none)* | `<576px` |
| Small | `sm` | `≥576px` |
| Medium | `md` | `≥768px` |
| Large | `lg` | `≥992px` |
| Extra large | `xl` | `≥1200px` |
| XXL | `xxl` | `≥1400px` |

Classes apply at their breakpoint **and up** — `.col-md-6` means 50% on md, lg, xl, xxl.

Gutter utilities: `g-*` (all), `gx-*` (horizontal), `gy-*` (vertical).

---

## Bootstrap component best practices

### Data attributes vs JS API
- **Use `data-bs-*` attributes for simple toggle behaviour** — less JS to maintain:
  ```html
  <button data-bs-toggle="modal" data-bs-target="#myModal">Open</button>
  <button data-bs-toggle="collapse" data-bs-target="#menu">Toggle</button>
  <button data-bs-dismiss="modal" aria-label="Close">×</button>
  ```
- **Use the JS API when you need events or dynamic control**:
  ```js
  const modal = new bootstrap.Modal('#myModal', { backdrop: 'static' });
  modal.show();

  document.getElementById('myModal').addEventListener('shown.bs.modal', () => {
      document.getElementById('myInput').focus();
  });
  ```

### Modals — required attributes
```html
<div class="modal fade" id="myModal" tabindex="-1"
     aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="myModalLabel">Title</h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>
```
- `tabindex="-1"` is required
- `aria-labelledby` must match the `id` of the `.modal-title`
- `aria-hidden="true"` on the outer `.modal` element
- `aria-label="Close"` on the dismiss button

### Tooltips — must be initialised in JS
Bootstrap tooltips are opt-in. The project initialises them globally in `app.js`:
```js
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
```
Add `data-bs-toggle="tooltip" title="My tip"` to any element — do not add JS initialisation elsewhere.

### Toasts
```js
// Show a toast programmatically
const toastEl = document.getElementById('myToast');
const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
toast.show();
```

### Forms — validation
Use Bootstrap's built-in validation classes. Trigger via JS `needs-validation` pattern:
```html
<form class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" required>
        <div class="invalid-feedback">Please enter a valid email.</div>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
```
```js
document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
```

---

## Webpack Encore entry point strategy

### Decision: `app.js` vs separate entry

| Scenario | Where it goes |
|---|---|
| Code needed on **every page** (global Bootstrap init, tooltips, clickable rows, etc.) | Add module to `assets/js/`, import & init in `assets/app.js` |
| Code needed on **one section or page** only (e.g. Swagger UI, a chart library, a payment widget, a rich text editor) | Create a **new entry** — separate file + `addEntry()` in `webpack.config.js` |
| Heavy third-party library used in only one place | **Always** a separate entry — keep it out of `app.js` to avoid loading it everywhere |
| CSS-only for a specific page | Use `addStyleEntry()` in `webpack.config.js` |

The existing `swagger` entry is the canonical example: Swagger UI is large and only needed on one admin page, so it has its own entry and is never loaded elsewhere.

### Creating a separate entry point

**Step 1 — Create the entry file** `assets/js/{name}.js`:
```js
import SomeLibrary from 'some-library';
import 'some-library/dist/some-library.css';

// Page-specific init — runs only when this entry is loaded
$(() => {
    SomeLibrary.init('#container');
});
```

**Step 2 — Register it in `webpack.config.js`**:
```js
Encore
    .addEntry('app', './assets/app.js')
    .addEntry('swagger', './assets/js/swagger-ui.js')  // existing
    .addEntry('{name}', './assets/js/{name}.js')        // new entry
```

**Step 3 — Load it only on the relevant Twig template** via block extension:
```twig
{# templates/admin/{section}/index.html.twig #}
{% extends 'base.html.twig' %}

{% block stylesheets %}
    {{ parent() }}
    {{ encore_entry_link_tags('{name}') }}
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    {{ encore_entry_script_tags('{name}') }}
{% endblock %}
```

`{{ parent() }}` ensures the base `app` entry (Bootstrap, jQuery, global init) is always included first.

### How `splitEntryChunks()` works (already enabled)

The project already has `.splitEntryChunks()` and `.enableSingleRuntimeChunk()` configured. This means:
- Shared dependencies between entries (e.g. jQuery, Bootstrap) are automatically extracted into shared chunks — no duplication
- `encore_entry_script_tags()` automatically outputs all required `<script>` tags (runtime + shared chunks + entry chunk)
- **Never manually reference chunk filenames** in Twig — always use `encore_entry_script_tags('name')` and `encore_entry_link_tags('name')`

### Dynamic imports — lazy-load within an entry

For code that should only load on user interaction within a page (e.g. opening a modal that needs a heavy parser):
```js
$('[data-action="open-editor"]').on('click', async () => {
    const { default: Editor } = await import('./editor-module.js');
    Editor.init('#target');
});
```
Webpack splits this into a separate chunk automatically — no `addEntry()` needed. Use this for optional UI that not every user will trigger.

### CSS-only entry (no JS)

When a section needs extra CSS but no new JS:
```js
// webpack.config.js
Encore
    .addStyleEntry('print', './assets/styles/print.scss')
```
```twig
{{ encore_entry_link_tags('print') }}
```

---

## Adding a new JS module

1. Create `assets/js/{name}.js`:
```js
const {Name} = {
    init() {
        this.bindEvents();
    },

    bindEvents() {
        $(document).on('click', '[data-action="{name}"]', (e) => {
            this.handleAction(e);
        });
    },

    handleAction(e) {
        // logic here
    },
};

export default {Name};
```

2. Import and register in `assets/app.js`:
```js
import {Name} from './js/{name}';

$(() => {
    // ... existing inits ...
    {Name}.init();
});
```

---

## Adding a custom SCSS rule

Add at the bottom of `assets/styles/bootstrap_override.scss` — do not create a new file:
```scss
// After the @import line — can use Bootstrap variables and mixins
.my-component {
    background-color: tint-color($primary, 80%);
    border-left: 3px solid $primary;
    border-radius: $border-radius;
}
```

---

## Adding a static font

1. Place font files in `assets/fonts/<font-name>/`
2. Confirm the existing `copyFiles()` in `webpack.config.js` already covers `woff2`/`woff` — only add a new block if needed
3. Declare `@font-face` in `bootstrap_override.scss` **above** the `@import` line so it is available to Bootstrap:
```scss
@font-face {
    font-family: 'MyFont';
    src: url('/build/fonts/my-font/my-font.woff2') format('woff2'),
         url('/build/fonts/my-font/my-font.woff')  format('woff');
    font-weight: normal;
    font-style: normal;
}

$font-family-sans-serif: 'MyFont', system-ui, sans-serif; // optional override

@import "~bootstrap/scss/bootstrap";
```
For Browsershot/PDF templates, use `file://` absolute path and inject the build dir via the service.

---

## Twig asset references

```twig
{# Compiled asset (versioned in prod) #}
<img src="{{ asset('build/images/logo.png') }}" alt="Logo">

{# Bootstrap Icon #}
<i class="bi bi-check-circle-fill text-success"></i>

{# Entry point tags (in base layout) #}
{{ encore_entry_link_tags('app') }}
{{ encore_entry_script_tags('app') }}
```

Never hardcode `/build/` paths — always use `{{ asset('build/...') }}`.

---

## After any change

Remind the user to rebuild:
```
npm run dev      # development build (source maps on)
npm run build    # production build (versioned, minified)
npm run watch    # watch mode during development
```
