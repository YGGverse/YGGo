<?php

class Filter {

  static public function string(mixed $data) {

    return (string) $data;
  }

  static public function url(mixed $url) {

    $url = (string) $url;

    return trim(urldecode($url));
  }

  static public function mime(mixed $mime) {

    $mime = (string) $mime;

    return trim(strtolower($mime));
  }

  static public function pageTitle(mixed $title) {

    $title = (string) $title;

    $title = preg_replace('/[\s]+/', ' ', $title);

    $title = trim($title);

    return $title;
  }

  static public function pageDescription(mixed $description) {

    $description = (string) $description;

    $description = preg_replace('/[\s]+/', ' ', $description);

    $description = trim($description);

    return $description;
  }

  static public function pageKeywords(mixed $keywords) {

    $keywords = (string) $keywords;

    $keywords = preg_replace('/[\s]+/', ' ', $keywords);

    $keywords = trim($keywords);

    return $keywords;
  }

  static public function pageData(mixed $data) {

    $data = (string) $data;

    $filterDataPre = [
      '/<script.*?\/script>/s',
      '/<style.*?\/style>/s'
    ];

    $filterDataPost = [
      '/[\s]{2,}/',
    ];

    $data = preg_replace($filterDataPre, ' ', $data);

    $data = html_entity_decode($data);
    $data = strip_tags($data);

    $data = preg_replace($filterDataPost, ' ', $data);

    return $data;
  }

  static public function searchQuery(string $query, string $mode = 'default') {

    if ($mode == 'default') {

        // Remove SphinxQL special chars
        $query = str_replace(['\\', '/', '~', '@', '!', '"', '(', ')'], ['\\\\', '\/', '\~', '\@', '\!', '\"', '\(', '\)'], $query);

        // Replace query separators to the MAYBE operator
        $query = str_replace(['.', ',', ':', ';'], ' ', $query);
        $query = preg_replace('/[\s]+/', ' ', $query);
        $query = str_replace(' ', ' MAYBE ', $query);
    }

    $query = trim($query);

    return $query;
  }

  static public function plural(int $number, array $texts) {

    $cases = array (2, 0, 1, 1, 1, 2);

    return $texts[(($number % 100) > 4 && ($number % 100) < 20) ? 2 : $cases[min($number % 10, 5)]];
  }
}