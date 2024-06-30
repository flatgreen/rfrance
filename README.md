# RFrance

PHP library for french radio.
RFrance a class to scrape and parse r a d i o f r a n c e

## Prerequisites
- php >= 8.0

## Installation
- Use the package manager [composer](https://getcomposer.org/) to install RFrance.
```bash
composer require flatgreen/rfrance
```
- Optional: Create a 'cache' directory (with read|write permissions), by default the cache directory is inside the system temporary directory.

## Usage

```php
require_once 'vendor/autoload.php';
use Flatgreen\RFrance\RFrance;
```

Instantiate the class, define a URL page

```php
$rf = new RFrance();
    // optional, add a cache directory and cache duration

$rf->extract(URL);
    // force extract with rss in page : $force_rss
    // ATTENTION, one item <=> one request, set a limit $max_items !
```

Read the informations
```php
if (empty($rf->error)){
    $title = $rf->page->title;
    $all_items = $rf->all_items; // array of Item
}
```

Two output helpers :

```php
// RSS 2.0
header("Content-Type: text/xml; charset=UTF-8");
echo $FC->toRss();

// or

// youtube-dl|yt-dlp info.json like
header('Content-Type: application/json; charset=utf-8');
echo $FC->toInfoJson();
```

## License
RFrance is licensed under the MIT License (MIT). Please see the [license file](/LICENSE) for more information.