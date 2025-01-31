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


    // /**
    //  * @param mixed[] $graph
    //  */
    // public function setPageSeries(array $graph): void
    // {
    //     $graph_web_page = $graph['WebPage'];
    //     $this->station = $graph_web_page['mainEntity']['sourceOrganization']['name'];
    //     $this->type = $graph_web_page['mainEntity']['@type'];
    //     $this->title = html_entity_decode($graph_web_page['mainEntity']['name']);
    //     // 'emission' pour PodcastSeries est trouvée dans getAllItemsFromSerie
    //     if ($this->type == 'RadioSeries') {
    //         $this->emission = $this->title;
    //     }
    //     $this->description = html_entity_decode($graph_web_page['mainEntity']['abstract'] ?? '');
    //     // FIXME à vérifeir pour les tests
    //     // $this->image = $this->extractImage($graph_web_page['mainEntity']);
    //     // FIXME à vérifier pour les tests
    //     // $this->timestamp = strtotime($this->crawler->filter('meta[property="article:published_time"]')->attr('content') ?: '') ?: 0;
    // }



}
