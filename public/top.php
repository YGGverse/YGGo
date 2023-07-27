<?php

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/sphinxql.php');

// Connect Sphinx search server
$sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Define page basics
$totalPages = $sphinx->getHostPagesTotal();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'),  $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            ]);



?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo _('Top - YGGo!') ?></title>
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
        margin-top: 80px;
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
        font-size: 14px;
      }

      table {
        font-size: 14px;
        display: block;
        max-width: 640px;
        margin: 0 auto;
      }

      table tr td {
        border-top: 1px #000 dashed;
      }

      table tr th,
      table tr td {
        padding: 8px 16px;
      }

      table tr td {
        font-size: 12px;
      }

      table tr th:nth-child(1),
      table tr td:nth-child(1) {
        text-align: center;
      }

      table tr th:nth-child(2),
      table tr td:nth-child(2) {
        text-align: left;
        white-space: nowrap;
      }

      table tr th:nth-child(3),
      table tr td:nth-child(3) {
        text-align: center;
      }

      table tr th:nth-child(4),
      table tr td:nth-child(4) {
        text-align: center;
      }

      table tr th:nth-child(5),
      table tr td:nth-child(5) {
        text-align: center;
      }
    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><a href="<?php echo WEBSITE_DOMAIN; ?>"><?php echo _('YGGo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit"><?php echo _('Search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($topHostPages = $db->getTopHostPages()) { ?>
        <table>
          <tr>
            <th><?php echo _('#') ?></th>
            <th><?php echo _('Address') ?></th>
            <th><?php echo _('Pages') ?></th>
            <th><?php echo _('PR') ?></th>
            <th><?php echo _('Actions') ?></th>
          </tr>
          <?php foreach ($topHostPages as $i => $topHostPage) { ?>
              <tr>
                <td><?php echo $i + 1 ?></td>
                <td>
                  <?php if ($hostPageDescription = $db->getLastPageDescription($topHostPage->hostPageId)) { ?>
                    <?php if (!empty($hostPageDescription->title)) { ?>
                      <?php $title = htmlentities($hostPageDescription->title) ?>
                    <?php } else if (!empty($hostPageDescription->description)) { ?>
                      <?php $title = htmlentities($hostPageDescription->description) ?>
                    <?php } else if (!empty($hostPageDescription->keywords)) { ?>
                      <?php $title = htmlentities($hostPageDescription->keywords) ?>
                    <?php } else { ?>
                      <?php $title = false ?>
                    <?php } ?>
                  <?php } ?>
                  <a href="<?php echo $topHostPage->scheme . '://' . $topHostPage->name . ($topHostPage->port ? ':' . $topHostPage->port : false) . $topHostPage->uri ?>"title="<?php echo trim($title) ?>">
                    <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode($topHostPage->name) ?>" alt="identicon" width="16" height="16" class="icon" />
                    <?php echo htmlentities(urldecode($topHostPage->scheme . '://' . $topHostPage->name . ($topHostPage->port ? ':' . $topHostPage->port : false))) ?>
                  </a>
                </td>
                <td>
                  <?php $totalHostPages = $db->getTotalHostPages($topHostPage->hostId) ?>
                  <?php echo $totalHostPages . ($totalHostPages >= CRAWL_HOST_DEFAULT_PAGES_LIMIT ? '+' : false) ?>
                </td>
                <td><?php echo $topHostPage->rank ?></td>
                <td>
                  <a href="<?php echo WEBSITE_DOMAIN; ?>/explore.php?hp=<?php echo $topHostPage->hostPageId ?>">
                      <?php echo _('explore'); ?>
                  </a>
                </td>
              </tr>
          <?php } ?>
        </table>
      <?php } else { ?>
        <div style="text-align:center">
          <span><?php echo _('Not found') ?></span>
          <?php if ($queueTotal = $db->getHostPageCrawlQueueTotal(time() - CRAWL_PAGE_SECONDS_OFFSET, time() - CRAWL_PAGE_HOME_SECONDS_OFFSET)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>