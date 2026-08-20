# silverstripe-tumu

[![CI](https://github.com/WPP-Public/akqa-nz-silverstripe-tumu/actions/workflows/ci.yml/badge.svg)](https://github.com/WPP-Public/akqa-nz-silverstripe-tumu/actions/workflows/ci.yml)

A unique combination of foundational modules for Silverstripe websites.

## 🌟 Install

```sh
composer require "akqa/silverstripe-tumu"
```

## ♻️ Why use tumu

Tumu is designed for a few purposes:

1. To make our projects consistent and predictable as possible.
1. Simplify upgrading between major platform versions.
1. DRY - Don't Repeat Yourself.

By replacing direct composer requirements of things like `cms` and `elemental`
and using `tumu` we can delegate some of the heavy lifting to a single source of
truth and handle major version upgrades with a single dependency

## 🚀 Features Included

-   Silverstripe 6 CMS
-   Common functionality such as `TagField` and `Taxonomy` which we widely use.
-   Elemental
-   UserForms
-   [Menu
    Manager](https://github.com/WPP-Public/akqa-nz-silverstripe-menumanager)
-   [Redirects](https://github.com/silverstripe/silverstripe-redirectedurls)
-   SEO related improvements (sitemaps, robots)
-   Vite and SSR integration for building modern front-ends.
-   Extensions for common functionality (Sorting, )
-   🔨more to come

### Project Setup

Ideal situation is your project specific `composer.json` reflects something as
simple as

```json
{
    "name": "akqa/project",
    "type": "project",
    "require": {
        "akqa/silverstripe-tumu": "dev-main"
    },
    "require-dev": {
        "cambis/silverstan": "^2",
        "phpro/grumphp-shim": "^2",
        "phpunit/phpunit": "^11",
        "squizlabs/php_codesniffer": "^3.13",
        "php-parallel-lint/php-parallel-lint": "^1.4"
    },
    "scripts": {
        "test": "php -d memory_limit=-1 ./vendor/bin/phpunit",
        "phpstan": "phpstan analyse --memory-limit 1024M",
        "lint": "phpcs app/src app/tests",
        "lint:fix": "phpcbf app/src app/tests",
        "coverage": "XDEBUG_MODE=coverage php -d memory_limit=-1 ./vendor/bin/phpunit --coverage-text"
    },
    "config": {
        "platform": {
            "php": "8.4"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

The `dev` dependencies are used for [GrumPHP](https://github.com/phpro/grumphp)
and our standard linting rules.

### Front-end assets

For this we assume a default location of javascript, css and images of
`app/client`. You can use themes if you wish but most of our sites use a single
app directory. Under the `app/client` we usually structure it as

```
app/client
    /src
        /js
        /css
    /dist
        .. vite controlled output
```

Tumu provides a `ViteProvider` trait which extracts our handling of importing
Vite requirements (but not the running of the Vite process, you will need to
update your environment to physically build or run the hot-reload server).

```php
<?php

use Akqa\SilverStripe\Traits\ViteProvider;

class PageController extends ContentController
{
    use ViteProvider;

}
```

In your `Page.ss` template include the following `<% include Vite %>`. By
default, this will handle the hot-reload and requirements for 2 entry points
`app/client/src/index.ts` and `app/client/src/index.css`. To rename or change
these entrypoints, use the API on ViteProvider

```php
<?php

use Akqa\SilverStripe\Traits\ViteProvider;

class PageController extends ContentController
{
    use ViteProvider;

    protected function init()
    {
        parent::init();

        $this->setPackageManager('pnpm');
        $this->setDefaultCssAsset('app/client/src/style.css')
    }

}
```

To include additional CSS or JavaScript files (such as print stylesheets),
implement the `getAdditionalRequirements()` method:

```php
<?php

use Akqa\SilverStripe\Traits\ViteProvider;

class PageController extends ContentController
{
    use ViteProvider;

    /**
     * @return array<string>
     */
    public function getAdditionalRequirements(): array
    {
        return [
            'app/client/src/print.css' => [
                'media' => 'print'
            ],
            'app/client/src/additional.jsx'
        ];
    }
}
```

The `getAdditionalRequirements()` method should return an array of asset paths
relative to your Vite source directory. CSS files (`.css` or `.scss`) will be
automatically included in the page requirements, while JavaScript files will be
loaded as modules. This is useful for including print stylesheets, page-specific
styles, or additional JavaScript modules.

#### Modulepreload for imported chunks

Vite's production HTML is typically a tiny inline module:

```html
<script type="module">import '/_resources/app/client/dist/index-….js'</script>
```

Any file listed in that entry's `imports` in `manifest.json` (for example a
`Registry` chunk, or a shared vendor chunk) is only discovered after `index.js`
downloads and parses — an extra round trip on the critical path.

`ViteProvider` emits `<link rel="modulepreload">` for those JS imports (and for
imports of any additional JS entries from `getAdditionalRequirements()`). CSS
imports are unchanged: they still go through `Requirements::css()`. No project
code is required beyond using `<% include Vite %>`.

If you render `Includes/ViteRequirements` yourself, pass `ModulePreloads` as well
as `JSModules`:

```php
return $this->renderWith('Includes/ViteRequirements', [
    'JSModules' => $jsModules,
    'ModulePreloads' => $this->getViteModulePreloads($manifest, [
        $this->getDefaultJsAsset(),
    ]),
]);
```

### React components (SSR)

Tumu can optionally server-render React "islands" into Silverstripe templates,
then hydrate them with the same Vite client bundle. This is **opt-in**: client-only
mount nodes keep working without it.

The approach is deliberately small. Silverstripe still owns the page. React
renders named components (a banner, an accordion, a listing) rather than the
whole site. PHP asks Node.js to `renderToString` a component from a Vite SSR
bundle, wraps that HTML in a mount node, and the browser hydrates it.

```
Silverstripe template
        │
        ▼
$ReactComponent('Banner', $BannerProps)
        │
        ├─ SSR off / Vite HMR / Node failure
        │       → <div data-component="Banner" data-props="{...}"></div>
        │
        └─ SSR on
                → Node runs app/client/dist/ssr.js
                → <div data-component="Banner" data-props="{...}"><h1>...</h1></div>
        │
        ▼
Client bundle hydrates [data-component] (hydrateRoot if the node has markup,
createRoot if it is empty)
```

#### Enable it

1. Node.js must be on the PATH of the **PHP** process (DDEV's web container is
   fine; many PHP-only hosts are not).
2. Build a Vite SSR entry that reads JSON from stdin and writes HTML to stdout
   (see below).
3. Turn it on:

```
SS_REACT_SSR_ENABLED="true"
```

Or in YAML:

```yaml
Akqa\SilverStripe\SSR\ReactRenderer:
  enabled: true
  entry: app/client/dist/ssr.js
  node_binary: node
  timeout_ms: 5000
  fallback_on_error: true
  skip_when_vite_hot: true
```

Optional env overrides: `SS_REACT_SSR_ENTRY`, `SS_REACT_SSR_NODE`,
`SS_REACT_SSR_TIMEOUT` (milliseconds).

#### Use it in templates

Build props in PHP (JSON in `.ss` files is miserable):

```php
public function getBannerProps(): string
{
    return json_encode([
        'title' => (string) $this->Title,
    ], JSON_THROW_ON_ERROR);
}
```

```html
<% cached 'banner', $ID, $LastEdited %>
$ReactComponent('Banner', $BannerProps)
<% end_cached %>
```

That prints a mount node using the same `data-component` / `data-props`
attributes as the [Vite starter](https://github.com/WPP-Public/akqa-nz-silverstripe-starter-vite-ddev),
so existing client registries keep working. The global is already cast as
`HTMLFragment` — do not add `.RAW`.

From PHP:

```php
use Akqa\SilverStripe\SSR\ReactRenderer;

$html = ReactRenderer::singleton()->render('Banner', [
    'title' => $this->Title,
]);
```

Partial caching around `$ReactComponent` is strongly recommended. Each SSR call
spawns Node; caching the Silverstripe fragment is how this stays cheap.

#### Project JavaScript contract

Tumu ships the PHP side only. The project owns the React registry, the SSR
entry, and hydration.

SSR entry (`app/client/src/ssr.tsx`) — write to stdout with
`process.stdout.write`, never `console.log` (a trailing newline becomes a text
node and hydration warns):

```ts
import { createElement } from "react";
import { renderToString } from "react-dom/server";
import { registry } from "./state/Registry";

function readStdin(): Promise<string> {
    return new Promise((resolve, reject) => {
        const chunks: Buffer[] = [];
        process.stdin.on("data", (chunk) => chunks.push(Buffer.from(chunk)));
        process.stdin.on("end", () =>
            resolve(Buffer.concat(chunks).toString("utf8"))
        );
        process.stdin.on("error", reject);
    });
}

readStdin()
    .then((raw) => {
        const { component, props } = JSON.parse(raw) as {
            component: string;
            props: Record<string, unknown>;
        };
        const Component = registry[component];
        if (!Component) {
            process.stderr.write(`Unknown React component: ${component}\n`);
            process.exit(1);
        }
        process.stdout.write(renderToString(createElement(Component, props)));
    })
    .catch((error) => {
        process.stderr.write(String(error));
        process.exit(1);
    });
```

PHP sends `{"component":"Banner","props":{...}}` on stdin.

Client hydration (`app/client/src/index.tsx`):

```ts
import { createElement } from "react";
import { createRoot, hydrateRoot } from "react-dom/client";
import { registry } from "./state/Registry";

document.querySelectorAll<HTMLElement>("[data-component]").forEach((el) => {
    const name = el.dataset.component;
    const Component = name ? registry[name] : undefined;
    if (!Component) {
        return;
    }
    const props = JSON.parse(el.dataset.props || "{}");
    const node = createElement(Component, props);
    if (el.hasChildNodes()) {
        hydrateRoot(el, node);
    } else {
        createRoot(el).render(node);
    }
});
```

Vite SSR build (second config so it does not wipe the client `dist`). Bundle
dependencies (`ssr.noExternal: true`) so production only needs the generated
file and `node`, not `node_modules` on the web host:

```ts
// vite.ssr.config.ts
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: { "@": path.resolve(__dirname, "./app/client/src/") },
    },
    ssr: {
        noExternal: true,
    },
    publicDir: false,
    build: {
        ssr: "./app/client/src/ssr.tsx",
        outDir: "./app/client/dist",
        emptyOutDir: false,
        copyPublicDir: false,
        rollupOptions: {
            output: {
                format: "cjs",
                entryFileNames: "ssr.js",
            },
        },
    },
});
```

```json
{
    "scripts": {
        "build": "vite build && vite build --config vite.ssr.config.ts"
    }
}
```

When `SS_USE_VITE_DEV_SERVER=true`, Tumu **skips SSR** and emits an empty mount
node. HMR stays fast and you avoid hydrating against a stale SSR bundle. To
exercise SSR locally, build assets and set `SS_USE_VITE_DEV_SERVER=false`.

#### Testing

PHPUnit talks to a real Node process and `react-dom/server` when Node is on
PATH and fixture deps are installed:

```sh
npm ci --prefix tests/SSR/fixtures
vendor/bin/phpunit --group node
```

If Node or `tests/SSR/fixtures/node_modules` is missing, those tests are
skipped. CI installs Node and the fixtures and sets `SSR_INTEGRATION=1` so a
skip becomes a failure. Successful SSR must include the React markup inside the
mount node; a thrown render, unknown component, or invalid bundle must fall
back to an empty client mount.

#### Limitations

Treat these as product constraints, not temporary gaps:

- **Node at request time.** PHP must be able to `proc_open` `node`. This is
  normal on DDEV; it is not true of PHP-only PaaS images unless you add Node to
  the web container. Tumu will not SSH to a remote Node service or talk HTTP to
  a sidecar.
- **Not a React meta-framework.** No file-based routing, streaming
  (`renderToPipeableStream`), RSC, or data loaders. Silverstripe remains the
  server. SSR here is `renderToString` for islands.
- **Components must be isomorphic.** No `window`, `document`, `localStorage`,
  or layout reads during the first render. `useEffect` / `useLayoutEffect` do
  not run on the server. `Date.now()` and `Math.random()` cause hydration
  mismatches — keep them in effects.
- **Browser-only libraries.** Maps, carousels, and similar that assume a DOM
  should stay client-only (empty mount node). SSR the content-ish islands
  (headings, listings, FAQ copy); hydrate the widgets.
- **CSS-in-JS and CSS modules** need extra SSR wiring (style collection,
  class name stability). Prefer CSS imported through the Vite client bundle.
- **JSON-serialisable props only.** No functions, class instances, or
  DataObjects. Do not put secrets in props — they are printed into
  `data-props` in the HTML.
- **Hydration must match.** If SSR HTML and the client first render differ,
  React will warn and may discard markup. Keep the registry identical on both
  entries.
- **Process cost.** Spawning Node per component is slow relative to PHP.
  Cache the Silverstripe fragment. Do not SSR every icon or toggle.
- **Failure mode.** With `fallback_on_error` (the default) a missing bundle,
  timeout, or unknown component is logged and the empty mount node is returned
  so the client can still render. Set `fallback_on_error: false` in dev if you
  want that to throw.
- **`proc_open` must be allowed.** Disabled `proc_open` in `php.ini` makes SSR
  impossible.
- **No streaming or chunked HTML.** The PHP request waits for the full string
  (default timeout 5s).

If those limits are a problem for a given component, skip SSR and keep the
client-only `data-component` mount. That is the supported default.

## ❌ What tumu is not

It should not be treated as a dumping ground for every and all clever ideas
someone has. Features (especially composer ones) should be added with some level
of skepticism as to whether they will practically be used by all our clients.
Features such as TagField and LinkField is fine as any usage is 'opt-in' for
specific sites.

Modules such as Subsites or Translatable haven't been included since we perhaps
use them in less than half of the active supported clients and these
dramatically alter the CMS interface.

There is also an assumption that this is used for your typical stock standard
website and not slightly more left field projects (i.e framework-only).

## 🔗 See also

-   [silverstripe-vite-ddev](https://github.com/WPP-Public/akqa-nz-silverstripe-starter-vite-ddev)

## Licence

Copyright 2025 AKQA NZ Limited

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

3. Neither the name of the copyright holder nor the names of its contributors
   may be used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS “AS IS” AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
