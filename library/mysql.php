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

  public function getHosts() {

    $query = $this->_db->query('SELECT * FROM `host`');

    return $query->fetchAll();
  }

  public function getHost(int $hostId) {

    $query = $this->_db->prepare("SELECT *,
                                         IF (`port` IS NOT NULL,
                                                CONCAT(`scheme`, '://', `name`, ':', `port`),
                                                CONCAT(`scheme`, '://', `name`)
                                            ) AS `url`

                                        FROM `host` WHERE `hostId` = ? LIMIT 1");

    $query->execute([$hostId]);

    return $query->fetch();
  }

  public function getHostByCRC32URL(int $crc32url) {

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

  public function getHostPage(int $hostPageId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->fetch();
  }

  public function findHostPageByCRC32URI(int $hostId, int $crc32uri) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? AND `crc32uri` = ? LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->fetch();
  }

  public function getHostPages(int $hostId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getTopHostPages(int $limit = 100) {

    // Get ID (to prevent memory over usage)
    $query = $this->_db->query("SELECT `hostPage`.`hostPageId`

                                        FROM `hostPage`
                                        JOIN `host` ON (`hostPage`.`hostId` = `host`.`hostId`)

                                        WHERE `host`.`status`         = '1'
                                        AND   `hostPage`.`httpCode`   = 200
                                        AND   `hostPage`.`rank`       > 0
                                        AND   `hostPage`.`timeBanned` IS NULL
                                        AND   `hostPage`.`mime`       IS NOT NULL

                                        ORDER BY `rank` DESC

                                        LIMIT " . (int) $limit);

    // Get required page details
    foreach ($query->fetchAll() as $top) {

      $query = $this->_db->prepare("SELECT  `hostPage`.`hostId`,
                                            `hostPage`.`hostPageId`,
                                            `hostPage`.`uri`,
                                            `hostPage`.`rank`,

                                            `host`.`scheme`,
                                            `host`.`name`,
                                            `host`.`port`,

                                            IF (`host`.`port` IS NOT NULL,
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, ':', `host`.`port`),
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`)
                                            ) AS `hostURL`,

                                            IF (`host`.`port` IS NOT NULL,
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, ':', `host`.`port`, `hostPage`.`uri`),
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, `hostPage`.`uri`)
                                            ) AS `hostPageURL`

                                            FROM `hostPage`
                                            JOIN `host` ON (`hostPage`.`hostId` = `host`.`hostId`)

                                            WHERE `hostPage`.`hostPageId` = ?

                                            LIMIT 1");

      $query->execute([$top->hostPageId]);

      if ($query->rowCount()) {

        $result[] = $query->fetch();
      }
    }

    return $result;
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

  public function updateHostPageRank(int $hostPageId, int $rank) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `rank` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$rank, $hostPageId]);

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

  public function getTotalHostPagesToHostPageByHostPageIdTarget(int $hostPageIdTarget) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ?');

    $query->execute([$hostPageIdTarget]);

    return $query->fetch()->total;
  }

  public function getHostPagesToHostPageByHostPageIdTarget(int $hostPageIdTarget, int $limit = 1000) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ? LIMIT ' . (int) $limit);

    $query->execute([$hostPageIdTarget]);

    return $query->fetchAll();
  }

  public function addHostPageSnap(int $hostPageId, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageSnap` (`hostPageId`, `timeAdded`) VALUES (?, ?)');

    $query->execute([$hostPageId, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function deleteHostPageSnap(int $hostPageSnapId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function getTotalHostPageSnaps(int $hostPageId) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageSnap` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

    return $query->fetch()->total;
  }

  public function getHostPageSnaps(int $hostPageId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageId` = ? ORDER BY `timeAdded` DESC');

    $query->execute([$hostPageId]);

    return $query->fetchAll();
  }

  public function getHostPageSnap(int $hostPageSnapId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->fetch();
  }

  public function addHostPageSnapDownload(int $hostPageSnapStorageId, string $crc32ip, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageSnapDownload` (`hostPageSnapStorageId`,
                                                                      `crc32ip`,
                                                                      `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageSnapStorageId, $crc32ip, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function addHostPageSnapStorage(int $hostPageSnapId, int $crc32name, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostPageSnapStorage` (`hostPageSnapId`,
                                                                     `crc32name`,
                                                                     `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageSnapId, $crc32name,  $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findHostPageSnapStorageByCRC32Name(int $hostPageSnapId, int $crc32name) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ? AND `crc32name` = ?');

    $query->execute([$hostPageSnapId, $crc32name]);

    return $query->fetch();
  }

  public function getHostPageSnapStorages(int $hostPageSnapId) {

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ?');

    $query->execute([$hostPageSnapId]);

    return $query->fetchAll();
  }

  public function deleteHostPageSnapStorages(int $hostPageSnapId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ?');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function deleteHostPageSnapDownloads(int $hostPageSnapStorageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageSnapDownload` WHERE `hostPageSnapStorageId` = ?');

    $query->execute([$hostPageSnapStorageId]);

    return $query->rowCount();
  }

  public function addHostPageDom(int $hostPageId, int $timeAdded, string $selector, string $value) {

    $query = $this->_db->prepare('INSERT INTO `hostPageDom` SET `hostPageId` = ?, `timeAdded` = ?, `selector` = ?, `value` = ?');

    $query->execute([$hostPageId, $timeAdded, $selector, $value]);
  }

  public function deleteHostPageDoms(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPageDom` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

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
  public function resetBannedHostPages(int $timeOffset) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeBanned` = NULL WHERE `timeBanned` IS NOT NULL AND `timeBanned` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  // Crawler tools
  public function getHostPageCrawlQueueTotal(int $hostPageTimeFrom, int $hostPageHomeTimeFrom) {

    $query = $this->_db->prepare("SELECT COUNT(*) AS `total`

                                          FROM `hostPage`
                                          JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                          WHERE (
                                            `hostPage`.`timeUpdated` IS NULL OR
                                            `hostPage`.`timeUpdated` < ? OR (
                                              `hostPage`.`uri` = '/' AND
                                              `hostPage`.`timeUpdated` < ?
                                              )
                                            )

                                          AND  `host`.`status` <> ?
                                          AND  `hostPage`.`timeBanned` IS NULL");

    $query->execute([$hostPageTimeFrom, $hostPageHomeTimeFrom, 0]);

    return $query->fetch()->total;
  }

  public function getHostPageCrawlQueue(int $limit, int $hostPageTimeFrom, int $hostPageHomeTimeFrom) {

    $result = [];

    // Get ID (to prevent memory over usage)
    $query = $this->_db->prepare("SELECT `hostPage`.`hostPageId`

                                         FROM `hostPage`
                                         JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                         WHERE (
                                          `hostPage`.`timeUpdated` IS NULL OR
                                          `hostPage`.`timeUpdated` < ?
                                          OR (
                                            `hostPage`.`uri` = '/' AND
                                            `hostPage`.`timeUpdated` < ?
                                            )
                                          )

                                         AND  `host`.`status` <> ?
                                         AND  `hostPage`.`timeBanned` IS NULL

                                         ORDER BY LENGTH(`hostPage`.`uri`) ASC, RAND()

                                         LIMIT " . (int) $limit);

    $query->execute([$hostPageTimeFrom, $hostPageHomeTimeFrom, 0]);

    // Get required page details
    foreach ($query->fetchAll() as $queue) {

      $query = $this->_db->prepare("SELECT  `hostPage`.`hostId`,
                                            `hostPage`.`hostPageId`,
                                            `hostPage`.`uri`,

                                            `host`.`scheme`,
                                            `host`.`name`,
                                            `host`.`port`,
                                            `host`.`crawlPageLimit`,
                                            `host`.`crawlMetaOnly`,
                                            `host`.`robots`,
                                            `host`.`robotsPostfix`,

                                            IF (`host`.`port` IS NOT NULL,
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, ':', `host`.`port`),
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`)
                                            ) AS `hostURL`,

                                            IF (`host`.`port` IS NOT NULL,
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, ':', `host`.`port`, `hostPage`.`uri`),
                                                CONCAT(`host`.`scheme`, '://', `host`.`name`, `hostPage`.`uri`)
                                            ) AS `hostPageURL`

                                            FROM `hostPage`
                                            JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                            WHERE `hostPage`.`hostPageId` = ? LIMIT 1");

      $query->execute([$queue->hostPageId]);

      if ($query->rowCount()) {

        $result[] = $query->fetch();
      }
    }

    return (object) $result;
  }

  public function updateHostPageCrawlQueue(int $hostPageId, int $timeUpdated, int $httpCode, int $size) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeUpdated` = ?, `httpCode` = ?, `size` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $size, $hostPageId]);

    return $query->rowCount();
  }

  public function getHostRobotsCrawlQueue(int $limit, int $timeFrom) {

    $result = [];

    // Get ID (to prevent memory over usage)
    $query = $this->_db->prepare("SELECT `hostId`

                                          FROM `host`

                                          WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ? ) AND `status` <> ?

                                          ORDER BY RAND()

                                          LIMIT " . (int) $limit);

    $query->execute([$timeFrom, 0]);

    // Get required page details
    foreach ($query->fetchAll() as $host) {

      $result[] = $this->getHost($host->hostId);
    }

    return (object) $result;
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

  public function optimize() {

    $this->_db->query('OPTIMIZE TABLE `host`');
    $this->_db->query('OPTIMIZE TABLE `hostPage`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDescription`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDom`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnap`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnapStorage`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnapDownload`');
    $this->_db->query('OPTIMIZE TABLE `hostPageToHostPage`');

    $this->_db->query('OPTIMIZE TABLE `manifest`');
  }
}
