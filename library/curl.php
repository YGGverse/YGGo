<?php

class Curl {

  private $_connection;

  public function __construct(string $url) {

    $this->_connection = curl_init($url);

    curl_setopt($this->_connection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->_connection, CURLOPT_TIMEOUT, 5);

    curl_exec($this->_connection);
  }

  public function __destruct() {

    curl_close($this->_connection);
  }

  public function getError() {

    if (curl_errno($this->_connection)) {

      return curl_errno($this->_connection);

    } else {

      return false;
    }
  }

  public function getCode() {

    return curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);

  }

  public function getContent() {

    return curl_exec($this->_connection);
  }
}