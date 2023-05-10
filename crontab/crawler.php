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

$httpRequestsTotal     = 0;
$httpRequestsSizeTotal = 0;
$httpDownloadSizeTotal = 0;
$httpRequestsTimeTotal = 0;

$hostPagesProcessed    = 0;
$manifestsProcessed    = 0;
$hostPagesIndexed      = 0;
$manifestsAdded        = 0;
$hostPagesAdded        = 0;
$hostsAdded            = 0;
$hostPagesBanned       = 0;

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

$db->beginTransaction();

try {

  // Process manifests crawl queue
  foreach ($db->getManifestCrawlQueue(CRAWL_MANIFEST_LIMIT, time() - CRAWL_MANIFEST_SECONDS_OFFSET) as $queueManifest) {

    $curl = new Curl($queueManifest->url, CRAWL_CURLOPT_USERAGENT);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

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
    $curl = new Curl($remoteManifest->result->api->hosts, CRAWL_CURLOPT_USERAGENT);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

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

        // Host not exists
        if (!$db->getHost(crc32($hostURL))) {

          // Get robots.txt if exists
          $curl = new Curl($hostURL . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

          // Update curl stats
          $httpRequestsTotal++;
          $httpRequestsSizeTotal += $curl->getSizeRequest();
          $httpDownloadSizeTotal += $curl->getSizeDownload();
          $httpRequestsTimeTotal += $curl->getTotalTime();

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS ? 1 : 0;
          $hostNsfw      = CRAWL_HOST_DEFAULT_NSFW ? 1 : 0;
          $hostMetaOnly  = CRAWL_HOST_DEFAULT_META_ONLY ? 1 : 0;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;

          $hostId = $db->addHost( $remoteManifestHosts->result->scheme,
                                  $remoteManifestHosts->result->name,
                                  $remoteManifestHosts->result->port,
                                  crc32($hostURL),
                                  time(),
                                  null,
                                  $hostPageLimit,
                                  (string) $hostMetaOnly,
                                  (string) $hostStatus,
                                  (string) $hostNsfw,
                                  $hostRobots,
                                  $hostRobotsPostfix);

          // Add web root host page to make host visible in the crawl queue
          $db->addHostPage($hostId, crc32('/'), '/', time());

          // Increase counters
          $hostPagesAdded++;
          $hostsAdded++;
        }
      }
    }
  }

  // Process pages crawl queue
  foreach ($db->getHostPageCrawlQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queueHostPage) {

    // Build URL from the DB
    $queueHostPageURL = $queueHostPage->scheme . '://' . $queueHostPage->name . ($queueHostPage->port ? ':' . $queueHostPage->port : false) . $queueHostPage->uri;

    // Init page request
    $curl = new Curl($queueHostPageURL, CRAWL_CURLOPT_USERAGENT);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

    // Update page index anyway, with the current time and http code
    $hostPagesProcessed += $db->updateHostPageCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode());

    // Skip page processing non 200 code
    if (200 != $curl->getCode()) {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      continue;
    }

    // Validate MIME content type
    if ($contentType = $curl->getContentType()) {

      $db->updateHostPageMime($queueHostPage->hostPageId, Filter::mime($contentType), time());

    // Ban page if not available
    } else {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      continue;
    }

    // Parse MIME
    $hostPageIsDom  = false;
    $hostPageInMime = false;

    foreach ((array) explode(',', CRAWL_PAGE_MIME) as $mime) {

      $mime = Filter::mime($mime);

      // Check for DOM
      if (false !== strpos('text/html', $mime)) {

        $hostPageIsDom  = true;
        $hostPageInMime = true;
        break;
      }

      // Ban page on MIME type not allowed in settings
      if (false !== strpos(Filter::mime($contentType), $mime)) {

        $hostPageInMime = true;
        break;
      }
    }

    // Ban page not in MIME list
    if (!$hostPageInMime) {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      continue;
    }

    // Skip page processing without returned data
    if (!$content = $curl->getContent()) {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      continue;
    }

    // Define variables
    $title        = null;
    $description  = null;
    $keywords     = null;
    $robots       = null;
    $yggoManifest = null;

    // Is DOM content
    if ($hostPageIsDom) {

      // Parse content
      $dom = new DomDocument();

      @$dom->loadHTML($content);

      // Skip index page links without titles
      $title = @$dom->getElementsByTagName('title');

      if ($title->length == 0) {

        $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

        continue;

      } else {

        $title = $title->item(0)->nodeValue;
      }

      // Get optional page meta data
      foreach (@$dom->getElementsByTagName('meta') as $meta) {

        if (@$meta->getAttribute('name') == 'description') {
          $description = @$meta->getAttribute('content');
        }

        if (@$meta->getAttribute('name') == 'keywords') {
          $keywords = @$meta->getAttribute('content');
        }

        if (@$meta->getAttribute('name') == 'robots') {

          $robots = @$meta->getAttribute('content');

          // Ban page with meta robots:noindex value
          if (false !== stripos($robots, 'noindex')) {

            $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

            continue;
          }

          // Skip page with meta robots:nofollow attribute
          if (false !== stripos($robots, 'nofollow')) {

            continue;
          }
        }

        // Grab meta yggo:manifest link when available
        if (@$meta->getAttribute('name') == 'yggo:manifest') {
          $yggoManifest = Filter::url(@$meta->getAttribute('content'));
        }
      }
    }

    // Add queued page description if not exists
    if ($title || $description || $keywords) {

      $db->addHostPageDescription($queueHostPage->hostPageId,
                                  $title       ? Filter::pageTitle($title) : null,
                                  $description ? Filter::pageDescription($description) : null,
                                  $keywords    ? Filter::pageKeywords($keywords) : null,
                                  $content     ? ($queueHostPage->crawlMetaOnly ? null : base64_encode($content)) : null,
                                  time());
    }

    // Update manifest registry
    if (CRAWL_MANIFEST && !empty($yggoManifest) && filter_var($yggoManifest, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $yggoManifest)) {

      $yggoManifestCRC32 = crc32($yggoManifest);

      if (!$db->getManifest($yggoManifestCRC32)) {
           $db->addManifest($yggoManifestCRC32,
                            $yggoManifest,
                            (string) CRAWL_MANIFEST_DEFAULT_STATUS,
                            time());

           $manifestsAdded++;
      }
    }

    // Begin page links collection
    $links = [];

    // Collect image links
    foreach (@$dom->getElementsByTagName('img') as $img) {

      // Skip images without src attribute
      if (!$src = @$img->getAttribute('src')) {

        continue;
      }

      // Skip images without alt attribute
      if (!$alt = @$img->getAttribute('alt')) {

        continue;
      }

      if (!$title = @$img->getAttribute('title')) {
           $title = null;
      }

      // Skip encoded content
      if (false !== strpos($src, 'data:')) {

        continue;
      }

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => Filter::pageKeywords($alt . ($title ? ',' . $title : '')),
        'data'        => null,
        'ref'         => $src,
      ];
    }

    // Collect internal links from page content
    foreach(@$dom->getElementsByTagName('a') as $a) {

      // Skip links without required attribute
      if (!$href = @$a->getAttribute('href')) {

        continue;
      }

      // Get title attribute if available
      if (!$title = @$a->getAttribute('title')) {
           $title = null;
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

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => Filter::pageKeywords($title),
        'data'        => null,
        'ref'         => $href,
      ];
    }

    // Process links collected
    foreach ($links as $link) {

      //Make relative links absolute
      if (!parse_url($link['ref'], PHP_URL_HOST)) {

        $link['ref'] = $queueHostPage->scheme . '://' .
                       $queueHostPage->name .
                      ($queueHostPage->port ? ':' . $queueHostPage->port : '') .
                      '/' . trim(ltrim(str_replace(['./', '../'], '', $link['ref']), '/'), '.');
      }

      // Validate formatted link
      if (filter_var($link['ref'], FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $link['ref'])) {

        // Parse formatted link
        $hostURL     = Parser::hostURL($link['ref']);
        $hostPageURI = Parser::uri($link['ref']);

        // Host exists
        if ($host = $db->getHost(crc32($hostURL->string))) {

          $hostStatus        = $host->status;
          $hostNsfw          = $host->nsfw;
          $hostPageLimit     = $host->crawlPageLimit;
          $hostMetaOnly      = $host->crawlMetaOnly;
          $hostId            = $host->hostId;
          $hostRobots        = $host->robots;
          $hostRobotsPostfix = $host->robotsPostfix;

        // Register new host
        } else {

          // Get robots.txt if exists
          $curl = new Curl($hostURL->string . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

          // Update curl stats
          $httpRequestsTotal++;
          $httpRequestsSizeTotal += $curl->getSizeRequest();
          $httpDownloadSizeTotal += $curl->getSizeDownload();
          $httpRequestsTimeTotal += $curl->getTotalTime();

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;
          $hostStatus        = CRAWL_HOST_DEFAULT_STATUS ? 1 : 0;
          $hostNsfw          = CRAWL_HOST_DEFAULT_NSFW ? 1 : 0;
          $hostMetaOnly      = CRAWL_HOST_DEFAULT_META_ONLY ? 1 : 0;
          $hostPageLimit     = CRAWL_HOST_DEFAULT_PAGES_LIMIT;

          $hostId = $db->addHost( $hostURL->scheme,
                                  $hostURL->name,
                                  $hostURL->port,
                                  crc32($hostURL->string),
                                  time(),
                                  null,
                                  $hostPageLimit,
                                  (string) $hostMetaOnly,
                                  (string) $hostStatus,
                                  (string) $hostNsfw,
                                  $hostRobots,
                                  $hostRobotsPostfix);

          // Add web root host page to make host visible in the crawl queue
          $db->addHostPage($hostId, crc32('/'), '/', time());

          // Increase counters
          $hostPagesAdded++;
          $hostsAdded++;

          // When page is root, skip next operations
          if ($hostPageURI->string == '/') {

            continue;
          }
        }

        // Init robots parser
        $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($hostRobotsPostfix ? (string) $hostRobotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

        // Save page info
        if ($hostStatus && // host enabled
            $robots->uriAllowed($hostPageURI->string) && // page allowed by robots.txt rules
            $hostPageLimit > $db->getTotalHostPages($hostId)) { // pages quantity not reached host limit

            if ($hostPage = $db->getHostPage($hostId, crc32($hostPageURI->string))) {

              $hostPageId = $hostPage->hostPageId;

            } else {

              $hostPageId = $db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time());

              $db->addHostPageDescription($hostPageId,
                                          $link['title'],
                                          $link['description'],
                                          $link['keywords'],
                                          $hostMetaOnly ? null : ($link['data'] ? base64_encode($link['data']) : null),
                                          time());

              $hostPagesAdded++;
            }

            $db->addHostPageToHostPage($queueHostPage->hostPageId, $hostPageId);
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
$executionTimeTotal    = microtime(true) - $timeStart;
$httpRequestsTimeTotal = $httpRequestsTimeTotal / 1000000;

if (CRAWL_LOG_ENABLED) {

  $db->addCrawlerLog(time(),
                     $hostsAdded,
                     $hostPagesProcessed,
                     $hostPagesIndexed,
                     $hostPagesAdded,
                     $hostPagesBanned,
                     $manifestsProcessed,
                     $manifestsAdded,
                     $httpRequestsTotal,
                     $httpRequestsSizeTotal,
                     $httpDownloadSizeTotal,
                     $httpRequestsTimeTotal,
                     $executionTimeTotal);
}

// Debug output
echo 'Hosts added: ' . $hostsAdded . PHP_EOL;

echo 'Pages processed: ' . $hostPagesProcessed . PHP_EOL;
echo 'Pages indexed: ' . $hostPagesIndexed . PHP_EOL;
echo 'Pages added: ' . $hostPagesAdded . PHP_EOL;
echo 'Pages banned: ' . $hostPagesBanned . PHP_EOL;

echo 'Manifests processed: ' . $manifestsProcessed . PHP_EOL;
echo 'Manifests added: ' . $manifestsAdded . PHP_EOL;

echo 'HTTP Requests total: ' . $httpRequestsTotal . PHP_EOL;
echo 'HTTP Requests total size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo 'HTTP Download total size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo 'HTTP Requests total time: ' . $httpRequestsTimeTotal . PHP_EOL;

echo 'Total time: ' . $executionTimeTotal . PHP_EOL . PHP_EOL;
