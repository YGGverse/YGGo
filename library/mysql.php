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

  public function addHost(string $scheme, string $name, mixed $port, int $crc32url, int $timeAdded, mixed $timeUpdated, int $crawlPageLimit, int $crawlImageLimit, string $crawlPageMetaOnly, string $status, mixed $robots, mixed $robotsPostfix) {

    $query = $this->_db->prepare('INSERT INTO `host` (`scheme`, `name`, `port`, `crc32url`, `timeAdded`, `timeUpdated`, `crawlPageLimit`, `crawlImageLimit`, `crawlPageMetaOnly`, `status`, `robots`, `robotsPostfix`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([$scheme, $name, $port, $crc32url, $timeAdded, $timeUpdated, $crawlPageLimit, $crawlImageLimit, $crawlPageMetaOnly, $status, $robots, $robotsPostfix]);

    return $this->_db->lastInsertId();
  }

  public function updateHostRobots(int $hostId, mixed $robots, int $timeUpdated) {

    $query = $this->_db->prepare('UPDATE `host` SET `robots` = ?, `timeUpdated` = ? WHERE `hostId` = ? LIMIT 1');

    $query->execute([$robots, $timeUpdated, $hostId]);

    return $query->rowCount();
  }

  // Images
  public function getTotalHostImages(int $hostId) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostImage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetch()->total;
  }

  public function getHostImage(int $hostId, int $crc32uri) {

    $query = $this->_db->prepare('SELECT * FROM `hostImage` WHERE `hostId` = ? AND `crc32uri` = ? LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->fetch();
  }

  public function getHostImages(int $hostId) {

    $query = $this->_db->prepare('SELECT * FROM `hostImage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getUnrelatedHostImages() {

    $query = $this->_db->prepare('SELECT * FROM  `hostImage`
                                           WHERE `hostImage`.`hostImageId` NOT IN (SELECT  `hostImageToHostPage`.`hostImageId`
                                                                                     FROM  `hostImageToHostPage`

                                                                                     WHERE `hostImageToHostPage`.`hostImageId` = `hostImage`.`hostImageId`)');

    $query->execute();

    return $query->fetchAll();
  }

  public function getHostImagesByLimit(int $hostId, int $limit) {

    $query = $this->_db->prepare('SELECT * FROM `hostImage` WHERE `hostId` = ? ORDER BY hostImageId DESC LIMIT ' . (int) $limit);

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function addHostImage(int $hostId,
                              int $crc32uri,
                              string $uri,
                              int $timeAdded,
                              mixed $timeUpdated = null,
                              mixed $httpCode = null,
                              mixed $rank = null) {

    $query = $this->_db->prepare('INSERT INTO `hostImage` ( `hostId`,
                                                            `crc32uri`,
                                                            `uri`,
                                                            `timeAdded`,
                                                            `timeUpdated`,
                                                            `httpCode`,
                                                            `rank`) VALUES (?, ?, ?, ?, ?, ?, ?)');

    $query->execute([$hostId, $crc32uri, $uri, $timeAdded, $timeUpdated, $httpCode, $rank]);

    return $this->_db->lastInsertId();
  }

  public function updateHostImageRank(int $hostId,
                                      int $crc32uri,
                                      int $increment) {

    $query = $this->_db->prepare('UPDATE `hostImage` SET `rank` = `rank` + ' . (int) $increment . ' WHERE `hostId` = ? AND   `crc32uri` = ? LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->rowCount();
  }

  public function deleteHostImage(int $hostImageId) {

    $query = $this->_db->prepare('DELETE FROM `hostImage` WHERE `hostImageId` = ? LIMIT 1');

    $query->execute([$hostImageId]);

    return $query->rowCount();
  }

  public function getHostImageDescription(int $hostImageId, int $crc32id) {

    $query = $this->_db->prepare('SELECT * FROM `hostImageDescription` WHERE `hostImageId` = ? AND `crc32id` = ? LIMIT 1');

    $query->execute([$hostImageId, $crc32id]);

    return $query->fetch();
  }

  public function addHostImageDescription(int $hostImageId, int $crc32id, string $alt, string $title, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `hostImageDescription` (`hostImageId`,
                                                                      `crc32id`,
                                                                      `alt`,
                                                                      `title`,
                                                                      `timeAdded`) VALUES (?, ?, ?, ?, ?)');

    $query->execute([$hostImageId, $crc32id, $alt, $title, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function deleteHostImageDescription(int $hostImageId) {

    $query = $this->_db->prepare('DELETE FROM `hostImageDescription` WHERE `hostImageId` = ?');

    $query->execute([$hostImageId]);

    return $query->rowCount();
  }

  public function getHostImageToHostPage(int $hostImageId, int $hostPageId) {

    $query = $this->_db->prepare('SELECT * FROM `hostImageToHostPage` WHERE `hostImageId` = ? AND `hostPageId` = ? LIMIT 1');

    $query->execute([$hostImageId, $hostPageId]);

    return $query->fetch();
  }

  public function getHostImageHostPages(int $hostImageId) {

    $query = $this->_db->prepare('SELECT * FROM `hostImageToHostPage` WHERE `hostImageId` = ?');

    $query->execute([$hostImageId]);

    return $query->fetchAll();
  }

  public function addHostImageToHostPage(int $hostImageId, int $hostPageId, int $timeAdded, mixed $timeUpdated, int $quantity) {

    $query = $this->_db->prepare('INSERT INTO `hostImageToHostPage` (`hostImageId`,
                                                                     `hostPageId`,
                                                                     `timeAdded`,
                                                                     `timeUpdated`,
                                                                     `quantity`) VALUES (?, ?, ?, ?, ?)');

    $query->execute([$hostImageId, $hostPageId, $timeAdded, $timeUpdated, $quantity]);

    return $query->rowCount(); // no primary key
  }

  public function updateHostImageToHostPage(int $hostImageId, int $hostPageId, int $timeAdded, int $quantity) {

    $query = $this->_db->prepare('UPDATE `hostImageToHostPage` SET   `quantity` = `quantity` + ' . (int) $quantity . ', `timeUpdated` = ?

                                                               WHERE `hostImageId` = ?
                                                               AND   `hostPageId`  = ?

                                                               LIMIT 1');

    $query->execute([$timeAdded, $hostImageId, $hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostImageToHostPage(int $hostImageId) {

    $query = $this->_db->prepare('DELETE FROM `hostImageToHostPage` WHERE `hostImageId` = ?');

    $query->execute([$hostImageId]);

    return $query->rowCount();
  }

  // Pages
  public function getTotalHostPages(int $hostId) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `hostId` = ?');

    $query->execute([$hostId]);

    return $query->fetch()->total;
  }

  public function getTotalPagesByHttpCode(mixed $httpCode) {

    if (is_null($httpCode)) {

      $query = $this->_db->query('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `httpCode` IS NULL');

    } else {

      $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE `httpCode` = ?');

      $query->execute([$httpCode]);

    }

    return $query->fetch()->total;
  }

  public function getTotalPages() {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `hostPage`');

    $query->execute();

    return $query->fetch()->total;
  }

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

  public function getHostPagesByLimit(int $hostId, int $limit) {

    $query = $this->_db->prepare('SELECT * FROM `hostPage` WHERE `hostId` = ? ORDER BY hostPageId DESC LIMIT ' . (int) $limit);

    $query->execute([$hostId]);

    return $query->fetchAll();
  }

  public function getFoundHostPage(int $hostPageId) {

    $query = $this->_db->prepare('SELECT `hostPage`.`metaTitle`,
                                         `hostPage`.`metaDescription`,
                                         `hostPage`.`data`,
                                         `hostPage`.`uri`,
                                         `hostPage`.`rank`,
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

  public function getFoundHostImage(int $hostImageId) {

    $query = $this->_db->prepare('SELECT `hostImage`.`uri`,
                                         `hostImage`.`rank`,
                                         `host`.`scheme`,
                                         `host`.`name`,
                                         `host`.`port`,

                                         (SELECT  GROUP_CONCAT(CONCAT_WS(" | ", `hostImageDescription`.`alt`, `hostImageDescription`.`title`))

                                            FROM  `hostImageDescription`
                                            WHERE `hostImageDescription`.`hostImageId` = `hostImage`.`hostImageId`) AS `description`

                                          FROM `hostImage`
                                          JOIN `host` ON (`host`.`hostId` = `hostImage`.`hostId`)

                                          WHERE `hostImage`.`hostImageId` = ?

                                          LIMIT 1');

    $query->execute([$hostImageId]);

    return $query->fetch();
  }

  public function addHostPage(int $hostId,
                              int $crc32uri,
                              string $uri,
                              int $timeAdded,
                              mixed $timeUpdated = null,
                              mixed $httpCode = null,
                              mixed $rank = null,
                              mixed $metaTitle = null,
                              mixed $metaDescription = null,
                              mixed $metaKeywords = null,
                              mixed $data = null) {

    $query = $this->_db->prepare('INSERT INTO `hostPage` (`hostId`,
                                                          `crc32uri`,
                                                          `uri`,
                                                          `timeAdded`,
                                                          `timeUpdated`,
                                                          `httpCode`,
                                                          `rank`,
                                                          `metaTitle`,
                                                          `metaDescription`,
                                                          `metaKeywords`,
                                                          `data`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $query->execute([$hostId, $crc32uri, $uri, $timeAdded, $timeUpdated, $httpCode, $rank, $metaTitle, $metaDescription, $metaKeywords, $data]);

    return $this->_db->lastInsertId();
  }

  public function updateHostPage( int $hostPageId,
                                  mixed $metaTitle,
                                  mixed $metaDescription,
                                  mixed $metaKeywords,
                                  mixed $data) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `metaTitle`       = ?,
                                                        `metaDescription` = ?,
                                                        `metaKeywords`    = ?,
                                                        `data`            = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$metaTitle, $metaDescription, $metaKeywords, $data, $hostPageId]);

    return $query->rowCount();
  }

  public function updateHostPageRank(int $hostId,
                                     int $crc32uri,
                                     int $increment) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET   `rank` = `rank` + ' . (int) $increment . '

                                                    WHERE `hostId` = ?
                                                    AND   `crc32uri` = ?

                                                    LIMIT 1');

    $query->execute([$hostId, $crc32uri]);

    return $query->rowCount();
  }

  public function deleteHostPage(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostPage` WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  public function deleteHostPageToHostImage(int $hostPageId) {

    $query = $this->_db->prepare('DELETE FROM `hostImageToHostPage` WHERE `hostPageId` = ?');

    $query->execute([$hostPageId]);

    return $query->rowCount();
  }

  // Cleaner tools
  public function getCleanerQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT * FROM `host`

                                           WHERE (`timeUpdated` IS NULL OR `timeUpdated` < ? ) AND `host`.`status` <> 0

                                           ORDER BY `hostId`

                                           LIMIT ' . (int) $limit);

    $query->execute([$timeFrom]);

    return $query->fetchAll();
  }

  // Crawl tools
  public function getCrawlQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT `hostPage`.`hostId`,
                                         `hostPage`.`hostPageId`,
                                         `hostPage`.`uri`,
                                         `host`.`scheme`,
                                         `host`.`name`,
                                         `host`.`port`,
                                         `host`.`crawlPageLimit`,
                                         `host`.`crawlImageLimit`,
                                         `host`.`crawlPageMetaOnly`,
                                         `host`.`robots`,
                                         `host`.`robotsPostfix`

                                          FROM `hostPage`
                                          JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                          WHERE (`hostPage`.`timeUpdated` IS NULL OR `hostPage`.`timeUpdated` < ? ) AND `host`.`status` <> 0

                                          ORDER BY `hostPage`.`hostPageId`

                                          LIMIT ' . (int) $limit);

    $query->execute([$timeFrom]);

    return $query->fetchAll();
  }

  public function updateCrawlQueue(string $hostPageId, int $timeUpdated, int $httpCode) {

    $query = $this->_db->prepare('UPDATE `hostPage` SET `timeUpdated` = ?, `httpCode` = ? WHERE `hostPageId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $hostPageId]);

    return $query->rowCount();
  }
}
