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

$hostsTotal         = $db->getTotalHosts();
$hostsUpdated       = 0;
$hostsPagesDeleted  = 0;
$hostsImagesDeleted = 0;

// Get host queue
foreach ($db->getCleanerQueue(CLEAN_HOST_LIMIT, time() - CLEAN_HOST_SECONDS_OFFSET) as $host) {

  // Parse host info
  $hostURL = $host->scheme . '://' . $host->name . ($host->port ? ':' . $host->port : false);

  // Get robots.txt if exists
  $curl = new Curl($hostURL . '/robots.txt');

  if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
    $hostRobots = $curl->getContent();
  } else {
    $hostRobots = null;
  }

  // Begin update
  $db->beginTransaction();

  try {

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
        $hostsImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
      }
    }

    // Apply host pages limits
    $totalHostPages = $db->getTotalHostPages($host->hostId);

    if ($totalHostPages > $host->crawlPageLimit) {

      foreach ((array) $db->getHostPagesByLimit($host->hostId, $totalHostPages - $host->crawlPageLimit) as $hostPage) {

        // Delete foreign key relations
        $db->deleteHostPageToHostImage($hostPage->hostPageId);

        // Delete host page
        $hostsPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
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
        $hostsImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
      }
    }

    foreach ($db->getHostPages($host->hostId) as $hostPage) {

      if (!$robots->uriAllowed($hostPage->uri)) {

        // Delete foreign key relations
        $db->deleteHostPageToHostImage($hostPage->hostPageId);

        // Delete host page
        $hostsPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
      }
    }

    // Clean up host images unrelated to host pages
    foreach ($db->getUnrelatedHostImages() as $hostImage) {

      // Delete foreign key relations
      $db->deleteHostImageDescription($hostImage->hostImageId);
      $db->deleteHostImageToHostPage($hostImage->hostImageId);

      // Delete host image
      $hostsImagesDeleted += $db->deleteHostImage($hostImage->hostImageId);
    }

    $db->commit();

  } catch(Exception $e){

    var_dump($e);

    $db->rollBack();
  }
}

// Debug
echo 'Hosts total: ' . $hostsTotal . PHP_EOL;
echo 'Hosts updated: ' . $hostsUpdated . PHP_EOL;
echo 'Hosts pages deleted: ' . $hostsPagesDeleted . PHP_EOL;
echo 'Hosts images deleted: ' . $hostsImagesDeleted . PHP_EOL;
echo 'Execution time: ' . microtime(true) - $timeStart . PHP_EOL;