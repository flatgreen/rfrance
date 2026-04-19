# RFrance

PHP library for french radio.
RFrance a class to scrape and parse r a d i o f r a n c e

## Prerequisites
- php >= 8.1

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

Instantiate the class, extract with an URL page

```php
$rf = new RFrance(); // optional in constructor, add cache directory and cache duration for page, 1 day by default.

try {
    $rf->extract(URL); // optional: set a (approx.) limit $max_items (default: -1, all items)
} catch (\Throwable $th) {
    // ...
}
```

Read the informations
```php
if (empty($rf->error)){
    $title = $rf->page->title;
    $all_items = $rf->page->all_items; // array of Item
    ...
}
```

All properties for Item, in [Item.php](/src/Item.php).

Three output helpers :

```php
// to array
echo $FC->toArray();

// RSS 2.0, always return informations (even with no item)
// Be carefull, max_items is not present in the feed
header("Content-Type: text/xml; charset=UTF-8");
echo $FC->toRss();

// or

// youtube-dl|yt-dlp info.json like, maybe empty if no item
header('Content-Type: application/json; charset=utf-8');
echo $FC->toInfoJson();
```

### Remarks
Does not take into account some pages that are not broadcasts.

### Changelog
[changelog](/CHANGELOG.md)

## License
RFrance is licensed under the MIT License (MIT). Please see the [license file](/LICENSE) for more information.