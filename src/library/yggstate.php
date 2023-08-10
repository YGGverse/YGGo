<?php

class YGGstate {

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

  // Tools
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

  // Peer
  public function getPeersByMinLastUptime(int $time) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer`

                                           HAVING (
                                             SELECT   `peerSession`.`uptime`
                                             FROM     `peerSession`
                                             WHERE    `peerSession`.`peerId` = `peer`.`peerId`
                                             ORDER BY `peerSession`.`peerSessionId` DESC
                                             LIMIT 1
                                           ) >= ?');

    $query->execute([$time]);

    return $query->fetchAll();
  }
}
