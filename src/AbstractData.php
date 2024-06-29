<?php

namespace Flatgreen\RFrance;

use stdClass;

abstract class AbstractData
{
    public string $webpage_url;
    public string $title;
    public string $description;
    public int $timestamp;
    public stdClass $image;

    public function __construct()
    {
        $this->image = new stdClass();
    }
}
