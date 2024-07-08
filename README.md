# RFrance

PHP library for french radio.
RFrance a class to scrape and parse r a d i o f r a n c e

## Prerequisites
- php >= 8.2
- cUrl extension recommanded

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
// optional in constructor, add a cache directory and cache duration for page, 1 day by default

$rf->extract(URL);
// optional:
// - force extract when rss exist : $force_rss (default: false)
// - set a limit $max_items (default: -1, all items)
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

### Remarks
Does not take into account pages that are not broadcasts.

## License
RFrance is licensed under the MIT License (MIT). Please see the [license file](/LICENSE) for more information.