<?php

// Lock multi-thread execution
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo 'Process locked by another thread.' . PHP_EOL;
  exit;
}

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/filter.php');
require_once('../library/parser.php');
require_once('../library/mysql.php');

// Check disk quota
if (CRAWL_STOP_DISK_QUOTA_MB_LEFT > disk_free_space('/') / 1000000) {

  echo 'Disk quota reached.' . PHP_EOL;
  exit;
}

// Debug
$timeStart = microtime(true);

$hostPagesProcessed = 0;
$hostPagesIndexed   = 0;
$hostPagesAdded     = 0;
$hostsAdded         = 0;

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Process crawl queue
foreach ($db->getCrawlQueue(CRAWL_PAGE_LIMIT, time() - CRAWL_PAGE_SECONDS_OFFSET) as $queueHostPage) {

  // Build URL from the DB
  $queueHostPageURL = $queueHostPage->scheme . '://' . $queueHostPage->name . ($queueHostPage->port ? ':' . $queueHostPage->port : false) . $queueHostPage->uri;

  $curl = new Curl($queueHostPageURL);

  // Update page index anyway, with the current time and http code
  $hostPagesProcessed += $db->updateCrawlQueue($queueHostPage->hostPageId, time(), $curl->getCode());

  // Skip next page processing non 200 code
  if (200 != $curl->getCode()) {

    continue;
  }

  // Skip next page processing pages without returned data
  if (!$content = $curl->getContent()) {

    continue;
  }

  // Grab page content
  $dom = new DomDocument();

  @$dom->loadHTML($content);

  // Skip index page links without titles
  $title = @$dom->getElementsByTagName('title');

  if ($title->length == 0) {
    continue;
  }

  // Get optional page meta data
  $metaDescription = '';
  $metaKeywords    = '';
  $metaRobots      = '';

  foreach (@$dom->getElementsByTagName('meta') as $meta) {

    if (@$meta->getAttribute('name') == 'description') {
      $metaDescription = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'keywords') {
      $metaKeywords = @$meta->getAttribute('content');
    }

    if (@$meta->getAttribute('name') == 'robots') {
      $metaRobots = @$meta->getAttribute('content');
    }
  }

  // Update queued page data
  $hostPagesIndexed += $db->updateHostPage($queueHostPage->hostPageId,
                                           Filter::pageTitle($title->item(0)->nodeValue),
                                           Filter::pageDescription($metaDescription),
                                           Filter::pageKeywords($metaKeywords),
                                           CRAWL_HOST_DEFAULT_META_ONLY ? null : Filter::pageData($content));

  // Append page with meta robots:noindex value to the robotsPostfix disallow list
  if (false !== stripos($metaRobots, 'noindex')) {

    $robots        = new Robots($queueHostPage->robots);
    $robotsPostfix = new Robots($queueHostPage->robotsPostfix);

    // Ignore URI if does not match existing rules yet
    if ($robotsPostfix->uriAllowed($queueHostPage->uri) &&
        $robots->uriAllowed($queueHostPage->uri)) {

      $robotsPostfix->append('Disallow:', $queueHostPage->uri);

      $db->updateHostRobotsPostfix($queueHostPage->hostId, $robotsPostfix->getData(), time());
    }
  }

  // Skip page links following by robots:nofollow attribute detected
  if (false !== stripos($metaRobots, 'nofollow')) {

    continue;
  }

  // Collect internal links from page content
  foreach(@$dom->getElementsByTagName('a') as $a) {

    // Skip links without required attribute
    if (!$href = @$a->getAttribute('href')) {

      continue;
    }

    // Skip anchor links
    if (false !== strpos($href, '#')) {

      continue;
    }

    // Skip javascript links
    if (false !== strpos($href, 'javascript:')) {

      continue;
    }

    // Skip mailto links
    if (false !== strpos($href, 'mailto:')) {

      continue;
    }

    // Skip x-raw-image links
    if (false !== strpos($href, 'x-raw-image:')) {

      continue;
    }

    // @TODO skip other apps

    // Add absolute URL prefixes to the relative links found
    if (!parse_url($href, PHP_URL_HOST)) {

      $href = $queueHostPage->scheme . '://' .
              $queueHostPage->name .
             ($queueHostPage->port ? ':' . $queueHostPage->port : '') .
             '/' . trim(ltrim(str_replace(['./', '../'], '', $href), '/'), '.');
    }

    // Validate formatted link
    if (filter_var($href, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $href)) {

      $db->beginTransaction();

      try {

        // Parse formatted link
        $hostURL     = Parser::hostURL($href);
        $hostPageURI = Parser::uri($href);

        // Host exists
        if ($host = $db->getHost(crc32($hostURL->string))) {

          $hostStatus        = $host->status;
          $hostPageLimit     = $host->crawlPageLimit;
          $hostId            = $host->hostId;
          $hostRobots        = $host->robots;
          $hostRobotsPostfix = $host->robotsPostfix;

          // Increase page rank when link does not match the current host
          if ($hostURL->scheme . '://' .
              $hostURL->name .
             ($hostURL->port ? ':' . $hostURL->port : '')
              !=
              $queueHostPage->scheme . '://' .
              $queueHostPage->name .
             ($queueHostPage->port ? ':' . $queueHostPage->port : '')) {

              $db->updateHostPageRank($hostId, crc32($hostPageURI->string), 1);
          }

        // Register new host
        } else {

          // Get robots.txt if exists
          $curl = new Curl($hostURL->string . '/robots.txt');

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = CRAWL_ROBOTS_DEFAULT_RULES;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;
          $hostId        = $db->addHost($hostURL->scheme,
                                        $hostURL->name,
                                        $hostURL->port,
                                        crc32($hostURL->string),
                                        time(),
                                        null,
                                        $hostPageLimit,
                                        (string) CRAWL_HOST_DEFAULT_META_ONLY,
                                        (string) $hostStatus,
                                        $hostRobots,
                                        $hostRobotsPostfix);

          if ($hostId) {

            $hostsAdded++;

          } else {

            continue;
          }
        }

        // Init robots parser
        $robots = new Robots((!$hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . (string) $hostRobotsPostfix);

        // Save page info
        if ($hostStatus && // host enabled
            $robots->uriAllowed($hostPageURI->string) && // page allowed by robots.txt rules
            $hostPageLimit > $db->getTotalHostPages($hostId) && // pages quantity not reached host limit
            !$db->getHostPage($hostId, crc32($hostPageURI->string))) {  // page not exists

            if ($db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time())) {

              $hostPagesAdded++;
            }
        }

        $db->commit();

      } catch(Exception $e){

        var_dump($e);

        $db->rollBack();
      }
    }
  }
}

// Debug
echo 'Pages processed: ' . $hostPagesProcessed . PHP_EOL;
echo 'Pages indexed: ' . $hostPagesIndexed . PHP_EOL;
echo 'Pages added: ' . $hostPagesAdded . PHP_EOL;
echo 'Hosts added: ' . $hostsAdded . PHP_EOL;
echo 'Total time: ' . microtime(true) - $timeStart . PHP_EOL;
