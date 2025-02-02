<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

use stdClass;

class Page
{
    public string $station;
    public string $webpage_url;
    public string $short_path;
    public string $title;
    public string $description;
    public int $timestamp = 0;
    public string $emission = '';
    public string $type;
    public ?string $rss_url = null;
    public stdClass $image;
    /** @var array<Item> $all_items */
    public array $all_items = [];
}
