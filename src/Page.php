<?php

namespace Flatgreen\RFrance;

use stdClass;

class Page extends AbstractData
{
    public string $type;
    /** @var array<Item> $all_items */
    public array $all_items = [];
}
