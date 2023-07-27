<?php

class Robots {

  private $_rule    = [];
  private $_sitemap = null;
  private $_data    = null;

  public function __construct(mixed $data) {

    $this->_data = $data;

    $read = false;

    foreach ((array) explode(PHP_EOL, (string) $data) as $row) {

      $row = strtolower(trim($row));

      // Parse sitemap address
      if (preg_match('!^sitemap:\s?(.*)!', $row, $matches)) {

        if (!empty($matches[1])) {

          $this->_sitemap = urldecode(trim($matches[1]));
        }
      }

      // User-agent * begin
      if (preg_match('!^user-agent:\s?\*!', $row)) {
        $read = true;
        continue;
      }

      if ($read) {
        $part = explode(' ', $row);

        if (isset($part[0]) && isset($part[1])) {

          if (false !== strpos($part[0], 'allow')) {
            $this->_rule[$this->_regex(trim($part[1]))] = true;
          }

          if (false !== strpos($part[0], 'disallow')) {
            $this->_rule[$this->_regex(trim($part[1]))] = false;
          }
        }
      }

      // User-agent * end
      if ($read && preg_match('!^user-agent:!', $row)) {
        break;
      }
    }
  }

  public function uriAllowed(string $uri) {

    // Unify case match
    $uri = strtolower(trim($uri));

    // Index by default
    $result = true;

    // Begin index rules by ASC priority
    foreach ($this->_rule as $rule => $value) {

      if (preg_match('!^' . $rule . '!', $uri)) {

        $result = $value;
      }
    }

    return $result;
  }

  /* @TODO not in use
  public function append(string $key, string $value) {

    if (!preg_match('!^user-agent:\s?\*!', strtolower(trim($this->_data)))) {

      $this->_data .= PHP_EOL . 'User-agent: *' . PHP_EOL;
    }

    if (false === stripos($this->_data, PHP_EOL . $key . ' ' . $value)) {

      $this->_data .= PHP_EOL . $key . ' ' . $value;
    }
  }
  */

  public function getData() {

    return $this->_data;
  }

  public function getSitemap() {

    return $this->_sitemap;
  }

  private function _regex(string $string) {

    return str_replace(
      [
        '*',
        '?',
        '+'
      ],
      [
        '.*',
        '\?',
        '\+'
      ],
      $string
    );
  }
}