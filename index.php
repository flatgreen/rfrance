<?php

require_once 'vendor/autoload.php';

use Flatgreen\RFrance\RFrance;

$url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-affaires-culturelles-a-cannes';

$url = 'https://www.radiofrance.fr/franceculture/podcasts/';

// blabla
$url = 'https://www.radiofrance.fr/franceculture/comment-le-front-populaire-a-t-il-gagne-les-elections-de-1936-5455469';

// one
// $url = 'https://www.radiofrance.fr/franceculture/podcasts/affaires-culturelles/cannes-1-5-rencontres-avec-la-cineaste-justine-triet-et-l-actrice-hafsia-herzi-2552923';

// error
// $url = 'https://www.radiofrance.fr/franceinter/podcasts/serie-bel';

// serie 5 épisodes
$url = 'https://www.radiofrance.fr/franceinter/podcasts/serie-belin-co';

// serie 83 épisodes
$url = 'https://www.radiofrance.fr/francemusique/podcasts/les-sagas-musicales';
// un seul de la série précedentes OK
// $url = 'https://www.radiofrance.fr/francemusique/podcasts/les-sagas-musicales/les-sagas-musicales-maurice-ravel-1-5-jeux-basques-3027626';

// $url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-affaires-culturelles-a-cannes';

// one OK
// $url = 'https://www.radiofrance.fr/franceinter/podcasts/le-grand-dimanche-soir/le-grand-dimanche-soir-du-dimanche-16-juin-2024-5335413';

// serie non accessible
// $url = 'https://www.radiofrance.fr/franceculture/podcasts/serie-vie-et-destin-de-vassili-grossman';

// one ss audio (greve)
// $url = 'https://www.radiofrance.fr/franceculture/podcasts/les-midis-de-culture/pas-d-emission-pour-cause-de-greve-5016080';


$FC = new RFrance('cache');
$aa = $FC->extract($url, true, 32);

// dd($FC);

// header("Content-Type: text/xml; charset=UTF-8");
// echo $FC->toRss();

header('Content-Type: application/json; charset=utf-8');
echo $FC->toInfoJson();

// $data_folder = empty($data_folder) ? './' : $data_folder;
// $rss_filename = $data_folder . '/'. basename($this->url) . '.xml';
