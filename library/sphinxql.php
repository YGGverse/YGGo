<?php

class SphinxQL {

  private $_sphinx;

  public function __construct(string $host, int $port) {

    $this->_sphinx = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8', false, false, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_sphinx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_sphinx->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
  }

  public function searchHostPagesTotal(string $keyword, string $mime) {

    $query = $this->_sphinx->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE MATCH(?) AND `mime` = ?');

    $query->execute([$keyword, $mime]);

    return $query->fetch()->total;
  }

  public function searchHostPages(string $keyword, string $mime, int $start, int $limit, int $maxMatches) {

    $query = $this->_sphinx->prepare("SELECT *, WEIGHT() + `rank` * IF (`rank` > 0, 1000, 1) AS `priority`

                                      FROM `hostPage`

                                      WHERE MATCH(?) AND `mime` = ?

                                      ORDER BY `priority` DESC, WEIGHT() DESC

                                      LIMIT " . (int) ($start >= $maxMatches ? ($maxMatches > 0 ? $maxMatches - 1 : 0) : $start) . "," . (int) $limit . "

                                      OPTION `max_matches`=" . (int) ($maxMatches >= 1 ? $maxMatches : 1));

    $query->execute([$keyword, $mime]);

    return $query->fetchAll();
  }

  public function getHostPagesTotal() {

    $query = $this->_sphinx->prepare('SELECT COUNT(*) AS `total` FROM `hostPage`');

    $query->execute();

    return $query->fetch()->total;
  }

  public function getHostPagesMime() {

    $query = $this->_sphinx->prepare('SELECT `mime` FROM `hostPage` GROUP BY `mime` ORDER BY `mime` ASC');

    $query->execute();

    return $query->fetchAll();
  }

  public function searchHostPagesTotalByMime(string $keyword, string $mime) {

    $query = $this->_sphinx->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE MATCH(?) AND `mime` = ?');

    $query->execute([$keyword, $mime]);

    return $query->fetch()->total;
  }

  public function searchHostPagesMime(string $keyword) {

    $query = $this->_sphinx->prepare('SELECT `mime` FROM `hostPage` WHERE MATCH(?) GROUP BY `mime` ORDER BY `mime` ASC');

    $query->execute([$keyword]);

    return $query->fetchAll();
  }
}
