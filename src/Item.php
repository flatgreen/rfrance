<?php

/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

class Item
{
    public string $webpage_url;
    public string $title;
    public string $description;
    public int $timestamp;
    public string $id; // md5('title') or other */
    public string $mimetype;
    public int $duration;
    public string $emission;
    public ?string $thumbnail = null; // url (src) of the image
    public string $url; // url of the media
    /** @var mixed[] $media */
    public array $media = [];
    public string $web_extractor = 'rfrance';

    /*
    Niveau encodage son, rangé selon mon classement du meilleur au moins bon
    peut être aussi : "null"

    0 est donc la meilleure préférence, cad pour 'id' : '28'
    rem : au 26 jan. 2025, le 22 et 27 semblent plus souvent utilisés

    2026 avril
        RF a changé son site et le preset ne contient plus 'id'
        'id' est présent dans le nom du fichier audio (à la fin -**.ext ou preque à la fin -**-)
        À priori (mais pas toujours), RF semble diffuser le meilleur encodage
    */
    public const PREFERENCE = [
        '28' => 0, // "{id:"28",name:"binaural",encoding:"AAC",bitrate:192,frequency:48,level:"Binaural -16LUFS"}"
        '25' => 1, // "{id:"25",name:"stereo",encoding:"AAC",bitrate:192,frequency:48,level:"-16LUFS (stéréo)"}"
        '16' => 2, // "{id:"16",name:"stereo",encoding:"AAC",bitrate:192,frequency:48,level:"-23LUFS (stéréo)"}"
        '29' => 3, // "{id:"29",name:"5.1",encoding:"AAC",bitrate:192,frequency:48,level:"5.1 -18LUFS"}"
        '27' => 4, // "{id:"27",name:"stereo",encoding:"AAC",bitrate:192,frequency:48,level:"Stéréo -16LUFS (stéréo + compression “FINTER”)"}"
        '30' => 5, // "{id:"30",name:"binaural",encoding:"MP3",bitrate:192,frequency:48,level:"Binaural -16LUFS"}"
        '21' => 6, // "{id:"21",name:"stereo",encoding:"MP3",bitrate:128,frequency:44.1,level:"-15LUFS (stereo)"}"
        '22' => 7  // "{id:"22",name:"stereo",encoding:"MP3",bitrate:128,frequency:44.1,level:"-16LUFS (stéréo + compression “FINTER”)"}"
    ];

}
