<?php

class Filter {

  static public function url(string $url) {

    return trim(urldecode($url));
  }

  static public function pageTitle(string $title) {

    $title = preg_replace('/[\s]+/', ' ', $title);

    $title = trim($title);

    return $title;
  }

  static public function pageDescription(string $description) {

    $description = preg_replace('/[\s]+/', ' ', $description);

    $description = trim($description);

    return $description;
  }

  static public function pageKeywords(string $keywords) {

    $keywords = preg_replace('/[\s]+/', ' ', $keywords);

    $keywords = trim($keywords);

    return $keywords;
  }

  static public function pageData(string $data) {

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
        $query = str_replace(['\\', '/', '~', '@', '!', '"', '(', ')'], ['\\\\', '\/', '\~', '\@', '\!', '\"', '\(', '\)'], $query);
    }

    $query = trim($query);

    return $query;
  }

  static public function plural(int $number, array $texts) {

    $cases = array (2, 0, 1, 1, 1, 2);

    return $texts[(($number % 100) > 4 && ($number % 100) < 20) ? 2 : $cases[min($number % 10, 5)]];
  }
}