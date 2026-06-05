<?php

use Flatgreen\RFrance\RFrance;
use PHPUnit\Framework\TestCase;

final class RFranceTest extends TestCase
{
    private RFrance $rf;
    protected function setUp(): void
    {
        $this->rf = new RFrance();
    }

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
    public function testBadUrls(string $bad_url, string $expectedException)
    {
        $this->expectException($expectedException);
        $this->rf->extract($bad_url);
    }

    public function testExtractUneEmission()
    {
        // with 2 medias
        $url = 'https://www.radiofrance.fr/franceinter/podcasts/en-quete-de-politique/en-quete-de-politque-du-dimanche-03-novembre-2024-2553296';
        $this->assertTrue($this->rf->extract($url));
        $extracted = $this->rf->toArray();
        $this->assertJsonStringEqualsJsonFile(__DIR__ . '/json/episode.json', json_encode($extracted));
    }

    public function testExtractUneEmissionAvecEncarts()
    {
        // un media et 5 encarts
        $url = 'https://www.radiofrance.fr/franceculture/podcasts/les-pieds-sur-terre/topette-9225175';
        $this->assertTrue($this->rf->extract($url));
        $extracted = $this->rf->toArray();
        $this->assertJsonStringEqualsJsonFile(__DIR__ . '/json/episode5encarts.json', json_encode($extracted));
    }

    public function testPageSeries()
    {
        // 2025-01-31 une petite série: trois épisodes venant de Fictions / Théâtre et Cie
        $url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-deux-flics-de-remi-de-vos';
        $this->assertTrue($this->rf->extract($url));
        $extracted = $this->rf->toArray();
        $this->assertJsonStringEqualsJsonFile(__DIR__ . '/json/serie3episodes.json', json_encode($extracted));
    }

    public function testPageEmissionItemMax10()
    {
        $url = 'https://www.radiofrance.fr/franceculture/podcasts/fictions-la-vie-moderne';
        $this->assertTrue($this->rf->extract($url, 10)); // sera de 15 items, 1 page
        $extracted = $this->rf->toArray();
        $this->assertJsonStringEqualsJsonFile(__DIR__ . '/json/emission10episodes.json', json_encode($extracted));
    }

    public function testPageEmissionItemMax25()
    {
        $url = 'https://www.radiofrance.fr/franceinter/podcasts/la-tete-au-carre';
        $this->assertTrue($this->rf->extract($url, 25)); // sera de 40 items, 2 pages
        $extracted = $this->rf->toArray();
        $this->assertJsonStringEqualsJsonFile(__DIR__ . '/json/emission25episodes.json', json_encode($extracted));
    }

}
