<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\SSR;

use Akqa\SilverStripe\SSR\NodeProcess;
use Akqa\SilverStripe\SSR\NodeProcessResult;
use Akqa\SilverStripe\SSR\ReactRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBHTMLText;

class ReactRendererTest extends SapphireTest
{
    protected NodeProcess&MockObject $nodeProcess;

    protected LoggerInterface&MockObject $logger;

    protected ReactRenderer $renderer;

    protected string $entryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nodeProcess = $this->createMock(NodeProcess::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->renderer = ReactRenderer::create($this->nodeProcess, $this->logger);

        $this->entryPath = sys_get_temp_dir() . '/tumu-ssr-' . uniqid('', true) . '.js';
        file_put_contents($this->entryPath, '// fixture');

        ReactRenderer::config()->set('enabled', true);
        ReactRenderer::config()->set('entry', $this->entryPath);
        ReactRenderer::config()->set('fallback_on_error', true);
        ReactRenderer::config()->set('skip_when_vite_hot', true);

        Environment::setEnv('SS_REACT_SSR_ENABLED', '');
        Environment::setEnv('SS_REACT_SSR_ENTRY', '');
        Environment::setEnv('SS_REACT_SSR_NODE', '');
        Environment::setEnv('SS_REACT_SSR_TIMEOUT', '');
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'live');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'false');
    }


    protected function tearDown(): void
    {
        if (isset($this->entryPath) && file_exists($this->entryPath)) {
            unlink($this->entryPath);
        }

        parent::tearDown();
    }


    public function testRenderWrapsClientMountWithoutSsrWhenDisabled(): void
    {
        ReactRenderer::config()->set('enabled', false);
        $this->nodeProcess->expects($this->never())->method('run');

        $result = $this->renderer->render('Banner', ['title' => 'Hello']);

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertSame(
            '<div data-component="Banner" data-props="{&quot;title&quot;:&quot;Hello&quot;}"></div>',
            $result->getValue()
        );
    }


    public function testRenderIncludesServerMarkupWhenEnabled(): void
    {
        $this->nodeProcess->expects($this->once())
            ->method('run')
            ->with(
                $this->equalTo(['node', $this->entryPath]),
                $this->callback(function (string $payload): bool {
                    $data = json_decode($payload, true);

                    return $data['component'] === 'Banner'
                        && $data['props'] === ['title' => 'Hello'];
                }),
                $this->equalTo(5000),
                $this->anything()
            )
            ->willReturn(new NodeProcessResult(0, "<h1>Hello</h1>\n", ''));

        $html = $this->renderer->render('Banner', ['title' => 'Hello'])->getValue();

        $this->assertSame(
            '<div data-component="Banner" data-props="{&quot;title&quot;:&quot;Hello&quot;}"><h1>Hello</h1></div>',
            $html
        );
    }


    public function testRenderAcceptsJsonStringAndArrayDataProps(): void
    {
        $this->nodeProcess->method('run')
            ->willReturn(new NodeProcessResult(0, '<p>ok</p>', ''));

        $fromJson = $this->renderer->render('Banner', '{"title":"A"}')->getValue();
        $fromArrayData = $this->renderer->render(
            'Banner',
            ArrayData::create(['title' => 'A'])
        )->getValue();

        $this->assertStringContainsString('&quot;title&quot;:&quot;A&quot;', $fromJson);
        $this->assertStringContainsString('&quot;title&quot;:&quot;A&quot;', $fromArrayData);
        $this->assertStringContainsString('<p>ok</p>', $fromJson);
    }


    public function testRenderFallsBackWhenNodeFails(): void
    {
        $this->nodeProcess->method('run')
            ->willReturn(new NodeProcessResult(1, '', 'Unknown component: Missing'));

        $this->logger->expects($this->once())->method('warning');

        $html = $this->renderer->render('Banner', [])->getValue();

        $this->assertSame(
            '<div data-component="Banner" data-props="{}"></div>',
            $html
        );
    }


    public function testRenderThrowsWhenFallbackDisabled(): void
    {
        ReactRenderer::config()->set('fallback_on_error', false);
        $this->nodeProcess->method('run')
            ->willReturn(new NodeProcessResult(1, '', 'boom'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSR process failed for Banner: boom');

        $this->renderer->render('Banner', []);
    }


    public function testRenderFallsBackWhenEntryMissing(): void
    {
        ReactRenderer::config()->set('entry', '/tmp/does-not-exist-ssr.js');
        $this->nodeProcess->expects($this->never())->method('run');
        $this->logger->expects($this->once())->method('warning');

        $html = $this->renderer->render('Banner', [])->getValue();

        $this->assertSame('<div data-component="Banner" data-props="{}"></div>', $html);
    }


    public function testShouldRenderServerSideSkipsViteHot(): void
    {
        Environment::setEnv('SS_ENVIRONMENT_TYPE', 'dev');
        Environment::setEnv('SS_USE_VITE_DEV_SERVER', 'true');

        $this->assertFalse($this->renderer->shouldRenderServerSide());

        $this->nodeProcess->expects($this->never())->method('run');
        $html = $this->renderer->render('Banner', [])->getValue();
        $this->assertSame('<div data-component="Banner" data-props="{}"></div>', $html);
    }


    public function testEnvVarDisablesSsr(): void
    {
        Environment::setEnv('SS_REACT_SSR_ENABLED', 'false');

        $this->assertFalse($this->renderer->shouldRenderServerSide());
    }


    public function testInvalidComponentNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid React component name');

        $this->renderer->render('banner-alert', []);
    }


    public function testInvalidPropsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('React component props must be a JSON object');

        $this->renderer->render('Banner', '["not","an","object"]');
    }


    public function testEmptyPropsDefaultToObject(): void
    {
        $this->nodeProcess->expects($this->never())->method('run');
        ReactRenderer::config()->set('enabled', false);

        $html = $this->renderer->render('Banner')->getValue();

        $this->assertSame('<div data-component="Banner" data-props="{}"></div>', $html);
    }
}
