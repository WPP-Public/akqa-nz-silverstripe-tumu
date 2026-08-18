<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\SSR;

use Akqa\SilverStripe\SSR\NodeProcess;
use Akqa\SilverStripe\SSR\ReactComponentProvider;
use Akqa\SilverStripe\SSR\ReactRenderer;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * End-to-end React SSR against a real Node.js process and react-dom/server.
 *
 * Skipped unless Node is on PATH and `npm ci` has been run in tests/SSR/fixtures.
 * CI sets SSR_INTEGRATION=1 so a missing Node or fixtures fails instead of skipping.
 */
#[Group('node')]
class ReactRendererNodeTest extends SapphireTest
{
    protected string $nodeBinary = 'node';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireNodeAndFixtures();

        ReactRenderer::config()->set('enabled', true);
        ReactRenderer::config()->set('entry', $this->fixturePath('ssr.cjs'));
        ReactRenderer::config()->set('node_binary', $this->nodeBinary);
        ReactRenderer::config()->set('fallback_on_error', true);
        ReactRenderer::config()->set('skip_when_vite_hot', true);

        Environment::setEnv('SS_REACT_SSR_ENABLED', '');
        Environment::setEnv('SS_REACT_SSR_ENTRY', '');
        Environment::setEnv('SS_REACT_SSR_NODE', '');
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'live');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'false');
    }


    public function testRenderProducesReactHtml(): void
    {
        $html = $this->renderer()->render('Banner', [
            'title' => 'Kia ora',
            'items' => ['One', 'Two'],
        ])->getValue();

        $this->assertStringContainsString('data-component="Banner"', $html);
        $this->assertStringContainsString('class="banner"', $html);
        $this->assertStringContainsString('<h1>Kia ora</h1>', $html);
        $this->assertStringContainsString('<li>One</li>', $html);
        $this->assertStringContainsString('<li>Two</li>', $html);
        $this->assertStringContainsString('&quot;title&quot;:&quot;Kia ora&quot;', $html);
        $this->assertMatchesRegularExpression(
            '/^<div data-component="Banner" data-props="[^"]+">.+/',
            $html ?? ''
        );
    }


    public function testRenderEscapesComponentText(): void
    {
        $html = $this->renderer()->render('Banner', [
            'title' => '<em>Hi</em>',
        ])->getValue();

        $this->assertStringContainsString('<h1>&lt;em&gt;Hi&lt;/em&gt;</h1>', $html);
        $this->assertStringNotContainsString('<em>Hi</em>', $html);
    }


    public function testTemplateGlobalRendersHtml(): void
    {
        $html = ReactComponentProvider::react_component('Banner', [
            'title' => 'From template',
        ]);

        $this->assertStringContainsString('data-component="Banner"', $html);
        $this->assertStringContainsString('<h1>From template</h1>', $html);
    }


    public function testUnknownComponentFallsBackToClientMount(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $html = $this->renderer($logger)->render('Missing', [
            'title' => 'unused',
        ])->getValue();

        $this->assertClientFallback($html, 'Missing', ['title' => 'unused']);
    }


    public function testBrokenComponentFallsBackToClientMount(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $html = $this->renderer($logger)->render('Broken', [
            'title' => 'unused',
        ])->getValue();

        $this->assertClientFallback($html, 'Broken', ['title' => 'unused']);
    }


    public function testInvalidBundleFallsBackToClientMount(): void
    {
        ReactRenderer::config()->set('entry', $this->fixturePath('ssr-invalid.cjs'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $html = $this->renderer($logger)->render('Banner', [
            'title' => 'Hello',
        ])->getValue();

        $this->assertClientFallback($html, 'Banner', ['title' => 'Hello']);
    }


    public function testInvalidBundleThrowsWhenFallbackDisabled(): void
    {
        ReactRenderer::config()->set('entry', $this->fixturePath('ssr-invalid.cjs'));
        ReactRenderer::config()->set('fallback_on_error', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSR process failed for Banner');

        $this->renderer()->render('Banner', ['title' => 'Hello']);
    }


    protected function renderer(?LoggerInterface $logger = null): ReactRenderer
    {
        return ReactRenderer::create(NodeProcess::create(), $logger ?? Injector::inst()->get(LoggerInterface::class));
    }


    /**
     * @param array<string, mixed> $props
     */
    protected function assertClientFallback(?string $html, string $name, array $props): void
    {
        $this->assertNotNull($html);
        $this->assertStringContainsString(sprintf('data-component="%s"', $name), $html);
        $this->assertStringEndsWith('></div>', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('class="banner"', $html);
        $this->assertStringNotContainsString('<section', $html);

        foreach ($props as $key => $value) {
            if (is_string($value)) {
                $this->assertStringContainsString(
                    sprintf('&quot;%s&quot;:&quot;%s&quot;', $key, $value),
                    $html
                );
            }
        }
    }


    protected function requireNodeAndFixtures(): void
    {
        $required = getenv('SSR_INTEGRATION') === '1';
        $node = $this->findNodeBinary();

        if ($node === null) {
            $message = 'Node.js is not available on PATH';
            if ($required) {
                $this->fail($message);
            }
            $this->markTestSkipped($message);
        }

        $this->nodeBinary = $node;

        if (!is_dir($this->fixturesDir() . '/node_modules/react')) {
            $message = 'SSR fixtures are not installed. Run `npm ci` in tests/SSR/fixtures';
            if ($required) {
                $this->fail($message);
            }
            $this->markTestSkipped($message);
        }
    }


    protected function findNodeBinary(): ?string
    {
        $configured = Environment::getEnv('SS_REACT_SSR_NODE');
        if (is_string($configured) && $configured !== '') {
            return $this->nodeRuns($configured) ? $configured : null;
        }

        return $this->nodeRuns('node') ? 'node' : null;
    }


    protected function nodeRuns(string $binary): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([$binary, '-v'], $descriptors, $pipes);

        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }


    protected function fixturePath(string $file): string
    {
        return $this->fixturesDir() . '/' . $file;
    }


    protected function fixturesDir(): string
    {
        return __DIR__ . '/fixtures';
    }
}
