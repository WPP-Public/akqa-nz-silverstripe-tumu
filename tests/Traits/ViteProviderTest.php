<?php

namespace Akqa\SilverStripe\Tests\Traits;

use Akqa\SilverStripe\Traits\ViteProvider;
use Exception;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\Requirements;

class TestViteProviderClass extends Controller
{
    use ViteProvider;

    /**
     * @return array<string>
     */
    public function getAdditionalRequirements(): array
    {
        return [
            'app/client/src/additional.css',
            'app/client/src/additional.jsx'
        ];
    }
}

class TestViteProviderClassWithoutAdditionalRequirements extends Controller
{
    use ViteProvider;
}

class ViteProviderTest extends SapphireTest
{
    protected TestViteProviderClass $testClass;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test class that uses the trait
        $this->testClass = TestViteProviderClass::create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset Requirements
        Requirements::clear();
    }

    public function testSetDefaultCssAsset(): void
    {
        $result = $this->testClass->setDefaultCssAsset('test.css');

        $this->assertSame($this->testClass, $result);
        $this->assertEquals('test.css', $this->testClass->getDefaultCssAsset());
    }

    public function testSetDefaultJsAsset(): void
    {
        $result = $this->testClass->setDefaultJsAsset('test.jsx');

        $this->assertSame($this->testClass, $result);
        $this->assertEquals('test.jsx', $this->testClass->getDeaf);
    }

    public function testBuildRequirementsManifestWithMissingFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('client/dist/manifest.json does not exist. Please run `yarn build`');

        $this->testClass->buildRequirementsManifest();
    }

    public function testBuildRequirementsManifestWithInvalidJson(): void
    {
        // Create a temporary invalid manifest file
        $manifestPath = Director::baseFolder() . '/app/client/dist/manifest.json';
        $dir = dirname($manifestPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($manifestPath, 'invalid json');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('client/dist/manifest.json is not valid JSON. Please run `yarn build`');

        try {
            $this->testClass->buildRequirementsManifest();
        } finally {
            // Clean up
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function testBuildRequirementsManifestWithValidJson(): void
    {
        // Create a temporary valid manifest file
        $manifestPath = Director::baseFolder() . '/app/client/dist/manifest.json';
        $dir = dirname($manifestPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifestData = [
            'app/client/src/index.css' => ['file' => 'index.css'],
            'app/client/src/index.jsx' => ['file' => 'index.jsx']
        ];

        file_put_contents($manifestPath, json_encode($manifestData));

        try {
            $result = $this->testClass->buildRequirementsManifest();

            $this->assertEquals($manifestData, $result);
        } finally {
            // Clean up
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function testGetViteBaseHref(): void
    {
        // Test that the method returns a string and contains expected port
        $result = $this->testClass->getViteBaseHref();

        $this->assertIsString($result);
        $this->assertStringContainsString(':', $result);

        // Should contain either 5173 or 5174 port
        $this->assertTrue(
            strpos($result, ':5173') !== false || strpos($result, ':5174') !== false,
            'Base href should contain Vite dev server port'
        );
    }

    public function testGetIncludeViteRequirementsWithMissingManifestEntries(): void
    {
        // Create a temporary manifest file with missing entries
        $manifestPath = Director::baseFolder() . '/app/client/dist/manifest.json';
        $dir = dirname($manifestPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifestData = [
            'app/client/src/index.css' => ['file' => 'index.css']
            // Missing index.jsx entry
        ];

        file_put_contents($manifestPath, json_encode($manifestData));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('client/dist/manifest.json is missing required entries. Please run `yarn build`');

        try {
            $this->testClass->getIncludeViteRequirements();
        } finally {
            // Clean up
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function testGetIncludeViteRequirementsWithValidManifest(): void
    {
        // Create a temporary valid manifest file
        $manifestPath = Director::baseFolder() . '/app/client/dist/manifest.json';
        $dir = dirname($manifestPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifestData = [
            'app/client/src/index.css' => ['file' => 'index.css'],
            'app/client/src/index.jsx' => ['file' => 'index.jsx'],
            'app/client/src/additional.css' => ['file' => 'additional.css'],
            'app/client/src/additional.jsx' => ['file' => 'additional.jsx']
        ];

        file_put_contents($manifestPath, json_encode($manifestData));

        // Mock cache
        $mockCache = $this->createMock(CacheInterface::class);
        $mockCache->method('has')->willReturn(false);
        $mockCache->method('set')->willReturn(true);
        $mockCache->method('get')->willReturn($manifestData);

        Injector::inst()->registerService($mockCache, CacheInterface::class . '.ViteRequirementsManifest');

        try {
            $result = $this->testClass->getIncludeViteRequirements();

            // Should return rendered template
            $this->assertStringContainsString('script type="module"', $result);
            $this->assertStringContainsString('index.jsx', $result);
            $this->assertStringContainsString('additional.jsx', $result);

            // Check that CSS was added to Requirements
            $this->assertTrue(Requirements::backend()->getCSS() !== []);
        } finally {
            // Clean up
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function testGetIncludeViteRequirementsWithCachedManifest(): void
    {
        // Create a temporary valid manifest file
        $manifestPath = Director::baseFolder() . '/app/client/dist/manifest.json';
        $dir = dirname($manifestPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifestData = [
            'app/client/src/index.css' => ['file' => 'index.css'],
            'app/client/src/index.jsx' => ['file' => 'index.jsx']
        ];

        file_put_contents($manifestPath, json_encode($manifestData));

        // Mock cache to return cached data
        $mockCache = $this->createMock(CacheInterface::class);
        $mockCache->method('has')->willReturn(true);
        $mockCache->method('get')->willReturn($manifestData);

        Injector::inst()->registerService($mockCache, CacheInterface::class . '.ViteRequirementsManifest');

        try {
            $result = $this->testClass->getIncludeViteRequirements();

            // Should return rendered template
            $this->assertStringContainsString('script type="module"', $result);
            $this->assertStringContainsString('index.jsx', $result);
        } finally {
            // Clean up
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function testGetHotAdditionalRequirements(): void
    {
        $result = $this->testClass->getHotAdditionalRequirements();

        $this->assertInstanceOf(ArrayList::class, $result);
        $this->assertEquals(1, $result->count());

        $firstItem = $result->first();
        $this->assertInstanceOf(ArrayData::class, $firstItem);
        $this->assertEquals('app/client/src/additional.jsx', $firstItem->Asset);
    }

    public function testGetHotAdditionalRequirementsWithNoAdditionalRequirements(): void
    {
        $testClass = new TestViteProviderClassWithoutAdditionalRequirements();

        $result = $testClass->getHotAdditionalRequirements();

        $this->assertInstanceOf(ArrayList::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function testIsDevHotWithDevEnvironmentAndViteDevServerEnabled(): void
    {
        // Mock environment variables
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'dev');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'true');

        $result = $this->testClass->isDevHot();

        $this->assertTrue($result);
    }

    public function testIsDevHotWithDevEnvironmentButViteDevServerDisabled(): void
    {
        // Mock environment variables
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'dev');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'false');

        $result = $this->testClass->isDevHot();

        $this->assertFalse($result);
    }

    public function testIsDevHotWithNonDevEnvironment(): void
    {
        // Mock environment variables
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'live');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'true');

        $result = $this->testClass->isDevHot();

        $this->assertFalse($result);
    }

    public function testIsDevHotWithNoEnvironmentVariables(): void
    {
        // Clear environment variables
        Environment::setEnv('SS_ENVIRONMENT_TYPE', '');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', '');

        $result = $this->testClass->isDevHot();

        $this->assertFalse($result);
    }
}
