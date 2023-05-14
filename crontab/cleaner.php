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
$hostPagesDescriptionsDeleted = 0;
$hostPagesSnapDeleted         = 0;
$hostPagesToHostPageDeleted   = 0;
$manifestsDeleted             = 0;
$hostPagesBansRemoved         = 0;

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
    $httpRequestsSizeTotal += $curl->getSizeRequest();
    $httpDownloadSizeTotal += $curl->getSizeDownload();
    $httpRequestsTimeTotal += $curl->getTotalTime();

    if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
      $hostRobots = $curl->getContent();
    } else {
      $hostRobots = null;
    }

    // Update host data
    $hostsUpdated += $db->updateHostRobots($host->hostId, $hostRobots, time());

    // Apply host pages limits
    $totalHostPages = $db->getTotalHostPages($host->hostId);

    if ($totalHostPages > $host->crawlPageLimit) {

      foreach ((array) $db->getHostPagesByLimit($host->hostId, $totalHostPages - $host->crawlPageLimit) as $hostPage) {

        if ($hostPage->uri != '/') {

          // Delete host page descriptions
          $hostPagesDescriptionsDeleted += $db->deleteHostPageDescriptions($hostPage->hostPageId);

          // Delete host page refs data
          $hostPagesToHostPageDeleted += $db->deleteHostPageToHostPage($hostPage->hostPageId);

          // Delete host page snaps
          $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

          foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

            $snapFileLocalExists = (bool) $hostPageSnap->storageLocal;
            $snapFileMegaExists  = (bool) $hostPageSnap->storageMega;

            if ($snapFileLocalExists) {

              if (unlink('../public/snap/hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip')) {

                $snapFileLocalExists = false;
              }
            }

            if ($snapFileMegaExists) {

              $ftp = new Ftp();

              if ($ftp->connect(MEGA_FTP_HOST, MEGA_FTP_PORT, null, null, MEGA_FTP_DIRECTORY)) {

                if ($ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip')) {

                  $snapFileMegaExists = false;
                }
              }
            }

            if (!$snapFileLocalExists && !$snapFileMegaExists) {
              $hostPagesSnapDeleted += $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);
            }
          }

          // Delete host page
          $hostPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
        }
      }
    }

    // Apply new robots.txt rules
    $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($host->robotsPostfix ? (string) $host->robotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

    foreach ($db->getHostPages($host->hostId) as $hostPage) {

      if ($hostPage->uri != '/' && !$robots->uriAllowed($hostPage->uri)) {

        // Delete host page descriptions
        $hostPagesDescriptionsDeleted += $db->deleteHostPageDescriptions($hostPage->hostPageId);

        // Delete host page refs data
        $hostPagesToHostPageDeleted += $db->deleteHostPageToHostPage($hostPage->hostPageId);

        // Delete host page snaps
        $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

        foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

          $snapFileLocalExists = (bool) $hostPageSnap->storageLocal;
          $snapFileMegaExists  = (bool) $hostPageSnap->storageMega;

          if ($snapFileLocalExists) {

            if (unlink('../public/snap/hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip')) {

              $snapFileLocalExists = false;
            }
          }

          if ($snapFileMegaExists) {

            $ftp = new Ftp();

            if ($ftp->connect(MEGA_FTP_HOST, MEGA_FTP_PORT, null, null, MEGA_FTP_DIRECTORY)) {

              if ($ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip')) {

                $snapFileMegaExists = false;
              }
            }
          }

          if (!$snapFileLocalExists && !$snapFileMegaExists) {
            $hostPagesSnapDeleted += $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);
          }
        }

        // Delete host page
        $hostPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
      }
    }
  }

  // Clean up deprecated manifests
  foreach ($db->getManifests() as $manifest) {

    $delete = false;

    $curl = new Curl($manifest->url, CRAWL_CURLOPT_USERAGENT);

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
  $hostPagesDescriptionsDeleted += $db->deleteHostPageDescriptionsByTimeAdded(time() - CLEAN_PAGE_DESCRIPTION_OFFSET);

  // Delete deprecated logs
  $logsCleanerDeleted += $db->deleteLogCleaner(time() - CLEAN_LOG_SECONDS_OFFSET);
  $logsCrawlerDeleted += $db->deleteLogCrawler(time() - CRAWL_LOG_SECONDS_OFFSET);

  // Commit results
  $db->commit();

  // Optimize tables
  $db->optimize();

} catch(Exception $e) {

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
                      $hostPagesDescriptionsDeleted,
                      $hostPagesSnapDeleted,
                      $hostPagesToHostPageDeleted,
                      $hostPagesBansRemoved,
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

echo 'Manifests total: ' . $manifestsTotal . PHP_EOL;
echo 'Manifests deleted: ' . $manifestsDeleted . PHP_EOL;

echo 'Host page bans removed: ' . $hostPagesBansRemoved . PHP_EOL;
echo 'Host page descriptions deleted: ' . $hostPagesDescriptionsDeleted . PHP_EOL;
echo 'Host page snaps deleted: ' . $hostPagesSnapDeleted . PHP_EOL;
echo 'Host page to host page deleted: ' . $hostPagesToHostPageDeleted . PHP_EOL;

echo 'Cleaner logs deleted: ' . $logsCleanerDeleted . PHP_EOL;
echo 'Crawler logs deleted: ' . $logsCrawlerDeleted . PHP_EOL;

echo 'HTTP Requests total: ' . $httpRequestsTotal . PHP_EOL;
echo 'HTTP Requests total size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo 'HTTP Download total size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo 'HTTP Requests total time: ' . $httpRequestsTimeTotal . PHP_EOL;

echo 'Total time: ' . $executionTimeTotal . PHP_EOL . PHP_EOL;