<?php

namespace Akqa\SilverStripe\Traits;

use Exception;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\Requirements;

/**
 * Trait for providing Vite requirements. Assumes by default we have a default
 * index.css and index.tsx file in the app/client/src directory but this can be
 * overridden as needed for projects using {@link setDefaultCssAsset()} and
 * {@link setDefaultJsAsset()} inside the init() method on the controller.
 *
 * Trait should be applied to the `PageController` class.
 *
 * Subclasses should implement the getAdditionalRequirements() method to return
 * an array of additional assets to include.
 */
trait ViteProvider
{
    protected string $defaultCssAsset = 'app/client/src/index.css';

    protected string $defaultJsAsset = 'app/client/src/index.ts';

    protected string $distPath = 'app/client/dist/';

    protected string $packageManager = 'yarn';

    public function setDefaultCssAsset(string $asset): self
    {
        $this->defaultCssAsset = $asset;
        return $this;
    }

    public function setDefaultJsAsset(string $asset): self
    {
        $this->defaultJsAsset = $asset;
        return $this;
    }

    public function setDistPath(string $path): self
    {
        $this->distPath = $path;
        return $this;
    }


    public function getDefaultCssAsset(): string|null
    {
        return $this->defaultCssAsset;
    }


    public function getDefaultJsAsset(): string|null
    {
        return $this->defaultJsAsset;
    }


    public function getPackageManager(): string
    {
        return $this->packageManager;
    }


    /**
     * @return array<string, array<string, string>>
     */
    public function buildRequirementsManifest(): array
    {
        $manifestFile = Director::baseFolder() . '/app/client/dist/manifest.json';

        if (!file_exists($manifestFile)) {
            throw new Exception(sprintf(
                'client/dist/manifest.json does not exist. Please run `%s build`',
                $this->packageManager
            ));
        }

        $content = file_get_contents($manifestFile);
        if ($content === false) {
            throw new Exception(sprintf(
                'client/dist/manifest.json could not be read. Please run `%s build`',
                $this->packageManager
            ));
        }
        $manifest = json_decode($content, true);

        if (!$manifest) {
            throw new Exception(sprintf(
                'client/dist/manifest.json is not valid JSON. Please run `%s build`',
                $this->packageManager
            ));
        }

        return $manifest;
    }


    public function getViteBaseHref(): string
    {
        $base = explode(':', Director::absoluteBaseURL());

        if (count($base) > 2) {
            $base = implode(':', array_slice($base, 0, 2));
        } else {
            $base = implode(':', $base);
        }

        if (Director::is_https()) {
            return rtrim($base, '/') . ':5174';
        } else {
            return rtrim($base, '/') . ':5173';
        }
    }


    public function getIncludeViteBuiltRequirements(): string
    {
        $m = Environment::getEnv('BUILD_VERSION');

        if (!$m) {
            $m = time();
        }

        $key = 'vite-requirements-manifest-' . $m;

        $cache = Injector::inst()->get(CacheInterface::class . '.ViteRequirementsManifest');

        if (!$cache->has($key) || isset($_GET['flush'])) {
            $manifest = $this->buildRequirementsManifest();
            $cache->set($key, $manifest);
        } else {
            $manifest = $cache->get($key);
        }

        if (!isset($manifest[$this->defaultCssAsset]) || !isset($manifest[$this->defaultJsAsset])) {
            throw new Exception(sprintf(
                'client/dist/manifest.json is missing required entries. Please run `%s build`',
                $this->packageManager
            ));
        }

        if ($this->defaultCssAsset) {
            Requirements::css($this->distPath . $manifest[$this->defaultCssAsset]['file']);
        }

        $resourcesPath = '/_resources/';
        $jsModules = ArrayList::create();
        $jsModules->push(ArrayData::create([
            'Asset' => Controller::join_links($resourcesPath, $this->distPath, $manifest[$this->defaultJsAsset]['file'])
        ]));

        if ($this->hasMethod('getAdditionalRequirements') && ($additional = $this->getAdditionalRequirements())) {
            foreach ($additional as $asset) {
                if (substr($asset, -4) == '.css' || substr($asset, -5) == '.scss') {
                    if (isset($manifest[$asset])) {
                        Requirements::css($this->distPath . $manifest[$asset]['file']);
                    }
                } else {
                    if (isset($manifest[$asset])) {
                        $jsModules->push(ArrayData::create([
                            'Asset' => Controller::join_links(
                                $resourcesPath,
                                $this->distPath,
                                $manifest[$asset]['file']
                            )
                        ]));
                    }
                }
            }
        }

        return $this->renderWith('Includes/ViteRequirements', [
            'JSModules' => $jsModules,
        ]);
    }


    /**
     * @return ArrayList<ArrayData>
     */
    public function getHotAdditionalRequirements(): ArrayList
    {
        $jsModules = ArrayList::create();

        if ($this->hasMethod('getAdditionalRequirements')) {
            $pageAssets = $this->getAdditionalRequirements();

            foreach ($pageAssets as $asset) {
                if (substr($asset, -4) == '.css' || substr($asset, -5) == '.scss') {
                    Requirements::css($asset);
                } else {
                    $jsModules->push(ArrayData::create([
                        'Asset' => $asset
                    ]));
                }
            }
        }

        return $jsModules;
    }


    public function getViteEntryPoint(): string|null
    {
        return $this->defaultJsAsset;
    }


    public function isDevHot(): bool
    {
        if (Environment::getEnv('SS_ENVIRONMENT_TYPE') == 'dev') {
            if (Environment::getEnv('SS_USE_VITE_DEV_SERVER') == 'true') {

                return true;
            }
        }

        return false;
    }
}
