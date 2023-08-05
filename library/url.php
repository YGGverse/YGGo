<?php

class URL {

  public static function is(string $url) : bool {

    return filter_var($url, FILTER_VALIDATE_URL);
  }

  public static function parse(string $url) : mixed {

    $result = (object)
    [
      'host' => (object)
      [
        'url'    => null,
        'scheme' => null,
        'name'   => null,
        'port'   => null,
      ],
      'page' => (object)
      [
        'url'   => null,
        'uri'   => null,
        'path'  => null,
        'query' => null,
      ]
    ];

    // Validate URL
    if (!self::is($url)) {

      return false;
    }

    // Parse host
    if ($scheme = parse_url($url, PHP_URL_SCHEME)) {

      $result->host->url    = $scheme . '://';
      $result->host->scheme = $scheme;

    } else {

      return false;
    }

    if ($host = parse_url($url, PHP_URL_HOST)) {

      $result->host->url .= $host;
      $result->host->name = $host;

    } else {

      return false;
    }

    if ($port = parse_url($url, PHP_URL_PORT)) {

      $result->host->url .= ':' . $port;
      $result->host->port = $port;

      // port is optional
    }

    // Parse page
    if ($path = parse_url($url, PHP_URL_PATH)) {

      $result->page->uri  = $path;
      $result->page->path = $path;
    }

    if ($query = parse_url($url, PHP_URL_QUERY)) {

      $result->page->uri  .= '?' . $query;
      $result->page->query = '?' . $query;
    }

    $result->page->url = $result->host->url . $result->page->uri;

    return $result;
  }
}