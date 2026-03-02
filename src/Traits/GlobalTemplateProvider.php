<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Traits;

use SilverStripe\Control\Director;
use SilverStripe\View\TemplateGlobalProvider;

class GlobalTemplateProvider implements TemplateGlobalProvider
{
    public static function get_template_global_variables()
    {
        return [
            'IsLive' => 'is_live',
            'IsTest' => 'is_test',
            'IsDev' => 'is_dev',
        ];
    }

    public static function is_live(): bool
    {
        return Director::isLive();
    }

    public static function is_dev(): bool
    {
        return Director::isDev();
    }

    public static function is_test(): bool
    {
        return Director::isTest();
    }
}
