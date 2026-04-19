<?php

/*
 * (c) flatgreen <flatgreen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Flatgreen\RFrance;

use DOMDocument;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use stdClass;

/**
 * RFrance a class to scrape and parse r a d i o f r a n c e
 */
class RFrance
{
    public const RADIOFRANCE_HOST = 'www.radiofrance.fr';
    private const USER_AGENT = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/124.0';

    private FilesystemAdapter $cache;
    private DOMDocument $dom;

    public Page $page;
    private string $html = '';
    /** @deprecated */
    public string $error = '';
    private int $max_items;
    private string $webpage_url = '';

    /** @var array<string> $excluded_end_urls */
    private array $excluded_end_urls = [
        'podcasts',
        'grille-programmes',
        'societe'
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
     * If we have already HTML string, we can use it.
     */
    public function setHtml(string $html): void
    {
        $this->html = $html;
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
        $url = strtok($url, '?#');
        
        if (filter_var($url, FILTER_VALIDATE_URL) === false || $url === false) {
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
      * Merge all <script type="application/ld+json"> in an array, return a special '@graph'
      *
      * @return array<mixed> The keys are the value of @type in @graph
      */
    private function getGraphFromScriptJson(): array
    {
        // on prend tous les ld+json, en général il y en a 2, chacun avec +sieurs @graph
        $elements = find_in_dom($this->dom, 'script', 'type', 'application/ld+json');
        $all_scripts_decode = [];
        foreach ($elements as $element) {
            $ar_decode = json_decode($element->textContent, true);
            $all_scripts_decode = array_merge_recursive($all_scripts_decode, $ar_decode);
        }
        // on vire les @context et les @graph @type="BreadcrumbList"
        $ar_filter = array_filter($all_scripts_decode['@graph'], function ($v) {
            return ($v['@type'] !== 'BreadcrumbList');
        });

        // pour chaque type on garde le array le plus grand
        $ar_final = [];
        $ar_type = array_keys(array_flip(array_column($ar_filter, '@type')));
        foreach ($ar_type as $type) {
            $ar_one_type = array_filter($ar_filter, function ($v) use ($type) {
                return ($v['@type'] == $type);
            });
            $good_type = (count($ar_one_type) > 1) ? max($ar_one_type) : array_shift($ar_one_type);
            $ar_final[$type] = $good_type;
        }
        
        return $ar_final;
    }

    /**
     * parse, extract all good informations.
     *
     * @param string $url
     * @param int $max_items max number of items (approx.), -1 for all items
     * @throws \InvalidArgumentException
     * @throws \Exception (http)
     * @return bool
     */
    public function extract(string $url, int $max_items = -1)
    {
        $ret = $this->realExtract($url, $max_items);
        unset($this->html);
        return $ret;
    }

    /**
     * parse, extract all good informations.
     *
     * @param string $url
     * @param int $max_items max number of items (approx.), -1 for all items
     * @throws \InvalidArgumentException
     * @throws \Exception (http)
     * @return bool
     */
    private function realExtract(string $url, int $max_items = -1)
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException($this->error);
        }

        if (empty($this->html)) {
            $html = $this->getHtml($url);
            if ($html === false) {
                $this->error = 'Le scraping a échoué : ' . $url;
                throw new \Exception($this->error);
            }
            $this->html = $html;
        }

        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($this->html);

        $this->max_items = $max_items;
        $this->webpage_url = $url;

        $this->page = new Page();
        $this->page->webpage_url = $url;
        $this->page->short_path = short_path($this->webpage_url);

        // un flux rss ?
        $this->page->rss_url = $this->getRssUrl();
        // on récupère les <script type="application/ld+json">
        $graph = $this->getGraphFromScriptJson();
        $graph_keys = array_keys($graph);

        // un seul épisode (on peut avoir des "encarts")
        if (in_array('RadioEpisode', $graph_keys)) {
            $this->setPageRadioEpisode($graph);
            $this->setItemRadioEpisode($graph);
            $this->setItemsEncartsInRadioEpisode();
            return true;
        }

        // cas 'WebPage', selon mainEntity@type :
        // - 'RadioSeries' c'est une émission de radio (liste épisodes ou de saison)
        // - 'PodcastSeries' une liste d'épisode ('série' ou podcast seul)
        if (in_array('WebPage', $graph_keys)) {
            if (isset($graph['WebPage']['mainEntity'])) {
                $this->setPageSeries($graph);
                return $this->setAllItemsFromSerie();
            } else {
                $this->page->type = 'WebPage';
                $this->page->title = $graph['WebPage']['name'];
                $this->page->description = $graph['WebPage']['description'];
                $this->page->image = $this->extractImageMeta();
                return true;
            }
        }

        // cas 'NewsArticle', c'est un article de blabla
        // il peut y avoir des encarts, on essaie
        if (in_array('NewsArticle', $graph_keys)) {
            $graph = $graph['NewsArticle'];
            $this->page->title = $graph['headline'];
            $this->page->description = $graph['description'];
            $this->page->timestamp = strtotime($graph['datePublished']);
            $this->page->station = $graph['publisher']['name'];
            $this->page->type = 'NewsArticle';
            $this->page->image = $this->extractImageMeta();
            $this->setItemsEncartsInRadioEpisode();
            return true;
        }

        $this->error = 'Pas de donnée extraite (pas d\'audio ?)';
        // throw new \Exception($this->error);
        
        return false;
    }

    /**
     * recherche (basique) d'un flus rss, on ne sait jamais...
     *
     * <link rel="alternate" title="À v ..." href="https://radiofran...0351.xml" type="application/rss+xml">
     */
    private function getRssUrl(): ?string
    {
        // '#<link .*?rel="alternate".*?href="(?<RSS>[^"]*?)"#';
        $element = find_in_dom($this->dom, 'link', 'rel', 'alternate');
        return empty($element) ? null : $element[0]->getAttribute('href');
    }

    /**
     * Set Page info with the good graph for one radio episode
     *
     * @param mixed[] $graph
     */
    private function setPageRadioEpisode(array $graph): void
    {
        $this->page->type = 'RadioEpisode';
        $this->page->station = $graph['NewsArticle']['publisher']['name'];
        $this->page->emission = trim($graph['RadioEpisode']['partOfSeries']['name']);
        $this->page->title = trim(html_entity_decode($graph['NewsArticle']['headline']));
        $this->page->description = trim(html_entity_decode($graph['NewsArticle']['description']));
        $this->page->image = $this->extractImageArray($graph['RadioEpisode']['image']);
        $this->page->timestamp = strtotime($graph['RadioEpisode']['dateCreated']);
    }

    /**
     * Set Item from the good graph for one radio episode
     *
     * @param mixed[] $graph
     */
    private function setItemRadioEpisode(array $graph): void
    {
        // si l'épisode n'est pas prêt on abandonne
        if (!isset($graph['RadioEpisode']['mainEntity'])) {
            return;
        }

        $item = new Item();
        $episode_graphe = $graph['RadioEpisode'];

        $item->webpage_url = $graph['NewsArticle']['mainEntityOfPage']['@id'];
        $item->title = trim(html_entity_decode($graph['NewsArticle']['headline']));
        $item->id = md5($item->title);
        $item->description = trim(html_entity_decode($graph['NewsArticle']['description']));
        $item->timestamp = strtotime($graph['RadioEpisode']['dateCreated']);
        $item->thumbnail = $graph['RadioEpisode']['image']['url'];
        $item->emission = trim($graph['RadioEpisode']['partOfSeries']['name']);

        $item->url = $episode_graphe['mainEntity']['contentUrl'];
        $item->mimetype = $episode_graphe['mainEntity']['encodingFormat'];
        $item->duration = duration_ISO_to_timestamp($episode_graphe['mainEntity']['duration']);


        // on test si plusieurs media disponibles
        // ==> Abandon au 17 avril 2026, c'est possible en scrapant le 1er bouton,
        // prendre l'id et avec une requête sur aod avoir tous les medias.
        // à priori, RF délivre la meileure qualité
        // voir aussi Item.php

        $this->page->all_items[] = $item;
    }

    private function setItemsEncartsInRadioEpisode(): void
    {
        $preg = preg_match_all('/{type:"audio",data:({.*?})/', $this->html, $matches);
        if ($preg === 0 || $preg === false) {
            return;
        }
        foreach ($matches[1] as $matche) {
            try {
                $decode = json5_decode($matche, true);
            } catch (\Throwable $th) {
            }
            if (isset($decode)) {
                $item = new Item();
                $item->id = $decode['id'];
                $item->title = trim(html_entity_decode($decode['title']));
                $item->description = $decode['title']; // pas de description dans les encarts
                $item->url = $decode['url'];
                $item->duration = $decode['duration'];
                $item->webpage_url = $this->page->webpage_url;
                $item->thumbnail = ($this->page->image->src) ?? '';
                $item->timestamp = $this->page->timestamp;
                $item->mimetype = get_audio_mimetype($item->url);
                $this->page->all_items[] = $item;
            }
        }
    }

    /**
     * From an array ['url' =>, 'height' =>, 'width' => ] set an image object
     *
     * image dans les @graph depuis nov. 2024 (et aussi dans les meta retour avril 2026)
     *
     * @param string[] $info_image
     * @return stdClass $image
     */
    private function extractImageArray(array $info_image)
    {
        $image = new stdClass();
        $image->src = $info_image['url'] ?? null;
        $image->height = $info_image['height'] ?? null;
        $image->width = $info_image['width'] ?? null;
        return $image;
    }

    /**
     * Use the metadata to retriev image infos
     *
     * image dans @graph disparue depuis avril 2026 (à voir?), retour dans les meta
     *
     * <meta property="og:image" content="https://www.radiofrance.fr/pikapi/images/dea723ef-66b0-4b7b-bc2c-d82db0a4dbb7/1200x680"/> <!--[--><meta property="og:image:width" content="1200"/> <meta property="og:image:height" content="680"/>
     *
     * @return stdClass $image
     */
    private function extractImageMeta()
    {
        $image = new stdClass();
        $src_elt = find_in_dom($this->dom, 'meta', 'property', 'og:image');
        $image->src = (isset($src_elt[0])) ? $src_elt[0]->getAttribute('content') : null;
        $width_elt = find_in_dom($this->dom, 'meta', 'property', 'og:image:width');
        $image->width = (isset($width_elt[0])) ? $width_elt[0]->getAttribute('content') : null;
        $height_elt = find_in_dom($this->dom, 'meta', 'property', 'og:image:height');
        $image->height = (isset($height_elt[0])) ? $height_elt[0]->getAttribute('content') : null;

        return $image;
    }

    /**
     * Only for media information, set media and url from preference
     *
     * @param Item $item
     * @param mixed[] $sources
     */
    private function setItemMediaFromSources(Item $item, array $sources): void
    {
        foreach ($sources as $k => $medium) {
            $item->media[] = [
                'url' => $medium['url'],
                'preset' => $medium['preset'] ?? null,
                'mimetype' => get_audio_mimetype($medium['url'])
            ];

            // voir Item.php pour explications

            //    // on ajoute une 'preference' pour selon le type de preset
            //     if (!empty($medium['preset']) || $medium['preset'] == 'null') {
            //         if (key_exists($medium['preset']['id'], Item::PREFERENCE)) {
            //             $item->media[$k]['preset']['preference'] = Item::PREFERENCE[$medium['preset']['id']];
            //         }
            //     }
        }

        // // range les média par préférence
        // usort($item->media, function ($a, $b) {
        //     if (isset($a['preset']['preference']) && isset($b['preset']['preference'])) {
        //         return $a['preset']['preference'] <=> $b['preset']['preference'];
        //     } else {
        //         return 0;
        //     }
        // });

        // pour 'url' on prend le premier
        $item->url = $item->media[0]['url'];
        $item->mimetype = $item->media[0]['mimetype'];
    }

    /**
     * Set all informations for a page if type "series" (RadioSeries or PodcastSeries)
     *
     * @param mixed[] $graph
    */
    private function setPageSeries(array $graph): void
    {
        $graph_web_page = $graph['WebPage'];

        $this->page->station = $graph_web_page['mainEntity']['sourceOrganization']['name'];
        $this->page->type = $graph_web_page['mainEntity']['@type'];
        // title semble être aussi dans ['name'] directement (sans la fin ...|...)
        $this->page->title = trim(html_entity_decode($graph_web_page['name']));
        // 'emission' pour PodcastSeries est trouvée dans getAllItemsFromSerie
        $this->page->emission = ($this->page->type == 'RadioSeries') ? $this->page->title : null;
        $this->page->description = html_entity_decode($graph_web_page['mainEntity']['abstract'] ?? '');
        $this->page->image = $this->extractImageMeta();
    }

    /**
     * Inside a series set all good informations in an Item
     *
     * @param string $webpage_url
     * @param mixed[] $player_info
     * @return Item
     */
    private function setItemFromSeries(string $webpage_url, array $player_info): Item
    {
        $item = new Item();
        $item->title = trim(html_entity_decode($player_info['titleProps']['title']));
        $item->description = trim(html_entity_decode($player_info['titleProps']['text']));
        $item->id = $player_info['id'];
        $item->emission = $player_info['metadata']['firstLine'] ?? '';
        // la fin de l'url est un peu au pif ...
        $item->thumbnail = $player_info['visual']['src'] . '/' . '2048';
        if (!empty($player_info['titleProps']['href'])) {
            $item->webpage_url = ($player_info['titleProps']['href'][0] == '/') ? 'https://' . self::RADIOFRANCE_HOST . $player_info['titleProps']['href'] : $player_info['titleProps']['href'];
        } else {
            $item->webpage_url = $webpage_url;
        }
        $item->duration = (int) $player_info['manifestation']['duration'];
        $item->timestamp = date_french_to_timestamp($player_info['publishedDate']);

        // media, url et mimetype
        $this->setItemMediaFromSources($item, $player_info['sources']);
        return $item;
    }

    /**
     * Populate $all_items[] from serie page
     */
    private function setAllItemsFromSerie(): bool
    {
        $num_page = 1;
        $a_next = '';

        // +sieurs pages ? => On repère dans $html (c'est dans le dernier 'script') le "a.next=...;"
        // si c'est "null" (string), c'est une seule page (ou pas de page suivante)
        // sinon, on scrape $url?p=2, on prend les items, et on recommence jusqu'à la limite max_items.

        while ($a_next !== null) {
            // on scrape la page (la page "1" est dejà dans $this->html)
            if ($num_page !== 1) {
                $html = $this->getHtml($this->webpage_url . '?p=' . $num_page);
                if ($html === false) {
                    $this->error = 'Le scraping de la page ' . $num_page . ' a échoué';
                    throw new \Exception($this->error);
                } else {
                    $this->html = $html;
                }
            }

            // on cherche la grande liste de tous les épisodes, on en profite pour prendre 'next'
            $ret = preg_match('/expressions:{items:(\[.*?\]),next:(.{6})}/', $this->html, $matches);
            if ($ret === false || !isset($matches[0])) {
                throw new \Exception('Pas d\'information extraite (page : ' . $num_page . ')');
            }

            // next:"MjA=" or next:void 0
            $next = trim($matches[2], '"');
            $next = ($next == 'void 0' || $next == null) ? null : $next;

            $matches[1] = str_replace('void 0', 'null', $matches[1]);
            try {
                $items_array = json5_decode($matches[1], true);
            } catch (\Throwable $th) {
                $this->error = 'json5 : ' . $th->getMessage() . ' -- line: ' . $th->getLine() . ' -- page: ' . $num_page;
                throw new \Exception($this->error);
            }

            // on vire les éléments avec la clé 'manifestation' à null => pas d'audio
            foreach ($items_array as $k => $v) {
                if ($v['manifestation'] == null) {
                    unset($items_array[$k]);
                }
            }

            // on a les infos pour les épisodes (sans 'url') venant de la page html
            // à partir des 'id', requêtes pour les json correspondants
            // ça complète alors les épisodes
            foreach ($items_array as $k => $v) {
                $id = $v['id'];
                $url = 'https://www.radiofrance.fr/transistor/aod/' . $id;

                // TODO si false, on continue (et on vire cet item) ou exception et c'est fini ?
                $ret_json = $this->getHtml($url);
                if ($ret_json === false) {
                    $this->error = 'Le scraping a échoué : ' . $url;
                    throw new \Exception($this->error);
                }
                $item_fetch = json_decode($ret_json, true, 512, JSON_THROW_ON_ERROR);
                $items_array[$k] = array_merge($items_array[$k], $item_fetch);
            }

            // au passage, le nom de l'émission, pour PodcastSeries
            $this->page->emission = $this->page->emission ?? $items_array[0]['metadata']['firstLine'] ?? $this->page->title;

            // on assigne les items
            foreach ($items_array as $info) {
                $item = $this->setItemFromSeries($this->webpage_url, $info);
                $this->page->all_items[] = $item;
            }

            // limite ?
            if ($this->max_items == -1 || count($this->page->all_items) <= $this->max_items) {
                $a_next = $next;
            } else {
                $a_next = null;
            }
            // si le query contient genre ...?p=3 on ne scrape qu'une page
            if (strpos((string)parse_url($this->webpage_url, PHP_URL_QUERY), 'p=') !== false) {
                $a_next = null;
            }

            ++$num_page;
        }
        return true;
    }

    public function pruneCache(): void
    {
        $this->cache->prune();
    }

    /**
     * From all data create a classic RSS file, always return information even no item
     *
     * @return string the feed string
     */
    public function toRss(): string
    {
        $rssfeed = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $rssfeed .= '<rss version="2.0">' . "\n";
        if ($this->page->type == 'RadioSeries') {
            $rssfeed .= '<channel>' . "\n" . '<title>' . hexa_encoding($this->page->emission ?? '') . '</title>'. "\n";
        } else {
            $rssfeed .= '<channel>' . "\n" . '<title>' . hexa_encoding($this->page->title ?? '') . '</title>'. "\n";
        }
        $rssfeed .= '<link>' . $this->page->webpage_url . '</link>' . "\n";
        $rssfeed .= '<description>' . hexa_encoding($this->page->description) . '</description>' . "\n";
        $rssfeed .= '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
        $rssfeed .= '<pubDate>' . date(DATE_RSS, $this->page->timestamp) . '</pubDate>' . "\n";
        $rssfeed .= '<language>fr</language>' . "\n";
        if (isset($this->page->image) && !empty((array)$this->page->image)) {
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
            $json = str_replace('emission', 'playlist', $json);
            return str_replace('station', 'channel', $json);
        }

        $json = [];
        $json['title'] = ($this->page->type == 'RadioSeries') ? $this->page->emission : $this->page->title;
        $json['description'] = $this->page->description;
        $json['channel'] = $this->page->station;
        $json['playlist'] = $this->page->emission;
        $json['channel_url'] = $this->page->webpage_url;
        $json['webpage_url'] = $this->page->webpage_url;
        $json['uploader'] = $this->page->station . ' - ' . $this->page->emission;
        $json['extractor'] = $this->page->extractor;
        if (isset($this->page->image) && !empty((array)$this->page->image)) {
            $json['image'] = (array)$this->page->image;
        }
        $json['playlist_count'] = $nb_items;
        $json['_type'] = 'playlist';
        foreach ($this->page->all_items as $entrie) {
            $new = object_to_array($entrie);
            $new['playlist'] = ($new['emission']) ?? '';
            $json['entries'][] = $new;
        }
        $json_encode = json_encode($json);
        return ($json_encode === false) ? '' : $json_encode;
    }

    /** @return mixed[] */
    public function toArray(): array
    {
        return object_to_array($this->page);
    }

}
