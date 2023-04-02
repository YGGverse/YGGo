<?php

// Load system dependencies
require_once('../config/app.php');
require_once('../library/filter.php');
require_once('../library/sqlite.php');

// Connect database
$db = new SQLite(DB_NAME, DB_USERNAME, DB_PASSWORD);

// Define page basics
$totalPages = $db->getTotalPages();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            ]);

// Filter request data
$q = !empty($_GET['q']) ? Filter::url($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Crawl request
if (filter_var($q, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $q)) {

  $db->initPage($q, crc32($q), time());
}

// Search request
if (!empty($q)) {

  $results      = $db->searchPages('"' . $q . '"', $p * WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT);
  $resultsTotal = $db->searchPagesTotal('"' . $q . '"');

} else {

  $results      = [];
  $resultsTotal = 0;
}

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo (empty($q) ? _('Empty request - YGGo!') : ($p > 1 ? sprintf(_('%s - #%s - YGGo!'), htmlentities($q), $p) : sprintf(_('%s - YGGo!'), htmlentities($q)))) ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo _('Javascript-less Open Source Web Search Engine') ?>" />
    <meta name="keywords" content="<?php echo _('web, search, engine, crawler, php, pdo, sqlite, fts5, yggdrasil, js-less, open source') ?>" />
    <style>

      * {
        border: 0;
        margin: 0;
        padding: 0;
        font-family: Sans-serif;
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
        margin-top: 92px;
        margin-bottom: 76px;
      }

      h1 {
        color: #fff;
        font-weight: normal;
        font-size: 26px;
        margin: 16px 0;
        position: fixed;
        top: 8px;
        left: 24px;
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
        max-width: 640px;
        margin: 0 auto;
        text-align: center;
      }

      input {
        width: 100%;
        margin: 16px 0;
        padding: 14px 0;
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

      button {
        padding: 12px 16px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
        position: fixed;
        top: 18px;
        right: 24px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      a, a:visited, a:active {
        color: #3394fb;
        display: block;
        font-size: 14px;
      }

      a:hover {
        color: #54a3f7;
      }

      div {
        max-width: 640px;
        margin: 0 auto;
        padding: 16px 0;
        border-top: 1px #000 dashed;
        font-size: 14px
      }

      span {
        color: #ccc;
        display: block;
        margin: 8px 0;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <a href="<?php echo WEBSITE_DOMAIN; ?>"><h1><?php echo _('YGGo!') ?></h1></a>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="<?php echo htmlentities($q) ?>" />
        <button type="submit"><?php echo _('Search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($results) { ?>
        <div>
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($queueTotal = $db->getTotalPagesByHttpCode(null)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
        <?php foreach ($results as $result) { ?>
          <div>
            <h2><?php echo $result->title ?></h2>
            <?php if (!empty($result->description)) { ?>
            <span><?php echo $result->description ?></span>
            <?php } ?>
            <a href="<?php echo $result->url ?>"><?php echo $result->url ?></a>
          </div>
        <?php } ?>
        <?php if ($p * WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT <= $resultsTotal) { ?>
          <div>
            <a href="<?php echo WEBSITE_DOMAIN; ?>/search.php?q=<?php echo urlencode(htmlentities($q)) ?>&p=<?php echo $p + 1 ?>"><?php echo _('Next page') ?></a>
          </div>
          <?php } ?>
      <?php } else { ?>
        <div style="text-align:center">
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($q && $queueTotal = $db->getTotalPagesByHttpCode(null)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>