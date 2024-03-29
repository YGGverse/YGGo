<?php

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/sphinxql.php');

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

// Filter request data
$hp = !empty($_GET['hp']) ? Filter::url($_GET['hp']) : 0;

// Define page basics
$totalPages = $sphinx->getHostPagesTotal();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'),  number_format($totalPages)),
                                            sprintf(_('Over %s pages or enter the new one...'), number_format($totalPages)),
                                            sprintf(_('Over %s pages or enter the new one...'), number_format($totalPages)),
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
        color: #ccc;
      }

      body {
        background-color: #2e3436;
        word-break: break-word;
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
        font-size: 14px;
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

      .text-warning {
        color: #db6161;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><a href="<?php echo WEBSITE_DOMAIN; ?>"><?php echo _('YGGo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit"><?php echo _('search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($hostPage = $db->getHostPage($hp)) { ?>
        <?php if ($host = $db->getHost($hostPage->hostId)) { ?>
          <div>
            <?php if ($hostPageDescription = $db->getLastPageDescription($hp)) { ?>
              <?php if (!empty($hostPageDescription->title)) { ?>
                <h2><?php echo htmlentities($hostPageDescription->title) ?></h2>
              <?php } ?>
              <?php if (!empty($hostPageDescription->description)) { ?>
                <span><?php echo htmlentities($hostPageDescription->description) ?></span>
              <?php } ?>
              <?php if (!empty($hostPageDescription->keywords)) { ?>
                <span><?php echo htmlentities($hostPageDescription->keywords) ?></span>
              <?php } ?>
            <?php } ?>
            <a href="<?php echo urldecode($host->url . $hostPage->uri) ?>">
              <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode(str_replace(['[',']'], false, $host->name)) ?>" alt="identicon" width="16" height="16" class="icon" />
              <?php echo htmlentities(urldecode($host->url . $hostPage->uri)) ?>
            </a>
          </div>
          <div>
            <p><?php echo _('HTTP') ?></p>
            <?php if ($hostPage->httpCode == 200) { ?>
              <p><?php echo $hostPage->httpCode ?></p>
            <?php } else { ?>
              <p class="text-warning">
                <?php echo $hostPage->httpCode ?>
              </p>
            <?php } ?>
            <?php if (!empty($hostPage->mime)) { ?>
              <p><?php echo _('MIME') ?></p>
              <p><?php echo $hostPage->mime ?></p>
            <?php } ?>
            <?php if (!empty($hostPage->size)) { ?>
              <p><?php echo _('Size') ?></p>
              <p><?php echo $hostPage->size ?></p>
            <?php } ?>
            <?php if (!empty($hostPage->timeAdded)) { ?>
              <p><?php echo _('Time added') ?></p>
              <p><?php echo date('c', $hostPage->timeAdded) ?></p>
            <?php } ?>
            <?php if (!empty($hostPage->timeUpdated)) { ?>
              <p><?php echo _('Time updated') ?></p>
              <p><?php echo date('c', $hostPage->timeUpdated) ?></p>
            <?php } ?>
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
            <?php $totalHostPageIdSources = $db->getTotalHostPagesToHostPageByHostPageIdTarget($hp); ?>
            <p>
              <?php echo $totalHostPageIdSources ? Filter::plural($totalHostPageIdSources, [sprintf(_('%s referrer'),  $totalHostPageIdSources),
                                                                                            sprintf(_('%s referrers'), $totalHostPageIdSources),
                                                                                            sprintf(_('%s referrers'), $totalHostPageIdSources)]) : false ?>
            </p>
            <?php if ($totalHostPageIdSources) { ?>
              <?php foreach ($db->getHostPagesToHostPageByHostPageIdTarget($hp) as $hostPagesToHostPageByHostPageIdTarget) { ?>
                <?php if ($hostPage = $db->getHostPage($hostPagesToHostPageByHostPageIdTarget->hostPageIdSource)) { ?>
                  <?php if ($host = $db->getHost($hostPage->hostId)) { ?>
                    <p>
                      <a href="<?php echo urldecode($host->url . $hostPage->uri) ?>">
                        <img src="<?php echo WEBSITE_DOMAIN; ?>/file.php?type=identicon&query=<?php echo urlencode(str_replace(['[',']'], false, $host->name)) ?>" alt="identicon" width="16" height="16" class="icon" />
                        <?php echo htmlentities(urldecode($host->url) . (mb_strlen(urldecode($hostPage->uri)) > 28 ? '...' . mb_substr(urldecode($hostPage->uri), -28) : urldecode($hostPage->uri))) ?>
                      </a>
                      <?php if ($hostPage->httpCode != 200) { ?>
                        |
                        <small class="text-warning">
                          <?php echo $hostPage->httpCode ?>
                        </small>
                      <?php } ?>
                      |
                      <a href="<?php echo WEBSITE_DOMAIN; ?>/explore.php?hp=<?php echo $hostPage->hostPageId ?>">
                        <?php echo _('explore'); ?>
                      </a>
                    </p>
                  <?php } ?>
                <?php } ?>
              <?php } ?>
            <?php } ?>
          </div>
        <?php } ?>
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