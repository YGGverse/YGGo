<?php

require_once(__DIR__ . '/../library/robots.php');
require_once __DIR__ . '/../../vendor/autoload.php';

class Helper {

  public static function getHostSetting(MySQL $db,
                                        Memcached $memcached,
                                        int $hostId,
                                        string $key,
                                        mixed $defaultValue) : mixed {

    if ($value = $memcached->get(sprintf('Helper.getHostSetting.%s.%s', $hostId, $key))) {

      return $value;
    }

    if (!$value = $db->findHostSettingValue($hostId, $key)) {

         $value = $defaultValue;
    }

    $memcached->set(sprintf('Helper.getHostSetting.%s.%s', $hostId, $key), $value, time() + 3600);

    return $value;
  }

  public static function setHostSetting(MySQL $db,
                                        Memcached $memcached,
                                        int $hostId,
                                        string $key,
                                        mixed $value) : int {

    if ($hostSetting = $db->findHostSetting($hostId, $key)) {

      $rowsAffected = $db->updateHostSetting($hostSetting->hostSettingId, $value, time());

    } else {

      $rowsAffected = $db->addHostSetting($hostId, $key, $value, time());
    }

    $memcached->set(sprintf('Helper.getHostSetting.%s.%s', $hostId, $key), $value, time() + 3600);

    return $rowsAffected;
  }

  public static function addLinkToDB(MySQL $db, Memcached $memcached, string $link) : mixed {

    // Define variables
    $result = (object)
    [
      'new' => (object)
      [
        'hostId'     => [],
        'hostPageId' => [],
      ],
      'old' => (object)
      [
        'hostId'     => [],
        'hostPageId' => [],
      ],
    ];

    // Validate DB connection
    if (!$db) {

      return false;
    }

    // Validate link URL
    if (!$link = Yggverse\Parser\Url::parse($link)) {

      return false;
    }

    // Init host
    if ($host = $db->findHostByCRC32URL(crc32($link->host->url))) {

      // Make sure host URL compatible with this host rules before continue
      if (!preg_match(self::getHostSetting($db, $memcached, $host->hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP), $link->host->url)) {

        return false;
      }

      $hostId = $host->hostId;

      $result->old->hostId[] = $host->hostId;

    } else {

      // Make sure link compatible with default host rules before create new host
      if (!preg_match(DEFAULT_HOST_URL_REGEXP, $link->host->url)) {

        return false;
      }

      // Register new host
      if ($hostId = $db->addHost($link->host->scheme, $link->host->name, $link->host->port, crc32($link->host->url), time())) {

        $result->new->hostId[] = $hostId;

        // Init required for app web root page
        if ($link->page->uri != '/') {

          if ($hostPageId = $db->addHostPage($hostId, crc32('/'), '/', time())) {

            // Note: commented because of referrer link registration implemented out of this method
            // $result->new->hostPageId[] = $hostPageId;
          }
        }

      } else {

        return false;
      }
    }

    // URI correction
    if (empty($link->page->uri)) {

      $link->page->uri = '/';
    }

    // Add host page if not exists
    if ($hostPage = $db->findHostPageByCRC32URI($hostId, crc32($link->page->uri))) {

      $result->old->hostPageId[] = $hostPage->hostPageId;

    } else {

      // Make sure host page URL compatible with this host rules before continue
      if (!preg_match(self::getHostSetting($db, $memcached, $hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP), $link->page->url)) {

        return false;
      }

      // Validate page limits for this host
      if ($db->getTotalHostPages($hostId) >= self::getHostSetting($db, $memcached, $hostId, 'PAGES_LIMIT', DEFAULT_HOST_PAGES_LIMIT)) {

        return false;
      }

      // Validate ROBOTS.TXT
      $robots = new Robots(
        self::getHostSetting($db, $memcached, $hostId, 'ROBOTS_TXT', NULL) . PHP_EOL .
        self::getHostSetting($db, $memcached, $hostId, 'ROBOTS_TXT_POSTFIX', DEFAULT_HOST_ROBOTS_TXT_POSTFIX)
      );

      if (!$robots->uriAllowed($link->page->uri)) {

        return false;
      }

      // Validate host page MIME
      // Note: passed to the crawl queue to prevent extra-curl requests

      // Add host page
      if ($hostPageId = $db->addHostPage($hostId, crc32($link->page->uri), $link->page->uri, time())) {

        $result->new->hostPageId[] = $hostPageId;

      } else {

        return false;
      }
    }

    return $result;
  }

  // Cache host setting requests
}