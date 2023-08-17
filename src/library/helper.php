<?php

require_once(__DIR__ . '/../library/robots.php');
require_once __DIR__ . '/../../vendor/autoload.php';

class Helper {

  public static function getHostSettingValue(MySQL $db,
                                        Yggverse\Cache\Memory $memory,
                                        int $hostId,
                                        string $key,
                                        mixed $defaultValue) : mixed {

    if (false !== $value = $memory->getByMethodCallback(
      $db, 'findHostSettingValue', [$hostId, $key], time() + 3600
    )) {

      return $value;

    } else {

      return $defaultValue;
    }
  }

  public static function setHostSetting(MySQL $db,
                                        int $hostId,
                                        string $key,
                                        mixed $value) : int {

    if ($hostSetting = $db->findHostSetting($hostId, $key)) {

      return $db->updateHostSetting($hostSetting->hostSettingId, $value, time());

    } else {

      return $db->addHostSetting($hostId, $key, $value, time());
    }

    // @TODO update cache
  }

  public static function addLinkToDB(MySQL $db, Yggverse\Cache\Memory $memory, string $link) : mixed {

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
      if (!preg_match(self::getHostSettingValue($db, $memory, $host->hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP), $link->page->url)) {

        return false;
      }

      $hostId = $host->hostId;

      $result->old->hostId[] = $host->hostId;

    } else {

      // Make sure link compatible with default host rules before create new host
      if (!preg_match(DEFAULT_HOST_URL_REGEXP, $link->page->url)) {

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
      if (!preg_match(self::getHostSettingValue($db, $memory, $hostId, 'URL_REGEXP', DEFAULT_HOST_URL_REGEXP), $link->page->url)) {

        return false;
      }

      // Validate page limits for this host
      if ($db->getTotalHostPages($hostId) >= self::getHostSettingValue($db, $memory, $hostId, 'PAGES_LIMIT', DEFAULT_HOST_PAGES_LIMIT)) {

        return false;
      }

      // Validate ROBOTS.TXT
      $robots = new Robots(
        self::getHostSettingValue($db, $memory, $hostId, 'ROBOTS_TXT', NULL) . PHP_EOL .
        self::getHostSettingValue($db, $memory, $hostId, 'ROBOTS_TXT_POSTFIX', DEFAULT_HOST_ROBOTS_TXT_POSTFIX)
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