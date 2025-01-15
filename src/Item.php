<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

use Symfony\Component\DomCrawler\UriResolver;

class Item
{
    public string $webpage_url;
    public string $title;
    public string $description;
    public int $timestamp;
    /** @var string $id = md5('title') or other */
    public string $id;
    public string $mimetype;
    public int $duration;
    /** @var string $playlist maybe partOfSeries */
    public string $playlist;
    /** @var string $thumbnail the url (src) of the image */
    public ?string $thumbnail = null;
    /** @var string $url url of the media */
    public string $url;
    /** @var mixed[] $media */
    public array $media = [];

    /*
    Niveau encodage son:
    "{id:"28",name:"binaural",encoding:"AAC",bitrate:192,frequency:48,level:"Binaural -16LUFS"}"
    "{id:"25",name:"stereo",encoding:"AAC",bitrate:192,frequency:48,level:"-16LUFS (stéréo)"}"
    "{id:"16",name:"stereo",encoding:"AAC",bitrate:192,frequency:48,level:"-23LUFS (stéréo)"}"
    "{id:"29",name:"5.1",encoding:"AAC",bitrate:192,frequency:48,level:"5.1 -18LUFS"}"
    "{id:"30",name:"binaural",encoding:"MP3",bitrate:192,frequency:48,level:"Binaural -16LUFS"}"
    "{id:"21",name:"stereo",encoding:"MP3",bitrate:128,frequency:44.1,level:"-15LUFS (stereo)"}"
    "null"

    '0' est la meilleure préférence, cad pour ['id' => '28']
    */
    public const PREFERENCE = ['28' => '0', '25' => '1', '16' => '2', '29' => '3', '30' => '4', '21' => '5'];

    /**
     * Set Item from the good graph
     *
     * @param string $webpage_url
     * @param mixed[] $episode_graphe
     */
    public function setItemFromGraph(string $webpage_url, array $episode_graphe): void
    {
        $this->webpage_url = $webpage_url;
        $this->title = html_entity_decode($episode_graphe['name']);
        $this->id = md5($this->title);
        $this->description = html_entity_decode($episode_graphe['description']);
        $this->timestamp = strtotime($episode_graphe['dateCreated']);
        $this->thumbnail = $episode_graphe['image']['url'];
        $this->playlist = $episode_graphe['partOfSeries']['name'];
        if (isset($episode_graphe['mainEntity'])) {
            $this->url = $episode_graphe['mainEntity']['contentUrl'];
            $this->mimetype = $episode_graphe['mainEntity']['encodingFormat'];
            $this->duration = duration_ISO_to_timestamp($episode_graphe['mainEntity']['duration']);
        }
    }

    /**
     * @param string $page_webpage_url
     * @param mixed[] $player_info
     */
    public function setItemFromPlayerInfo(string $page_webpage_url, array $player_info): void
    {

        $this->title = $player_info['title'];
        $this->description = trim($player_info['description']);
        $this->id = $player_info['id'];
        $this->playlist = $player_info['playerInfo']['playerMetadata']['firstLine'] ?? '';
        $this->thumbnail = $player_info['visual']['src'];
        $this->webpage_url = UriResolver::resolve($player_info['link'], $page_webpage_url);
        $this->duration = (int) $player_info['manifestations'][0]['duration'];
        $this->timestamp = (int) $player_info['manifestations'][0]['created'];
        foreach($player_info['manifestations'] as $k => $medium) {
            $this->media[] = [
                'url' => $medium['url'],
                'preset' => $medium['preset'],
                'mimetype' => get_audio_mimetype($medium['url'])
            ];
            if (!empty($medium['preset']) || $medium['preset'] == 'null') {
                if (key_exists($medium['preset']['id'], Item::PREFERENCE)) {
                    $this->media[$k]['preset']['preference'] = Item::PREFERENCE[$medium['preset']['id']];
                }
            }
        }
        // range les média par préférence (voir Item)
        usort($this->media, function ($a, $b) {
            if (isset($a['preset']['preference']) && isset($b['preset']['preference'])) {
                return $a['preset']['preference'] <=> $b['preset']['preference'];
            } else {
                return 0;
            }
        });
        // pour 'url' on prend le premier
        $this->url = $this->media[0]['url'];
        $this->mimetype = $this->media[0]['mimetype'];
    }
}
