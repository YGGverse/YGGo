<?php

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/filter.php');
require_once('../library/parser.php');
require_once('../library/mysql.php');
require_once('../library/sphinxql.php');

// Connect Sphinx search server
$sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Filter request data
$hp = !empty($_GET['hp']) ? Filter::url($_GET['hp']) : 0;

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
  <title><?php echo sprintf(_('#%s info - YGGo!'), (int) $hp) ?></title>
    <meta charset="utf-8" />
    <meta name="description" content="<?php echo _('Javascript-less Open Source Web Search Engine') ?>" />
    <meta name="keywords" content="<?php echo _('web, search, engine, crawler, php, pdo, mysql, sphinx, yggdrasil, js-less, open source') ?>" />
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
        color: #ccc;
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
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit"><?php echo _('Search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($hostPage = $db->getFoundHostPage($hp)) { ?>
        <div>
          <?php if ($hostPageDescription = $db->getLastPageDescription($hp)) { ?>
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
          <a href="<?php echo $hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false) . $hostPage->uri ?>">
            <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode($hostPage->name) ?>" alt="identicon" width="16" height="16" class="icon" />
            <?php echo htmlentities(urldecode($hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false)) . urldecode($hostPage->uri)) ?>
          </a>
        </div>
        <div>
          <p><?php echo $hostPage->mime ? _('MIME') : false ?></p>
          <p><?php echo $hostPage->mime ?></p>
          <p><?php echo $hostPage->size ? _('Size') : false ?></p>
          <p><?php echo $hostPage->size ?></p>
          <p><?php echo $hostPage->timeAdded ? _('Time added') : false ?></p>
          <p><?php echo date('c', $hostPage->timeAdded) ?></p>
          <p><?php echo $hostPage->timeUpdated ? _('Time updated') : false ?></p>
          <p><?php echo date('c', $hostPage->timeUpdated) ?></p>
          <?php $totalHostPageSnaps = $db->getTotalHostPageSnaps($hp); ?>
          <p>
            <?php echo $totalHostPageSnaps ? Filter::plural($totalHostPageSnaps, [sprintf(_('%s snap'),  $totalHostPageSnaps),
                                                                                  sprintf(_('%s snaps'), $totalHostPageSnaps),
                                                                                  sprintf(_('%s snaps'), $totalHostPageSnaps)]) : false ?>
          </p>
          <?php if ($totalHostPageSnaps) { ?>
            <?php foreach ($db->getHostPageSnaps($hp) as $hostPageSnap) { ?>
              <p>
                <a href="<?php echo WEBSITE_DOMAIN . '/file.php?type=snap&hps=' . $hostPageSnap->hostPageSnapId ?>">
                  <?php echo date('c', $hostPageSnap->timeAdded) ?>
                </a>
              </p>
            <?php } ?>
          <?php } ?>
          <?php $totalHostPageIdSources = $db->getTotalHostPageIdSourcesByHostPageIdTarget($hp); ?>
          <p>
            <?php echo $totalHostPageIdSources ? Filter::plural($totalHostPageIdSources, [sprintf(_('%s referrer'),  $totalHostPageIdSources),
                                                                                          sprintf(_('%s referrers'), $totalHostPageIdSources),
                                                                                          sprintf(_('%s referrers'), $totalHostPageIdSources)]) : false ?>
          </p>
          <?php if ($totalHostPageIdSources) { ?>
            <?php foreach ($db->getHostPageIdSourcesByHostPageIdTarget($hp) as $hostPageIdSource) { ?>
              <?php if ($hostPage = $db->getFoundHostPage($hostPageIdSource->hostPageIdSource)) { ?>
                <p>
                  <a href="<?php echo $hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false) . $hostPage->uri ?>">
                    <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode($hostPage->name) ?>" alt="identicon" width="16" height="16" class="icon" />
                    <?php echo htmlentities(urldecode($hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false)) . (mb_strlen(urldecode($hostPage->uri)) > 32 ? '...' . mb_substr(urldecode($hostPage->uri), -32) : urldecode($hostPage->uri))) ?>
                  </a>
                  |
                  <a href="<?php echo WEBSITE_DOMAIN; ?>/explore.php?hp=<?php echo $hostPage->hostPageId ?>">
                    <?php echo _('explore'); ?>
                  </a>
                </p>
              <?php } ?>
            <?php } ?>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div style="text-align:center">
          <span><?php echo _('Not found') ?></span>
          <?php if ($queueTotal = $db->getHostPageCrawlQueueTotal(time() - CRAWL_PAGE_SECONDS_OFFSET)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>