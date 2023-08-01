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

    // Prepare user-friendly search request (default mode)
    // https://sphinxsearch.com/docs/current.html#extended-syntax
    if ($mode == 'default') {

      // Remove extra separators
      $query = preg_replace('/[\s]+/', ' ', $query);

      $query = trim($query);

      // Remove single char words
      $words = [];
      foreach ((array) explode(' ', $query) as $word) {

        if (mb_strlen($word) > 1) {
          $words[] = $word;
        }
      }

      if ($words) {
        $query = implode(' ', $words);
      }

      // Quote reserved keyword operators
      $operators = [
        'MAYBE',
        'AND',
        'OR',
        'NOT',
        'SENTENCE',
        'NEAR',
        'ZONE',
        'ZONESPAN',
        'PARAGRAPH',

        '\\', '/', '~', '@', '!', '"', '(', ')', '[', ']', '|', '?', '%', '-', '>', '<', ':', ';', '^', '$'
      ];

      foreach ($operators as $operator) {
        $query = str_ireplace($operator, '\\' . $operator,  $query);
      }
    }

    // Apply query semantics

    // Long queries
    // @TODO seems that queries longer than 68 chars cropping
    if (mb_strlen($query) > 68) {

      $query = sprintf('%s*', substr($query, 0, 67));

    } else {

      $query = sprintf('"%s" | (%s)', $query, str_replace(' ', '* MAYBE ', $query) . '*');
    }

    return trim($query);
  }

  static public function plural(int $number, array $texts) {

    $cases = array (2, 0, 1, 1, 1, 2);

    return $texts[(($number % 100) > 4 && ($number % 100) < 20) ? 2 : $cases[min($number % 10, 5)]];
  }
}