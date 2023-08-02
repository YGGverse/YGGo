<?php

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/curl.php');
require_once(__DIR__ . '/../library/robots.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/parser.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/sphinxql.php');

// Connect Sphinx search server
$sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Filter request data
$t = !empty($_GET['t']) ? Filter::url($_GET['t']) : 'text';
$m = !empty($_GET['m']) ? Filter::url($_GET['m']) : 'default';
$q = !empty($_GET['q']) ? Filter::url($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Search request
$resultsTotal = $sphinx->searchHostPagesTotal(Filter::searchQuery($q, $m), $t);
$results      = $sphinx->searchHostPages(Filter::searchQuery($q, $m), $t, $p * WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, $resultsTotal);

// Mime list
$hostPagesMime = $sphinx->searchHostPagesMime(Filter::searchQuery($q, $m));

// Define page basics
$totalPages = $sphinx->getHostPagesTotal();


$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'),  $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            ]);

// Crawl request
if (filter_var($q, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $q)) {

  $db->beginTransaction();

  try {

    // Parse host info
    if ($hostURL = Parser::hostURL($q)) {

      // Host exists
      if ($host = $db->getHost(crc32($hostURL->string))) {

        $hostStatus        = $host->status;
        $hostNsfw          = $host->nsfw;
        $hostPageLimit     = $host->crawlPageLimit;
        $hostMetaOnly      = $host->crawlMetaOnly;
        $hostId            = $host->hostId;
        $hostRobots        = $host->robots;
        $hostRobotsPostfix = $host->robotsPostfix;

      // Register new host
      } else {

        // Disk quota not reached
        if (CRAWL_STOP_DISK_QUOTA_MB_LEFT < disk_free_space('/') / 1000000) {

          // Get robots.txt if exists
          $curl = new Curl($hostURL->string . '/robots.txt', CRAWL_CURLOPT_USERAGENT);

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = null;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS ? 1 : 0;
          $hostNsfw      = CRAWL_HOST_DEFAULT_NSFW ? 1 : 0;
          $hostMetaOnly  = CRAWL_HOST_DEFAULT_META_ONLY ? 1 : 0;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;

          $hostId = $db->addHost( $hostURL->scheme,
                                  $hostURL->name,
                                  $hostURL->port,
                                  crc32($hostURL->string),
                                  time(),
                                  null,
                                  $hostPageLimit,
                                  (string) $hostMetaOnly,
                                  (string) $hostStatus,
                                  (string) $hostNsfw,
                                  $hostRobots,
                                  $hostRobotsPostfix);

          // Add web root host page to make host visible in the crawl queue
          $db->addHostPage($hostId, crc32('/'), '/', time());
        }
      }

      // Parse page URI
      $hostPageURI = Parser::uri($q);

      // Init robots parser
      $robots = new Robots((!$hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . (string) $hostRobotsPostfix);

      // Save page info
      if ($hostStatus && // host enabled
          $robots->uriAllowed($hostPageURI->string) && // page allowed by robots.txt rules
          $hostPageLimit > $db->getTotalHostPages($hostId) && // pages quantity not reached host limit
         !$db->findHostPageByCRC32URI($hostId, crc32($hostPageURI->string))) {  // page not exists

          $db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time());
      }
    }

    $db->commit();

  } catch(Exception $e){

    var_dump($e);

    $db->rollBack();
  }
}

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo (empty($q) ? _('Empty request - YGGo!') : ($p > 1 ? sprintf(_('%s - #%s - YGGo!'), htmlentities($q), $p) : sprintf(_('%s - YGGo!'), htmlentities($q)))) ?></title>
    <meta charset="utf-8" />
    <meta name="description" content="<?php echo _('Javascript-less Open Source Web Search Engine') ?>" />
    <meta name="keywords" content="<?php echo _('web, search, engine, crawler, php, pdo, mysql, sphinx, yggdrasil, js-less, open source') ?>" />
    <style>

      * {
        border: 0;
        margin: 0;
        padding: 0;
        font-family: Sans-serif;
        color: #ccc;
      }

      body {
        background-color: #2e3436;
      }

      header {
        background-color: #34393b;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
      }

      main {
        margin-top: 110px;
        margin-bottom: 76px;
        padding: 0 20px;
      }

      h1 {
        position: fixed;
        top: 8px;
        left: 24px;
      }

      h1 > a,
      h1 > a:visited,
      h1 > a:active,
      h1 > a:hover {
        color: #fff;
        font-weight: normal;
        font-size: 24px;
        margin: 10px 0;
        text-decoration: none;
      }

      h2 {
        display: block;
        font-size: 16px;
        font-weight: normal;
        margin: 4px 0;
        color: #fff;
      }

      form {
        display: block;
        max-width: 678px;
        margin: 0 auto;
        text-align: center;
      }

      input {
        width: 100%;
        margin: 12px 0;
        padding: 10px 0;
        border-radius: 32px;
        background-color: #000;
        color: #fff;
        font-size: 16px;
        text-align: center;
      }

      input:hover {
        background-color: #111
      }

      input:focus {
        outline: none;
        background-color: #111
      }

      input:focus::placeholder {
        color: #090808
      }

      label {
        font-size: 14px;
        color: #fff;
        float: left;
        margin-left: 16px;
        margin-bottom: 14px;
      }

      label > input {
        width: auto;
        margin: 0 4px;
      }

      button {
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
        position: fixed;
        top: 15px;
        right: 24px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      a, a:visited, a:active {
        color: #9ba2ac;
        display: inline-block;
        font-size: 12px;
        margin-top: 8px;
      }

      a:hover {
        color: #54a3f7;
      }

      img.icon {
        float: left;
        border-radius: 50%;
        margin-right: 8px;
      }

      img.image {
        max-width: 100%;
        border-radius: 3px;
      }

      div {
        max-width: 640px;
        margin: 0 auto;
        padding: 16px 0;
        border-top: 1px #000 dashed;
        font-size: 14px
      }

      span {
        display: block;
        margin: 8px 0;
      }

      p {
        margin: 16px 0;
        text-align: right;
        font-size: 11px;
      }

      p > a, p > a:visited, p > a:active {
        font-size: 11px;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><a href="<?php echo WEBSITE_DOMAIN; ?>"><?php echo _('YGGo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="<?php echo htmlentities($q) ?>" />
        <?php foreach ($hostPagesMime as $hostPageMime) { ?>
          <label title="<?php $searchHostPagesTotalByMime = $sphinx->searchHostPagesTotalByMime(Filter::searchQuery($q, $m), $hostPageMime->mime);
                              echo Filter::plural($searchHostPagesTotalByMime, [sprintf(_('%s result'),  $searchHostPagesTotalByMime),
                                                                                sprintf(_('%s results'), $searchHostPagesTotalByMime),
                                                                                sprintf(_('%s results'), $searchHostPagesTotalByMime)]) ?>">
          <input type="radio" name="t" value="<?php echo $hostPageMime->mime ?>" <?php echo ($t == $hostPageMime->mime ? 'checked="checked"' : false) ?>/> <?php echo $hostPageMime->mime ?>
        </label>
        <?php } ?>
        <button type="submit"><?php echo _('Search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($results) { ?>
        <div>
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($queueTotal = $db->getHostPageCrawlQueueTotal(time() - CRAWL_PAGE_SECONDS_OFFSET, time() - CRAWL_PAGE_HOME_SECONDS_OFFSET)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
        <?php foreach ($results as $result) { ?>
          <?php if ($hostPage = $db->getFoundHostPage($result->id)) { ?>
            <div>
              <?php if ($hostPageDescription = $db->getLastPageDescription($result->id)) { ?>
                <?php if (!empty($hostPageDescription->title)) { ?>
                  <h2><?php echo $hostPageDescription->title ?></h2>
                <?php } ?>
                <?php if (!empty($hostPageDescription->description)) { ?>
                  <span><?php echo $hostPageDescription->description ?></span>
                <?php } ?>
                <?php if (!empty($hostPageDescription->keywords)) { ?>
                  <span><?php echo $hostPageDescription->keywords ?></span>
                <?php } ?>
              <?php } ?>
              <a href="<?php echo $hostPage->hostPageURL ?>">
                <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode($hostPage->name) ?>" alt="identicon" width="16" height="16" class="icon" />
                <?php echo htmlentities(urldecode($hostPage->hostURL) . (mb_strlen(urldecode($hostPage->uri)) > 28 ? '...' . mb_substr(urldecode($hostPage->uri), -28) : urldecode($hostPage->uri))) ?>
              </a>
              |
              <a href="<?php echo WEBSITE_DOMAIN; ?>/explore.php?hp=<?php echo $result->id ?>">
                <?php echo _('explore'); ?>
              </a>
            </div>
          <?php } ?>
        <?php } ?>
        <?php if ($p * WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT <= $resultsTotal) { ?>
          <div>
            <a href="<?php echo WEBSITE_DOMAIN; ?>/search.php?q=<?php echo urlencode(htmlentities($q)) ?>&t=<?php echo $t ?>&m=<?php echo $m ?>&p=<?php echo $p + 1 ?>"><?php echo _('Next page') ?></a>
          </div>
          <?php } ?>
      <?php } else { ?>
        <div style="text-align:center">
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($q && $queueTotal = $db->getHostPageCrawlQueueTotal(time() - CRAWL_PAGE_SECONDS_OFFSET, time() - CRAWL_PAGE_HOME_SECONDS_OFFSET)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>