<?php

class Robots {

  private $_rule = [];

  public function __construct(string $data) {

    $read = false;

    foreach ((array) explode(PHP_EOL, $data) as $row) {

      $row = strtolower(trim($row));

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

  private function _regex(string $string) {

    return str_replace(
      [
        '*',
        '?'
      ],
      [
        '.*',
        '\?'
      ],
      $string
    );
  }
}