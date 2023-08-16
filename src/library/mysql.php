<?php

class MySQL {

  private PDO $_db;

  private object $_debug;

  public function __construct(string $host, int $port, string $database, string $username, string $password) {

    $this->_db = new PDO('mysql:dbname=' . $database . ';host=' . $host . ';port=' . $port . ';charset=utf8', $username, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $this->_db->setAttribute(PDO::ATTR_TIMEOUT, 600);

    $this->_debug = (object)
    [
      'query' => (object)
      [
        'select' => (object)
        [
          'total' => 0
        ],
        'insert' => (object)
        [
          'total' => 0
        ],
        'update' => (object)
        [
          'total' => 0
        ],
        'delete' => (object)
        [
          'total' => 0
        ],
      ]
    ];
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

  public function getDebug() {

    return $this->_debug;
  }

  // Host
  public function getAPIHosts(string $apiHostFields) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT ' . $apiHostFields . ' FROM `host`');

    $query->execute();

    return $query->fetchAll();
  }

  public function getHosts() {

    $this->_debug->query->select->total++;

    $query = $this->_db->query('SELECT * FROM `host`');

    return $query->fetchAll();
  }

  public function getHost(int $hostId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare("SELECT *,
                                         IF (`port` IS NOT NULL,
                                                CONCAT(`scheme`, '://', `name`, ':', `port`),
                                                CONCAT(`scheme`, '://', `name`)
                                            ) AS `url`

                                        FROM `host` WHERE `hostId` = ? LIMIT 1");

    $query->execute([$hostId]);

    return $query->fetch();
  }

  public function findHostByCRC32URL(int $crc32url) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `host` WHERE `crc32url` = ? LIMIT 1');

    $query->execute([$crc32url]);

    return $query->fetch();
  }

  public function getTotalHosts() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `host`');

    $query->execute();

    return $query->fetch()->total;
  }

  public function addHost(string $scheme, string $name, mixed $port, int $crc32url, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `host` (`scheme`,
                                                      `name`,
                                                      `port`,
                                                      `crc32url`,
                                                      `timeAdded`) VALUES (?, ?, ?, ?, ?)');

    $query->execute([$scheme, $name, $port, $crc32url, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  // Host settings
  public function findHostSettingValue(int $hostId, string $key) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT `value` FROM `hostSetting` WHERE `hostId` = ? AND `key` = ? LIMIT 1');

    $query->execute([$hostId, $key]);

    return $query->rowCount() ? json_decode($query->fetch()->value) : false;
  }

  public function findHostSetting(int $hostId, string $key) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostSetting` WHERE `hostId` = ? AND `key` = ? LIMIT 1');

    $query->execute([$hostId, $key]);

    return $query->fetch();
  }

  public function addHostSetting(int $hostId, string $key, mixed $value, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `hostSetting` (`hostId`, `key`, `value`, `timeAdded`) VALUES (?, ?, ?, ?)');

    $value = json_encode($value);

    $query->execute(
      [
        $hostId,
        $key,
        $value,
        $timeAdded
      ]
    );

    return $query->rowCount();
  }

  public function updateHostSetting(int $hostSettingId, mixed $value, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostSetting` SET  `value`       = ?,
                                                            `timeUpdated` = ?

                                                      WHERE `hostSettingId` = ?

                                                      LIMIT 1');

    $value = json_encode($value);

    $query->execute(
      [
        $value,
        $timeUpdated,
        $hostSettingId
      ]
    );

    return $query->rowCount();
  }

  public function deleteHostSetting(int $hostSettingId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostSetting` WHERE `hostSettingId` = ?');

    $query->execute([$hostSettingId]);

    return $query->rowCount();
  }

  // Host pages
  public function getTotalHostPages(int $hostId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetch()->total;
  }

  public function getHostPage(int $hostPageId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->fetch();
  }

  public function findHostPageByCRC32URI(int $hostId, int $crc32uri) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? AND `crc32uri` = ? LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->fetch();
  }

  public function getHostPages(int $hostId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getTopHostPages(int $limit = 100) {

    $this->_debug->query->select->total++;

    // Get ID (to prevent memory over usage)
    $query = $this->_db->query("SELECT `hostPageId` FROM `hostPage`

                                                    WHERE `httpCode`   = 200
                                                    AND   `rank`       > 0
                                                    AND   `timeBanned` IS NULL
                                                    AND   `mime`       IS NOT NULL

                                                    ORDER BY `rank` DESC

                                                    LIMIT " . (int) $limit);

    // Get required page details
    foreach ($query->fetchAll() as $top) {

      $this->_debug->query->select->total++;

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

  public function getHostPagesByLimit(int $hostId, int $limit) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? ORDER BY `hostPageId` DESC LIMIT ' . (int) $limit);

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getLastPageDescription(int $hostPageId) {

    $this->_debug->query->select->total++;

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

    $this->_debug->query->insert->total++;

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

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeBanned` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeBanned, $hostPageId]);

    return $query->rowCount();
  }

  public function updateHostPageMime(int $hostPageId, string $mime) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostPage` SET `mime` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$mime, $hostPageId]);

    return $query->rowCount();
  }

  public function updateHostPageRank(int $hostPageId, int $rank) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostPage` SET `rank` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$rank, $hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPage(int $hostPageId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPage` WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPageDescriptions(int $hostPageId) {

    $this->_debug->query->delete->total++;

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

    $this->_debug->query->insert->total++;

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

  public function setHostPageToHostPage(int $hostPageIdSource, int $hostPageIdTarget) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT IGNORE `hostPageToHostPage` (`hostPageIdSource`, `hostPageIdTarget`) VALUES (?, ?)');

    $query->execute([$hostPageIdSource, $hostPageIdTarget]);
  }

  public function deleteHostPageToHostPage(int $hostPageId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageToHostPage` WHERE `hostPageIdSource` = ? OR `hostPageIdTarget` = ?');

    $query->execute([$hostPageId, $hostPageId]);

    return $query->rowCount();
  }

  public function getTotalHostPagesToHostPageByHostPageIdTarget(int $hostPageIdTarget) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ?');

    $query->execute([$hostPageIdTarget]);

    return $query->fetch()->total;
  }

  public function getHostPagesToHostPageByHostPageIdTarget(int $hostPageIdTarget, int $limit = 1000) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageToHostPage` WHERE `hostPageIdTarget` = ? LIMIT ' . (int) $limit);

    $query->execute([$hostPageIdTarget]);

    return $query->fetchAll();
  }

  public function getHostPageToHostPage(int $hostPageIdSource, int $hostPageIdTarget) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageToHostPage` WHERE `hostPageIdSource` = ? AND `hostPageIdTarget` = ? LIMIT 1');

    $query->execute([$hostPageIdSource, $hostPageIdTarget]);

    return $query->fetch();
  }

  public function addHostPageSnap(int $hostPageId, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `hostPageSnap` (`hostPageId`, `timeAdded`) VALUES (?, ?)');

    $query->execute([$hostPageId, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function deleteHostPageSnap(int $hostPageSnapId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function getTotalHostPageSnaps(int $hostPageId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPageSnap` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

    return $query->fetch()->total;
  }

  public function getHostPageSnaps(int $hostPageId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageId` = ? ORDER BY `timeAdded` DESC');

    $query->execute([$hostPageId]);

    return $query->fetchAll();
  }

  public function getHostPageSnap(int $hostPageSnapId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnap` WHERE `hostPageSnapId` = ? LIMIT 1');

    $query->execute([$hostPageSnapId]);

    return $query->fetch();
  }

  public function addHostPageSnapDownload(int $hostPageSnapStorageId, string $crc32ip, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `hostPageSnapDownload` (`hostPageSnapStorageId`,
                                                                      `crc32ip`,
                                                                      `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageSnapStorageId, $crc32ip, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function addHostPageSnapStorage(int $hostPageSnapId, int $crc32name, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `hostPageSnapStorage` (`hostPageSnapId`,
                                                                     `crc32name`,
                                                                     `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$hostPageSnapId, $crc32name,  $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findHostPageSnapStorageByCRC32Name(int $hostPageSnapId, int $crc32name) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ? AND `crc32name` = ?');

    $query->execute([$hostPageSnapId, $crc32name]);

    return $query->fetch();
  }

  public function getHostPageSnapStorages(int $hostPageSnapId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ?');

    $query->execute([$hostPageSnapId]);

    return $query->fetchAll();
  }

  public function deleteHostPageSnapStorages(int $hostPageSnapId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageSnapStorage` WHERE `hostPageSnapId` = ?');

    $query->execute([$hostPageSnapId]);

    return $query->rowCount();
  }

  public function deleteHostPageSnapDownloads(int $hostPageSnapStorageId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageSnapDownload` WHERE `hostPageSnapStorageId` = ?');

    $query->execute([$hostPageSnapStorageId]);

    return $query->rowCount();
  }

  public function addHostPageDom(int $hostPageId, string $selector, string $value, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `hostPageDom` SET `hostPageId` = ?, `timeAdded` = ?, `selector` = ?, `value` = ?');

    $query->execute([$hostPageId, $timeAdded, $selector, $value]);
  }

  public function deleteHostPageDoms(int $hostPageId) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageDom` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPageDomsByTimeAdded(int $timeOffset) {

    $this->_debug->query->delete->total++;

    $query = $this->_db->prepare('DELETE FROM `hostPageDom` WHERE `timeAdded` < ' . (int) $timeOffset);

    $query->execute();

    return $query->rowCount();
  }

  public function truncateHostPageDom() {

    $query = $this->_db->query('TRUNCATE `hostPageDom`');
  }

  // Cleaner tools
  public function resetBannedHostPages(int $timeOffset) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeBanned` = NULL WHERE `timeBanned` IS NOT NULL AND `timeBanned` < ?');

    $query->execute([$timeOffset]);

    return $query->rowCount();
  }

  public function resetBannedHosts(int $timeOffset) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `host` SET `timeBanned` = NULL WHERE `timeBanned` IS NOT NULL AND `timeBanned` < ?');

    $query->execute([$timeOffset]);

    return $query->rowCount();
  }

  // Crawler tools
  public function getHostPageCrawlQueueTotal(int $timeFrom) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare("SELECT COUNT(*) AS `total` FROM  `hostPage`

                                                             WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ?) AND `hostPage`.`timeBanned` IS NULL");

    $query->execute([$timeFrom]);

    return $query->fetch()->total;
  }

  public function getHostPageCrawlQueue(int $limit, int $timeFrom) {

    $this->_debug->query->select->total++;

    $result = [];

    // Get ID (to prevent memory over usage)
    $query = $this->_db->prepare("SELECT `hostPageId` FROM `hostPage`

                                                      WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ?) AND `timeBanned` IS NULL

                                                      ORDER BY LENGTH(`uri`) ASC, RAND()

                                                      LIMIT " . (int) $limit);

    $query->execute([$timeFrom]);

    // Get required page details
    foreach ($query->fetchAll() as $queue) {

      $this->_debug->query->select->total++;

      $query = $this->_db->prepare("SELECT  `hostPage`.`hostId`,
                                            `hostPage`.`hostPageId`,
                                            `hostPage`.`uri`,

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

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeUpdated` = ?, `httpCode` = ?, `size` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $size, $hostPageId]);

    return $query->rowCount();
  }

  public function getHostCrawlQueue(int $limit, int $timeFrom) {

    $this->_debug->query->select->total++;

    $result = [];

    // Get ID (to prevent memory over usage)
    $query = $this->_db->prepare("SELECT `hostId`

                                          FROM `host`

                                          WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ?) AND `timeBanned` IS NULL

                                          ORDER BY RAND()

                                          LIMIT " . (int) $limit);

    $query->execute([$timeFrom]);

    // Get required page details
    foreach ($query->fetchAll() as $host) {

      $result[] = $this->getHost($host->hostId);
    }

    return (object) $result;
  }

  public function updateHostCrawlQueue(int $hostId, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `host` SET `timeUpdated` = ? WHERE `hostId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $hostId]);

    return $query->rowCount();
  }

  public function optimize() {

    $this->_db->query('OPTIMIZE TABLE `host`');
    $this->_db->query('OPTIMIZE TABLE `hostSetting`');
    $this->_db->query('OPTIMIZE TABLE `hostPage`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDescription`');
    $this->_db->query('OPTIMIZE TABLE `hostPageDom`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnap`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnapStorage`');
    $this->_db->query('OPTIMIZE TABLE `hostPageSnapDownload`');
    $this->_db->query('OPTIMIZE TABLE `hostPageToHostPage`');
  }
}
