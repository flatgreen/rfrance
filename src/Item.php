<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

class Item extends AbstractData
{
    /** @var string $id = md5('title') */
    public string $id;
    public string $mimetype;
    public int $duration;
    /** @var string $playlist maybe partOfSeries */
    public string $playlist;
    /** @var string $thumbnail the src of the image */
    public string $thumbnail;
    /** @var string $url url of the media */
    public string $url;

}
