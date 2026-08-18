<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\SSR;

use Akqa\SilverStripe\SSR\ReactComponentProvider;
use Akqa\SilverStripe\SSR\ReactRenderer;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\TemplateGlobalProvider;

class ReactComponentProviderTest extends SapphireTest
{
    public function testImplementsTemplateGlobalProvider(): void
    {
        $this->assertInstanceOf(TemplateGlobalProvider::class, new ReactComponentProvider());
    }


    public function testGetTemplateGlobalVariablesRegistersHtmlFragment(): void
    {
        $vars = ReactComponentProvider::get_template_global_variables();

        $this->assertArrayHasKey('ReactComponent', $vars);
        $this->assertSame('react_component', $vars['ReactComponent']['method']);
        $this->assertSame('HTMLFragment', $vars['ReactComponent']['casting']);
    }


    public function testReactComponentDelegatesToRenderer(): void
    {
        $markup = '<div data-component="Banner" data-props="{}"></div>';
        /** @var DBHTMLText $field */
        $field = DBField::create_field('HTMLFragment', $markup);

        $renderer = $this->createMock(ReactRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with('Banner', ['title' => 'Hi'])
            ->willReturn($field);

        Injector::inst()->registerService($renderer, ReactRenderer::class);

        $result = ReactComponentProvider::react_component('Banner', ['title' => 'Hi']);

        $this->assertSame($markup, $result);
    }
}
