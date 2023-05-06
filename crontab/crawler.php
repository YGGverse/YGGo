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
$manifestsProcessed  = 0;
$hostPagesIndexed    = 0;
$hostImagesIndexed   = 0;
$manifestsIndexed    = 0;
$hostPagesAdded      = 0;
$hostImagesAdded     = 0;
$hostsAdded          = 0;
$hostPagesBanned     = 0;
$hostImagesBanned    = 0;

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

$db->beginTransaction();

try {

  // Process manifests crawl queue
  foreach ($db->getManifestCrawlQueue(CRAWL_MANIFEST_LIMIT, time() - CRAWL_MANIFEST_SECONDS_OFFSET) as $queueManifest) {

    $curl = new Curl($queueManifest->url);

    // Update manifest index anyway, with the current time and http code
    $manifestsProcessed += $db->updateManifestCrawlQueue($queueManifest->manifestId, time(), $curl->getCode());

    // Skip processing non 200 code
    if (200 != $curl->getCode()) {

      continue;
    }

    // Skip processing without returned data
    if (!$remoteManifest = $curl->getContent()) {

      continue;
    }

    // Skip processing on json encoding error
    if (!$remoteManifest = @json_decode($remoteManifest)) {

      continue;
    }

    // Skip processing on required fields missed
    if (empty($remoteManifest->status) ||
        empty($remoteManifest->result->config->crawlUrlRegexp) ||
        empty($remoteManifest->result->api->version) ||
        empty($remoteManifest->result->api->hosts)) {

      continue;
    }

    // Skip processing on API version not compatible
    if ($remoteManifest->result->api->version !== CRAWL_MANIFEST_API_VERSION) {

      continue;
    }

    // Skip processing on host API not available
    if (!$remoteManifest->result->api->hosts) {

      continue;
    }

    // Skip processing on crawlUrlRegexp does not match CRAWL_URL_REGEXP condition
    if ($remoteManifest->result->config->crawlUrlRegexp !== CRAWL_URL_REGEXP) {

      continue;
    }

    // Skip processing on host link does not match condition
    if (false === preg_match(CRAWL_URL_REGEXP, $remoteManifest->result->api->hosts)) {

      continue;
    }

    // Begin hosts collection
    $curl = new Curl($remoteManifest->result->api->hosts);

    // Skip processing non 200 code
    if (200 != $curl->getCode()) {

      continue;
    }

    // Skip processing without returned data
    if (!$remoteManifestHosts = $curl->getContent()) {

      continue;
    }

    // Skip processing on json encoding error
    if (!$remoteManifestHosts = @json_decode($remoteManifestHosts)) {

      continue;
    }

    // Skip processing on required fields missed
    if (empty($remoteManifestHosts->status) ||
        empty($remoteManifestHosts->result)) {

      continue;
    }

    // Begin hosts processing
    foreach ($remoteManifestHosts->result as $remoteManifestHost) {

      // Skip processing on required fields missed
      if (empty($remoteManifestHost->scheme) ||
          empty($remoteManifestHost->name)) {

        continue;
      }

      $hostURL = $remoteManifestHost->scheme . '://' .
                 $remoteManifestHost->name .
                (!empty($remoteManifestHost->port) ? ':' . $remoteManifestHost->port : false);

      // Validate formatted link
      if (filter_var($hostURL, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $hostURL)) {

        // Host exists
        if ($host = $db->getHost(crc32($hostURL))) {

          $hostStatus        = $host->status;
          $hostPageLimit     = $host->crawlPageLimit;
          $hostImageLimit    = $host->crawlImageLimit;
          $hostId            = $host->hostId;
          $hostRobots        = $host->robots;
          $hostRobotsPostfix = $host->robotsPostfix;

        // Register new host
        } else {

          // Get robots.txt if exists
          $curl = new Curl($hostURL . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;
          $hostImageLimit= CRAWL_HOST_DEFAULT_IMAGES_LIMIT;

          $hostId        = $db->addHost($remoteManifestHosts->result->scheme,
                                        $remoteManifestHosts->result->name,
                                        $remoteManifestHosts->result->port,
                                        crc32($hostURL),
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

        // Save home page info
        // Until page API not implemented, save at least home page to have ability to crawl
        // @TODO
        if ($hostStatus && // host enabled
            $robots->uriAllowed('/') && // page allowed by robots.txt rules
            $hostPageLimit > $db->getTotalHostPages($hostId) && // pages quantity not reached host limit
            !$db->getHostPage($hostId, crc32('/'))) {  // page not exists

            if ($db->addHostPage($hostId, crc32('/'), '/', time())) {

              $hostPagesAdded++;
            }
        }
      }
    }
  }

  // Process images crawl queue
  foreach ($db->getHostImageCrawlQueue(CRAWL_IMAGE_LIMIT, time() - CRAWL_IMAGE_SECONDS_OFFSET) as $queueHostImage) {

    // Define image variables
    $hostImageTimeBanned = null;

    // Build URL from the DB
    $queueHostImageURL = $queueHostImage->scheme . '://' . $queueHostImage->name . ($queueHostImage->port ? ':' . $queueHostImage->port : false) . $queueHostImage->uri;

    // Init image request
    $curl = new Curl($queueHostImageURL, CRAWL_CURLOPT_USERAGENT);

    // Update image index anyway, with the current time and http code
    $hostImagesProcessed += $db->updateHostImageCrawlQueue($queueHostImage->hostImageId, time(), $curl->getCode());

    // Skip image processing non 200 code
    if (200 != $curl->getCode()) {

      $hostImagesBanned++;

      $hostImageTimeBanned = time();

      continue;
    }

    // Skip image processing on MIME type not provided
    if (!$hostImageContentType = $curl->getContentType()) {

      $hostImagesBanned++;

      $hostImageTimeBanned = time();

      continue;
    }

    // Skip image processing on MIME type not allowed in settings
    if (false === strpos(CRAWL_IMAGE_MIME_TYPE, $hostImageContentType)) {

      $hostImagesBanned++;

      $hostImageTimeBanned = time();

      continue;
    }

    // Convert remote image data to base64 string
    if (!CRAWL_HOST_DEFAULT_META_ONLY) {

      // Skip image processing without returned content
      if (!$hostImageContent = $curl->getContent()) {

        $hostImagesBanned++;

        $hostImageTimeBanned = time();

        continue;
      }

      if (!$hostImageExtension = @pathinfo($queueHostImageURL, PATHINFO_EXTENSION)) {

        $hostImagesBanned++;

        $hostImageTimeBanned = time();

        continue;
      }

      if (!$hostImageBase64 = @base64_encode($hostImageContent)) {

        $hostImagesBanned++;

        $hostImageTimeBanned = time();

        continue;
      }

      $hostImageData = 'data:image/' . $hostImageExtension . ';base64,' . $hostImageBase64;

    } else {

      $hostImageData = null;
    }

    $hostImagesIndexed += $db->updateHostImage($hostImage->hostImageId,
                                               Filter::mime($hostImageContentType),
                                               $hostImageData,
                                               time(),
                                               $hostImageTimeBanned);
  }

  // Process pages crawl queue
  foreach ($db->getHostPageCrawlQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queueHostPage) {

    // Define page variables
    $hostPageTimeBanned = null;

    // Build URL from the DB
    $queueHostPageURL = $queueHostPage->scheme . '://' . $queueHostPage->name . ($queueHostPage->port ? ':' . $queueHostPage->port : false) . $queueHostPage->uri;

    // Init page request
    $curl = new Curl($queueHostPageURL, CRAWL_CURLOPT_USERAGENT);

    // Update page index anyway, with the current time and http code
    $hostPagesProcessed += $db->updateHostPageCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode());

    // Skip page processing non 200 code
    if (200 != $curl->getCode()) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

      continue;
    }

    // Skip page processing on MIME type not provided
    if (!$contentType = $curl->getContentType()) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

      continue;
    }

    // Skip page processing on MIME type not allowed in settings
    if (false === strpos(CRAWL_PAGE_MIME_TYPE, $contentType)) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

      continue;
    }

    // Skip page processing without returned data
    if (!$content = $curl->getContent()) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

      continue;
    }

    // Grab page content
    $dom = new DomDocument();

    @$dom->loadHTML($content);

    // Skip index page links without titles
    $title = @$dom->getElementsByTagName('title');

    if ($title->length == 0) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

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

    // Append page with meta robots:noindex value to the robotsPostfix disallow list
    if (false !== stripos($metaRobots, 'noindex')) {

      $hostPagesBanned++;

      $hostPageTimeBanned = time();

      continue;
    }

    // Skip page links following by robots:nofollow attribute detected
    if (false !== stripos($metaRobots, 'nofollow')) {

      continue;
    }

    // Update queued page data
    $hostPagesIndexed += $db->updateHostPage($queueHostPage->hostPageId,
                                             Filter::pageTitle($title->item(0)->nodeValue),
                                             Filter::pageDescription($metaDescription),
                                             Filter::pageKeywords($metaKeywords),
                                             Filter::mime($contentType),
                                             CRAWL_HOST_DEFAULT_META_ONLY ? null : Filter::pageData($content),
                                             time(),
                                             $hostPageTimeBanned);

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

          // Save new image info
          $hostImageId = $db->getHostImageId($hostId, crc32($hostImageURI->string));

          if (!$hostImageId && // image not exists
               $hostStatus && // host enabled
               $robots->uriAllowed($hostImageURI->string) && // src allowed by robots.txt rules
               $hostImageLimit > $db->getTotalHostImages($hostId)) { // images quantity not reached host limit

            // Add host image
            if ($hostImageId = $db->addHostImage($hostId,
                                                 crc32($hostImageURI->string),
                                                 $hostImageURI->string,
                                                 time())) {

              $hostImagesAdded++;

            } else {

              continue;
            }
          }

          // Host image exists or created new one
          if ($hostImageId) {

            // Add/update host image description
            $db->setHostImageDescription($hostImageId,
                                          crc32(md5((string) $imageAlt . (string) $imageTitle)),
                                          Filter::imageAlt($imageAlt),
                                          Filter::imageTitle($imageTitle),
                                          time(),
                                          time());

            // Relate host image with host page was found
            $db->setHostImageToHostPage($hostImageId, $queueHostPage->hostPageId, time(), time(), 1);
          }

          // Increase image rank when link does not match the current host
          if ($hostImageURL->scheme . '://' .
              $hostImageURL->name .
              ($hostImageURL->port ? ':' . $hostImageURL->port : '')
              !=
              $queueHostPage->scheme . '://' .
              $queueHostPage->name .
              ($queueHostPage->port ? ':' . $queueHostPage->port : '')) {

              $db->updateHostImageRank($hostId, crc32($hostImageURI->string), 1);
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
      }
    }
  }

  $db->commit();

} catch(Exception $e) {

  var_dump($e);

  $db->rollBack();
}

// Debug
echo 'Pages processed: ' . $hostPagesProcessed . PHP_EOL;
echo 'Pages indexed: ' . $hostPagesIndexed . PHP_EOL;
echo 'Pages added: ' . $hostPagesAdded . PHP_EOL;
echo 'Images processed: ' . $hostImagesProcessed . PHP_EOL;
echo 'Images indexed: ' . $hostImagesIndexed . PHP_EOL;
echo 'Images added: ' . $hostImagesAdded . PHP_EOL;
echo 'Manifests processed: ' . $manifestsProcessed . PHP_EOL;
echo 'Manifests indexed: ' . $manifestsIndexed . PHP_EOL;
echo 'Hosts added: ' . $hostsAdded . PHP_EOL;
echo 'Hosts pages banned: ' . $hostPagesBanned . PHP_EOL;
echo 'Hosts images banned: ' . $hostImagesBanned . PHP_EOL;
echo 'Total time: ' . microtime(true) - $timeStart . PHP_EOL . PHP_EOL;
