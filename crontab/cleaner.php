<?php

// Stop cleaner on cli running
$semaphore = sem_get(crc32('cli.yggo'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'cli.yggo process running in another thread.' . PHP_EOL;
  exit;
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.cleaner'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'process locked by another thread.' . PHP_EOL;
  exit;
}

// Define variables
$timeStart = microtime(true);

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/mysql.php');

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Reset banned hosts
$hostsBansRemoved = $db->resetBannedHostPages(time() - CLEAN_HOST_PAGE_BAN_SECONDS_OFFSET);

// Reset banned pages
$hostPagesBansRemoved = $db->resetBannedHosts(time() - CLEAN_HOST_BAN_SECONDS_OFFSET);

// Optimize tables
if (CLEAN_DB_TABLES_OPTIMIZATION) {

  try {

    $db->optimize();

  } catch (Exception $e) {

    var_dump($e);
  }
}

// Debug
echo 'Host bans removed: ' . $hostsBansRemoved . PHP_EOL;
echo 'Host page bans removed: ' . $hostPagesBansRemoved . PHP_EOL;

echo 'Total time: ' . microtime(true) - $timeStart . PHP_EOL . PHP_EOL;