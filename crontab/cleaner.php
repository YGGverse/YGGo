<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.cleaner'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'Process locked by another thread.' . PHP_EOL;
  exit;
}

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/curl.php');
require_once(__DIR__ . '/../library/robots.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/ftp.php');

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
$hostPagesDomsDeleted         = 0;
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

    // Get robots.txt if exists
    $curl = new Curl($host->hostURL . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

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

          // Delete host page DOMs
          $hostPagesDomsDeleted += $db->deleteHostPageDoms($hostPage->hostPageId);

          // Delete host page refs data
          $hostPagesToHostPageDeleted += $db->deleteHostPageToHostPage($hostPage->hostPageId);

          // Delete host page snaps
          $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

          foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

            // Delete snap files
            foreach (json_decode(SNAP_STORAGE) as $name => $storages) {

              foreach ($storages as $i => $storage) {

                // Generate storage id
                $crc32name = crc32(sprintf('%s.%s', $name, $i));

                switch ($name) {

                  case 'localhost':

                    @unlink($storage->directory . $snapFilePath . $hostPageSnap->timeAdded . '.zip');

                  break;
                  case 'ftp':

                    $ftp = new Ftp();

                    if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {
                        $ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip');
                    }

                  break;
                }

                // Clean up DB registry
                foreach ($db->getHostPageSnapStorages($hostPageSnap->hostPageSnapId) as $hostPageSnapStorage) {

                  $db->deleteHostPageSnapDownloads($hostPageSnapStorage->hostPageSnapStorageId);
                }

                $db->deleteHostPageSnapStorages($hostPageSnap->hostPageSnapId);

                $hostPagesSnapDeleted += $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);
              }
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

        // Delete host page DOMs
        $hostPagesDomsDeleted += $db->deleteHostPageDoms($hostPage->hostPageId);

        // Delete host page refs data
        $hostPagesToHostPageDeleted += $db->deleteHostPageToHostPage($hostPage->hostPageId);

        // Delete host page snaps
        $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

        foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

          // Delete snap files
          foreach (json_decode(SNAP_STORAGE) as $name => $storages) {

            foreach ($storages as $i => $storage) {

              // Generate storage id
              $crc32name = crc32(sprintf('%s.%s', $name, $i));

              switch ($name) {

                case 'localhost':

                  @unlink($storage->directory . $snapFilePath . $hostPageSnap->timeAdded . '.zip');

                break;
                case 'ftp':

                  $ftp = new Ftp();

                  if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {
                      $ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip');
                  }

                break;
              }

              // Clean up DB registry
              foreach ($db->getHostPageSnapStorages($hostPageSnap->hostPageSnapId) as $hostPageSnapStorage) {

                $db->deleteHostPageSnapDownloads($hostPageSnapStorage->hostPageSnapStorageId);
              }

              $db->deleteHostPageSnapStorages($hostPageSnap->hostPageSnapId);

              $hostPagesSnapDeleted += $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);
            }
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

  // Clean up banned pages extra data
  foreach ($db->getHostPagesBanned() as $hostPage) {

    // Delete host page descriptions
    $hostPagesDescriptionsDeleted += $db->deleteHostPageDescriptions($hostPage->hostPageId);

    // Delete host page DOMs
    $hostPagesDomsDeleted += $db->deleteHostPageDoms($hostPage->hostPageId);

    // Delete host page refs data
    $hostPagesToHostPageDeleted += $db->deleteHostPageToHostPage($hostPage->hostPageId);

    // Delete host page snaps
    $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

    foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

      // Delete snap files
      foreach (json_decode(SNAP_STORAGE) as $name => $storages) {

        foreach ($storages as $i => $storage) {

          // Generate storage id
          $crc32name = crc32(sprintf('%s.%s', $name, $i));

          switch ($name) {

            case 'localhost':

              @unlink($storage->directory . $snapFilePath . $hostPageSnap->timeAdded . '.zip');

            break;
            case 'ftp':

              $ftp = new Ftp();

              if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {
                  $ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip');
              }

            break;
          }

          // Clean up DB registry
          foreach ($db->getHostPageSnapStorages($hostPageSnap->hostPageSnapId) as $hostPageSnapStorage) {

            $db->deleteHostPageSnapDownloads($hostPageSnapStorage->hostPageSnapStorageId);
          }

          $db->deleteHostPageSnapStorages($hostPageSnap->hostPageSnapId);

          $hostPagesSnapDeleted += $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);
        }
      }
    }
  }

  // Delete page description history
  $hostPagesDescriptionsDeleted += $db->deleteHostPageDescriptionsByTimeAdded(time() - CLEAN_PAGE_DESCRIPTION_OFFSET);

  // Delete page dom history
  $hostPagesDomsDeleted += $db->deleteHostPageDomsByTimeAdded(time() - CLEAN_PAGE_DOM_OFFSET);

  // Delete deprecated logs
  $logsCleanerDeleted += $db->deleteLogCleaner(time() - CLEAN_LOG_SECONDS_OFFSET);
  $logsCrawlerDeleted += $db->deleteLogCrawler(time() - CRAWL_LOG_SECONDS_OFFSET);

  // Delete failed snap files
  // @TODO

  // Commit results
  $db->commit();

} catch (Exception $e) {

  $db->rollBack();

  var_dump($e);
}

// Optimize tables
if (CLEAN_DB_TABLES_OPTIMIZATION) {

  try {

    $db->optimize();

  } catch (Exception $e) {

    var_dump($e);
  }
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
                      $hostPagesDomsDeleted,
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
echo 'Host page doms deleted: ' . $hostPagesDomsDeleted . PHP_EOL;
echo 'Host page snaps deleted: ' . $hostPagesSnapDeleted . PHP_EOL;
echo 'Host page to host page deleted: ' . $hostPagesToHostPageDeleted . PHP_EOL;

echo 'Cleaner logs deleted: ' . $logsCleanerDeleted . PHP_EOL;
echo 'Crawler logs deleted: ' . $logsCrawlerDeleted . PHP_EOL;

echo 'HTTP Requests total: ' . $httpRequestsTotal . PHP_EOL;
echo 'HTTP Requests total size: ' . $httpRequestsSizeTotal . PHP_EOL;
echo 'HTTP Download total size: ' . $httpDownloadSizeTotal . PHP_EOL;
echo 'HTTP Requests total time: ' . $httpRequestsTimeTotal . PHP_EOL;

echo 'Total time: ' . $executionTimeTotal . PHP_EOL . PHP_EOL;