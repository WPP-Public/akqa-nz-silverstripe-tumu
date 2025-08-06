# silverstripe-tumu

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

In your `Page.ss` template include the following `<% include Vite %>`.
By default, this will handle the hot-reload and requirements for 2 entry points
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
website and not slightly more left field projects (i.e framework only APIs).

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
