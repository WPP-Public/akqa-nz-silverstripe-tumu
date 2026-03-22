<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\Extensions;

use SilverStripe\Dev\SapphireTest;

class SortableExtensionTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        SortableTestObject::class,
    ];

    protected $usesDatabase = true;

    public function testGetMaxSortReturnsZeroWhenNoRecords(): void
    {
        $obj = new SortableTestObject();
        $obj->Title = 'First';
        $obj->write();

        $this->assertSame(1, $obj->Sort);
        $this->assertSame(1, $obj->getMaxSort());
    }

    public function testGetMaxSortReturnsMaxWhenRecordsExist(): void
    {
        $obj1 = SortableTestObject::create(['Title' => 'First']);
        $obj1->write();

        $obj2 = SortableTestObject::create(['Title' => 'Second']);
        $obj2->write();

        $obj3 = SortableTestObject::create(['Title' => 'Third']);
        $obj3->write();

        $this->assertSame(1, $obj1->Sort);
        $this->assertSame(2, $obj2->Sort);
        $this->assertSame(3, $obj3->Sort);

        $this->assertSame(3, $obj1->getMaxSort());
        $this->assertSame(3, $obj2->getMaxSort());
        $this->assertSame(3, $obj3->getMaxSort());
    }

    public function testOnBeforeWriteSetsSortWhenNotSet(): void
    {
        $obj = SortableTestObject::create(['Title' => 'Auto Sort']);
        $this->assertNull($obj->Sort);

        $obj->write();

        $this->assertSame(1, $obj->Sort);
    }

    public function testOnBeforeWritePreservesExplicitSort(): void
    {
        $obj = SortableTestObject::create([
            'Title' => 'Explicit Sort',
            'Sort' => 42,
        ]);
        $obj->write();

        $this->assertSame(42, $obj->Sort);
    }
}
