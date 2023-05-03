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

$hostsTotal        = $db->getTotalHosts();
$hostsUpdated      = 0;
$hostsPagesDeleted = 0;

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

    // Apply host pages limits
    $totalHostPages = $db->getTotalHostPages($host->hostId);

    if ($totalHostPages > $host->crawlPageLimit) {

      $hostsPagesDeleted += $db->deleteHostPages($host->hostId, $totalHostPages - $host->crawlPageLimit);
    }

    // Apply new robots.txt rules
    $robots = new Robots(($hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . ($host->robotsPostfix ? (string) $host->robotsPostfix : (string) CRAWL_ROBOTS_POSTFIX_RULES));

    foreach ($db->getHostPages($host->hostId) as $hostPage) {

      if (!$robots->uriAllowed($hostPage->uri)) {

        $hostsPagesDeleted += $db->deleteHostPage($hostPage->hostPageId);
      }
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
echo 'Execution time: ' . microtime(true) - $timeStart . PHP_EOL;