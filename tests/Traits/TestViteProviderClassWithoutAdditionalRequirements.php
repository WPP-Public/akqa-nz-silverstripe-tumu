<?php

namespace Akqa\SilverStripe\Tests\Traits;

use Akqa\SilverStripe\Traits\ViteProvider;
use SilverStripe\Control\Controller;

class TestViteProviderClassWithoutAdditionalRequirements extends Controller
{
    use ViteProvider;
}
