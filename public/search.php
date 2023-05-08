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
$t = !empty($_GET['t']) ? Filter::url($_GET['t']) : 'page';
$m = !empty($_GET['m']) ? Filter::url($_GET['m']) : 'default';
$q = !empty($_GET['q']) ? Filter::url($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Define page basics
switch ($t) {

  case 'image':

    $totalPages = $sphinx->getHostImagesTotal();

    $placeholder = Filter::plural($totalPages, [sprintf(_('Over %s image or enter the new one...'), $totalPages),
                                                sprintf(_('Over %s images or enter the new one...'), $totalPages),
                                                sprintf(_('Over %s images or enter the new one...'), $totalPages),
                                                ]);

  break;
  default:

    $totalPages = $sphinx->getHostPagesTotal();

    $placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'), $totalPages),
                                                sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                                sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                                ]);
}


// Crawl request
if (filter_var($q, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $q)) {

  $db->beginTransaction();

  try {

    // Parse host info
    if ($hostURL = Parser::hostURL($q)) {

      // Host exists
      if ($host = $db->getHost(crc32($hostURL->string))) {

        $hostStatus        = $host->status;
        $hostPageLimit     = $host->crawlPageLimit;
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
         !$db->getHostPage($hostId, crc32($hostPageURI->string))) {  // page not exists

          $db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time());
      }
    }

    $db->commit();

  } catch(Exception $e){

    $db->rollBack();
  }
}

// Search request
if (!empty($q)) {

  if ($t == 'image') {

    $resultsTotal = $sphinx->searchHostImagesTotal(Filter::searchQuery($q, $m));
    $results      = $sphinx->searchHostImages(Filter::searchQuery($q, $m), $p * WEBSITE_PAGINATION_SEARCH_IMAGE_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_IMAGE_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_IMAGE_RESULTS_LIMIT, $resultsTotal);

  } else {

    $resultsTotal = $sphinx->searchHostPagesTotal(Filter::searchQuery($q, $m));
    $results      = $sphinx->searchHostPages(Filter::searchQuery($q, $m), $p * WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT, $resultsTotal);
  }

} else {

  $resultsTotal = 0;
  $results      = [];
}

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo (empty($q) ? _('Empty request - YGGo!') : ($p > 1 ? sprintf(_('%s - #%s - YGGo!'), htmlentities($q), $p) : sprintf(_('%s - YGGo!'), htmlentities($q)))) ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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

      h3 {
        display: block;
        font-size: 16px;
        font-weight: normal;
        margin: 12px 0;
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
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><a href="<?php echo WEBSITE_DOMAIN; ?>"><?php echo _('YGGo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="<?php echo htmlentities($q) ?>" />
        <label><input type="radio" name="t" value="page" <?php echo ($t == 'page' ? 'checked="checked"' : false) ?>/> <?php echo _('Pages') ?></label>
        <label><input type="radio" name="t" value="image" <?php echo ($t == 'image' ? 'checked="checked"' : false) ?>/> <?php echo _('Images') ?></label>
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
          <?php if ($t == 'image' && $hostImage = $db->getFoundHostImage($result->id)) { ?>
            <?php

              // Built image url
              $hostImageURL = $hostImage->scheme . '://' .
                              $hostImage->name .
                             ($hostImage->port ? ':' . $hostImage->port : false) .
                              $hostImage->uri;

              // Get remote image data
              if (empty($hostImage->data)) {

                // Init image request
                $hostImageCurl = new Curl($hostImageURL, PROXY_CURLOPT_USERAGENT);

                // Skip item render on timeout
                $hostImageHttpCode = $hostImageCurl->getCode();

                $db->updateHostImageHttpCode($hostImage->hostImageId, (int) $hostImageHttpCode, time());

                if (200 != $hostImageHttpCode) {

                  $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                // Skip image processing on MIME type not provided
                if (!$hostImageContentType = $hostImageCurl->getContentType()) {

                  $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                // Skip image processing on MIME type not allowed in settings
                $hostImageBanned = true;
                foreach ((array) explode(',', CRAWL_IMAGE_MIME) as $mime) {

                  if (false !== strpos($hostImageContentType, trim($mime))) {

                    $hostImageBanned = false;
                    break;
                  }
                }

                if ($hostImageBanned) {

                  $hostImagesBanned += $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                // Skip image processing without returned content
                if (!$hostImageContent = $hostImageCurl->getContent()) {

                  $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                // Convert remote image data to base64 string to prevent direct URL call
                if (!$hostImageExtension = @pathinfo($hostImageURL, PATHINFO_EXTENSION)) {

                  $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                if (!$hostImageBase64 = @base64_encode($hostImageContent)) {

                  $db->updateHostImageTimeBanned($hostImage->hostImageId, time());

                  continue;
                }

                $hostImageURLencoded  = 'data:image/' . str_replace(['svg'], ['svg+xml'], $hostImageExtension) . ';base64,' . $hostImageBase64;

                // Save image content on data settings enabled
                $db->updateHostImage($hostImage->hostImageId,
                                     Filter::mime($hostImageContentType),
                                     CRAWL_HOST_DEFAULT_META_ONLY ? null : $hostImageURLencoded,
                                     time());

              // Local image data exists
              } else {

                $hostImageURLencoded = $hostImage->data;
              }

            ?>
            <div>
              <a href="<?php echo $hostImageURL ?>">
                <img src="<?php echo $hostImageURLencoded ?>" alt="<?php echo htmlentities($hostImage->description) ?>" title="<?php echo htmlentities($hostImageURL) ?>" class="image" />
              </a>
              <?php $hostImageHostPagesTotal = $db->getHostImageHostPagesTotal($result->id) ?>
              <?php foreach ((array) $db->getHostImageHostPages($result->id, WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT) as $hostPage) { ?>
                <?php if ($hostPage = $db->getFoundHostPage($hostPage->hostPageId)) { ?>
                  <?php $hostPageURL = $hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false) . $hostPage->uri ?>
                  <h3><?php echo $hostPage->metaTitle ?></h3>
                  <?php if (!empty($hostImage->description)) { ?>
                    <span><?php echo $hostImage->description ?></span>
                  <?php } ?>
                  <a href="<?php echo $hostPageURL ?>">
                    <img src="<?php echo WEBSITE_DOMAIN ?>/image.php?q=<?php echo urlencode($hostPage->name) ?>" alt="favicon" width="16" height="16" class="icon" />
                    <?php echo htmlentities(urldecode($hostPageURL)) ?>
                  </a>
                <?php } ?>
              <?php } ?>
              <?php if ($hostImageHostPagesTotal - WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT > 0) { ?>
                <p>
                  <small>
                    <?php echo Filter::plural($hostImageHostPagesTotal - WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT,
                    [
                      sprintf(_('+%s other page'),  $hostImageHostPagesTotal - WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT),
                      sprintf(_('+%s other pages'), $hostImageHostPagesTotal - WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT),
                      sprintf(_('+%s other pages'), $hostImageHostPagesTotal - WEBSITE_SEARCH_IMAGE_RELATED_PAGE_RESULTS_LIMIT),
                    ]); ?>
                  </small>
                </p>
              <?php } ?>
            </div>
          <?php } else if ($hostPage = $db->getFoundHostPage($result->id)) { ?>
              <?php

                $hostPageURL = $hostPage->scheme . '://' .
                $hostPage->name .
               ($hostPage->port ? ':' . $hostPage->port : false) .
                $hostPage->uri;

              ?>
            <div>
              <h2><?php echo $hostPage->metaTitle ?></h2>
              <?php if (!empty($hostPage->metaDescription)) { ?>
              <span><?php echo $hostPage->metaDescription ?></span>
              <?php } ?>
              <a href="<?php echo $hostPageURL ?>">
                <img src="<?php echo WEBSITE_DOMAIN; ?>/image.php?q=<?php echo urlencode($hostPage->name) ?>" alt="favicon" width="16" height="16" class="icon" />
                <?php echo htmlentities(urldecode($hostPageURL)) ?>
              </a>
            </div>
          <?php } ?>
        <?php } ?>
        <?php if ($p * ($t == 'image' ? WEBSITE_PAGINATION_SEARCH_IMAGE_RESULTS_LIMIT : WEBSITE_PAGINATION_SEARCH_PAGE_RESULTS_LIMIT) <= $resultsTotal) { ?>
          <div>
            <a href="<?php echo WEBSITE_DOMAIN; ?>/search.php?q=<?php echo urlencode(htmlentities($q)) ?>&t=<?php echo $t ?>&p=<?php echo $p + 1 ?>"><?php echo _('Next page') ?></a>
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