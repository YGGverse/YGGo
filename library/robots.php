<?php

// @TODO #2

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

  public function indexURL(string $url) {

    // Unify case match
    $url = strtolower(trim($url));

    // Convert to URI
    $url = str_replace(parse_url($url, PHP_URL_SCHEME) . '://' .
                       parse_url($url, PHP_URL_HOST) .
                       parse_url($url, PHP_URL_PORT),
                       '', $url);

    // Index by default
    $result = true;

    // Begin index rules by ASC priority
    foreach ($this->_rule as $rule => $value) {

      if (preg_match('!^' . $rule . '!', $url)) {

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