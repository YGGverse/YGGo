<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'Process locked by another thread.' . PHP_EOL;
  exit;
}

// Load system dependencies
require_once('../config/app.php');
require_once('../library/ftp.php');
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
$manifestsAdded        = 0;
$hostPagesAdded        = 0;
$hostsAdded            = 0;
$hostPagesBanned       = 0;
$hostPagesSnapAdded    = 0;

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  // Debug std
  var_dump($e);

  exit;
}

// Process manifests crawl queue
foreach ($db->getManifestCrawlQueue(CRAWL_MANIFEST_LIMIT, time() - CRAWL_MANIFEST_SECONDS_OFFSET) as $queueManifest) {

  $db->beginTransaction();

  try {

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

      $db->commit();

      continue;
    }

    // Skip processing without returned data
    if (!$remoteManifest = $curl->getContent()) {

      $db->commit();

      continue;
    }

    // Skip processing on json encoding error
    if (!$remoteManifest = @json_decode($remoteManifest)) {

      $db->commit();

      continue;
    }

    // Skip processing on required fields missed
    if (empty($remoteManifest->status) ||
        empty($remoteManifest->result->config->crawlUrlRegexp) ||
        empty($remoteManifest->result->api->version) ||
        empty($remoteManifest->result->api->hosts)) {

        $db->commit();

        continue;
    }

    // Skip processing on API version not compatible
    if ($remoteManifest->result->api->version !== CRAWL_MANIFEST_API_VERSION) {

      $db->commit();

      continue;
    }

    // Skip processing on host API not available
    if (!$remoteManifest->result->api->hosts) {

      $db->commit();

      continue;
    }

    // Skip processing on crawlUrlRegexp does not match CRAWL_URL_REGEXP condition
    if ($remoteManifest->result->config->crawlUrlRegexp !== CRAWL_URL_REGEXP) {

      $db->commit();

      continue;
    }

    // Skip processing on host link does not match condition
    if (false === preg_match(CRAWL_URL_REGEXP, $remoteManifest->result->api->hosts)) {

      $db->commit();

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

      $db->commit();

      continue;
    }

    // Skip processing without returned data
    if (!$remoteManifestHosts = $curl->getContent()) {

      $db->commit();

      continue;
    }

    // Skip processing on json encoding error
    if (!$remoteManifestHosts = @json_decode($remoteManifestHosts)) {

      $db->commit();

      continue;
    }

    // Skip processing on required fields missed
    if (empty($remoteManifestHosts->status) ||
        empty($remoteManifestHosts->result)) {

      $db->commit();

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

    // Apply changes
    $db->commit();

  // Process update errors
  } catch (Exception $e) {

    // Debug std
    var_dump($e);

    // Skip item
    $db->rollBack();

    continue;
  }
}

// Process pages crawl queue
foreach ($db->getHostPageCrawlQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queueHostPage) {

  $db->beginTransaction();

  try {

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
    $hostPagesProcessed += $db->updateHostPageCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode(), $curl->getSizeDownload());

    // This page has on 200 code
    if (200 != $curl->getCode()) {

      // Ban this page
      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      // Try to receive target page location on page redirect available
      $curl = new Curl($queueHostPageURL, CRAWL_CURLOPT_USERAGENT, 10, true, true);

      // Update curl stats
      $httpRequestsTotal++;
      $httpRequestsSizeTotal += $curl->getSizeRequest();
      $httpDownloadSizeTotal += $curl->getSizeDownload();
      $httpRequestsTimeTotal += $curl->getTotalTime();

      if (200 == $curl->getCode()) {

        if (preg_match('~Location: (.*)~i', $curl->getContent(), $match)) {

          if (empty($match[1])) {

            $db->commit();

            continue;
          }

          $url = trim($match[1]);

          //Make relative links absolute
          if (!parse_url($url, PHP_URL_HOST)) { // @TODO probably, case not in use

            $url = $queueHostPage->scheme . '://' .
                  $queueHostPage->name .
                  ($queueHostPage->port ? ':' . $queueHostPage->port : '') .
                  '/' . trim(ltrim(str_replace(['./', '../'], '', $url), '/'), '.');
          }

          // Validate formatted link
          if (filter_var($url, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $url)) {

            // Parse formatted link
            $hostURL     = Parser::hostURL($url);
            $hostPageURI = Parser::uri($url);

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

                $db->commit();

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

                  // Apply referer meta description to the target page before indexing it
                  if ($lastHostPageDescription = $db->getLastPageDescription($queueHostPage->hostPageId)) {

                      $db->addHostPageDescription($hostPageId,
                                                  $lastHostPageDescription->title,
                                                  $lastHostPageDescription->description,
                                                  $lastHostPageDescription->keywords,
                                                  $hostMetaOnly ? null : ($lastHostPageDescription->data ? base64_encode($lastHostPageDescription->data) : null),
                                                  time());
                  }

                  $hostPagesAdded++;
                }

                $db->addHostPageToHostPage($queueHostPage->hostPageId, $hostPageId);
            }
          }
        }
      }

      // Skip other this page actions
      $db->commit();

      continue;
    }

    // Validate MIME content type
    if ($contentType = $curl->getContentType()) {

      $db->updateHostPageMime($queueHostPage->hostPageId, Filter::mime($contentType), time());

    // Ban page if not available
    } else {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      $db->commit();

      continue;
    }

    // Parse MIME
    $hostPageIsHtml = false;
    $hostPageInMime = false;

    foreach ((array) explode(',', CRAWL_PAGE_MIME_INDEX) as $mime) {

      // Ban page on MIME type not allowed in settings
      if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

        // Check for HTML page
        if (false !== stripos(Filter::mime($contentType), 'text/html')) {

          $hostPageIsHtml  = true;
        }

        $hostPageInMime = true;
        break;
      }
    }

    // Ban page not in MIME list
    if (!$hostPageInMime) {

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      $db->commit();

      continue;
    }

    // Skip page processing without returned data
    if (!$content = $curl->getContent()) {

      // Prevent page ban when it MIME in the whitelist, skip steps below only
      // This case possible for multimedia/streaming resources index
      // $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      $db->commit();

      continue;
    }

    // Define variables
    $title        = null;
    $description  = null;
    $keywords     = null;
    $robots       = null;
    $yggoManifest = null;

    // Is DOM content
    if ($hostPageIsHtml) {

      // Parse content
      $dom = new DomDocument();

      @$dom->loadHTML($content);

      // Skip index page links without titles
      $title = @$dom->getElementsByTagName('title');

      if ($title->length == 0) {

        $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

        $db->commit();

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

    // Begin snaps
    $snapLocal = false;
    $snapMega  = false;

    // Snap local enabled and MIME in white list
    if (false !== CRAWL_PAGE_MIME_SNAP_LOCAL) {

      foreach ((array) explode(',', CRAWL_PAGE_MIME_SNAP_LOCAL) as $mime) {

        // MIME type allowed in settings
        if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

          $snapLocal = true;
          break;
        }
      }
    }

    // Snap MEGA enabled and MIME in white list
    if (false !== CRAWL_PAGE_MIME_SNAP_MEGA) {

      foreach ((array) explode(',', CRAWL_PAGE_MIME_SNAP_MEGA) as $mime) {

        // MIME type allowed in settings
        if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

          $snapMega = true;
          break;
        }
      }
    }

    // At least one snap storage match settings condition
    if ($snapLocal || $snapMega) {

      $crc32data = crc32($content);

      // Create not duplicated data snaps only, even new time
      if (!$db->findHostPageSnap($queueHostPage->hostPageId, $crc32data)) {

        $snapTime = time();
        $snapPath = chunk_split($queueHostPage->hostPageId, 1, '/');

        $snapTmp  = '../storage/tmp/snap/hp/' . $snapPath . $snapTime . '.zip';
            @mkdir('../storage/tmp/snap/hp/' . $snapPath, 0755, true);

        // Create new ZIP container
        $zip = new ZipArchive();

        if (true === $zip->open($snapTmp, ZipArchive::CREATE)) {

          // Insert compressed snap data into the tmp storage
          if (true === $zip->addFromString('DATA', $content) &&
              true === $zip->addFromString('META', sprintf('TIMESTAMP: %s', $snapTime) . PHP_EOL .
                                                   sprintf('CRC32: %s',     $crc32data . PHP_EOL .
                                                   sprintf('MIME: %s',      Filter::mime($contentType)) . PHP_EOL .
                                                   sprintf('SOURCE: %s',    Filter::url(WEBSITE_DOMAIN . '/explore.php?hp=' . $queueHostPage->hostPageId)) . PHP_EOL .
                                                   sprintf('TARGET: %s',    Filter::url($queueHostPageURL))))) {

            // Done
            $zip->close();

            // Temporarily snap file exists
            if (file_exists($snapTmp)) {

              // Register snap in DB
              if ($hostPageSnapId = $db->addHostPageSnap($queueHostPage->hostPageId, $crc32data, $snapTime)) {

                $hostPagesSnapAdded++;

                // Copy tmp snap to the permanent local storage
                if ($snapLocal) {

                  @mkdir('../storage/snap/hp/' . $snapPath, 0755, true);

                  if (copy($snapTmp, '../storage/snap/hp/' . $snapPath . $snapTime . '.zip')) {

                    // Update snap location info
                    $db->updateHostPageSnapStorageLocal($hostPageSnapId, true);
                  }
                }

                // Copy tmp snap to the permanent MEGA storage
                if ($snapMega) {

                  $ftp = new Ftp();

                  if ($ftp->connect(MEGA_FTP_HOST, MEGA_FTP_PORT, null, null, MEGA_FTP_DIRECTORY)) {

                    $ftp->mkdir('hp/' . $snapPath, true);

                    if ($ftp->copy($snapTmp, 'hp/' . $snapPath . $snapTime . '.zip')) {

                      // Update snap location info
                      $db->updateHostPageSnapStorageMega($hostPageSnapId, true);
                    }

                    $ftp->close();
                  }
                }
              }
            }
          }
        }

        // Remove tmp
        @unlink($snapTmp);
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
      if (false !== stripos($src, 'data:')) {

        continue;
      }

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => Filter::pageKeywords($alt . ($title ? ',' . $title : '')),
        'data'        => null,
        'mime'        => null,
        'ref'         => $src,
      ];
    }

    // Collect media links
    foreach (@$dom->getElementsByTagName('source') as $source) {

      // Skip images without src attribute
      if (!$src = @$source->getAttribute('src')) {

        continue;
      }

      // Skip media without type attribute
      if (!$type = @$source->getAttribute('type')) {

        continue;
      }

      // Skip encoded content
      if (false !== stripos($src, 'data:')) {

        continue;
      }

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => null,
        'data'        => null,
        'mime'        => Filter::mime($type),
        'ref'         => $src,
      ];
    }

    foreach (@$dom->getElementsByTagName('video') as $video) {

      // Skip images without src attribute
      if (!$src = @$video->getAttribute('src')) {

        continue;
      }

      // Skip media without type attribute
      if (!$type = @$video->getAttribute('type')) {
          $type = 'video/*';
      }

      // Skip encoded content
      if (false !== stripos($src, 'data:')) {

        continue;
      }

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => null,
        'data'        => null,
        'mime'        => Filter::mime($type),
        'ref'         => $src,
      ];
    }

    foreach (@$dom->getElementsByTagName('audio') as $audio) {

      // Skip images without src attribute
      if (!$src = @$audio->getAttribute('src')) {

        continue;
      }

      // Skip media without type attribute
      if (!$type = @$audio->getAttribute('type')) {
          $type = 'audio/*';
      }

      // Skip encoded content
      if (false !== stripos($src, 'data:')) {

        continue;
      }

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => null,
        'data'        => null,
        'mime'        => Filter::mime($type),
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
      if (false !== stripos($href, '#')) {

        continue;
      }

      // Skip javascript links
      if (false !== stripos($href, 'javascript:')) {

        continue;
      }

      // Skip mailto links
      if (false !== stripos($href, 'mailto:')) {

        continue;
      }

      // Skip magnet links
      if (false !== stripos($href, 'magnet:')) {

        continue;
      }

      // Skip x-raw-image links
      /*
      if (false !== stripos($href, 'x-raw-image:')) {

        continue;
      }
      */

      // Add link to queue
      $links[] = [
        'title'       => null,
        'description' => null,
        'keywords'    => Filter::pageKeywords($title),
        'data'        => null,
        'mime'        => null,
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

    // Apply changes
    $db->commit();

  // Process update errors
  } catch (Exception $e) {

    // Debug std
    var_dump($e);

    // Ban page that throws the data type error and stuck the crawl queue
    if (!empty($queueHostPage->hostPageId) &&
        !empty($e->errorInfo[0]) && in_array($e->errorInfo[0], ['HY000']) &&
        !empty($e->errorInfo[1]) && in_array($e->errorInfo[1], [1366])) { // @TODO

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      $hostPagesProcessed++;
    }

    // Skip item
    $db->rollBack();

    continue;
  }
}

// Debug
$executionTimeTotal    = microtime(true) - $timeStart;
$httpRequestsTimeTotal = $httpRequestsTimeTotal / 1000000;

if (CRAWL_LOG_ENABLED) {

  $db->addCrawlerLog(time(),
                     $hostsAdded,
                     $hostPagesProcessed,
                     $hostPagesAdded,
                     $hostPagesSnapAdded,
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
echo 'Pages added: ' . $hostPagesAdded . PHP_EOL;
echo 'Pages snaps added: ' . $hostPagesSnapAdded . PHP_EOL;
echo 'Pages banned: ' . $hostPagesBanned . PHP_EOL;

echo 'Manifests processed: ' . $manifestsProcessed . PHP_EOL;
echo 'Manifests added: ' . $manifestsAdded . PHP_EOL;

echo 'HTTP Requests total: ' . $httpRequestsTotal . PHP_EOL;
echo 'HTTP Requests total size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo 'HTTP Download total size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo 'HTTP Requests total time: ' . $httpRequestsTimeTotal . PHP_EOL;

echo 'Total time: ' . $executionTimeTotal . PHP_EOL . PHP_EOL;
