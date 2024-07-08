<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Flatgreen\RFrance;

use Psr\Cache\CacheItemInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RFrance a class to scrape and parse r a d i o f r a n c e
 *
 * > $rf = new RFrance(); // cache directory en option
 * >
 * > $rf->extract(URL); // see others params
 * >
 * > echo $rf->page->...
 * >
 * > echo $rf->page->all_items // array of Item
 *
 */
class RFrance
{
    public const RADIOFRANCE_BASE_URL = 'https://www.radiofrance.fr/';
    private const USER_AGENT = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/124.0';

    private HttpClientInterface $client;
    private Crawler $crawler;
    private FilesystemAdapter $cache;
    private int $defaultLifetime;

    public Page $page;
    public string $error = '';
    public string $short_path = '';

    /** @var array<string> */
    private array $excluded_end_urls = [
        'podcasts',
        'grille-programmes'
    ];

    /**
     * @param string|null $cache_directory
     * @param integer $defaultLifetime in second for pages (default : 1 day)
     */
    public function __construct(?string $cache_directory = null, int $defaultLifetime = 86400)
    {
        $this->client = HttpClient::create(['headers' => ['User-Agent' => self::USER_AGENT],]);
        $this->page  = new Page();
        $this->cache = new FilesystemAdapter('rfrance', 0, $cache_directory);
        $this->defaultLifetime = $defaultLifetime;
    }

    /**
     * Get Html from url, cache with defaultLifetime
     *
     * @param string $url
     * @return string|false
     */
    private function getHtml(string $url)
    {
        $ttl = $this->defaultLifetime;
        /** @var string|false $html */
        $html = $this->cache->get(md5($url), function (ItemInterface $item) use ($url, $ttl) {
            $item->expiresAfter($ttl);
            $opts = ['http' => ['header' => self::USER_AGENT]];
            $context = stream_context_create($opts);
            $html = @file_get_contents($url, false, $context);
            return $html;
        });
        return $html;
    }

    /**
     * Get htmls from urls, cache never expires, use cUrl for concurrent download
     *
     * @param string[]|string $urls
     * @return string[] ['url' => 'html']
     */
    private function getHtml2(array|string $urls): array
    {
        $urls_request = [];
        if (is_string($urls)) {
            $urls_request[] = $urls;
        } else {
            $urls_request = $urls;
        }

        $responses = [];
        $reponses_union = [];

        /** @var array<CacheItemInterface> */
        $cache_items = $this->cache->getItems(\array_map(function (string $url) {
            return base64_encode_key($url);
        }, $urls_request));

        /** @var CacheItem $item */
        foreach($cache_items as $item) {
            $id = base64_decode_key($item->getKey()); // this is $url
            if ($item->isHit()) {
                $responses[$id] = $item->get();
            } else {
                $reponses_union[$id] = [
                    $item, // Store cache item in the union
                    $this->client->request('GET', $id)
                ];
            }
        }
        // If we did not have data in cache, fetch it.
        foreach($reponses_union as $id => [$item, $response]) {
            $responses[$id] = $response->getContent(false);
            // no expiration time
            $item->set($responses[$id]);
            $this->cache->saveDeferred($item);
        }

        if (!empty($reponses_union)) {
            $this->cache->commit();
        }
        return $responses;
    }

    /**
     * Merge all <script type="application/ld+json"> in an array, return a special '@graph'
     *
     * @return array<mixed> The keys are the value of @type in @graph
     */
    private function extractGraphFromScriptsJson(Crawler $a_crawler): array
    {
        $scripts_json = $a_crawler->filter('script[type="application/ld+json"]')->extract(['_text']);

        $all_scripts_decode = [];
        foreach ($scripts_json as $script_json) {
            $ar_decode = json_decode($script_json, true);
            $all_scripts_decode = array_merge_recursive($all_scripts_decode, $ar_decode);
        }
        $ar_filter = array_filter($all_scripts_decode['@graph'], function ($v) {
            return ($v['@type'] !== 'BreadcrumbList');
        });
        $ar_combine = array_combine(array_column($ar_filter, '@type'), $ar_filter);
        return $ar_combine;
    }

    /**
     * extract
     *
     * With an url : parse, extract all good informations.
     *
     * Attention : download one page for one item
     *
     * @param string $url
     * @param bool $force_rss extract if a rss feed exist
     * @param int $max_items max number of items (approx.)
     * @return bool
     */
    public function extract(string $url, bool $force_rss = false, int $max_items = -1)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error = 'url non valide';
            return false;
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        $this->short_path = basename($path);
        if (in_array($this->short_path, $this->excluded_end_urls)) {
            $this->error = 'URL non prise en charge';
            return false;
        }

        $html = $this->getHtml($url);
        if ($html === false) {
            $this->error = 'Le scraping a échoué (' . $url . ')';
            return false;
        }

        $this->crawler = new Crawler($html);

        if ($force_rss === false) {
            // recherche d'un flus rss, on ne sait jamais...
            // <link rel="alternate" title="À voix ..." href="https://radiofran...0351.xml" type="application/rss+xml">
            $rss_url = $this->crawler->filter('link[rel="alternate"]')->attr('href', '');
            if (!empty($rss_url)) {
                $this->error = 'Il y a un flux : ' . $rss_url;
                return false;
            }
        }

        // on récupère les <script type="application/ld+json">, on merge, on récupère @graph
        $graph = $this->extractGraphFromScriptsJson($this->crawler);

        if (in_array('RadioEpisode', array_keys($graph))) {
            // un seul épisode|item|émission
            $radio_episode = $graph['RadioEpisode'];
            $this->page->type = 'RadioEpisode';
            $this->page->webpage_url = $url;
            $this->page->title = html_entity_decode($radio_episode['name']);
            $this->page->description = html_entity_decode($radio_episode['description']);
            if (isset($radio_episode['image'])) {
                $this->page->image->src = $radio_episode['image']['url'];
                $this->page->image->width = (int)$radio_episode['image']['width'];
                $this->page->image->height = (int)$radio_episode['image']['height'];
            }
            $item = $this->getEpisodeItem($this->page->webpage_url, $radio_episode);
            $this->page->all_items[] = $item;
            return true;
        }

        if (in_array('WebPage', array_keys($graph))) {
            // cas 'WebPage' c'est une serie ou une liste de podcasts
            $web_page = $graph['WebPage'];
            if (isset($web_page['mainEntity'])) {
                $this->page->type = 'RadioSeries';
                $this->page->webpage_url = $url;
                $this->page->title = html_entity_decode($web_page['mainEntity']['name']);
                $this->page->description = html_entity_decode($web_page['mainEntity']['abstract']);
                // pas d'image dans les @graph
                $this->page->image = $this->extractImage();
                $this->page->timestamp = strtotime($this->crawler->filter('meta[property="article:published_time"]')->attr('content') ?: '') ?: 0;
                return $this->getAllItemsFromSerie($graph, $max_items);
            } else {
                $this->page->type = 'WebPage';
                $this->page->title = $web_page['name'];
                $this->page->description = $web_page['description'];
                return true;
            }
        }

        // pas recherché : 'NewsArticle' (dans $type) seul, c'est un article de blabla
        $this->error = 'Pas de donnée extraite (pas d\'émission ?)';
        return  false;
    }

    /**
     * Extract all Item properties from the good graph
     *
     * @param string $url
     * @param mixed[] $episode_graphe
     * @return Item
     */
    private function getEpisodeItem(string $url, array $episode_graphe)
    {
        $item = new Item();
        $item->webpage_url = $url;
        $item->title = html_entity_decode($episode_graphe['name']);
        $item->id = md5($item->title);
        $item->description = html_entity_decode($episode_graphe['description']);
        $item->timestamp = strtotime($episode_graphe['dateCreated']);
        if (!empty((array)$this->page->image)) {
            $item->image = $this->page->image;
        }
        $item->thumbnail = $item->image->src ?? '';
        $item->playlist = $episode_graphe['partOfSeries']['name'];
        if (isset($episode_graphe['mainEntity'])) {
            $item->url = $episode_graphe['mainEntity']['contentUrl'];
            $item->mimetype = $episode_graphe['mainEntity']['encodingFormat'];
            $item->duration = duration_ISO_to_timestamp($episode_graphe['mainEntity']['duration']);
        }
        return $item;
    }

    /**
     * getAllItemsFromSerie
     *
     * Populate $all_items[] from serie page
     *
     * @param array<mixed> $graph all json (ld+json) array manner
     * @return bool
     */
    private function getAllItemsFromSerie(array $graph, int $max_items)
    {
        $num_page = 1;
        $a_next = '';
        $num_episode = 1;

        // +sieurs pages ? => On repère dans $html (c'est dans le dernier 'script') le "a.next=...;"
        // si c'est "null" (string), c'est une seule page (ou pas de page suivante)
        // sinon, on scrape $url?p=2, on prend les items, et on recommence jusqu'à la limite max_items.

        while ($a_next !== 'null') {
            // on scrape le site (la page "1" est dejà dans crawler)
            // on pourrait prendre la page 1 dans le cache et enlever le param '$graph'
            if ($num_page !== 1) {
                $html = $this->getHtml($this->page->webpage_url . '?p=' . $num_page);
                if ($html === false) {
                    $this->error = 'Le scraping de la page ' . $num_page . ' a échoué';
                    return false;
                }
                $this->crawler = new Crawler($html);
                $graph = $this->extractGraphFromScriptsJson($this->crawler);
            }

            // on prend récolte les urls dans @graph['ItemList']
            $urls = [];
            foreach ($graph['ItemList']['itemListElement'] as $item) {
                if (isset($item['url'])) {
                    $urls[] = $item['url'];
                }
            }

            // on scrape tous les épisodes d'un coup, on traite un par un
            $htmls = $this->getHtml2($urls);
            foreach($htmls as $k_url => $html) {
                $a_crawler = new Crawler($html);
                $a_graph = $this->extractGraphFromScriptsJson($a_crawler);
                $item = $this->getEpisodeItem($k_url, $a_graph['RadioEpisode']);
                // on renumérote (l'info n'est pas dans un @graph, mais dans la page de l'émission)
                $item->title = $num_episode . ' - ' . $item->title;
                $this->page->all_items[] = $item;
                // si pas d'émission, on enlève du cache.
                // permet d'avoir des épisodes pas encore en ligne, mais reprend des vieux sans épisodes
                if (empty($item->url)) {
                    $this->cache->deleteItem(base64_encode_key($k_url));
                }
                ++$num_episode;
            }

            // limite ?
            if ($max_items == -1 || count($this->page->all_items) <= $max_items) {
                $a_next = $this->getNext();
            } else {
                $a_next = 'null';
            }
            ++$num_page;
        }
        return true;
    }

    /**
     * From dom_parser (html) retrieve a.next du type 'a.next="MjA=";' et return "MjA="
     *
     * @return string
     */
    private function getNext()
    {
        $html = $this->crawler->html();
        preg_match('/a\.next=(.{4,6});/', $html, $matches);
        $a_next = $matches[1];
        $a_next = trim($a_next, '"');
        return $a_next;
    }

    /**
     * Use the crawler to find an image in meta tags
     *
     * @return stdClass $image
     */
    private function extractImage()
    {
        $image = new stdClass();
        $image->src = $this->crawler->filter('meta[property="og:image"]')->attr('content', '');
        $image->height = (int) $this->crawler->filter('meta[property="og:image:height"]')->attr('content', '0');
        $image->width = (int) $this->crawler->filter('meta[property="og:image:width"]')->attr('content', '0');
        return $image;
    }

    public function pruneCache(): void
    {
        $this->cache->prune();
    }

    /**
     * From all data create a classic RSS file
     *
     * @return string the feed string
     */
    public function toRss(): string
    {
        $rssfeed = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $rssfeed .= '<rss version="2.0">' . "\n";
        $rssfeed .= '<channel>' . "\n" . '<title>' . hexa_encoding($this->page->title) . '</title>'. "\n";
        $rssfeed .= '<link>' . $this->page->webpage_url . '</link>' . "\n";
        $rssfeed .= '<description>' . hexa_encoding($this->page->description) . '</description>' . "\n";
        $rssfeed .= '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
        $rssfeed .= '<language>fr</language>' . "\n";
        if (!empty((array)$this->page->image)) {
            $rssfeed .= '<image>' . "\n";
            $rssfeed .= "\t" . '<url>' . $this->page->image->src . '</url>' . "\n";
            $rssfeed .= "\t" . '<title>' . hexa_encoding($this->page->title) . '</title>' . "\n";
            $rssfeed .= "\t" . '<description>' . hexa_encoding($this->page->description) . '</description>' . "\n";
            $rssfeed .= "\t" . '<link>' . $this->page->webpage_url . '</link>' . "\n";
            $rssfeed .= "\t" . '<height>' . $this->page->image->height . '</height>' . "\n";
            $rssfeed .= "\t" . '<width>' . $this->page->image->width . '</width>' . "\n";
            $rssfeed .= '</image>' . "\n";
        }

        /** @var Item $item */
        foreach ($this->page->all_items as $item) {
            $rssfeed .= "\t" . '<item>' . "\n";
            $rssfeed .= "\t\t" . '<title>' . hexa_encoding($item->title) . '</title>' . "\n";
            $rssfeed .= "\t\t" . '<link>' . $item->webpage_url . '</link>' . "\n";
            $rssfeed .= "\t\t" . '<description><![CDATA[' . $item->description . ']]></description>' . "\n";
            $rssfeed .= "\t\t" . '<pubDate>' . date('r', $item->timestamp) . '</pubDate>' . "\n";
            if (isset($item->url)) {
                $rssfeed .= "\t\t" . '<enclosure length="0" url="' . $item->url . '" type="' . $item->mimetype . '" />' . "\n";
            }
            $rssfeed .= "\t" . '</item>' . "\n";
        }

        $rssfeed .= '</channel>' . "\n";
        $rssfeed .= '</rss>';

        return $rssfeed;
    }

    /**
     * From all data return json like yt-dlp (or youtube-dl) *.info.json
     *
     * @return string maybe empty if no item or json_encode error
     */
    public function toInfoJson(): string
    {
        $nb_items = count($this->page->all_items);
        if ($nb_items == 0) {
            return '';
        }

        if ($nb_items == 1) {
            $the_item = $this->page->all_items[0];
            $json = json_encode($the_item);
            return ($json === false) ? '' : $json;
        }

        $json = [];
        $json['title'] = $this->page->title;
        $json['description'] = $this->page->description;
        $json['channel_url'] = $this->page->webpage_url;
        $json['webpage_url'] = $this->page->webpage_url;
        $json['playlist_count'] = $nb_items;
        $json['_type'] = 'playlist';
        foreach($this->page->all_items as $entrie) {
            $json['entries'][] = $entrie;
        }
        $json_encode = json_encode($json);
        return ($json_encode === false) ? '' : $json_encode;
    }
}
