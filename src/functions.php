<?php

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
