<?php

// CLI only to prevent https server connection timeout
if (php_sapi_name() != 'cli') {

  CLI::danger(_('supported command line interface only'));
  exit;
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('cli.yggo'), 1);

if (false === sem_acquire($semaphore, true)) {

  CLI::danger(_('Process locked by another thread.'));
  exit;
}

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/cli.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/ftp.php');
require_once(__DIR__ . '/../library/vendor/simple_html_dom.php');

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// CLI begin
if (empty($argv[1])) $argv[1] = 'help';

switch ($argv[1]) {

  case 'crawl':

    CLI::notice(_('crawler queue step begin...'));

    include_once(__DIR__ . '/../crontab/crawler.php');

    CLI::notice(_('crawler queue step begin...'));
  break;
  case 'clean':

    CLI::notice(_('cleaner queue step begin...'));

    include_once(__DIR__ . '/../crontab/cleaner.php');

    CLI::notice(_('cleaner queue step completed.'));

  break;
  case 'snap':

    if (empty($argv[2])) {
      CLI::danger(_('snap method requires action argument'));
    }

    switch ($argv[2]) {

      case 'reindex':

        // Scan for new files/storages
        CLI::notice(_('scan database registry for missed snap files...'));

        foreach ($db->getHosts() as $host) {

          foreach ($db->getHostPages($host->hostId) as $hostPage) {

            foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

              // Define variables
              $snapFilesExists = false;

              $snapPath = chunk_split($hostPage->hostPageId, 1, '/');

              // Check file exists
              foreach (json_decode(SNAP_STORAGE) as $name => $storages) {

                foreach ($storages as $i => $storage) {

                  // Generate storage id
                  $crc32name = crc32(sprintf('%s.%s', $name, $i));

                  switch ($name) {

                    case 'localhost':

                      $filename = $storage->directory . $snapPath . $hostPageSnap->timeAdded . '.zip';

                      if (file_exists($filename)) {

                        $snapFilesExists = true;

                        if (!$db->getHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

                          if ($db->addHostPageSnapStorage($hostPageSnap->hostPageSnapId, $crc32name, $hostPageSnap->timeAdded)) {

                            CLI::warning(sprintf(_('register snap #%s file: %s storage: %s index: %s;'), $hostPageSnap->hostPageSnapId, $filename, $name, $i));
                          }

                        } else {

                          CLI::success(sprintf(_('skip related snap #%s file: %s storage: %s index: %s;'), $hostPageSnap->hostPageSnapId, $filename, $name, $i));
                        }
                      }

                    break;

                    case 'ftp':

                      $ftp = new Ftp();

                      if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {

                        $filename = 'hp/' . $snapPath . $hostPageSnap->timeAdded . '.zip';

                        if ($ftp->size($filename)) {

                          $snapFilesExists = true;

                          if (!$db->getHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

                            if ($db->addHostPageSnapStorage($hostPageSnap->hostPageSnapId, $crc32name, $hostPageSnap->timeAdded)) {

                              CLI::warning(sprintf(_('register snap #%s file: %s storage: %s index: %s;'), $hostPageSnap->hostPageSnapId, $filename, $name, $i));
                            }
                          } else {

                            CLI::success(sprintf(_('skip related snap #%s file: %s storage: %s index: %s;'), $hostPageSnap->hostPageSnapId, $filename, $name, $i));
                          }
                        }
                      }

                      $ftp->close();

                    break;
                  }
                }
              }

              // Files not exists
              if (!$snapFilesExists) {

                // Delete snap from registry
                try {

                  $db->beginTransaction();

                  foreach ($db->getHostPageSnapStorages($hostPageSnapId) as $hostPageSnapStorage) {

                    $db->deleteHostPageSnapDownloads($hostPageSnapStorage->hostPageSnapStorageId);
                  }

                  $db->deleteHostPageSnapStorages($hostPageSnapId);
                  $db->deleteHostPageSnap($hostPageSnapId);

                  CLI::danger(sprintf(_('delete snap index: #%s file: %s not found in the any of storage;'), $hostPageSnap->hostPageSnapId, $filename));

                  $db->commit();

                } catch(Exception $e) {

                  $db->rollBack();

                  var_dump($e);
                }
              }
            }
          }
        }

        CLI::notice(_('optimize database tables...'));

        $db->optimize();

        CLI::success(_('tables successfully optimized!'));

        CLI::notice(_('scan storage locations for snap files not registered in the DB...'));

        CLI::success(_('snap index successfully updated!'));

        // Cleanup FS items on missed DB registry
        // @TODO

      break;
      default:

        CLI::danger(_('undefined action argument'));
    }

  break;
  case 'hostPage':

    if (empty($argv[2])) {

      CLI::danger(_('hostPage method requires action argument'));
    }

    switch ($argv[2]) {

      case 'rank':

        if (empty($argv[3])) {

          CLI::danger(_('hostPage rank requires action argument'));
        }

        switch ($argv[3]) {

          case 'reindex':

            foreach ($db->getHosts() as $host) {

              foreach ($db->getHostPages($host->hostId) as $hostPage) {

                $db->updateHostPageRank($hostPage->hostPageId, $db->getTotalExternalHostPageIdSourcesByHostPageIdTarget($hostPage->hostPageId)); // @TODO add library cover
              }
            }

            CLI::success(_('hostPage rank successfully updated'));
            exit;

          break;
          default:

          CLI::danger(_('undefined action argument'));
        }

      break;
      case 'truncate':

        $db->truncateHostPageDom();

        CLI::success(_('hostPageDom table successfully truncated'));
        exit;

      break;
      default:

        CLI::danger(_('undefined action argument'));
    }

  break;
  case 'hostPageDom':

    if (empty($argv[2])) {

      CLI::danger(_('hostPageDom method requires action argument'));
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

          CLI::success(sprintf(_('Host pages processed: %s'), $hostPagesProcessedTotal));
          CLI::success(sprintf(_('Host page DOM elements added: %s'), $hostPageDOMAddedTotal));
          exit;
        }

        CLI::danger(_('CRAWL_HOST_PAGE_DOM_SELECTORS not provided in the configuration file'));
        exit;

      break;
      case 'truncate':

        $db->truncateHostPageDom();

        CLI::success(_('hostPageDom table successfully truncated'));
        exit;

      break;
      default:

        CLI::danger(_('undefined action argument'));
    }

  break;
}

// Default message
CLI::default('__  ______________      __');
CLI::default('\ \/ / ____/ ____/___  / /');
CLI::default(' \  / / __/ / __/ __ \/ /' );
CLI::default(' / / /_/ / /_/ / /_/ /_/'  );
CLI::default('/_/\____/\____/\____(_)'   );

CLI::break();
CLI::default('available options:');

CLI::default('  help                             - this message');
CLI::default('  crawl                            - execute crawler step in the crontab queue');
CLI::default('  clean                            - execute cleaner step in the crontab queue');
CLI::default('  snap reindex                     - sync DB/FS relations');
CLI::default('  hostPage rank reindex            - generate rank indexes in hostPage table');
CLI::default('  hostPageDom generate [selectors] - make hostPageDom index based on related hostPage.data field');
CLI::default('  hostPageDom truncate             - flush hostPageDom table');
CLI::break();

CLI::default('get support: https://github.com/YGGverse/YGGo/issues');

CLI::break();
CLI::break();
