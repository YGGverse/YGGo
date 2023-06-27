<?php

class MySQL {

  private PDO $_db;

  public function __construct(string $host, int $port, string $database, string $username, string $password) {

    $this->_db = new PDO('mysql:dbname=' . $database . ';host=' . $host . ';port=' . $port . ';charset=utf8', $username, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $this->_db->setAttribute(PDO::ATTR_TIMEOUT, 600);
  }

  // System
  public function beginTransaction() {

    $this->_db->beginTransaction();
  }

  public function commit() {

    $this->_db->commit();
  }

  public function rollBack() {

    $this->_db->rollBack();
  }

  // Manifest
  public function getTotalManifests() {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `manifest`');

    $query->execute();

    return $query->fetch()->total;
  }

  public function getManifests() {

    $query = $this->_db->prepare('SELECT * FROM `manifest`');

    $query->execute();

    return $query->fetchAll();
  }

  public function getManifest(int $crc32url) {

    $query = $this->_db->prepare('SELECT * FROM `manifest` WHERE `crc32url` = ? LIMIT 1');

    $query->execute([$crc32url]);

    return $query->fetch();
  }

  public function addManifest(int $crc32url, string $url, string $status, int $timeAdded, mixed $timeUpdated = null) {

    $query = $this->_db->prepare('INSERT INTO `manifest` (`crc32url`, `url`, `status`, `timeAdded`, `timeUpdated`) VALUES (?, ?, ?, ?, ?)');

    $query->execute([$crc32url, $url, $status, $timeAdded, $timeUpdated]);

    return $this->_db->lastInsertId();
  }

  public function deleteManifest(int $manifestId) {

    $query = $this->_db->prepare('DELETE FROM `manifest` WHERE `manifestId` = ? LIMIT 1');

    $query->execute([$manifestId]);

    return $query->rowCount();
  }

  // Host
  public function getAPIHosts(string $apiHostFields) {

    $query = $this->_db->prepare('SELECT ' . $apiHostFields . ' FROM `host`');

    $query->execute();

    return $query->fetchAll();
  }

  public function getHost(int $crc32url) {

    $query = $this->_db->prepare('SELECT * FROM `host` WHERE `crc32url` = ? LIMIT 1');

    $query->execute([$crc32url]);

    return $query->fetch();
  }

  public function getTotalHosts() {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `host`');

    $query->execute();

    return $query->fetch()->total;
  }

  public function addHost(string $scheme,
                          string $name,
                          mixed $port,
                          int $crc32url,
                          int $timeAdded,
                          mixed $timeUpdated,
                          int $crawlPageLimit,
                          string $crawlMetaOnly,
                          string $status,
                          string $nsfw,
                          mixed $robots,
                          mixed $robotsPostfix) {

    $query = $this->_db->prepare('INSERT INTO `host` (`scheme`,
                                                      `name`,
                                                      `port`,
                                                      `crc32url`,
                                                      `timeAdded`,
                                                      `timeUpdated`,
                                                      `crawlPageLimit`,
                                                      `crawlMetaOnly`,
                                                      `status`,
                                                      `nsfw`,
                                                      `robots`,
                                                      `robotsPostfix`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([ $scheme,
                      $name,
                      $port,
                      $crc32url,
                      $timeAdded,
                      $timeUpdated,
                      $crawlPageLimit,
                      $crawlMetaOnly,
                      $status,
                      $nsfw,
                      $robots,
                      $robotsPostfix]);

    return $this->_db->lastInsertId();
  }

  public function updateHostRobots(int $hostId, mixed $robots, int $timeUpdated) {

    $query = $this->_db->prepare('UPDATE `host` SET `robots` = ?, `timeUpdated` = ? WHERE `hostId` = ? LIMIT 1');

    $query->execute([$robots, $timeUpdated, $hostId]);

    return $query->rowCount();
  }

  // Pages
  public function getTotalHostPages(int $hostId) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetch()->total;
  }

  /* not in use
  public function getTotalPagesByHttpCode(mixed $httpCode) {

    if (is_null($httpCode)) {

      $query = $this->_db->query('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `httpCode` IS NULL');

    } else {

      $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `httpCode` = ?');

      $query->execute([$httpCode]);

    }

    return $query->fetch()->total;
  }
  */

  public function getHostPage(int $hostId, int $crc32uri) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? AND `crc32uri` = ? LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->fetch();
  }

  public function getHostPages(int $hostId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getHostPagesByIndexed() {

    $query = $this->_db->query('SELECT * FROM `hostPage` WHERE `timeUpdated` IS NOT NULL AND `timeBanned` IS NULL');

    return $query->fetchAll();
  }

  public function getHostPagesByLimit(int $hostId, int $limit) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? ORDER BY `hostPageId` DESC LIMIT ' . (int) $limit);

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getLastPageDescription(int $hostPageId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageDescription` WHERE `hostPageId` = ? ORDER BY `timeAdded` DESC LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->fetch();
  }

  public function getFoundHostPage(int $hostPageId) {

    $query = $this->_db->prepare('SELECT `hostPage`.`hostPageId`,
                                         `hostPage`.`uri`,
                                         `hostPage`.`timeAdded`,
                                         `hostPage`.`timeUpdated`,
                                         `hostPage`.`mime`,
                                         `hostPage`.`size`,
                                         `host`.`scheme`,
                                         `host`.`name`,
                                         `host`.`port`

                                          FROM `hostPage`
                                          JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                          WHERE `hostPage`.`hostPageId` = ?

                                          LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->fetch();
  }

  public function addHostPage(int $hostId,
                              int $crc32uri,
                              string $uri,
                              int $timeAdded,
                              mixed $timeUpdated = null,
                              mixed $timeBanned = null,
                              mixed $httpCode = null,
                              mixed $mime = null) {

    $query = $this->_db->prepare('INSERT INTO `hostPage` (`hostId`,
                                                          `crc32uri`,
                                                          `uri`,
                                                          `timeAdded`,
                                                          `timeUpdated`,
                                                          `timeBanned`,
                                                          `httpCode`,
                                                          `mime`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([$hostId, $crc32uri, $uri, $timeAdded, $timeUpdated, $timeBanned, $httpCode, $mime]);

    return $this->_db->lastInsertId();
  }

  public function updateHostPageTimeBanned(int $hostPageId, int $timeBanned) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeBanned` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeBanned, $hostPageId]);

    return $query->rowCount();
  }

  public function updateHostPageMime(int $hostPageId, string $mime) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `mime` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$mime, $hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPage(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPage` WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPageDescriptions(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageDescription` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  public function addHostPageDescription(int $hostPageId,
                                         mixed $title,
                                         mixed $description,
                                         mixed $keywords,
                                         mixed $data,
                                         int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageDescription` ( `hostPageId`,
                                                                      `title`,
                                                                      `description`,
                                                                      `keywords`,
                                                                      `data`,
                                                                      `timeAdded`
                                                                      ) VALUES (?, ?, ?, ?, ?, ?)');

    $query->execute([
      $hostPageId,
      $title,
      $description,
      $keywords,
      $data,
      $timeAdded,
    ]);

    return $query->rowCount();
  }

  public function addHostPageToHostPage(int $hostPageIdSource, int $hostPageIdTarget) {

    $query = $this->_db->prepare('INSERT IGNORE `hostPageToHostPage` (`hostPageIdSource`, `hostPageIdTarget`) VALUES (?, ?)');

    $query->execute([$hostPageIdSource, $hostPageIdTarget]);

  }

  public function deleteHostPageToHostPage(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageToHostPage` WHERE `hostPageIdSource` = ? OR `hostPageIdTarget` = ?');

    $query->execute([$hostPageId, $hostPageId]);

    return $query->rowCount();
  }

  public function getTotalHostPageIdSourcesByHostPageIdTarget(int $hostPageIdTarget) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ?');

    $query->execute([$hostPageIdTarget]);

    return $query->fetch()->total;
  }

  public function getHostPageIdSourcesByHostPageIdTarget(int $hostPageIdTarget, int $limit = 1000) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ? LIMIT ' . (int) $limit);

    $query->execute([$hostPageIdTarget]);

    return $query->fetchAll();
  }

  public function addHostPageSnap(int $hostPageId, string $crc32data, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageSnap` (`hostPageId`,
                                                              `crc32data`,
                                                              `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageId, $crc32data, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function updateHostPageSnapStorageLocal(int $hostPageSnapId, mixed $value) {

    $query = $this->_db->prepare('UPDATE `hostPageSnap` SET `storageLocal` = ? WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$value, $hostPageSnapId]);

    return $query->rowCount();
  }

  public function updateHostPageSnapStorageMega(int $hostPageSnapId, mixed $value) {

    $query = $this->_db->prepare('UPDATE `hostPageSnap` SET `storageMega` = ? WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$value, $hostPageSnapId]);

    return $query->rowCount();
  }

  public function deleteHostPageSnap(int $hostPageSnapId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function getTotalHostPageSnaps(int $hostPageId, bool $storageLocal = true, bool $storageMega = true) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageSnap` WHERE `hostPageId` = ? AND (`storageLocal` = ? OR `storageMega` = ?)');

    $query->execute([$hostPageId, $storageLocal, $storageMega]);

    return $query->fetch()->total;
  }

  public function getHostPageSnaps(int $hostPageId, bool $storageLocal = true, bool $storageMega = true) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageId` = ? AND (`storageLocal` = ? OR `storageMega` = ?) ORDER BY `timeAdded` DESC');

    $query->execute([$hostPageId, $storageLocal, $storageMega]);

    return $query->fetchAll();
  }

  public function getHostPageSnap(int $hostPageSnapId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->fetch();
  }

  public function findHostPageSnap(int $hostPageId, int $crc32data) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageId` = ? AND `crc32data` = ? LIMIT 1');

    $query->execute([$hostPageId, $crc32data]);

    return $query->fetch();
  }

  /* not in use
  public function getHostPageSnapDownloads(int $hostPageSnapId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnapDownload` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->fetchAll();
  }
  */

  public function addHostPageSnapDownload(int $hostPageSnapId, string $crc32ip, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageSnapDownload` (`hostPageSnapId`,
                                                                      `crc32ip`,
                                                                      `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageSnapId, $crc32ip, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function updateHostPageSnapDownload(int $hostPageSnapDownloadId, string $storage, int $size, mixed $httpCode = NULL) {

    $query = $this->_db->prepare('UPDATE `hostPageSnapDownload` SET `storage` = ?, `size` = ?, `httpCode` = ? WHERE `hostPageSnapDownloadId` = ? LIMIT 1');

    $query->execute([$storage, $size, $httpCode, $hostPageSnapDownloadId]);

    return $query->rowCount();
  }

  public function deleteHostPageSnapDownloads(int $hostPageSnapId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageSnapDownload` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function findHostPageSnapDownloadsTotalSize(int $crc32ip, int $timeOffset) {

    $query = $this->_db->prepare('SELECT SUM(`size`) AS `size` FROM `hostPageSnapDownload`

                                                               WHERE `crc32ip` = ? AND `timeAdded` < ?');

    $query->execute([$crc32ip, $timeOffset]);

    return $query->fetch()->size;
  }

  public function addHostPageDom(int $hostPageId, int $timeAdded, string $selector, string $value) {

    $query = $this->_db->prepare('INSERT INTO `hostPageDom` SET `hostPageId` = ?, `timeAdded` = ?, `selector` = ?, `value` = ?');

    $query->execute([$hostPageId, $timeAdded, $selector, $value]);
  }

  public function deleteHostPageDoms(int $hostPageId) {

    $query = $this->_db->query('DELETE FROM `hostPageDom` WHERE `hostPageId` = ?');

    return $query->rowCount();
  }

  public function deleteHostPageDomsByTimeAdded(int $timeOffset) {

    $query = $this->_db->prepare('DELETE FROM `hostPageDom` WHERE `timeAdded` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  public function truncateHostPageDom() {

    $query = $this->_db->query('TRUNCATE `hostPageDom`');
  }

  // Cleaner tools
  public function getCleanerQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT * FROM `host`

                                           WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ? ) AND `host`.`status` <> ?

                                           ORDER BY `hostId`

                                           LIMIT ' . (int) $limit);

    $query->execute([$timeFrom, 0]);

    return $query->fetchAll();
  }

  public function getHostPagesBanned() {

    $query = $this->_db->query('SELECT * FROM `hostPage` WHERE `timeBanned` IS NOT NULL');

    return $query->fetchAll();
  }

  public function resetBannedHostPages(int $timeOffset) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeBanned` = NULL WHERE `timeBanned` IS NOT NULL AND `timeBanned` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  public function deleteHostPageDescriptionsByTimeAdded(int $timeOffset) {

    $query = $this->_db->prepare('DELETE FROM `hostPageDescription` WHERE `timeAdded` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  public function addCleanerLog(int $timeAdded,
                                int $hostsTotal,
                                int $hostsUpdated,
                                int $hostPagesDeleted,
                                int $hostPagesDescriptionsDeleted,
                                int $hostPagesDomsDeleted,
                                int $hostPagesSnapDeleted,
                                int $hostPagesToHostPageDeleted,
                                int $hostPagesBansRemoved,
                                int $manifestsTotal,
                                int $manifestsDeleted,
                                int $logsCleanerDeleted,
                                int $logsCrawlerDeleted,
                                int $httpRequestsTotal,
                                int $httpRequestsSizeTotal,
                                int $httpDownloadSizeTotal,
                                float $httpRequestsTimeTotal,
                                float $executionTimeTotal) {

    $query = $this->_db->prepare('INSERT INTO `logCleaner` (`timeAdded`,
                                                            `hostsTotal`,
                                                            `hostsUpdated`,
                                                            `hostPagesDeleted`,
                                                            `hostPagesDescriptionsDeleted`,
                                                            `hostPagesDomsDeleted`,
                                                            `hostPagesSnapDeleted`,
                                                            `hostPagesToHostPageDeleted`,
                                                            `hostPagesBansRemoved`,
                                                            `manifestsTotal`,
                                                            `manifestsDeleted`,
                                                            `logsCleanerDeleted`,
                                                            `logsCrawlerDeleted`,
                                                            `httpRequestsTotal`,
                                                            `httpRequestsSizeTotal`,
                                                            `httpDownloadSizeTotal`,
                                                            `httpRequestsTimeTotal`,
                                                            `executionTimeTotal`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([
      $timeAdded,
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
      $executionTimeTotal
    ]);

    return $this->_db->lastInsertId();
  }

  public function deleteLogCleaner(int $timeOffset) {

    $query = $this->_db->prepare('DELETE FROM `logCleaner` WHERE `timeAdded` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  // Crawl tools
  public function getHostPageCrawlQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT `hostPage`.`hostId`,
                                         `hostPage`.`hostPageId`,
                                         `hostPage`.`uri`,
                                         `host`.`scheme`,
                                         `host`.`name`,
                                         `host`.`port`,
                                         `host`.`crawlPageLimit`,
                                         `host`.`crawlMetaOnly`,
                                         `host`.`robots`,
                                         `host`.`robotsPostfix`

                                          FROM `hostPage`
                                          JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                          WHERE (`hostPage`.`timeUpdated` IS NULL OR `hostPage`.`timeUpdated` < ? ) AND `host`.`status` <> ?
                                                                                                                    AND `hostPage`.`timeBanned` IS NULL

                                          ORDER BY LENGTH(`hostPage`.`uri`) ASC, RAND()

                                          LIMIT ' . (int) $limit);

    $query->execute([$timeFrom, 0]);

    return $query->fetchAll();
  }

  public function getHostPageCrawlQueueTotal(int $timeFrom) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total`

                                          FROM `hostPage`
                                          JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                          WHERE (`hostPage`.`timeUpdated` IS NULL OR `hostPage`.`timeUpdated` < ? ) AND `host`.`status` <> ?
                                                                                                                    AND `hostPage`.`timeBanned` IS NULL');

    $query->execute([$timeFrom, 0]);

    return $query->fetch()->total;
  }

  public function updateHostPageCrawlQueue(int $hostPageId, int $timeUpdated, int $httpCode, int $size) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeUpdated` = ?, `httpCode` = ?, `size` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $size, $hostPageId]);

    return $query->rowCount();
  }

  public function getManifestCrawlQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT * FROM `manifest`

                                           WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ? ) AND `status` <> ?

                                           ORDER BY RAND()

                                           LIMIT ' . (int) $limit);

    $query->execute([$timeFrom, 0]);

    return $query->fetchAll();
  }

  public function updateManifestCrawlQueue(int $manifestId, int $timeUpdated, int $httpCode) {

    $query = $this->_db->prepare('UPDATE `manifest` SET `timeUpdated` = ?, `httpCode` = ? WHERE `manifestId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $manifestId]);

    return $query->rowCount();
  }

  public function addCrawlerLog(int $timeAdded,
                                int $hostsAdded,
                                int $hostPagesProcessed,
                                int $hostPagesAdded,
                                int $hostPagesSnapAdded,
                                int $hostPagesBanned,
                                int $manifestsProcessed,
                                int $manifestsAdded,
                                int $httpRequestsTotal,
                                int $httpRequestsSizeTotal,
                                int $httpDownloadSizeTotal,
                                float $httpRequestsTimeTotal,
                                float $executionTimeTotal) {

    $query = $this->_db->prepare('INSERT INTO `logCrawler` (`timeAdded`,
                                                            `hostsAdded`,
                                                            `hostPagesProcessed`,
                                                            `hostPagesAdded`,
                                                            `hostPagesSnapAdded`,
                                                            `hostPagesBanned`,
                                                            `manifestsProcessed`,
                                                            `manifestsAdded`,
                                                            `httpRequestsTotal`,
                                                            `httpRequestsSizeTotal`,
                                                            `httpDownloadSizeTotal`,
                                                            `httpRequestsTimeTotal`,
                                                            `executionTimeTotal`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([
      $timeAdded,
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
      $executionTimeTotal
    ]);

    return $this->_db->lastInsertId();
  }

  public function deleteLogCrawler(int $timeOffset) {

    $query = $this->_db->prepare('DELETE FROM `logCrawler` WHERE `timeAdded` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  public function optimize() {

    $this->_db->query('OPTIMIZE TABLE `host`');
    $this->_db->query('OPTIMIZE TABLE `hostPage`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDescription`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDom`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnap`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnapDownload`');
    $this->_db->query('OPTIMIZE TABLE `hostPageToHostPage`');

    $this->_db->query('OPTIMIZE TABLE `logCleaner`');
    $this->_db->query('OPTIMIZE TABLE `logCrawler`');

    $this->_db->query('OPTIMIZE TABLE `manifest`');
  }
}
