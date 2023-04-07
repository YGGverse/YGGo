<?php

class Parser {

  static public function hostURL(string $string) {

    $result = [
      'string' => null,
      'scheme' => null,
      'name'   => null,
      'port'   => null,
    ];

    if ($hostScheme = parse_url($string, PHP_URL_SCHEME)) {

      $result['string'] = $hostScheme . '://';

      $result['scheme'] = $hostScheme;

    } else {

      return false;
    }

    if ($hostName = parse_url($string, PHP_URL_HOST)) {

      $result['string'] .= $hostName;

      $result['name'] = $hostName;

    } else {

      return false;
    }

    if ($hostPort = parse_url($string, PHP_URL_PORT)) {

      $result['string'] .= ':' . $hostPort;

      $result['port'] = $hostPort;

    }

    return (object) $result;
  }

  static public function uri(string $string) {

    $result = [
      'string' => '/',
      'path'   => '/',
      'query'  => null,
    ];

    if ($path = parse_url($string, PHP_URL_PATH)) {

      $result['string'] = $path;

      $result['path'] = $path;

    }

    if ($query = parse_url($string, PHP_URL_QUERY)) {

      $result['string'] .= '?' . $query;

      $result['query'] = '?' . $query;

    }

    return (object) $result;
  }
}