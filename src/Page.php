<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

class Page extends AbstractData
{
    public string $type;
    /** @var array<Item> $all_items */
    public array $all_items = [];
}
