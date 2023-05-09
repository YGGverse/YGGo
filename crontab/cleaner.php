<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.cleaner'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'Process locked by another thread.' . PHP_EOL;
  exit;
}

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/mysql.php');

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Debug
$timeStart = microtime(true);

$httpRequestsTotal            = 0;
$httpRequestsSizeTotal        = 0;
$httpDownloadSizeTotal        = 0;
$httpRequestsTimeTotal        = 0;

$hostsTotal                   = $db->getTotalHosts();
$manifestsTotal               = $db->getTotalManifests();
$hostsUpdated                 = 0;
$hostPagesDeleted             = 0;
$hostPageDescriptionsDeleted  = 0;
$hostImagesDeleted            = 0;
$hostImageDescriptionsDeleted = 0;
$manifestsDeleted             = 0;
$hostPagesBansRemoved         = 0;
$hostImagesBansRemoved        = 0;

$logsCleanerDeleted           = 0;
$logsCrawlerDeleted           = 0;

// Begin update
$db->beginTransaction();

try {

  // Get cleaner queue
  foreach ($db->getCleanerQueue(CLEAN_HOST_LIMIT, time() - CLEAN_HOST_SECONDS_OFFSET) as $host) {

    // Parse host info
    $hostURL = $host->scheme . '://' . $host->name . ($host->port ? ':' . $host->port : false);

    // Get robots.txt if exists
    $curl = new Curl($hostURL . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal  += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

    if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
      $hostRobots = $curl->getContent();
    } else {
      $hostRobots = null;
    }

    // Update host data
    $hostsUpdated += $db->updateHostRobots($host->hostId, $hostRobots, time());

    // Apply host images limits
    $totalHostImages = $db->getTotalHostImages($host->hostId);

    if ($totalHostImages > $host->crawlImageLimit) {

      foreach ((array) $db->getHostImagesByLimit($host->hostId, $totalHostImages - $host->crawlImageLimit) as $hostImage) {

        // Delete foreign key relations
        $db->deleteHostImageDescription($hostImage->hostImageId);
        $db->deleteHostImageToHostPage($hostImage->hostImageId);

        // Delete host image
        $hostImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
      }
    }

    // Apply host pages limits
    $totalHostPages = $db->getTotalHostPages($host->hostId);

    if ($totalHostPages > $host->crawlPageLimit) {

      foreach ((array) $db->getHostPagesByLimit($host->hostId, $totalHostPages - $host->crawlPageLimit) as $hostPage) {

        // Delete foreign key relations
        $db->deleteHostPageToHostImage($hostPage->hostPageId);

        // Delete host page
        $db->deleteHostPageDescriptions($hostPage->hostPageId);

        $hostPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
      }
    }

    // Apply new robots.txt rules
    $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($host->robotsPostfix ? (string) $host->robotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

    foreach ($db->getHostImages($host->hostId) as $hostImage) {

      if (!$robots->uriAllowed($hostImage->uri)) {

        // Delete foreign key relations
        $db->deleteHostImageDescription($hostImage->hostImageId);
        $db->deleteHostImageToHostPage($hostImage->hostImageId);

        // Delete host image
        $hostImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
      }
    }

    foreach ($db->getHostPages($host->hostId) as $hostPage) {

      if (!$robots->uriAllowed($hostPage->uri)) {

        // Delete foreign key relations
        $db->deleteHostPageToHostImage($hostPage->hostPageId);

        // Delete host page
        $db->deleteHostPageDescriptions($hostPage->hostPageId);

        $hostPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
      }
    }

    // Clean up host images unrelated to host pages
    foreach ($db->getUnrelatedHostImages() as $hostImage) {

      // Delete foreign key relations
      $db->deleteHostImageDescription($hostImage->hostImageId);
      $db->deleteHostImageToHostPage($hostImage->hostImageId);

      // Delete host image
      $hostImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
    }
  }

  // Clean up deprecated manifests
  foreach ($db->getManifests() as $manifest) {

    $delete = false;

    $curl = new Curl($manifest->url);

    // Update curl stats
    $httpRequestsTotal++;
    $httpRequestsSizeTotal  += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

    // Skip processing non 200 code
    if (200 != $curl->getCode()) {

      continue; // Wait for reconnect
    }

    // Skip processing without returned data
    if (!$remoteManifest = $curl->getContent()) {

      $delete = true;
    }

    // Skip processing on json encoding error
    if (!$remoteManifest = @json_decode($remoteManifest)) {

      $delete = true;
    }

    // Skip processing on required fields missed
    if (empty($remoteManifest->status) ||
        empty($remoteManifest->result->config->crawlUrlRegexp) ||
        empty($remoteManifest->result->api->version)) {

      $delete = true;
    }

    // Skip processing on API version not compatible
    if ($remoteManifest->result->api->version !== CRAWL_MANIFEST_API_VERSION) {

      $delete = true;
    }

    // Skip processing on crawlUrlRegexp does not match CRAWL_URL_REGEXP condition
    if ($remoteManifest->result->config->crawlUrlRegexp !== CRAWL_URL_REGEXP) {

      $delete = true;
    }

    if ($delete) {

      $manifestsDeleted += $db->deleteManifest($manifest->manifestId);
    }
  }

  // Reset banned pages
  $hostPagesBansRemoved += $db->resetBannedHostPages(time() - CLEAN_PAGE_BAN_SECONDS_OFFSET);

  // Delete page description history
  $hostPageDescriptionsDeleted += $db->deleteHostPageDescriptionsByTimeAdded(time() - CLEAN_PAGE_DESCRIPTION_OFFSET);

  // Reset banned images
  $hostImagesBansRemoved += $db->resetBannedHostImages(time() - CLEAN_IMAGE_BAN_SECONDS_OFFSET);

  // Delete image description history
  $hostImageDescriptionsDeleted += $db->deleteHostImageDescriptionsByTimeAdded(time() - CLEAN_IMAGE_DESCRIPTION_OFFSET);

  // Delete deprecated logs
  $logsCleanerDeleted += $db->deleteLogCleaner(time() - CLEAN_LOG_SECONDS_OFFSET);
  $logsCrawlerDeleted += $db->deleteLogCrawler(time() - CRAWL_LOG_SECONDS_OFFSET);

  $db->commit();

} catch(Exception $e){

  var_dump($e);

  $db->rollBack();
}

// Debug
$executionTimeTotal    = microtime(true) - $timeStart;
$httpRequestsTimeTotal = $httpRequestsTimeTotal / 1000000;

if (CLEAN_LOG_ENABLED) {

  $db->addCleanerLog( time(),
                      $hostsTotal,
                      $hostsUpdated,
                      $hostPagesDeleted,
                      $hostPageDescriptionsDeleted,
                      $hostPagesBansRemoved,
                      $hostImagesDeleted,
                      $hostImageDescriptionsDeleted,
                      $hostImagesBansRemoved,
                      $manifestsTotal,
                      $manifestsDeleted,
                      $logsCleanerDeleted,
                      $logsCrawlerDeleted,
                      $httpRequestsTotal,
                      $httpRequestsSizeTotal,
                      $httpDownloadSizeTotal,
                      $httpRequestsTimeTotal,
                      $executionTimeTotal);

}

echo 'Hosts total: ' . $hostsTotal . PHP_EOL;
echo 'Hosts updated: ' . $hostsUpdated . PHP_EOL;
echo 'Hosts pages deleted: ' . $hostPagesDeleted . PHP_EOL;
echo 'Hosts images deleted: ' . $hostImagesDeleted . PHP_EOL;

echo 'Manifests total: ' . $manifestsTotal . PHP_EOL;
echo 'Manifests deleted: ' . $manifestsDeleted . PHP_EOL;

echo 'Host page bans removed: ' . $hostPagesBansRemoved . PHP_EOL;
echo 'Host page descriptions deleted: ' . $hostPageDescriptionsDeleted . PHP_EOL;
echo 'Host images bans removed: ' . $hostImagesBansRemoved . PHP_EOL;
echo 'Host image descriptions deleted: ' . $hostImageDescriptionsDeleted . PHP_EOL;

echo 'Cleaner logs deleted: ' . $logsCleanerDeleted . PHP_EOL;
echo 'Crawler logs deleted: ' . $logsCrawlerDeleted . PHP_EOL;

echo 'HTTP Requests total: ' . $httpRequestsTotal . PHP_EOL;
echo 'HTTP Requests total size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo 'HTTP Download total size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo 'HTTP Requests total time: ' . $httpRequestsTimeTotal . PHP_EOL;

echo 'Total time: ' . $executionTimeTotal . PHP_EOL . PHP_EOL;