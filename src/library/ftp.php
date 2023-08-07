<?php

class Ftp {

  private $_connection;
  private $_passive;

  public function __construct(bool $passive = true) {

    $this->_passive = $passive;
  }

  public function connect(string $host,
                          int    $port,
                          mixed  $login = null,
                          mixed  $password = null,
                          string $directory = '/',
                          int    $timeout = 90,
                          bool   $passive = false) {

    if (!$this->_connection = ftp_connect($host, $port, $timeout)) {

      return false;
    }

    if (!empty($login) && !empty($password)) {

      if (!ftp_login($this->_connection, $login, $password)) {

        return false;
      }
    }

    if ($passive && !ftp_pasv($this->_connection, $this->_passive)) {

      return false;
    }

    return ftp_chdir($this->_connection, $directory);
  }

  public function delete(string $target) {

    return ftp_delete($this->_connection, $target);
  }

  public function copy(string $source, string $target) {

    return ftp_put($this->_connection, $target, $source);
  }

  public function get(string $source, string $target) {

    return ftp_get($this->_connection, $target, $source);
  }

  public function mkdir(string $name, bool $recursive = false) {

    if ($recursive) {

      $path = [];

      foreach ((array) explode('/', trim($name, '/')) as $directory) {

        $path[] = $directory;

        @ftp_mkdir($this->_connection, implode('/', $path));
      }

    } else {

      @ftp_mkdir($this->_connection, $name);
    }
  }

  public function size(string $target) {

    if (-1 !== $size = ftp_size($this->_connection, $target)) {

      return $size;

    }

    return false;
  }

  public function nlist(string $path) {

    return ftp_nlist($this->_connection, $path);
  }

  public function nlistr(string $path) {

    $result = [];

    foreach (ftp_nlist($this->_connection, $path) as $line) {

      if (ftp_size($this->_connection, $line) == -1) {

        $result = array_merge($result, $this->nlistr($line));

      } else{

        $result[] = $line;
      }
    }

    return $result;
  }

  public function close() {

    return ftp_close($this->_connection);
  }
}