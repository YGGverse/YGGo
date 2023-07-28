<?php

// CLI only to prevent https server connection timeout
if (php_sapi_name() != 'cli') {
  echo sprintf(_('supported command line interface only'), PHP_EOL);
  exit;
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('cli.yggo'), 1);

if (false === sem_acquire($semaphore, true)) {

  echo _('Process locked by another thread.') . PHP_EOL;
  exit;
}

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/vendor/simple_html_dom.php');

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// CLI begin
if (empty($argv[1])) $argv[1] = 'help';

switch ($argv[1]) {

  case 'crawl':

    include_once(__DIR__ . '/../crontab/crawler.php');

  break;
  case 'clean':

    include_once(__DIR__ . '/../crontab/cleaner.php');

  break;
  case 'hostPage':

    if (empty($argv[2])) {
      echo PHP_EOL . _('hostPage method requires action argument') . PHP_EOL;
    }

    switch ($argv[2]) {

      case 'rank':

        if (empty($argv[3])) {
          echo PHP_EOL . _('hostPage rank requires action argument') . PHP_EOL;
        }

        switch ($argv[3]) {

          case 'reindex':

            foreach ($db->getHosts() as $host) {

              foreach ($db->getHostPages($host->hostId) as $hostPage) {

                $db->updateHostPageRank($hostPage->hostPageId, $db->getTotalExternalHostPageIdSourcesByHostPageIdTarget($hostPage->hostPageId)); // @TODO add library cover
              }
            }

            echo _('hostPage rank successfully updated') . PHP_EOL;
            exit;

          break;
          default:

            echo PHP_EOL . _('undefined action argument') . PHP_EOL;
        }

      break;
      case 'truncate':

        $db->truncateHostPageDom();

        echo _('hostPageDom table successfully truncated') . PHP_EOL;
        exit;

      break;
      default:

        echo PHP_EOL . _('undefined action argument') . PHP_EOL;
    }

  break;
  case 'hostPageDom':

    if (empty($argv[2])) {
      echo PHP_EOL . _('hostPageDom method requires action argument') . PHP_EOL;
    }

    switch ($argv[2]) {

      case 'generate':

        $selectors = [];

        foreach ((array) explode(';', !empty($argv[3]) ? $argv[3] : (string) CRAWL_HOST_PAGE_DOM_SELECTORS) as $selector) {

          if (!empty($selector)) {

            $selectors[] = trim($selector);
          }
        }

        if ($selectors) {

          // Init variables
          $hostPagesProcessedTotal = 0;
          $hostPageDOMAddedTotal   = 0;

          // Begin selectors extraction
          foreach ($db->getHostPagesByIndexed() as $hostPage) {

            if (false !== stripos(Filter::mime($hostPage->mime), 'text/html')) {

              if ($hostPageDescription = $db->getLastPageDescription($hostPage->hostPageId)) {

                $hostPagesProcessedTotal++;

                if (!empty($hostPageDescription->data)) {

                  $html = str_get_html(base64_decode($hostPageDescription->data));

                  foreach ($selectors as $selector) {

                    foreach($html->find($selector) as $element) {

                      if (!empty($element->innertext)) {

                        $hostPageDOMAddedTotal++;

                        $db->addHostPageDom($hostPage->hostPageId,
                                            time(),
                                            $selector,
                                            trim(CRAWL_HOST_PAGE_DOM_STRIP_TAGS ? strip_tags(
                                                                                  preg_replace('/[\s]+/',
                                                                                                ' ',
                                                                                                str_replace(['<br />', '<br/>', '<br>', '</'],
                                                                                                            [' ', ' ', ' ', ' </'],
                                                                                                            $element->innertext))) : $element->innertext));
                      }
                    }
                  }
                }
              }
            }
          }

          echo sprintf(_('Host pages processed: %s'), $hostPagesProcessedTotal) . PHP_EOL;
          echo sprintf(_('Host page DOM elements added: %s'), $hostPageDOMAddedTotal) . PHP_EOL;
          exit;
        }

        echo PHP_EOL . _('CRAWL_HOST_PAGE_DOM_SELECTORS not provided in the configuration file') . PHP_EOL;
        exit;

      break;
      case 'truncate':

        $db->truncateHostPageDom();

        echo _('hostPageDom table successfully truncated') . PHP_EOL;
        exit;

      break;
      default:

        echo PHP_EOL . _('undefined action argument') . PHP_EOL;
    }

  break;
  case 'hostPageSnap':

    if (empty($argv[2])) {
      echo PHP_EOL . _('hostPageSnap method requires action argument') . PHP_EOL;
    }

    switch ($argv[2]) {

      case 'truncate':

        foreach ($db->getHosts() as $host) {

          foreach ($db->getHostPages($host->hostId) as $hostPage) {

            $snapFilePath = chunk_split($hostPage->hostPageId, 1, '/');

            foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

              if ($hostPageSnap->storageLocal) {

                unlink(__DIR__ . '/../storage/snap/hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip');
              }

              if ($hostPageSnap->storageMega) {

                $ftp = new Ftp();

                if ($ftp->connect(MEGA_FTP_HOST, MEGA_FTP_PORT, null, null, MEGA_FTP_DIRECTORY)) {
                    $ftp->delete('hp/' . $snapFilePath . $hostPageSnap->timeAdded . '.zip');
                }
              }

              $db->deleteHostPageSnapDownloads($hostPageSnap->hostPageSnapId);
              $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);

              // @TODO reset primary key indexes
            }
          }
        }

        echo _('hostPageSnap, hostPageSnapDownload tables successfully truncated') . PHP_EOL;
        exit;

      break;
      default:

        echo PHP_EOL . _('undefined action argument') . PHP_EOL;
    }

  break;
}

// Default message
echo '__  ______________      __' . PHP_EOL;
echo '\ \/ / ____/ ____/___  / /' . PHP_EOL;
echo ' \  / / __/ / __/ __ \/ /'  . PHP_EOL;
echo ' / / /_/ / /_/ / /_/ /_/'   . PHP_EOL;
echo '/_/\____/\____/\____(_)'    . PHP_EOL;

echo PHP_EOL . _('available options:') . PHP_EOL . PHP_EOL;

echo _('  help                             - this message') . PHP_EOL;
echo _('  crawl                            - execute crawler step in the crontab queue') . PHP_EOL;
echo _('  clean                            - execute cleaner step in the crontab queue') . PHP_EOL;
echo _('  hostPage rank reindex            - generate rank indexes in hostPage table') . PHP_EOL;
echo _('  hostPageDom generate [selectors] - make hostPageDom index based on related hostPage.data field') . PHP_EOL;
echo _('  hostPageDom truncate             - flush hostPageDom table') . PHP_EOL;
echo _('  hostPageSnap truncate            - flush hostPageSnap, hostPageSnapDownload tables') . PHP_EOL . PHP_EOL;

echo _('get support: https://github.com/YGGverse/YGGo/issues') . PHP_EOL . PHP_EOL;
