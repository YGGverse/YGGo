<?php

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/helper.php');
require_once(__DIR__ . '/../library/sphinxql.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

// Connect Sphinx search server
try {

  $sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect Yggverse\Cache\Memory
try {

  $memory = new Yggverse\Cache\Memory(MEMCACHED_HOST, MEMCACHED_PORT, MEMCACHED_NAMESPACE, MEMCACHED_TIMEOUT + time());

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Filter request data
$t = !empty($_GET['t']) ? Filter::url($_GET['t']) : 'text';
$m = !empty($_GET['m']) ? Filter::url($_GET['m']) : 'default';
$q = !empty($_GET['q']) ? Filter::url($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Search request
if (empty($q)) {
  $resultsTotal  = 0;
  $results       = [];
  $hostPagesMime = [];
} else {
  $resultsTotal  = $sphinx->searchHostPagesTotal(Filter::searchQuery($q, $m), $t);
  $results       = $sphinx->searchHostPages(Filter::searchQuery($q, $m), $t, $p * WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, $resultsTotal);
  $hostPagesMime = $sphinx->searchHostPagesMime(Filter::searchQuery($q, $m));
}

// Define page basics
$totalPages = $sphinx->getHostPagesTotal();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'),  number_format($totalPages)),
                                            sprintf(_('Over %s pages or enter the new one...'), number_format($totalPages)),
                                            sprintf(_('Over %s pages or enter the new one...'), number_format($totalPages)),
                                            ]);
// Define alert message
$alertMessages = [];

// Register new host/page on search request contains the link
if (Yggverse\Parser\Url::is($q)) {

  try {

    $db->beginTransaction();

    if ($linkToDBresult = Helper::addLinkToDB($db, $memory, $q)) {

      if (count($linkToDBresult->new->hostPageId)) {

        $alertMessages[] = _('Link successfully registered in the crawl queue!');

      } else {

        if ($resultsTotal == 0) {

          $alertMessages[] = _('This link already registered in the crawl queue.');
        }

      }

    } else {

      $alertMessages[] = _('Link address not supported on this host!');
    }

    $db->commit();

  } catch(Exception $e){

    var_dump($e);

    $db->rollBack();
  }
}

// Count pages in the crawl queue
$timeThisHour = strtotime(sprintf('%s-%s-%s %s:00', date('Y'), date('n'), date('d'), date('H')));

if ($queueTotal = $memory->getByMethodCallback(
  $db, 'getHostPageCrawlQueueTotal', [$timeThisHour - CRAWL_HOST_PAGE_QUEUE_SECONDS_OFFSET], $timeThisHour + 3600
)) {

  $alertMessages[] = sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal);
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
          <?php foreach ($alertMessages as $alertMessage) { ?>
            <span><?php echo $alertMessage ?></span>
          <?php } ?>
        </div>
        <?php foreach ($results as $result) { ?>
          <?php if ($hostPage = $db->getHostPage($result->id)) { ?>
            <?php if ($host = $db->getHost($hostPage->hostId)) { ?>
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
                <a href="<?php echo urldecode($host->url . $hostPage->uri) ?>">
                  <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode(str_replace(['[',']'], false, $host->name)) ?>" alt="identicon" width="16" height="16" class="icon" />
                  <?php echo htmlentities(urldecode($host->url) . (mb_strlen(urldecode($hostPage->uri)) > 28 ? '...' . mb_substr(urldecode($hostPage->uri), -28) : urldecode($hostPage->uri))) ?>
                </a>
                |
                <a href="<?php echo WEBSITE_DOMAIN; ?>/explore.php?hp=<?php echo $result->id ?>">
                  <?php echo _('explore'); ?>
                </a>
              </div>
            <?php } ?>
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
          <span>
            <?php
              // Count pages in the crawl queue
              if ($q && $queueTotal = $memory->getByMethodCallback(
                $db, 'getHostPageCrawlQueueTotal', [$timeThisHour - CRAWL_HOST_PAGE_QUEUE_SECONDS_OFFSET], $timeThisHour + 3600
              )) {

                echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal);
              }
            ?>
          </span>
        </div>
      <?php } ?>
    </main>
  </body>
</html>