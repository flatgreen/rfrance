<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Flatgreen\RFrance;

// use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
// use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\ItemInterface;

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
    public const RADIOFRANCE_HOST = 'www.radiofrance.fr';
    private const USER_AGENT = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/124.0';

    private Crawler $crawler;
    private FilesystemAdapter $cache;

    public Page $page;
    private string $html;
    public string $error = '';
    private int $max_items;

    /** @var array<string> $excluded_end_urls */
    private array $excluded_end_urls = [
        'podcasts',
        'grille-programmes'
    ];

    /**
     * @param string|null $cache_directory
     * @param integer $cache_defaultLifetime in second for pages (default : 1 day)
     */
    public function __construct(string $url, ?Crawler $crawler = null, ?string $cache_directory = null, int $cache_defaultLifetime = 86400)
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException($this->error);
        }

        $this->cache = new FilesystemAdapter('rfrance', $cache_defaultLifetime, $cache_directory);

        if (null !== $crawler) {
            $this->crawler = $crawler;
            $this->html = $crawler->html();
        } else {
            $this->crawler = $this->getCrawler($url);
        }
        $this->page = new Page($this->crawler);
        $this->page->webpage_url = $url;
    }

    /**
     * From the actual webpage_url create|set a Crawler and html
     *
     * @param string $url
     * @throws \Exception
     * @return Crawler
     */
    private function getCrawler(string $url): Crawler
    {
        $html = $this->getHtml($url);
        if ($html === false) {
            $this->error = 'Le scraping a échoué : ' . $url;
            throw new \Exception($this->error);
        }
        $this->html = $html;
        return $this->crawler = new Crawler($this->html);
    }

    /**
     * Get Html from url, cached
     *
     * @param string $url
     * @return string|false
     */
    private function getHtml(string $url)
    {
        /** @var string|false $html */
        $html = $this->cache->get(md5($url), function (ItemInterface $item) use ($url) {
            $opts = ['http' => ['header' => self::USER_AGENT]];
            $context = stream_context_create($opts);
            $html = @file_get_contents($url, false, $context);
            return $html;
        });
        return $html;
    }

    /**
     * Merge all <script type="application/ld+json"> in an array, return a special '@graph'
     *
     * @return array<mixed> The keys are the value of @type in @graph
     */
    private function extractGraphFromScriptsJson(): array
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
        return $ar_combine;
    }

    private function isValidUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error = 'url non valide';
            return false;
        }

        if (parse_url($url, PHP_URL_HOST) !== self::RADIOFRANCE_HOST) {
            $this->error = 'Pas un site de RF';
            return false;
        }

        if (in_array(short_path($url), $this->excluded_end_urls)) {
            $this->error = 'Page de RF non prise en charge';
            return false;
        }
        return true;
    }


    /**
     * parse, extract all good informations.
     *
     * @param int $max_items max number of items (approx.), -1 for all items
     * @return bool
     */
    public function extract(int $max_items = -1)
    {
        $this->max_items = $max_items;

        $this->page->setRssUrl();
        $this->page->short_path = short_path($this->page->webpage_url);

        // on récupère les <script type="application/ld+json">, on merge, on récupère @graph
        $graph = $this->extractGraphFromScriptsJson();

        // un seul épisode
        if (in_array('RadioEpisode', array_keys($graph))) {
            $this->page->setPageRadioEpisode($graph);
            $item = new Item();
            $item->setItemFromGraph($this->page->webpage_url, $graph['RadioEpisode']);
            $this->page->all_items[] = $item;
            return true;
        }

        // cas 'WebPage', selon mainEntity@type :
        // - 'RadioSeries' c'est une émission de radio (liste épisodes ou de saison)
        // - 'PodcastSeries' une liste d'épisode (genre 'série' ou podcast seul)
        if (in_array('WebPage', array_keys($graph))) {
            if (isset($graph['WebPage']['mainEntity'])) {
                $this->page->setPageSeries($graph);
                return $this->getAllItemsFromSerie();
            } else {
                $this->page->type = 'WebPage';
                $this->page->title = $graph['WebPage']['name'];
                $this->page->description = $graph['WebPage']['description'];
                return true;
            }
        }

        // pas recherché : 'NewsArticle' (dans $type) seul, c'est un article de blabla
        $this->error = 'Pas de donnée extraite (pas d\'audio ?)';
        return false;
    }

    /**
     * Populate $all_items[] from serie page
     */
    private function getAllItemsFromSerie(): bool
    {
        $num_page = 1;
        $a_next = '';

        // +sieurs pages ? => On repère dans $html (c'est dans le dernier 'script') le "a.next=...;"
        // si c'est "null" (string), c'est une seule page (ou pas de page suivante)
        // sinon, on scrape $url?p=2, on prend les items, et on recommence jusqu'à la limite max_items.

        while ($a_next !== 'null') {
            // on scrape la page (la page "1" est dejà dans $this->html)
            if ($num_page !== 1) {
                $html = $this->getHtml($this->page->webpage_url . '?p=' . $num_page);
                if ($html === false) {
                    $this->error = 'Le scraping de la page ' . $num_page . ' a échoué';
                    return false;
                } else {
                    $this->html = $html;
                }
            }

            // Toutes les infos sont dans la page html (en bas :-)), on va chercher les infos
            // et reconstruire un tableau complet de 'playerInfo'

            // on prend entre ';a.prev=' et ';return'
            preg_match('/(a\.prev=.+);return/', $this->html, $matches);
            if (!isset($matches[1])) {
                $this->error = 'Pas d\'information extraite (oups !)';
                return false;
            }

            // transformation du retour de la regexp en tableau
            $items_array = from_js_obj_to_array($matches[1]);
            // on vire le 'a', il contient les infos de navigation (qui ne sont pas utilisées ici)
            unset($items_array['a']);

            $items_array_json = [];
            foreach($items_array as $k => $v) {
                // on transforme le 'preset' en array
                $v['preset'] = from_js_dict_to_array($v['preset']);
                // tout en json pour la futur intégration
                $json = json_encode($v);
                $items_array_json[$k] = ($json === false) ? '' : $json;
            }

            // on prend les 'playerInfo'
            $matches = [];
            preg_match('/a\.items=\[(.+)\];a\.prev/', $this->html, $matches);
            if (!isset($matches[1])) {
                $this->error = 'Pas trouvé les infos (playerInfo) (page: ' . $num_page . ')';
                return false;
            }
            $big_info = $matches[1];
            $big_info = str_replace('void 0', 'null', $big_info);

            $matches = [];
            $ret = preg_match_all('/\[((\w,?)+)\]/', $big_info, $matches, PREG_OFFSET_CAPTURE);
            if ($ret === false) {
                $this->error = 'Erreur analyse (position media) page:' . $num_page;
                return false;
            }
            // le résultat intéressant est dans la capture $matches[1], et dans ce array :
            // 0 : la liste de lettres si 2 audio ex: 'b,c'
            // 1 : l'offset dans $big_info
            $offset = 0;
            $delta = 0;
            // l'intégration de $items_array dans $big_info
            foreach($matches[1] as $m1) {
                $offset = $m1[1] + $delta;
                $letters = explode(',', $m1[0]);
                $letters_json = [];
                foreach($letters as $one_letter) {
                    $letters_json[] = $items_array_json[$one_letter];
                };
                $new_in = implode(',', $letters_json);
                $len_m1_0 = strlen($m1[0]);
                $big_info = substr_replace($big_info, $new_in, $offset, $len_m1_0);
                $delta = $delta + strlen($new_in) - $len_m1_0;
            }
            // parfois un épisode n'est pas près, on arrange
            $big_info = preg_replace('/manifestations:\w/', 'manifestation:"null"', $big_info);
            // pour une list de 'playerInfo'
            $big_info = '[' . $big_info . ']';
            // file_put_contents('cache/aa.json', $big_info);

            try {
                $big_info_array = json5_decode($big_info, true);
            } catch (\Throwable $th) {
                $this->error = $th->getMessage() . ' on line: ' . $th->getLine();
                return false;
            }

            // au passage, le nom de l'émission :
            $this->page->emission = $big_info_array[0]['tracking']['emission'];

            // on assigne pour chaque 'playerInfo' contenant 'manifestations'
            foreach($big_info_array as $info) {
                if (!empty($info['manifestations'])) {
                    $item = new Item();
                    $item->setItemFromPlayerInfo($this->page->webpage_url, $info);
                    $this->page->all_items[] = $item;
                }
            }

            // limite ?
            if ($this->max_items == -1 || count($this->page->all_items) <= $this->max_items) {
                $a_next = $this->getNext();
            } else {
                $a_next = 'null';
            }
            // si le query contient genre ...?p=3 on ne scrape qu'une page
            if (strpos((string)parse_url($this->page->webpage_url, PHP_URL_QUERY), 'p=') !== false) {
                $a_next = 'null';
            }

            ++$num_page;
        }
        return true;
    }

    /**
     * From current html retrieve a.next ex: 'a.next="MjA=";' return "MjA=" or 'null'
     */
    private function getNext(): string
    {
        // $html = $this->crawler->html();
        preg_match('/a\.next=(.{4,6});/', $this->html, $matches);
        $a_next = $matches[1] ?? 'null';
        $a_next = trim($a_next, '"');
        return $a_next;
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
        if ($this->page->type == 'RadioSeries') {
            $rssfeed .= '<channel>' . "\n" . '<title>' . hexa_encoding($this->page->emission) . '</title>'. "\n";
        } else {
            $rssfeed .= '<channel>' . "\n" . '<title>' . hexa_encoding($this->page->title) . '</title>'. "\n";
        }
        $rssfeed .= '<link>' . $this->page->webpage_url . '</link>' . "\n";
        $rssfeed .= '<description>' . hexa_encoding($this->page->description) . '</description>' . "\n";
        $rssfeed .= '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
        $rssfeed .= '<pubDate>' . date(DATE_RSS, $this->page->timestamp) . '</pubDate>' . "\n";
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
        $json['title'] = ($this->page->type == 'RadioSeries') ? $this->page->emission : $this->page->title;
        $json['description'] = $this->page->description;
        $json['channel_url'] = $this->page->webpage_url;
        $json['webpage_url'] = $this->page->webpage_url;
        $json['uploader'] = $this->page->station . ' - ' . $this->page->emission;
        $json['playlist_count'] = $nb_items;
        $json['_type'] = 'playlist';
        foreach($this->page->all_items as $entrie) {
            $json['entries'][] = $entrie;
        }
        $json_encode = json_encode($json);
        return ($json_encode === false) ? '' : $json_encode;
    }

}
