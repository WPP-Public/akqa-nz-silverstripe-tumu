<?php

namespace Akqa\SilverStripe\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

class SortableExtension extends Extension
{
    private static array $db = [
        'Sort' => 'Int'
    ];

    private static $default_sort = "Sort ASC";


    public function onBeforeWrite()
    {
        if (!$this->owner->Sort) {
            $this->owner->Sort = $this->owner->getMaxSort() + 1;
        }
    }


    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Sort');
    }


    /**
     * Returns the maximum Sort value for records of the owner's class.
     * Returns 0 when no records exist.
     */
    public function getMaxSort(): int
    {
        $max = $this->owner::get()->max('Sort');

        return $max !== null ? (int) $max : 0;
    }
}