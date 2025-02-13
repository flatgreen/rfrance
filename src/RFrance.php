<?php
/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Flatgreen\RFrance;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;
use stdClass;

/**
 * RFrance a class to scrape and parse r a d i o f r a n c e
 */
class RFrance
{
    public const RADIOFRANCE_HOST = 'www.radiofrance.fr';
    private const USER_AGENT = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/124.0';

    private Crawler $crawler;
    private FilesystemAdapter $cache;

    public Page $page;
    private string $html;
    /** @var mixed[] $graph */
    private array $graph;
    public string $error = '';
    private int $max_items;
    private string $webpage_url;

    /** @var array<string> $excluded_end_urls */
    private array $excluded_end_urls = [
        'podcasts',
        'grille-programmes',
    ];

    /**
     * @param string|null $cache_directory
     * @param integer $cache_defaultLifetime in second for pages (default : 1 day)
     */
    public function __construct(?string $cache_directory = null, int $cache_defaultLifetime = 86400)
    {
        $this->cache = new FilesystemAdapter('rfrance', $cache_defaultLifetime, $cache_directory);
    }

    /**
     * If we have already a Symfony\Component\DomCrawler\Crawler,
     * we can use it.
     */
    public function setCrawler(Crawler $crawler): void
    {
        if ($crawler instanceof Crawler) {
            $this->crawler = $crawler;
        }
    }

    /**
     * From the actual webpage_url create|set a Crawler and html
     *
     * @param string $url
     * @throws \Exception (http)
     * @throws \InvalidArgumentException
     * @return Crawler
     */
    private function getCrawler(string $url): Crawler
    {
        if (isset($this->crawler)) {
            return $this->crawler;
        } else {
            if (!$this->isValidUrl($url)) {
                throw new \InvalidArgumentException($this->error);
            }
            $html = $this->getHtml($url);
            if ($html === false) {
                $this->error = 'Le scraping a échoué : ' . $url;
                throw new \Exception($this->error);
            }
            $this->html = $html;
            return new Crawler($this->html);
        }
    }

    /**
     * Get Html from url, cached
     *
     * @return string|false content body
     */
    public function getHtml(string $url)
    {
        /** @var string|false $html */
        $html = $this->cache->get(md5($url), function () use ($url) {
            $opts = ['http' => ['header' => self::USER_AGENT]];
            $context = stream_context_create($opts);
            $html = @file_get_contents($url, false, $context);
            return $html;
        });
        return $html;
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
      * Merge all \<script type="application/ld+json"> in an array, return a special '@graph'
      *
      * @return array<mixed> The keys are the value of @type in @graph
      */
    private function getGraphFromScriptJson(): array
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
     * parse, extract all good informations.
     *
     * @param int $max_items max number of items (approx.), -1 for all items
     * @return bool
     */
    public function extract(string $url, int $max_items = -1)
    {
        $this->crawler = $this->getCrawler($url);
        $this->max_items = $max_items;
        $this->webpage_url = $url;

        $this->page = new Page();
        $this->page->webpage_url = $url;
        $this->page->short_path = short_path($this->webpage_url);

        // un flux rss ?
        $this->page->rss_url = $this->getRssUrl();
        // on récupère les <script type="application/ld+json">
        $graph = $this->getGraphFromScriptJson();

        // un seul épisode
        if (in_array('RadioEpisode', array_keys($graph))) {
            $this->page = $this->setPageRadioEpisode($this->page);
            $item = $this->setItemRadioEpisode(new Item());
            $this->page->all_items[] = $item;
            return true;
        }

        // cas 'WebPage', selon mainEntity@type :
        // - 'RadioSeries' c'est une émission de radio (liste épisodes ou de saison)
        // - 'PodcastSeries' une liste d'épisode ('série' ou podcast seul)
        if (in_array('WebPage', array_keys($graph))) {
            if (isset($graph['WebPage']['mainEntity'])) {
                $this->page = $this->setPageSeries($this->page);
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
     * recherche (basique) d'un flus rss, on ne sait jamais...
     *
     * ex : \<link rel="alternate" title="À v ..." href="https://radiofran...0351.xml" type="application/rss+xml">
     */
    private function getRssUrl(): ?string
    {
        $crawler_rss = $this->crawler->filter('link[rel="alternate"]');
        return ($crawler_rss->count() > 0) ? $crawler_rss->attr('href') : null;
    }

    /**
     * Set Page from the good graph for one radio episode
     *
     * @param Page $page
     * @return Page
     */
    private function setPageRadioEpisode(Page $page): Page
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
    private function setItemRadioEpisode(Item $item): Item
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
        $item->emission = trim($episode_graphe['partOfSeries']['name']);
        if (isset($episode_graphe['mainEntity'])) {
            $item->url = $episode_graphe['mainEntity']['contentUrl'];
            $item->mimetype = $episode_graphe['mainEntity']['encodingFormat'];
            $item->duration = duration_ISO_to_timestamp($episode_graphe['mainEntity']['duration']);
        }

        // on test si plusieurs media disponibles
        $preg = preg_match_all('/manifestations:(\[.*?\])/', $this->html, $matches);
        if ($preg !== 0) {
            $itema = get_itema($item->url);
            if ($itema !== null) {
                // tri des réponses
                $manifestation_audio = [];
                foreach($matches[1] as $matche) {
                    if (strpos($matche, $itema) !== false) {
                        try {
                            $decode = json5_decode($matche, true);
                        } catch (\Throwable $th) {
                        }
                        if (isset($decode)) {
                            $manifestation_audio = array_merge($manifestation_audio, $decode);
                        }
                    }
                }
                // ss doublons sur id
                $manifestation_audio_final = array_values(array_column($manifestation_audio, null, 'id'));
                $this->setItemMediaFromManifestations($item, $manifestation_audio_final);
            }
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
     * Only for media information, set media and url from preference
     *
     * @param mixed[] $manifestations
     */
    private function setItemMediaFromManifestations(Item $item, array $manifestations): void
    {
        foreach($manifestations as $k => $medium) {
            $item->media[] = [
                'url' => $medium['url'],
                'preset' => $medium['preset'],
                'mimetype' => get_audio_mimetype($medium['url'])
            ];
            // on ajoute une 'preference' pour selon le type de preset
            if (!empty($medium['preset']) || $medium['preset'] == 'null') {
                if (key_exists($medium['preset']['id'], Item::PREFERENCE)) {
                    $item->media[$k]['preset']['preference'] = Item::PREFERENCE[$medium['preset']['id']];
                }
            }
        }
        // range les média par préférence (voir Item)
        usort($item->media, function ($a, $b) {
            if (isset($a['preset']['preference']) && isset($b['preset']['preference'])) {
                return $a['preset']['preference'] <=> $b['preset']['preference'];
            } else {
                return 0;
            }
        });
        // pour 'url' on prend le premier
        $item->url = $item->media[0]['url'];
        $item->mimetype = $item->media[0]['mimetype'];
    }

    /**
     * Set all informations for a page if type "series" (RadioSeries or PodcastSeries)
     *
     * @param Page $page
    */
    private function setPageSeries(Page $page): Page
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
        $filter_publish = $this->crawler->filter('meta[property="article:published_time"]');
        if ($filter_publish->count() > 0) {
            $page->timestamp = strtotime($filter_publish->attr('content') ?? '') ?: 0;
        }

        return $page;
    }


    /**
     * Extract from html a big array with multiple playerInfo
     *
     * @param integer $num_page
     * @return mixed[]array
     * @throws \Exception
     */
    private function getBigPlayerInfoSeries(int $num_page): array
    {
        // en bas :-) de la page, entre ';a.prev=' et ';return'
        preg_match('/(a\.prev=.+);return/', $this->html, $matches);
        if (!isset($matches[1])) {
            throw new \Exception('Pas d\'information extraite (page : ' . $num_page . ')');
        }

        // transformation du retour de la regexp en tableau
        $items_array = from_js_obj_to_array($matches[1]);
        // on vire le 'a', ce sont des infos de navigation (pas utilisées)
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
            throw new \Exception('Pas trouvé les infos précises des playerInfo (page : ' . $num_page . ')');
        }
        $big_info = $matches[1];
        $big_info = str_replace('void 0', 'null', $big_info);

        $matches = [];
        $ret = preg_match_all('/\[((\w,?)+)\]/', $big_info, $matches, PREG_OFFSET_CAPTURE);
        if ($ret === false) {
            throw new \Exception('Erreur analyse des positions des media (page : ' . $num_page . ')');
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
        // parfois un épisode n'est pas prêt, on arrange (on vide)
        $big_info = preg_replace('/manifestations:\w/', 'manifestations:""', $big_info);
        // pour une liste de 'playerInfo'
        $big_info = '[' . $big_info . ']';

        try {
            $big_info_array = json5_decode($big_info, true);
        } catch (\Throwable $th) {
            throw new \Exception('json5 : ' . $th->getMessage() . ' on line: ' . $th->getLine() . '(page : ' . $num_page);
        }
        return $big_info_array;
    }

    /**
     * Inside a series set all good informations in Item
     *
     * @param Item $item
     * @param string $webpage_url
     * @param mixed[] $player_info
     * @return Item
     */
    private function setItemFromSeries(Item $item, string $webpage_url, array $player_info): Item
    {
        $item->title = $player_info['title'];
        $item->description = trim($player_info['description']);
        $item->id = $player_info['id'];
        $item->emission = $player_info['playerInfo']['playerMetadata']['firstLine'] ?? '';
        $item->thumbnail = $player_info['visual']['src'];
        $item->webpage_url = UriResolver::resolve($player_info['link'], $webpage_url);
        $item->duration = (int) $player_info['manifestations'][0]['duration'];
        $item->timestamp = (int) $player_info['manifestations'][0]['created'];
        // media, url et mimetype
        $this->setItemMediaFromManifestations($item, $player_info['manifestations']);
        return $item;
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
                $html = $this->getHtml($this->webpage_url . '?p=' . $num_page);
                if ($html === false) {
                    $this->error = 'Le scraping de la page ' . $num_page . ' a échoué';
                    return false;
                } else {
                    $this->html = $html;
                }
            }

            try {
                $big_info_array = $this->getBigPlayerInfoSeries($num_page);
            } catch (\Throwable $th) {
                $this->error = $th->getMessage();
                return false;
            }

            // au passage, le nom de l'émission
            $this->page->emission = $big_info_array[0]['playerInfo']['playerMetadata']['firstLine'] ?? '';

            // on assigne pour chaque 'playerInfo' contenant 'manifestations'
            foreach($big_info_array as $info) {
                if (!empty($info['manifestations'])) {
                    $item = $this->setItemFromSeries(new Item(), $this->webpage_url, $info);
                    $this->page->all_items[] = $item;
                }
            }

            // limite ?
            if ($this->max_items == -1 || count($this->page->all_items) <= $this->max_items) {
                $a_next = $this->getNextSeries();
            } else {
                $a_next = 'null';
            }
            // si le query contient genre ...?p=3 on ne scrape qu'une page
            if (strpos((string)parse_url($this->webpage_url, PHP_URL_QUERY), 'p=') !== false) {
                $a_next = 'null';
            }

            ++$num_page;
        }
        return true;
    }

    /**
     * From current html retrieve a.next ex: 'a.next="MjA=";'
     * @return string ex: "MjA=" or 'null'
     */
    private function getNextSeries(): string
    {
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
     * All data in json like yt-dlp (or youtube-dl) *.info.json
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
            $json = ($json === false) ? '' : $json;
            return str_replace('emission', 'channel', $json);
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
            $new = object_to_array($entrie);
            $new['playlist'] = $new['emission'];
            $json['entries'][] = $new;
        }
        $json_encode = json_encode($json);
        return ($json_encode === false) ? '' : $json_encode;
    }

    public function toArray(): array
    {
        return object_to_array($this->page);
    }

}
