<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  exit;
}

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/filter.php');
require_once('../library/sqlite.php');

// Connect database
$db = new SQLite(DB_NAME, DB_USERNAME, DB_PASSWORD);

// Process crawl queue
foreach ($db->getPageQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queue) {

  $url = new Curl($queue->url);

  $db->updatePageQueue($queue->pageId, time(), $url->getCode());

  // Skip processing non 200 code
  if (200 != $url->getCode()) {

    continue;
  }

  // Skip processing pages without returned data
  if (!$content = $url->getContent()) {

    continue;
  }

  $dom = new DomDocument();

  @$dom->loadHTML($content);

  // Skip index page links without titles
  $title = @$dom->getElementsByTagName('title');

  if ($title->length == 0) {
    continue;
  }

  // Get optional page meta data
  $description = '';
  $keywords    = '';

  foreach (@$dom->getElementsByTagName('meta') as $meta) {

    if (@$meta->getAttribute('name') == 'description') {
      $description = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'keywords') {
      $keywords = @$meta->getAttribute('content');
    }
  }

  // Index page data
  $db->updatePage($queue->pageId,
                  Filter::pageTitle($title->item(0)->nodeValue),
                  Filter::pageDescription($description),
                  Filter::pageKeywords($keywords),
                  CRAWL_META_ONLY ? '' : Filter::pageData($content),
                  time());

  // Update images
  $db->deleteImages($queue->pageId);

  if (CRAWL_IMAGE) {

    foreach (@$dom->getElementsByTagName('img') as $image) {

      // Skip images without required attributes
      if (!$src = @$image->getAttribute('src')) {

        continue;
      }

      if (!$alt = @$image->getAttribute('alt')) {

        continue;
      }

      // Add domain to the relative links
      if (!parse_url($src, PHP_URL_HOST)) {

        $src = parse_url($queue->url, PHP_URL_SCHEME) . '://' .
               parse_url($queue->url, PHP_URL_HOST) .
               parse_url($queue->url, PHP_URL_PORT) .
               $src; // @TODO sometimes wrong URL prefix available
      }

      // Add page images
      $db->addImage($queue->pageId,
                    Filter::url($src),
                    crc32($src),
                    Filter::imageAlt($alt));
    }
  }

  // Collect internal links from page content
  foreach(@$dom->getElementsByTagName('a') as $a) {

    // Skip links without required attribute
    if (!$href = @$a->getAttribute('href')) {

      continue;
    }

    // Skip anchor links
    if (false !== strpos($href, '#')) {

      continue;
    }

    // Add absolute prefixes to the relative links
    if (!parse_url($href, PHP_URL_HOST)) {

      $href = parse_url($queue->url, PHP_URL_SCHEME) . '://' .
              parse_url($queue->url, PHP_URL_HOST) .
              parse_url($queue->url, PHP_URL_PORT) .
              $href;
    }

    // Filter href URL
    $href = Filter::url($href);

    // Save valid internal links to the index queue
    if (filter_var($href, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $href)) {

      $db->initPage($href, crc32($href), time());
    }
  }
}