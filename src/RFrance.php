<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Flatgreen\RFrance;

use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * RFrance a class to scrape and parse r a d i o f r a n c e
 *
 * > $rf = new RFrance();
 * >
 * > $rf->extract(URL);
 * >
 * > echo $rf->page->
 * >
 * > echo $rf->page->all_items.
 *
 */
class RFrance
{
    private int $max_items;
    private \Symfony\Component\DomCrawler\Crawler $crawler;
    private \Symfony\Component\Cache\Adapter\FilesystemAdapter $cache;
    public string $error = '';

    public Page $page;

    public const RADIOFRANCE_BASE_URL = 'https://www.radiofrance.fr/';

    /** @var array<string> */
    private array $excluded_end_urls = [
        'podcasts',
        'grille-programmes'
    ];


    public function __construct(?string $cache_directory = null, int $defaultLifetime = 0)
    {
        $this->page  = new Page();
        $this->cache = new FilesystemAdapter('rfrance', $defaultLifetime, $cache_directory);
    }

    /**
     * Get Html from url
     *
     * @param string $url
     * @return string|false
     */
    private function getHtml(string $url)
    {
        /** @var string|false $html */
        $html = $this->cache->get(md5($url), function (ItemInterface $item) use ($url) {
            // $item->expiresAfter(86400);
            $opts = ['http' => [
                'header' => "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/124.0"]
            ];
            $context = stream_context_create($opts);
            $html = @file_get_contents($url, false, $context);
            return $html;
        });
        return $html;
    }

    /**
     * Merge all <script type="application/ld+json"> in an array
     *
     * @return array<mixed>
     */
    private function extractScriptsJson(): array
    {
        $scripts_json = $this->crawler->filter('script[type="application/ld+json"]')->extract(['_text']);

        $all_scripts_decode = [];
        foreach ($scripts_json as $script_json) {
            $ar_decode = json_decode($script_json, true);
            $all_scripts_decode = array_merge_recursive($all_scripts_decode, $ar_decode);
        }
        return $all_scripts_decode;
    }

    /**
     * extract
     *
     * With an url : parse, extract all good informations
     *
     * @param string $url
     * @param bool $force_rss extract if a rss feed exist
     * @return bool
     */
    public function extract(string $url, bool $force_rss = false, int $max_items = -1)
    {
        $this->max_items = $max_items;
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error = 'url non valide';
            return false;
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        if (in_array(basename($path), $this->excluded_end_urls)) {
            $this->error = 'URL non prise en charge';
            return false;
        }

        $this->page->webpage_url = $url;
        $html = $this->getHtml($this->page->webpage_url);
        if ($html === false) {
            $this->error = 'Le scraping a échoué (' . $this->page->webpage_url . ')';
            return false;
        }
        $this->crawler = new Crawler($html);

        if ($force_rss === false) {
            // recherche d'un flus rss, on ne sait jamais...
            // <link rel="alternate" title="À voix nue : podcast et émission en replay | France Culture" href="https://radiofrance-podcast.net/podcast09/rss_10351.xml" type="application/rss+xml">
            $rss_url = $this->crawler->filter('link[rel="alternate"]')->attr('href');
            if (!empty($rss_url)) {
                $this->error = 'Il y a un flux : ' . $rss_url;
                return false;
            }
        }

        // on récupère les <script type="application/ld+json">, on merge
        $scripts_arr = $this->extractScriptsJson();
        // recherche dans ['@graph' => ], il y 3 cas d'intéressant pour '@type' :
        foreach ($scripts_arr['@graph'] as $a_graph) {
            // 'NewsArticle' seul, c'est un article de blabla
            if ($a_graph['@type'] == 'NewsArticle') {
                $this->page->type = 'NewsArticle';
                $this->page->title = html_entity_decode($a_graph['headline']);
                $this->page->description = html_entity_decode($a_graph['description']);
                if (isset($a_graph['image'])) {
                    $this->page->image->src = $a_graph['image']['url'];
                    $this->page->image->width = (int)$a_graph['image']['width'];
                    $this->page->image->height = (int)$a_graph['image']['height'];
                }
            }
            // en complément de 'NewsArticle', un épisode seul
            if ($a_graph['@type'] == 'RadioEpisode') {
                $this->page->type = 'RadioEpisode';
                $item = new Item();
                $item->webpage_url = $this->page->webpage_url;
                $item->title = html_entity_decode($a_graph['name']);
                $item->id = md5($item->title);
                $item->description = html_entity_decode($a_graph['description']);
                $item->timestamp = strtotime($a_graph['dateCreated']);
                $item->image = $this->page->image;
                $item->thumbnail = $item->image->src;
                $item->playlist = $a_graph['partOfSeries']['name'];
                if (isset($a_graph['mainEntity'])) {
                    $item->url = $a_graph['mainEntity']['contentUrl'];
                    $item->mimetype = $a_graph['mainEntity']['encodingFormat'];
                    $item->duration = duration_ISO_to_timestamp($a_graph['mainEntity']['duration']);
                    $this->page->all_items[] = $item;
                } else {
                    $this->error = 'Pas d\'émission';
                    return false;
                }
            }
            // cas 'WebPage' c'est une serie ou une liste de podcasts
            if ($a_graph['@type'] == 'WebPage') {
                if (isset($a_graph['mainEntity'])) {
                    $this->page->type = 'RadioSeries';
                    $this->page->title = html_entity_decode($a_graph['mainEntity']['name']);
                    $this->page->description = html_entity_decode($a_graph['mainEntity']['abstract']);
                    // pas d'image dans les @graph
                    $this->page->image = $this->extractImage();
                    $this->page->timestamp = strtotime($this->crawler->filter('meta[property="article:published_time"]')->attr('content') ?: '') ?: 0;
                    return $this->getAllItemsFromSerie($scripts_arr);
                } else {
                    $this->page->type = 'WebPage';
                    $this->page->title = $a_graph['name'];
                    $this->page->description = $a_graph['description'];
                    return true;
                }
            }
        }

        return true;
    }

    /**
     * getAllItemsFromSerie
     *
     * Populate $all_items[] from serie page
     *
     * @param array<mixed> $scripts_arr all json (ld+json) array manner
     * @return bool
     */
    private function getAllItemsFromSerie(array $scripts_arr)
    {
        $num_page = 1;
        $a_next = '';

        // +sieurs pages ? => On repère dans $dom_parser (c'est dans le dernier 'script') le "a.next=...;"
        // si c'est "null" (string), c'est une seule page (ou pas de page suivante)
        // sinon, on scrape $url?p=2, on prend les items, et on recommence jusqu'à la limite max_items.

        while ($a_next !== 'null') {
            // on scrape le site (la page "1" est dejà dans crawler)
            if ($num_page !== 1) {
                $html = $this->getHtml($this->page->webpage_url . '?p=' . $num_page);
                if ($html === false) {
                    $this->error = 'Le scraping de la page ' . $num_page . ' a échoué';
                    return false;
                }
                $this->crawler = new Crawler($html);
            }
            // on prend récolte les urls dans le @graph : @type => ItemList
            // c'est (s'il existe) itemListElement[i]['url]
            $url = [];
            foreach ($scripts_arr['@graph'] as $a_graph) {
                if ($a_graph['@type'] == 'ItemList') {
                    foreach ($a_graph['itemListElement'] as $item) {
                        if (isset($item['url'])) {
                            $url[] = $item['url'];
                        }
                    }
                }
            }
            // on scrape les items un par un
            foreach ($url as $one_url) {
                $new_rf = new RFrance();
                $new_rf->extract($one_url, true);
                if (empty($new_rf->error)) {
                    $this->page->all_items = array_merge($this->page->all_items, $new_rf->page->all_items);
                } else {
                    $this->error = 'page ' . $num_page . ', pas d\'information extraite - js or json erreur.';
                    return false;
                }
            }
            // limite ?
            if ($this->max_items == -1 || count($this->page->all_items) <= $this->max_items) {
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
     * Use $this->dom_parser to find an image in meta tags
     *
     * @return stdClass $image
     */
    private function extractImage()
    {
        $image = new stdClass();
        $image->src = '';
        $image->width = 0;
        $image->height = 0;

        $image->src = $this->crawler->filter('meta[property="og:image"]')->attr('content');
        $image->height = (int) $this->crawler->filter('meta[property="og:image:height"]')->attr('content');
        $image->width = (int) $this->crawler->filter('meta[property="og:image:width"]')->attr('content');

        return $image;
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
        $rssfeed .= '<image>' . "\n";
        $rssfeed .= "\t" . '<url>' . $this->page->image->src . '</url>' . "\n";
        $rssfeed .= "\t" . '<title>' . hexa_encoding($this->page->title) . '</title>' . "\n";
        $rssfeed .= "\t" . '<description>' . hexa_encoding($this->page->description) . '</description>' . "\n";
        $rssfeed .= "\t" . '<link>' . $this->page->webpage_url . '</link>' . "\n";
        $rssfeed .= "\t" . '<height>' . $this->page->image->height . '</height>' . "\n";
        $rssfeed .= "\t" . '<width>' . $this->page->image->width . '</width>' . "\n";
        $rssfeed .= '</image>' . "\n";

        /** @var Item $item */
        foreach ($this->page->all_items as $item) {
            $rssfeed .= "\t" . '<item>' . "\n";
            $rssfeed .= "\t\t" . '<title>' . hexa_encoding($item->title) . '</title>' . "\n";
            $rssfeed .= "\t\t" . '<link>' . $item->url . '</link>' . "\n";
            $rssfeed .= "\t\t" . '<description><![CDATA[' . $item->description . ']]></description>' . "\n";
            $rssfeed .= "\t\t" . '<pubDate>' . date('r', $item->timestamp) . '</pubDate>' . "\n";
            $rssfeed .= "\t\t" . '<enclosure length="0" url="' . $item->url . '" type="' . $item->mimetype . '" />' . "\n";
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
