<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'Process locked by another thread.' . PHP_EOL;
  exit;
}

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/filter.php');
require_once('../library/parser.php');
require_once('../library/mysql.php');

// Check disk quota
if (CRAWL_STOP_DISK_QUOTA_MB_LEFT > disk_free_space('/') / 1000000) {

  echo 'Disk quota reached.' . PHP_EOL;
  exit;
}

// Debug
$timeStart = microtime(true);

$hostPagesProcessed  = 0;
$hostImagesProcessed = 0;
$hostPagesIndexed    = 0;
$hostImagesIndexed   = 0;
$hostPagesAdded      = 0;
$hostImagesAdded     = 0;
$hostsAdded          = 0;

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Process images crawl queue
foreach ($db->getHostImageCrawlQueue(CRAWL_IMAGE_LIMIT, time() - CRAWL_IMAGE_SECONDS_OFFSET) as $queueHostImage) {

  // Build URL from the DB
  $queueHostImageURL = $queueHostImage->scheme . '://' . $queueHostImage->name . ($queueHostImage->port ? ':' . $queueHostImage->port : false) . $queueHostImage->uri;

  $curl = new Curl($queueHostImageURL, CRAWL_CURLOPT_USERAGENT);

  // Update image index anyway, with the current time and http code
  $hostImagesProcessed += $db->updateHostImageCrawlQueue($queueHostImage->hostImageId, time(), $curl->getCode());

  // Skip next image processing non 200 code
  if (200 != $curl->getCode()) {

    continue;
  }

  // Save image content on data settings enabled
  if (!CRAWL_HOST_DEFAULT_META_ONLY) {

    // Skip next image processing images without returned data
    if (!$content = $curl->getContent()) {

      continue;
    }

    // Convert remote image data to base64 string to prevent direct URL call
    if (!$hostImageType = @pathinfo($queueHostImageURL, PATHINFO_EXTENSION)) {

      continue;
    }

    if (!$hostImageBase64 = @base64_encode($curl->getContent())) {

      continue;
    }

    $hostImagesIndexed += $db->updateHostImageData($hostImage->hostImageId, (string) 'data:image/' . $hostImageType . ';base64,' . $hostImageBase64, time());
  }
}

// Process pages crawl queue
foreach ($db->getHostPageCrawlQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queueHostPage) {

  // Build URL from the DB
  $queueHostPageURL = $queueHostPage->scheme . '://' . $queueHostPage->name . ($queueHostPage->port ? ':' . $queueHostPage->port : false) . $queueHostPage->uri;

  $curl = new Curl($queueHostPageURL, CRAWL_CURLOPT_USERAGENT);

  // Update page index anyway, with the current time and http code
  $hostPagesProcessed += $db->updateHostPageCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode());

  // Skip next page processing non 200 code
  if (200 != $curl->getCode()) {

    continue;
  }

  // Skip next page processing pages without returned data
  if (!$content = $curl->getContent()) {

    continue;
  }

  // Grab page content
  $dom = new DomDocument();

  @$dom->loadHTML($content);

  // Skip index page links without titles
  $title = @$dom->getElementsByTagName('title');

  if ($title->length == 0) {
    continue;
  }

  // Get optional page meta data
  $metaDescription  = '';
  $metaKeywords     = '';
  $metaRobots       = '';
  $metaYggoManifest = '';

  foreach (@$dom->getElementsByTagName('meta') as $meta) {

    if (@$meta->getAttribute('name') == 'description') {
      $metaDescription = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'keywords') {
      $metaKeywords = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'robots') {
      $metaRobots = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'yggo:manifest') {
      $metaYggoManifest = Filter::url(@$meta->getAttribute('content'));
    }
  }

  // Update queued page data
  $hostPagesIndexed += $db->updateHostPage($queueHostPage->hostPageId,
                                           Filter::pageTitle($title->item(0)->nodeValue),
                                           Filter::pageDescription($metaDescription),
                                           Filter::pageKeywords($metaKeywords),
                                           CRAWL_HOST_DEFAULT_META_ONLY ? null : Filter::pageData($content));

  // Update manifest registry
  if (CRAWL_MANIFEST && !empty($metaYggoManifest) && filter_var($metaYggoManifest, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $metaYggoManifest)) {

    $metaYggoManifestCRC32 = crc32($metaYggoManifest);

    if (!$db->getManifest($metaYggoManifestCRC32)) {
         $db->addManifest($metaYggoManifestCRC32,
                          $metaYggoManifest,
                          (string) CRAWL_MANIFEST_DEFAULT_STATUS,
                          time());
    }
  }

  // Append page with meta robots:noindex value to the robotsPostfix disallow list
  if (false !== stripos($metaRobots, 'noindex')) {

    continue;
  }

  // Skip page links following by robots:nofollow attribute detected
  if (false !== stripos($metaRobots, 'nofollow')) {

    continue;
  }

  // Collect page images
  if (CRAWL_HOST_DEFAULT_IMAGES_LIMIT > 0) {

    foreach (@$dom->getElementsByTagName('img') as $img) {

      // Skip images without src attribute
      if (!$imageSrc = @$img->getAttribute('src')) {

        continue;
      }

      // Skip images without alt attribute
      if (!$imageAlt = @$img->getAttribute('alt')) {

        continue;
      }

      if (!$imageTitle = @$img->getAttribute('title')) {
           $imageTitle = null;
      }

      // Add domain to the relative src links
      if (!parse_url($imageSrc, PHP_URL_HOST)) {

        $imageSrc = $queueHostPage->scheme . '://' .
                    $queueHostPage->name .
                   ($queueHostPage->port ? ':' . $queueHostPage->port : '') .
                    '/' . trim(ltrim(str_replace(['./', '../'], '', $imageSrc), '/'), '.');
      }

      // Validate formatted src link
      if (filter_var($imageSrc, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $imageSrc)) {

        $db->beginTransaction();

        try {

          // Parse formatted src link
          $hostImageURL = Parser::hostURL($imageSrc);
          $hostImageURI = Parser::uri($imageSrc);

          // Host exists
          if ($host = $db->getHost(crc32($hostImageURL->string))) {

            $hostStatus        = $host->status;
            $hostPageLimit     = $host->crawlPageLimit;
            $hostImageLimit    = $host->crawlImageLimit;
            $hostId            = $host->hostId;
            $hostRobots        = $host->robots;
            $hostRobotsPostfix = $host->robotsPostfix;

          // Register new host
          } else {

            // Get robots.txt if exists
            $curl = new Curl($hostImageURL->string . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

            if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
              $hostRobots = $curl->getContent();
            } else {
              $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
            }

            $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

            $hostStatus    = CRAWL_HOST_DEFAULT_STATUS;
            $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;
            $hostImageLimit= CRAWL_HOST_DEFAULT_IMAGES_LIMIT;
            $hostId        = $db->addHost($hostImageURL->scheme,
                                          $hostImageURL->name,
                                          $hostImageURL->port,
                                          crc32($hostURL->string),
                                          time(),
                                          null,
                                          $hostPageLimit,
                                          $hostImageLimit,
                                          (string) CRAWL_HOST_DEFAULT_META_ONLY,
                                          (string) $hostStatus,
                                          $hostRobots,
                                          $hostRobotsPostfix);

            if ($hostId) {

              $hostsAdded++;

            } else {

              continue;
            }
          }

          // Init robots parser
          $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($hostRobotsPostfix ? (string) $hostRobotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

          // Save image info
          $hostImageId = $db->getHostImage($hostId, crc32($hostImageURI->string));

          if ($hostStatus && // host enabled
              $robots->uriAllowed($hostImageURI->string) && // src allowed by robots.txt rules
              $hostImageLimit > $db->getTotalHostImages($hostId) && // images quantity not reached host limit
             !$hostImageId) {  // image not exists

              // Add host image
              if ($hostImageId = $db->addHostImage($hostId, crc32($hostImageURI->string), $hostImageURI->string, time(), null, 200)) {

                $hostImagesAdded++;

              } else {

                continue;
              }
          }

          // Add host image description
          $hostImageDescriptionCRC32id = crc32(md5((string) $imageAlt . (string) $imageTitle));

          if (!$db->getHostImageDescription($hostImageId, $hostImageDescriptionCRC32id)) {
               $db->addHostImageDescription($hostImageId, $hostImageDescriptionCRC32id, (string) Filter::imageAlt($imageAlt), (string) Filter::imageTitle($imageTitle), time());
          }

          // Relate host image with host page was found
          if (!$db->getHostImageToHostPage($hostImageId, $queueHostPage->hostPageId)) {
               $db->addHostImageToHostPage($hostImageId, $queueHostPage->hostPageId, time(), null, 1);
          } else {
               $db->updateHostImageToHostPage($hostImageId, $queueHostPage->hostPageId, time(), 1);
          }

          // Increase page rank when link does not match the current host
          if ($hostImageURL->scheme . '://' .
              $hostImageURL->name .
             ($hostImageURL->port ? ':' . $hostImageURL->port : '')
              !=
              $queueHostPage->scheme . '://' .
              $queueHostPage->name .
             ($queueHostPage->port ? ':' . $queueHostPage->port : '')) {

              $db->updateHostImageRank($hostId, crc32($hostImageURI->string), 1);
          }

          $db->commit();

        } catch(Exception $e) {

          var_dump($e);

          $db->rollBack();
        }
      }
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

    // Skip javascript links
    if (false !== strpos($href, 'javascript:')) {

      continue;
    }

    // Skip mailto links
    if (false !== strpos($href, 'mailto:')) {

      continue;
    }

    // Skip x-raw-image links
    if (false !== strpos($href, 'x-raw-image:')) {

      continue;
    }

    // @TODO skip other apps

    // Add absolute URL prefixes to the relative links found
    if (!parse_url($href, PHP_URL_HOST)) {

      $href = $queueHostPage->scheme . '://' .
              $queueHostPage->name .
             ($queueHostPage->port ? ':' . $queueHostPage->port : '') .
             '/' . trim(ltrim(str_replace(['./', '../'], '', $href), '/'), '.');
    }

    // Validate formatted link
    if (filter_var($href, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $href)) {

      $db->beginTransaction();

      try {

        // Parse formatted link
        $hostURL     = Parser::hostURL($href);
        $hostPageURI = Parser::uri($href);

        // Host exists
        if ($host = $db->getHost(crc32($hostURL->string))) {

          $hostStatus        = $host->status;
          $hostPageLimit     = $host->crawlPageLimit;
          $hostImageLimit    = $host->crawlImageLimit;
          $hostId            = $host->hostId;
          $hostRobots        = $host->robots;
          $hostRobotsPostfix = $host->robotsPostfix;

        // Register new host
        } else {

          // Get robots.txt if exists
          $curl = new Curl($hostURL->string . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;
          $hostImageLimit= CRAWL_HOST_DEFAULT_IMAGES_LIMIT;
          $hostId        = $db->addHost($hostURL->scheme,
                                        $hostURL->name,
                                        $hostURL->port,
                                        crc32($hostURL->string),
                                        time(),
                                        null,
                                        $hostPageLimit,
                                        $hostImageLimit,
                                        (string) CRAWL_HOST_DEFAULT_META_ONLY,
                                        (string) $hostStatus,
                                        $hostRobots,
                                        $hostRobotsPostfix);

          if ($hostId) {

            $hostsAdded++;

          } else {

            continue;
          }
        }

        // Init robots parser
        $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($hostRobotsPostfix ? (string) $hostRobotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

        // Save page info
        if ($hostStatus && // host enabled
            $robots->uriAllowed($hostPageURI->string) && // page allowed by robots.txt rules
            $hostPageLimit > $db->getTotalHostPages($hostId) && // pages quantity not reached host limit
            !$db->getHostPage($hostId, crc32($hostPageURI->string))) {  // page not exists

            if ($db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time())) {

              $hostPagesAdded++;
            }
        }

        // Increase page rank when link does not match the current host
        if ($hostURL->scheme . '://' .
            $hostURL->name .
            ($hostURL->port ? ':' . $hostURL->port : '')
            !=
            $queueHostPage->scheme . '://' .
            $queueHostPage->name .
            ($queueHostPage->port ? ':' . $queueHostPage->port : '')) {

            $db->updateHostPageRank($hostId, crc32($hostPageURI->string), 1);
        }

        $db->commit();

      } catch(Exception $e){

        var_dump($e);

        $db->rollBack();
      }
    }
  }
}

// Debug
echo 'Pages processed: ' . $hostPagesProcessed . PHP_EOL;
echo 'Pages indexed: ' . $hostPagesIndexed . PHP_EOL;
echo 'Pages added: ' . $hostPagesAdded . PHP_EOL;
echo 'Images processed: ' . $hostImagesProcessed . PHP_EOL;
echo 'Images indexed: ' . $hostImagesIndexed . PHP_EOL;
echo 'Images added: ' . $hostImagesAdded . PHP_EOL;
echo 'Hosts added: ' . $hostsAdded . PHP_EOL;
echo 'Total time: ' . microtime(true) - $timeStart . PHP_EOL;
