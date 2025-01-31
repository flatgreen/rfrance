<?php

use Flatgreen\RFrance\RFrance;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringStartsWith;
use function PHPUnit\Framework\assertTrue;

final class RFranceTest extends TestCase
{
    public static function providerExtractBadUrl()
    {
        return [
            ['icr,giuh', \InvalidArgumentException::class],
            ['https://stackoverflow.com/questions/5683592/phpunit-asse', \InvalidArgumentException::class],
            ['https://www.radiofrance.fr/franceculture/podcasts/', \InvalidArgumentException::class],
            ['https://www.radiofrance.fr/aaaaaaaaaaaaaaa', \Exception::class]
        ];
    }

    /**
     * @dataProvider providerExtractBadUrl
     */
    public function testBadUrls($bad_url, $expectedException)
    {
        $this->expectException($expectedException);
        (new RFrance())->extract($bad_url);
    }

    public function testExtractUneEmission()
    {
        $url = 'https://www.radiofrance.fr/franceinter/podcasts/en-quete-de-politique/en-quete-de-politque-du-dimanche-03-novembre-2024-2553296';

        $rf = new RFrance();
        assertTrue($rf->extract($url));

        $page = $rf->page;
        assertSame('France Inter', $page->station);
        assertSame('en-quete-de-politque-du-dimanche-03-novembre-2024-2553296', $page->short_path);
        assertSame('Le fascisme italien', $page->title);
        assertSame(1730653865, $page->timestamp);
        assertSame('En quête de politique', $page->emission);
        assertSame('RadioEpisode', $page->type);
        assertNull($page->rss_url);
        assertSame('https://www.radiofrance.fr/s3/cruiser-production-eu3/2024/10/75a77277-5c1c-43ea-9053-fe9408c0bd58/640x340_sc_horizon-le-mussolinisme.jpg', $page->image->src);
        assertCount(1, $page->all_items);

        $item = $page->all_items[0];
        assertSame('https://www.radiofrance.fr/franceinter/podcasts/en-quete-de-politique/en-quete-de-politque-du-dimanche-03-novembre-2024-2553296', $item->webpage_url);
        assertSame('Le fascisme italien', $item->title);
        assertStringStartsWith("C'est le fascisme originel", $item->description);
        assertSame(1730653865, $item->timestamp);
        assertSame('4f8ab77ee4205f8ff5e851966812efb6', $item->id);
        assertSame('audio/mp4', $item->mimetype);
        assertSame(2949, $item->duration);
        assertSame('En quête de politique', $item->playlist);
        assertSame('https://www.radiofrance.fr/s3/cruiser-production-eu3/2024/10/75a77277-5c1c-43ea-9053-fe9408c0bd58/640x340_sc_horizon-le-mussolinisme.jpg', $item->thumbnail);
        assertStringStartsWith('https://media.radiofrance-podcast.net/podcast09/23497-31.10.2024-ITEMA_23911275-2024F49012S0308', $item->url);
        assertEmpty($item->media);

    }

    public function testPageSeries()
    {
        // 2025-01-31 une petite série: trois épisodes
        $url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-deux-flics-de-remi-de-vos';
        $rf = new RFrance();
        assertTrue($rf->extract($url));

        $page = $rf->page;
        assertSame('France Culture', $page->station);
        assertSame('serie-deux-flics-de-remi-de-vos', $page->short_path);
        assertSame('"Deux Flics" de Rémi de Vos', $page->title);
        assertSame(1737369020, $page->timestamp);
        assertSame('Fictions / Théâtre et Cie', $page->emission);
        assertSame('PodcastSeries', $page->type);
        assertNull($page->rss_url);
        assertSame('https://www.radiofrance.fr/s3/cruiser-production-eu3/2025/01/fb171353-b40c-42e5-9956-cec1ba9dc3f5/400x400_sc_carre-4.jpg', $page->image->src);
        assertCount(3, $page->all_items);
    }

    public function testFirstItemSeries()
    {
        // 2025-01-31 une petite série: trois épisodes
        $url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-deux-flics-de-remi-de-vos';
        $rf = new RFrance();
        assertTrue($rf->extract($url));
        // que le premier épisode
        $item = $rf->page->all_items[0];
        assertSame('https://www.radiofrance.fr/franceculture/podcasts/serie-deux-flics-de-remi-de-vos', $item->webpage_url);
        assertSame('Épisode 1/3 : Deux flics en voiture', $item->title);
        assertStringStartsWith('Deux policiers, la quarantaine, au milieu', $item->description);
        assertSame(1737918000, $item->timestamp);
        assertSame('ae0c4daa-2cb4-4802-a414-f4d01c7c7e2b', $item->id);
        assertSame('audio/mpeg', $item->mimetype);
        assertSame(2096, $item->duration);
        assertSame('Fictions / Théâtre et Cie', $item->playlist);
        assertSame('https://www.radiofrance.fr/s3/cruiser-production-eu3/2025/01/7db253a3-7794-4d74-ace4-6737452c3915/200x200_sc_foret.jpg', $item->thumbnail);
        assertSame('https://media.radiofrance-podcast.net/podcast09/24824-26.01.2025-ITEMA_23989447-2024C11356E0038-ITE_00142028_RSCE-21.mp3', $item->url);
        assertCount(1, $item->media);
    }


}
