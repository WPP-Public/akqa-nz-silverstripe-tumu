<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\Extensions;

use Akqa\SilverStripe\Extensions\SortableExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test-only DataObject to exercise SortableExtension
 */
class SortableTestObject extends DataObject implements TestOnly
{
    private static string $table_name = 'SortableTestObject';

    private static array $db = [
        'Title' => 'Varchar(255)',
    ];

    private static array $extensions = [
        SortableExtension::class,
    ];
}
