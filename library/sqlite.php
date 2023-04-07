<?php

class SQLite {

  private PDO $_db;

  public function __construct(string $database, string $username, string $password) {

    $this->_db = new PDO('sqlite:' . $database, $username, $password);
    $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $this->_db->setAttribute(PDO::ATTR_TIMEOUT, 600);

    $this->_db->query('
      CREATE TABLE IF NOT EXISTS "page" (
        "pageId"	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        "crc32url"	INTEGER NOT NULL UNIQUE,
        "httpCode"	INTEGER,
        "timeAdded"	INTEGER NOT NULL,
        "timeUpdated"	INTEGER,
        "title"	TEXT,
        "data"	TEXT,
        "description"	TEXT,
        "keywords"	TEXT,
        "url"	TEXT NOT NULL
      )
    ');

    $this->_db->query('
      CREATE TABLE IF NOT EXISTS "image" (
        "imageId"	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        "crc32src"	INTEGER NOT NULL UNIQUE,
        "pageId"	INTEGER NOT NULL,
        "alt"	TEXT NOT NULL,
        "src"	TEXT NOT NULL
      )
    ');

    // FTS5
    $this->_db->query('
      CREATE VIRTUAL TABLE IF NOT EXISTS `ftsPage` USING fts5(`url`, `title`, `description`, `keywords`, `data`, tokenize=`unicode61`, content=`page`, content_rowid=`pageId`)
    ');

    $this->_db->query('
      CREATE TRIGGER IF NOT EXISTS `pageInsert` AFTER INSERT ON `page` BEGIN
        INSERT INTO ftsPage(`rowid`, `url`, `title`, `description`, `keywords`, `data`) VALUES (`new`.`pageId`, `new`.`url`, `new`.`title`, `new`.`description`, `new`.`keywords`, `new`.`data`);
      END
    ');

    $this->_db->query('
      CREATE TRIGGER IF NOT EXISTS `pageDelete` AFTER DELETE ON `page` BEGIN
        INSERT INTO ftsPage(`ftsPage`, `rowid`, `url`, `title`, `description`, `keywords`, `data`) VALUES ("delete", `old`.`pageId`, `old`.`url`, `old`.`title`, `old`.`description`, `old`.`keywords`, `old`.`data`);
      END
    ');

    $this->_db->query('
      CREATE TRIGGER IF NOT EXISTS `pageUpdate` UPDATE ON `page` BEGIN
        INSERT INTO ftsPage(`ftsPage`, `rowid`, `url`, `title`, `description`, `keywords`, `data`) VALUES ("delete", `old`.`pageId`, `old`.`url`, `old`.`title`, `old`.`description`, `old`.`keywords`, `old`.`data`);
        INSERT INTO ftsPage(`rowid`, `url`, `title`, `description`, `keywords`, `data`) VALUES (`new`.`pageId`, `new`.`url`, `new`.`title`, `new`.`description`, `new`.`keywords`, `new`.`data`);
      END
    ');
  }

  public function getTotalPagesByHttpCode(mixed $httpCode) {

    if (is_null($httpCode)) {

      $query = $this->_db->query('SELECT COUNT(*) AS `total` FROM `page` WHERE `httpCode` IS NULL');

    } else {

      $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `page` WHERE `httpCode` = ?');

      $query->execute([$httpCode]);

    }

    return $query->fetch()->total;
  }

  public function getTotalPages() {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `page`');

    $query->execute();

    return $query->fetch()->total;
  }

  public function updatePage(int $pageId, string $title, string $description, string $keywords, string $data, int $timeUpdated) {

    $query = $this->_db->prepare('UPDATE `page` SET `title` = ?, `description` = ?, `keywords` = ?, `data` = ?, `timeUpdated` = ? WHERE `pageId` = ?');

    $query->execute([$title, $description, $keywords, $data, $timeUpdated, $pageId]);

    return $query->rowCount();
  }

  public function addPage(string $title, string $description, string $keywords, string $data, int $timeAdded) {

    $query = $this->_db->prepare('INSERT INTO `page` (`title`, `description`, `keywords`, `data`, `timeAdded`) VALUES (?, ?, ?, ?, ?)');

    $query->execute([$title, $description, $keywords, $data, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function initPage(string $url, int $crc32url, int $timeAdded) {

    $query = $this->_db->prepare('INSERT OR IGNORE INTO `page` (`url`, `crc32url`, `timeAdded`) VALUES (?, ?, ?)');

    $query->execute([$url, $crc32url, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function addImage(int $pageId, string $src, int $crc32src, string $alt) {

    $query = $this->_db->prepare('INSERT OR IGNORE INTO `image` (`pageId`, `src`, `crc32src`, `alt`) VALUES (?, ?, ?, ?)');

    $query->execute([$pageId, $src, $crc32src, $alt]);

    return $this->_db->lastInsertId();
  }

  public function deleteImages(int $pageId) {

    $query = $this->_db->prepare('DELETE FROM `image` WHERE `pageId` = ?');

    $query->execute([$pageId]);

    return $query->rowCount();
  }

  public function getPageQueue(int $limit, int $timeFrom) {

    $query = $this->_db->prepare('SELECT * FROM `page` WHERE `timeUpdated` IS NULL OR `timeUpdated` < ? ORDER BY `pageId` LIMIT ' . (int) $limit);

    $query->execute([$timeFrom]);

    return $query->fetchAll();
  }

  public function updatePageQueue(string $pageId, int $timeUpdated, int $httpCode) {

    $query = $this->_db->prepare('UPDATE `page` SET `timeUpdated` = ?, `httpCode` = ? WHERE `pageId` = ? LIMIT 1');

    $query->execute([$timeUpdated, $httpCode, $pageId]);

    return $query->rowCount();
  }

  public function searchPages(string $q, int $start = 0, int $limit = 100) {

    $query = $this->_db->prepare('SELECT `title`, `description`, `url` FROM `ftsPage` WHERE `data` MATCH ? ORDER BY `rank` LIMIT ' . (int) $start . ',' . (int) $limit);

    $query->execute([$q]);

    return $query->fetchAll();
  }

  public function searchPagesTotal(string $q) {

    $query = $this->_db->prepare('SELECT COUNT(*) AS `total` FROM `ftsPage` WHERE `data` MATCH ?');

    $query->execute([$q]);

    return $query->fetch()->total;
  }
}
