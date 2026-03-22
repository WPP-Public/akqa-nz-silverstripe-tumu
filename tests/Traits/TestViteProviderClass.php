<?php

namespace Akqa\SilverStripe\Tests\Traits;

use Akqa\SilverStripe\Traits\ViteProvider;
use SilverStripe\Control\Controller;

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
            'app/client/src/additional.jsx',
            'app/client/src/print.css' => [
                'media' => 'print'
            ]
        ];
    }
}
