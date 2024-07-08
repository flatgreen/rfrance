<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

/**
 * from 'P0Y0M0DT0H54M38S' to second
 *
 * @param string $duration_iso
 * @return integer
 */
function duration_ISO_to_timestamp(string $duration_iso): int
{
    $duration_date = new \DateInterval($duration_iso);
    $duration = date_create('@0')->add($duration_date)->getTimestamp();
    return $duration;
}

function get_audio_mimetype(string $audio_file): string
{
    $extension = pathinfo($audio_file, PATHINFO_EXTENSION);
    switch ($extension) {
        case 'mp3':
            return 'audio/mpeg';
        case 'm4a':
            return 'audio/mp4';
        default:
            return 'application/octet-stream';
    }
}

function hexa_encoding(string $txt): string
{
    return str_replace(['<', '>', '&'], ['&#x3C;', '&#x3E;', '&#x26;'], $txt);
}

// From https://symfony.com/doc/current/components/cache/cache_items.html
// The key of a cache item is a plain string which acts as its identifier, so it must be unique for each cache pool. You can freely choose the keys, but they should only contain letters (A-Z, a-z), numbers (0-9) and the _ and . symbols. Other common symbols (such as { } ( ) / \ @ :) are reserved by the PSR-6 standard for future uses.

// base64 use '/' and '+'
// insired by https://www.php.net/manual/en/function.base64-encode.php#123098

function base64_encode_key(string $string): string
{
    return str_replace(['+','/'], ['-','_'], base64_encode($string));
}

function base64_decode_key(string $string): string
{
    return base64_decode(str_replace(['-','_'], ['+','/'], $string));
}
