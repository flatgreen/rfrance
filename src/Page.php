<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flatgreen\RFrance;

use stdClass;
use Symfony\Component\DomCrawler\Crawler;

class Page
{
    public string $station;
    public string $webpage_url;
    public string $short_path;
    public string $title;
    public string $description;
    public int $timestamp;
    public string $emission = '';
    public string $type;
    public ?string $rss_url = null;
    public stdClass $image;
    /** @var array<Item> $all_items */
    public array $all_items = [];

    private Crawler $crawler;


    public function __construct(Crawler $crawler)
    {
        $this->crawler = $crawler;
    }



    /**
     * @param mixed[] $graph
     */
    public function setPageRadioEpisode(array $graph): void
    {
        $radio_episode = $graph['RadioEpisode'];
        $this->type = 'RadioEpisode';
        $this->station = $graph['NewsArticle']['publisher']['name'];
        $this->emission = $radio_episode['partOfSeries']['name'];
        $this->title = html_entity_decode($radio_episode['name']);
        $this->description = html_entity_decode($radio_episode['description']);
        $this->image = $this->extractImage($radio_episode);
        ;
        $this->timestamp = strtotime($radio_episode['dateCreated']);
    }

    /**
     * @param mixed[] $graph
     */
    public function setPageSeries(array $graph): void
    {
        $graph_web_page = $graph['WebPage'];
        $this->station = $graph_web_page['mainEntity']['sourceOrganization']['name'];
        $this->type = $graph_web_page['mainEntity']['@type'];
        $this->title = html_entity_decode($graph_web_page['mainEntity']['name']);
        // 'emission' pour PodcastSeries est trouvée dans getAllItemsFromSerie
        if ($this->type == 'RadioSeries') {
            $this->emission = $this->title;
        }
        $this->description = html_entity_decode($graph_web_page['mainEntity']['abstract'] ?? '');
        $this->image = $this->extractImage($graph_web_page['mainEntity']);
        $this->timestamp = strtotime($this->crawler->filter('meta[property="article:published_time"]')->attr('content') ?: '') ?: 0;
    }




    /**
     * Use  the graph to find an image in meta tags
     *
     * image dans les @graph depuis nov. 2024 et plus dans les meta pour les series
     *
     * @param array<mixed> $graph_within_image
     * @return stdClass $image
     */
    private function extractImage(array $graph_within_image)
    {
        $image = new stdClass();
        // $image_crawler = $this->crawler->filter('meta[property="og:image"]');
        // if ($image_crawler->count() > 0) {
        //     $image->src = $this->crawler->filter('meta[property="og:image"]')->attr('content');
        //     $image->height = (int) $this->crawler->filter('meta[property="og:image:height"]')->attr('content');
        //     $image->width = (int) $this->crawler->filter('meta[property="og:image:width"]')->attr('content');
        // }

        if (isset($graph_within_image['image'])) {
            $image->src = $graph_within_image['image']['url'];
            $image->height = $graph_within_image['image']['height'];
            $image->width = $graph_within_image['image']['width'];
        }
        return $image;
    }
}
