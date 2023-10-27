<?php

class Filter {

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

  static public function searchQuery(string $query, string $mode = 'default') {

    // Create query CRC32
    $crc32query = crc32($query);

    // Prepare user-friendly search request (default mode)
    // https://sphinxsearch.com/docs/current.html#extended-syntax
    if ($mode == 'default') {

      // Remove extra separators
      $query = preg_replace('/[\s]+/', ' ', $query);

      $query = trim($query);

      // Return short or empty queries
      if (mb_strlen($query) <= 1) {

        return false;
      }

      // Lowercase query to deactivate reserved operators
      $query = mb_strtolower($query);

      // Quote other operators
      $operators = [
        /* lowercased
        'MAYBE',
        'AND',
        'OR',
        'NOT',
        'SENTENCE',
        'NEAR',
        'ZONE',
        'ZONESPAN',
        'PARAGRAPH',
        */
        '\\', '/', '~', '@', '!', '"', "'", '(', ')', '[', ']', '|', '?', '%', '-', '>', '<', ':', ';', '^', '$'
      ];

      foreach ($operators as $operator) {
        $query = str_ireplace($operator, '\\' . $operator,  $query);
      }

      // Apply separators
      $query = str_replace(['-', '_', '/'], ' ', $query);

      // Apply query MATCH rules
      if (false !== strpos($query, '\:\ \ ')) { // URL request

        $query = sprintf('"%s"', $crc32query);

      } else if (mb_strlen($query) > 68) { // @TODO Queries longer than 68 chars unreachable in search index

        $query = sprintf('"%s" | (%s*)', $crc32query, substr($query, 0, 67));

      } else { // Default condition

        $words = [];

        // Remove single char words
        foreach ((array) explode(' ', $query) as $word) {

          if (mb_strlen($word) > 1) {

            $words[] = sprintf('%s*', $word);

            foreach (self::_generateWordForms($word) as $wordForm)
            {
              $words[] = sprintf('(*%s*)', $wordForm);
            }
          }
        }

        $query = sprintf('@title %s | "%s" | (%s)', $query,
                                                    $crc32query,
                                                    implode(' | ', $words));
      }
    }

    return trim($query);
  }

  static public function plural(int $number, array $texts) {

    $cases = array (2, 0, 1, 1, 1, 2);

    return $texts[(($number % 100) > 4 && ($number % 100) < 20) ? 2 : $cases[min($number % 10, 5)]];
  }

  // Word forms generator to improve search results
  // e.g. transliteration rules for latin filenames
  private static function _generateWordForms(
    string $keyword,
    // #13 supported locales:
    // https://github.com/ashtokalo/php-translit
    array  $transliteration = [
      'be',
      'bg',
      'el',
      'hy',
      'kk',
      'mk',
      'ru',
      'ka',
      'uk'
    ],
    // Additional char forms
    array $charForms =
    [
      'c' => 'k',
      'k' => 'c',
    ]
  ): array
  {
    $wordForms = [];

    // Apply transliteration
    foreach ($transliteration as $locale)
    {
      $wordForms[] = \ashtokalo\translit\Translit::object()->convert(
        $keyword,
        $locale
      );
    }

    // Apply char forms
    foreach ($wordForms as $wordForm)
    {
      foreach ($charForms as $from => $to)
      {
        $wordForms[] = str_replace(
          $from,
          $to,
          $wordForm
        );
      }
    }

    // Remove duplicates
    return array_unique(
      $wordForms
    );
  }
}