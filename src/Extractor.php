<?php

namespace Flatgreen\RFrance;

use stdClass;
use Symfony\Component\DomCrawler\Crawler;

class Extractor
{
    private Crawler $crawler;
    private string $html;
    /** @var mixed[] $graph */
    private array $graph;

    public function __construct(Crawler $crawler, string $html)
    {
        $this->crawler = $crawler;
        $this->html = $html;
    }

    /**
     * recherche (basique) d'un flus rss, on ne sait jamais...
     *
     * ex : \<link rel="alternate" title="À v ..." href="https://radiofran...0351.xml" type="application/rss+xml">
     */
    public function getRssUrl(): ?string
    {
        $crawler_rss = $this->crawler->filter('link[rel="alternate"]');
        return ($crawler_rss->count() > 0) ? $crawler_rss->attr('href') : null;
    }

    /**
      * Merge all \<script type="application/ld+json"> in an array, return a special '@graph'
      *
      * @return array<mixed> The keys are the value of @type in @graph
      */
    public function getGraphFromScriptJson(): array
    {
        $scripts_json = $this->crawler->filter('script[type="application/ld+json"]')->extract(['_text']);

        $all_scripts_decode = [];
        foreach ($scripts_json as $script_json) {
            $ar_decode = json_decode($script_json, true);
            $all_scripts_decode = array_merge_recursive($all_scripts_decode, $ar_decode);
        }
        $ar_filter = array_filter($all_scripts_decode['@graph'], function ($v) {
            return ($v['@type'] !== 'BreadcrumbList');
        });
        $ar_combine = array_combine(array_column($ar_filter, '@type'), $ar_filter);
        return $this->graph = $ar_combine;

    }

    /**
     * Set Page from the good graph for one radio episode
     *
     * @param Page $page
     * @return Page
     */
    public function setPageRadioEpisode(Page $page): Page
    {
        if (empty($this->graph)) {
            $this->graph = $this->getGraphFromScriptJson();
        }

        $radio_episode = $this->graph['RadioEpisode'];
        // $page->webpage_url = $this->graph['NewsArticle']['mainEntityOfPage']['@id'];
        $page->type = 'RadioEpisode';
        $page->station = $this->graph['NewsArticle']['publisher']['name'];
        $page->emission = trim($radio_episode['partOfSeries']['name']);
        $page->title = trim(html_entity_decode($radio_episode['name']));
        $page->description = trim(html_entity_decode($radio_episode['description']));
        $page->image = $this->extractImage($radio_episode);
        ;
        $page->timestamp = strtotime($radio_episode['dateCreated']);
        return $page;
    }

    /**
     * Set Item (with all properties) from the good graph for one radio episode
     *
     * @param Item $item
     * @return Item
     */
    public function setItemRadioEpisode(Item $item): Item
    {
        if (empty($this->graph)) {
            $this->graph = $this->getGraphFromScriptJson();
        }
        $episode_graphe = $this->graph['RadioEpisode'];
        $item->webpage_url = $this->graph['NewsArticle']['mainEntityOfPage']['@id'];
        $item->title = trim(html_entity_decode($episode_graphe['name']));
        $item->id = md5($item->title);
        $item->description = trim(html_entity_decode($episode_graphe['description']));
        $item->timestamp = strtotime($episode_graphe['dateCreated']);
        $item->thumbnail = $episode_graphe['image']['url'];
        $item->playlist = trim($episode_graphe['partOfSeries']['name']);
        if (isset($episode_graphe['mainEntity'])) {
            $item->url = $episode_graphe['mainEntity']['contentUrl'];
            $item->mimetype = $episode_graphe['mainEntity']['encodingFormat'];
            $item->duration = duration_ISO_to_timestamp($episode_graphe['mainEntity']['duration']);
        }
        return $item;
    }

    /**
     * Use  the graph to find an image in meta tags
     *
     * image dans les @graph depuis nov. 2024 et pas/plus dans les meta pour les series
     *
     * @param array<mixed> $graph_within_image
     * @return stdClass $image
     */
    private function extractImage(array $graph_within_image)
    {
        $image = new stdClass();
        if (isset($graph_within_image['image'])) {
            $image->src = $graph_within_image['image']['url'];
            $image->height = $graph_within_image['image']['height'];
            $image->width = $graph_within_image['image']['width'];
        }
        return $image;
    }


    /**
     * Set all informations for a page if type "series" (RadioSeries or PodcastSeries)
     *
     * @param Page $page
     */
    public function setPageSeries(Page $page): Page
    {
        $graph_web_page = $this->graph['WebPage'];
        $page->station = $graph_web_page['mainEntity']['sourceOrganization']['name'];
        $page->type = $graph_web_page['mainEntity']['@type'];
        $page->title = html_entity_decode($graph_web_page['mainEntity']['name']);
        // TODO in big info ?
        // 'emission' pour PodcastSeries est trouvée dans getAllItemsFromSerie
        if ($page->type == 'RadioSeries') {
            $page->emission = $page->title;
        }
        $page->description = html_entity_decode($graph_web_page['mainEntity']['abstract'] ?? '');
        $page->image = $this->extractImage($graph_web_page['mainEntity']);
        // TODO with big info ?
        $filter_publish = $this->crawler->filter('meta[property="article:published_time"]');
        if ($filter_publish->count() > 0) {
            $page->timestamp = strtotime($filter_publish->attr('content') ?? '') ?: 0;
        }

        return $page;
    }
}
