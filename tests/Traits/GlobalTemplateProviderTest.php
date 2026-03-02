<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\Traits;

use Akqa\SilverStripe\Traits\GlobalTemplateProvider;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\TemplateGlobalProvider;

class GlobalTemplateProviderTest extends SapphireTest
{
    public function testImplementsTemplateGlobalProvider(): void
    {
        $this->assertInstanceOf(TemplateGlobalProvider::class, new GlobalTemplateProvider());
    }

    public function testGetTemplateGlobalVariablesReturnsExpectedKeys(): void
    {
        $vars = GlobalTemplateProvider::get_template_global_variables();

        $this->assertIsArray($vars);
        $this->assertArrayHasKey('IsLive', $vars);
        $this->assertArrayHasKey('IsTest', $vars);
        $this->assertArrayHasKey('IsDev', $vars);
        $this->assertSame('is_live', $vars['IsLive']);
        $this->assertSame('is_test', $vars['IsTest']);
        $this->assertSame('is_dev', $vars['IsDev']);
    }

    public function testIsTestDelegatesToDirector(): void
    {
        $this->assertSame(Director::isTest(), GlobalTemplateProvider::is_test());
    }

    public function testIsLiveDelegatesToDirector(): void
    {
        $this->assertSame(Director::isLive(), GlobalTemplateProvider::is_live());
    }

    public function testIsDevDelegatesToDirector(): void
    {
        $this->assertSame(Director::isDev(), GlobalTemplateProvider::is_dev());
    }
}
