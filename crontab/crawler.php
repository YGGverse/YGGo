<?php

// Stop crawler on cli running
$semaphore = sem_get(crc32('cli.yggo'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'cli.yggo process running in another thread.' . PHP_EOL;
  exit;
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'process locked by another thread.' . PHP_EOL;
  exit;
}

// Begin debug output
echo '-- ' . date('c') . ' --' . PHP_EOL . PHP_EOL;

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/ftp.php');
require_once(__DIR__ . '/../library/curl.php');
require_once(__DIR__ . '/../library/robots.php');
require_once(__DIR__ . '/../library/sitemap.php');
require_once(__DIR__ . '/../library/url.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/helper.php');
require_once(__DIR__ . '/../library/vendor/simple_html_dom.php');

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

$hostsProcessed        = 0;
$hostsAdded            = 0;

$hostPagesProcessed    = 0;
$hostPagesBanned       = 0;
$hostPagesSnapAdded    = 0;
$hostPagesAdded        = 0;

$manifestsProcessed    = 0;
$sitemapsProcessed     = 0;

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect memcached
try {

  $memcached = new Memcached();
  $memcached->addServer(MEMCACHED_HOST, MEMCACHED_PORT);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Process hosts crawl queue
foreach ($db->getHostCrawlQueue(CRAWL_HOST_LIMIT, time() - CRAWL_HOST_SECONDS_OFFSET) as $queueHost) {

  try {

    $db->beginTransaction();

    // Update host crawl queue
    $hostsProcessed += $db->updateHostCrawlQueue($queueHost->hostId, time());

    // Update host robots.txt settings from remote host
    if (CRAWL_ROBOTS) {

      $curl = new Curl($queueHost->url . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

      // Update curl stats
      $httpRequestsTotal++;
      $httpRequestsSizeTotal += $curl->getSizeRequest();
      $httpDownloadSizeTotal += $curl->getSizeDownload();
      $httpRequestsTimeTotal += $curl->getTotalTime();

      // Update robots.txt rules
      if (200 == $curl->getCode() && false !== stripos(trim(mb_strtolower((string) $curl->getContentType())), 'text/plain')) {

        Helper::setHostSetting($db, $memcached, $queueHost->hostId, 'ROBOTS_TXT', (string) $curl->getContent());
      }
    }

    // Process sitemaps when enabled
    if (CRAWL_SITEMAPS) {

      // Look for custom sitemap URL served in robots.txt
      $robots = new Robots(
        Helper::getHostSetting($db, $memcached, $queueHost->hostId, 'ROBOTS_TXT', NULL) . PHP_EOL .
        Helper::getHostSetting($db, $memcached, $queueHost->hostId, 'ROBOTS_TXT_POSTFIX', DEFAULT_HOST_ROBOTS_TXT_POSTFIX)
      );

      if ($sitemapLink = $robots->getSitemap()) {

        // Replace relative paths
        $sitemapURL = sprintf('%s/%s', $queueHost->url, trim(str_ireplace($queueHost->url, '', $sitemapLink), '/'));

      // Set default path
      } else {

        $sitemapURL = sprintf('%s/sitemap.xml', $queueHost->url);
      }

      // Init sitemap
      $sitemap = new Sitemap($sitemapURL);

      if ($sitemapLinks = $sitemap->getLinks()) {

        $sitemapsProcessed++;

        // Process collected sitemap links
        foreach ($sitemapLinks as $loc => $attributes) {

          // Replace relative paths
          $loc = sprintf('%s/%s', $queueHost->url, trim(str_ireplace($queueHost->url, '', $loc), '/'));

          // Validate link
          if (!$link = URL::parse($loc)) {

            continue;
          }

          // Collect this host links only
          if ($link->host->url != $queueHost->url) {

            continue;
          }

          // Register new link
          if ($linkToDBresult = Helper::addLinkToDB($db, $memcached, $loc)) {

            $hostsAdded     += count($linkToDBresult->new->hostId);
            $hostPagesAdded += count($linkToDBresult->new->hostPageId);
          }
        }
      }
    }

    // Update manifests
    if (CRAWL_MANIFEST) {

      // Host have manifest provided
      if ($manifestURL = Helper::getHostSetting($db, $memcached, $queueHost->hostId, 'MANIFEST_URL', NULL)) {

        // Get remote manifest
        $curl = new Curl($manifestURL, CRAWL_CURLOPT_USERAGENT);

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
        if (!$remoteManifest = $curl->getContent()) {

          continue;
        }

        // Skip processing on json encoding error
        if (!$remoteManifest = @json_decode($remoteManifest)) {

          continue;
        }

        // Skip processing on required fields missed
        if (empty($remoteManifest->status) ||
            empty($remoteManifest->result->config->DEFAULT_HOST_URL_REGEXP) ||
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

        // Skip processing on remote host URL does not match local condition
        if ($remoteManifest->result->config->DEFAULT_HOST_URL_REGEXP !=
            Helper::getHostSetting($db, $memcached, $queueHost->hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP)) {

          continue;
        }

        // Skip processing on remote host link does not match local condition
        if (false === preg_match(Helper::getHostSetting($db, $memcached, $queueHost->hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP),
                                 $remoteManifest->result->api->hosts)) {

          continue;
        }

        // Grab host URLs
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
        if (!$remoteManifest = $curl->getContent()) {

          continue;
        }

        // Skip processing on json encoding error
        if (!$remoteManifestHosts = @json_decode($remoteManifest)) {

          continue;
        }

        // Skip processing on required fields missed
        if (empty($remoteManifestHosts->result)) {

          continue;
        }

        // Begin hosts processing
        foreach ($remoteManifestHosts->result as $remoteManifestHost) {

          // Skip processing on required fields missed
          if (empty($remoteManifestHost->url)) {

            continue;
          }

          // Register new link
          if ($linkToDBresult = Helper::addLinkToDB($db, $memcached, $remoteManifestHost->url)) {

            $hostsAdded     += count($linkToDBresult->new->hostId);
            $hostPagesAdded += count($linkToDBresult->new->hostPageId);
          }
        }
      }
    }

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
foreach ($db->getHostPageCrawlQueue(CRAWL_HOST_PAGE_QUEUE_LIMIT, time() - CRAWL_HOST_PAGE_QUEUE_SECONDS_OFFSET) as $queueHostPage) {

  $db->beginTransaction();

  try {

    // Init page request
    $curl = new Curl($queueHostPage->hostPageURL, CRAWL_CURLOPT_USERAGENT);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

    // Update page rank
    if (CRAWL_HOST_PAGE_RANK_UPDATE) {

      $hostPageRank = 0;

      // Get referrers
      foreach ($db->getHostPagesToHostPageByHostPageIdTarget($queueHostPage->hostPageId) as $hostPageToHostPageByHostPageIdTarget) {

        // Get source page details
        if ($hostPageSource = $db->getHostPage($hostPageToHostPageByHostPageIdTarget->hostPageIdSource)) {

          // Increase PR on external referrer only
          if ($hostPageSource->hostId != $queueHostPage->hostId) {

            $hostPageRank++;
          }

          // Delegate page rank value from redirected pages
          if (false !== strpos($hostPageSource->httpCode, '30')) {

            $hostPageRank += $hostPageSource->rank;
          }
        }
      }

      // Update registry
      $db->updateHostPageRank($queueHostPage->hostPageId, $hostPageRank);
    }

    // Update page index anyway, with the current time and http code
    $hostPagesProcessed += $db->updateHostPageCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode(), $curl->getSizeDownload());

    // This page not available
    if (200 != $curl->getCode()) {

      // Ban this page
      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      // Try to receive target page location on page redirect available by following location
      $curl = new Curl($queueHostPage->hostPageURL, CRAWL_CURLOPT_USERAGENT, 10, true, true);

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
          if (!parse_url($url, PHP_URL_HOST)) {

            $url = $queueHostPage->hostURL . '/' . trim(ltrim(str_replace(['./', '../'], '', $url), '/'), '.');
          }

          // Register new link
          if ($linkToDBresult = Helper::addLinkToDB($db, $memcached, $url)) {

            $hostsAdded     += count($linkToDBresult->new->hostId);
            $hostPagesAdded += count($linkToDBresult->new->hostPageId);

            // Register referrer
            if ($linkToDBresult->old->hostPageId) {

              foreach ($linkToDBresult->old->hostPageId as $hostPageIdTarget) {

                $db->setHostPageToHostPage($queueHostPage->hostPageId, $hostPageIdTarget);
              }
            }

            if ($linkToDBresult->new->hostPageId) {

              foreach ($linkToDBresult->new->hostPageId as $hostPageIdTarget) {

                $db->setHostPageToHostPage($queueHostPage->hostPageId, $hostPageIdTarget);
              }
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

    // Check for MIME
    $hostPageInMime = false;

    foreach ((array) explode(',', Helper::getHostSetting($db, $memcached, $queueHostPage->hostId, 'PAGES_MIME', DEFAULT_HOST_PAGES_MIME)) as $mime) {

      // Ban page on MIME type not allowed in settings
      if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

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

    // Begin snaps
    if (SNAP_STORAGE) {

      // Register snap in DB
      if ($hostPageSnapId = $db->addHostPageSnap($queueHostPage->hostPageId, time())) {

        // Default storage success
        $snapFilesExists = false;

        // Prepare filenames
        $hostPageSnapPath = 'hps/' . substr(trim(chunk_split($hostPageSnapId, 1, '/'), '/'), 0, -1);
        $hostPageSnapFile = $hostPageSnapPath . substr($hostPageSnapId, -1) . '.zip';

        $hostPageSnapFilenameTmp  = __DIR__ . '/../storage/tmp/' . md5($hostPageSnapFile);

        // Create ZIP container
        $zip = new ZipArchive();

        if (true === $zip->open($hostPageSnapFilenameTmp, ZipArchive::CREATE)) {

          // Insert compressed snap data into the tmp storage
          if (true === $zip->addFromString('DATA', $content) &&
              true === $zip->addFromString('META', sprintf('MIME: %s',      Filter::mime($contentType)) . PHP_EOL .
                                                   sprintf('SOURCE: %s',    Filter::url($queueHostPage->hostPageURL)) . PHP_EOL .
                                                   sprintf('TIMESTAMP: %s', time()))) {
          }
        }

        $zip->close();

        // Temporarily snap file exists
        if (file_exists($hostPageSnapFilenameTmp)) {

          // Copy files to each storage
          foreach (json_decode(SNAP_STORAGE) as $node => $storages) {

            foreach ($storages as $location => $storage) {

              // Generate storage id
              $crc32name = crc32(sprintf('%s.%s', $node, $location));

              switch ($node) {

                case 'localhost':

                  // Validate mime
                  if (!$storage->quota->mime) continue 2;

                  $snapMimeValid = false;
                  foreach ((array) explode(',', $storage->quota->mime) as $mime) {

                    if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

                      $snapMimeValid = true;
                      break;
                    }
                  }

                  if (!$snapMimeValid) continue 2;

                  // Copy tmp snap file to the permanent storage
                  @mkdir($storage->directory . $hostPageSnapPath, 0755, true);

                  if (copy($hostPageSnapFilenameTmp, $storage->directory . $hostPageSnapFile)) {

                    // Register storage name
                    if ($db->addHostPageSnapStorage($hostPageSnapId, $crc32name, time())) {

                      $snapFilesExists = true;
                    }
                  }

                break;
                case 'ftp':

                  // Validate mime
                  if (!$storage->quota->mime) continue 2;

                  $snapMimeValid = false;
                  foreach ((array) explode(',', $storage->quota->mime) as $mime) {

                    if (false !== stripos(Filter::mime($contentType), Filter::mime($mime))) {

                      $snapMimeValid = true;
                      break;
                    }
                  }

                  if (!$snapMimeValid) continue 2;

                  $attempt = 1;

                  do {

                    // Copy tmp snap file to the permanent storage
                    $ftp = new Ftp();

                    // Remote host connected well...
                    if ($connection = $ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {

                      $ftp->mkdir($hostPageSnapPath, true);

                      if ($ftp->copy($hostPageSnapFilenameTmp, $hostPageSnapFile)) {

                        // Register storage name
                        if ($db->addHostPageSnapStorage($hostPageSnapId, $crc32name, time())) {

                          $snapFilesExists = true;
                        }
                      }

                      $ftp->close();

                    // On remote connection lost, repeat attempt after 60 seconds...
                    } else {

                      echo sprintf(_('[attempt: %s] wait for remote storage %s id %s connection...'), $attempt++, $node, $location) . PHP_EOL;

                      sleep(60);
                    }

                  } while ($connection === false);

                break;
              }
            }
          }
        }

        // At least one file have been stored
        if ($snapFilesExists) {

          $hostPagesSnapAdded++;

        } else {

          $db->deleteHostPageSnap($hostPageSnapId);
        }

        // Delete tmp snap
        unlink($hostPageSnapFilenameTmp);
      }
    }

    // Is HTML document
    if (false !== stripos(Filter::mime($contentType), 'text/html')) {

      // Define variables
      $metaDescription     = null;
      $metaKeywords        = null;
      $metaYggoManifestURL = null;

      // Parse page content
      $dom = new DomDocument();

      if ($encoding = mb_detect_encoding($content)) {

        @$dom->loadHTML(sprintf('<?xml encoding="%s" ?>', $encoding) . $content);

      } else {

        $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

        $db->commit();

        continue;
      }

      // Skip index page links without title tag
      $title = @$dom->getElementsByTagName('title');

      if ($title->length == 0) {

        $metaTitle = null;

        /*
        $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

        $db->commit();

        continue;
        */

      } else {

        $metaTitle = $title->item(0)->nodeValue;
      }

      // Get optional page meta data
      foreach (@$dom->getElementsByTagName('meta') as $meta) {

        if (@$meta->getAttribute('name') == 'description') {
          $metaDescription = @$meta->getAttribute('content');
        }

        if (@$meta->getAttribute('name') == 'keywords') {
          $metaKeywords = @$meta->getAttribute('content');
        }

        if (@$meta->getAttribute('name') == 'robots') {

          $metaRobots = @$meta->getAttribute('content');

          // Ban page with meta robots:noindex attribute
          if (false !== stripos($metaRobots, 'noindex')) {

            $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

            continue;
          }
        }

        // Grab meta yggo:manifest link when available
        if (@$meta->getAttribute('name') == 'yggo:manifest') {
          $metaYggoManifestURL = Filter::url(@$meta->getAttribute('content'));
        }
      }

      // Add queued page description if not exists
      $db->addHostPageDescription($queueHostPage->hostPageId,
                                  $metaTitle,
                                  $metaDescription ?  Filter::pageDescription($metaDescription) : null,
                                  $metaKeywords    ?  Filter::pageKeywords($metaKeywords) : null,
                                  $content         ? (Helper::getHostSetting($db, $memcached, $queueHostPage->hostId, 'PAGES_DATA', DEFAULT_HOST_PAGES_DATA) ? base64_encode($content) : null) : null,
                                  time());

      // Collect page DOM elements data on enabled
      if ($hostPageDomSelectors = Helper::getHostSetting($db, $memcached, $queueHostPage->hostId, 'PAGES_DOM_SELECTORS', DEFAULT_HOST_PAGES_DOM_SELECTORS)) {

        // Begin selectors extraction
        $html = str_get_html($content);

        foreach ((array) explode(';', $hostPageDomSelectors) as $selector) {

          foreach($html->find($selector) as $element) {

            if (!empty($element->innertext)) {

              $db->addHostPageDom($queueHostPage->hostPageId,
                                  time(),
                                  $selector,
                                  trim(Helper::getHostSetting($db, $memcached, $queueHostPage->hostId, 'PAGE_DOM_STRIP_TAGS', DEFAULT_HOST_PAGE_DOM_STRIP_TAGS) ? strip_tags( preg_replace('/[\s]+/',
                                                                                                                                                                              ' ',
                                                                                                                                                                              str_replace(['<br />', '<br/>', '<br>', '</'],
                                                                                                                                                                                          [' ', ' ', ' ', ' </'],
                                                                                                                                                                                          $element->innertext))) : $element->innertext));
            }
          }
        }
      }

      // Skip page links following with meta robots:nofollow attribute
      foreach (@$dom->getElementsByTagName('meta') as $meta) {

        if (@$meta->getAttribute('name') == 'robots') {

          if (false !== stripos($metaRobots, 'nofollow')) {

            $db->commit();

            continue 2;
          }
        }
      }

      // Update manifest registry
      if (CRAWL_MANIFEST &&
          !empty($metaYggoManifestURL) &&
          filter_var($metaYggoManifestURL, FILTER_VALIDATE_URL) &&
          preg_match(DEFAULT_HOST_URL_REGEXP, $metaYggoManifestURL)) {

          $manifestsProcessed += $db->setHostSetting($queueHostPage->hostId, 'MANIFEST_URL', $metaYggoManifestURL);
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
          'href'        => $src,
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
          'href'        => $src,
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
          'href'        => $src,
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
          'href'        => $src,
        ];
      }

      foreach (@$dom->getElementsByTagName('script') as $script) {

        // Skip nodes without href attribute
        if (!$src = @$script->getAttribute('src')) {

          continue;
        }

        // Add link to queue
        $links[] = [
          'title'       => null,
          'description' => null,
          'keywords'    => null,
          'data'        => null,
          'mime'        => null,
          'href'        => $src,
        ];
      }

      foreach (@$dom->getElementsByTagName('link') as $link) {

        // Skip nodes without href attribute
        if (!$href = @$link->getAttribute('href')) {

          continue;
        }

        // Add link to queue
        $links[] = [
          'title'       => null,
          'description' => null,
          'keywords'    => null,
          'data'        => null,
          'mime'        => null,
          'href'        => $href,
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

        // Skip xmpp links
        if (false !== stripos($href, 'xmpp:')) {

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
          'href'        => $href,
        ];
      }

      // Process links collected
      foreach ($links as $link) {

        // Make relative links absolute
        if (!parse_url($link['href'], PHP_URL_HOST)) {

          $link['href'] = $queueHostPage->hostURL . '/' . trim(ltrim(str_replace(['./', '../'], '', $link['href']), '/'), '.');
        }

        // Register new link
        if ($linkToDBresult = Helper::addLinkToDB($db, $memcached, $link['href'])) {

          // Increase new hosts counters
          if ($linkToDBresult->new->hostId) {

            $hostsAdded += count($linkToDBresult->new->hostId);
          }

          if ($linkToDBresult->new->hostPageId) {

            $hostPagesAdded += count($linkToDBresult->new->hostPageId);
          }

          // Register referrer
          if ($linkToDBresult->old->hostPageId) {

            foreach ($linkToDBresult->old->hostPageId as $hostPageIdTarget) {

              $db->setHostPageToHostPage($queueHostPage->hostPageId, $hostPageIdTarget);
            }
          }

          if ($linkToDBresult->new->hostPageId) {

            foreach ($linkToDBresult->new->hostPageId as $hostPageIdTarget) {

              $db->setHostPageToHostPage($queueHostPage->hostPageId, $hostPageIdTarget);
            }
          }
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
        !empty($e->errorInfo[1]) && in_array($e->errorInfo[1], [1366])) { // @TODO change DB

      $hostPagesBanned += $db->updateHostPageTimeBanned($queueHostPage->hostPageId, time());

      $hostPagesProcessed++;

      // Apply changes
      $db->commit();

    } else {

      // Skip item
      $db->rollBack();

    }

    continue;
  }
}

// Debug
$executionTimeTotal    = microtime(true) - $timeStart;
$httpRequestsTimeTotal = $httpRequestsTimeTotal / 1000000;

// Debug output
echo 'Crawl queue completed:' . PHP_EOL;

echo '[hosts]' . PHP_EOL;
echo '  processed: ' . $hostsProcessed . PHP_EOL;
echo '  added: ' . $hostsAdded . PHP_EOL . PHP_EOL;

echo '[hosts pages]' . PHP_EOL;
echo '  processed: ' . $hostPagesProcessed . PHP_EOL;
echo '  added: ' . $hostPagesAdded . PHP_EOL;
echo '  banned: ' . $hostPagesBanned . PHP_EOL . PHP_EOL;

echo '[host page snaps]' . PHP_EOL;
echo '  added: ' . $hostPagesSnapAdded . PHP_EOL . PHP_EOL;

echo '[sitemaps]' . PHP_EOL;
echo '  processed: ' . $sitemapsProcessed . PHP_EOL . PHP_EOL;

echo '[manifests]' . PHP_EOL;
echo '  processed: ' . $manifestsProcessed . PHP_EOL . PHP_EOL;

echo '[HTTP]' . PHP_EOL;
echo '  requests total:' . $httpRequestsTotal . PHP_EOL;
echo '  requests size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo '  download size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo '  requests time: ' . $httpRequestsTimeTotal . PHP_EOL . PHP_EOL;

echo '[MySQL]' . PHP_EOL;
echo 'queries:' . PHP_EOL;
echo '  select: ' . $db->getDebug()->query->select->total . PHP_EOL;
echo '  insert: ' . $db->getDebug()->query->insert->total . PHP_EOL;
echo '  update: ' . $db->getDebug()->query->update->total . PHP_EOL;
echo '  delete: ' . $db->getDebug()->query->delete->total . PHP_EOL . PHP_EOL;

echo '-- completed in ' . $executionTimeTotal . ' seconds --' . PHP_EOL . PHP_EOL;