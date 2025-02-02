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

/**
 * return mimetype for mp3 and m4a extensions
 *
 * @param string $audio_file
 * @return string
 */
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

/**
 * The function returns the complete first value of $haystack that contains a part of $needle. Sensitive case.
 *
 * https://www.php.net/manual/fr/function.array-search.php#90711
 *
 * @param string $needle the string to find
 * @param string[] $haystack the array to search in (values)
 * @return mixed[] [$key, $value] (like a tuple, usefull with list()) else [null, null]
 */
function array_find(string $needle, array $haystack)
{
    foreach ($haystack as $k => $item) {
        if (strpos($item, $needle) !== false) {
            return [$k, $item];
        }
    }
    return [null, null];
}

/**
 * from javascript object inline return a structured array
 *
 * @param string $js_object a.prev="MjA=";a.next="NjA=";b.model="ManifestationAudio";b.title="Le Momocon";
 * @return array<string, array<string, string>>
 *  ['a' => ['prev' => 'MjA, 'next' => 'NjA'],
 *   'b' => ['model' => 'ManifestationAudio', 'title' => 'Le Momocon']]
 */
function from_js_obj_to_array(string $js_object): array
{
    $items_object = explode(';', $js_object);
    $items_array = [];
    foreach($items_object as $i_obj) {
        list($i, $property) = explode('.', $i_obj, 2);
        list($key, $value) = explode('=', $property, 2);
        $items_array[$i][$key] = trim($value, '"');
    }
    return $items_array;
}

/**
 * from dict {aa:"bb",cc:"dd"} to [aa => (sting)bb, cc => (string)dd]
 *
 * @param string $dict
 * @return string[]
 */
function from_js_dict_to_array(string $dict): array
{
    if (trim($dict, '"') == 'null') {
        return [];
    }
    $brut = trim($dict, '{}');
    $pairs = explode(',', $brut);
    foreach($pairs as $v) {
        list($i, $value) = explode(':', $v, 2);
        $final[$i] = trim($value, '"');
    }
    return $final;
}

/**
 * return the last part of an url (remove query)
 */
function short_path(string $url): string
{
    return basename((string)parse_url($url, PHP_URL_PATH));
}

/**
 * Extract ITEMA id from url (media)
 *
 * @param string $media_url
 * @return string|null
 */
function get_itema($media_url)
{
    $reg = preg_match('/-ITEMA_(\d{8}-.{15})/', $media_url, $matches);
    if ($reg == 1 && isset($matches[1])) {
        return $matches[1];
    } else {
        return null;
    }
}
