<?php

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/cli.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/filter.php');
require_once(__DIR__ . '/../library/ftp.php');
require_once __DIR__ . '/../../vendor/autoload.php';

// CLI only to prevent https server connection timeout
if (php_sapi_name() != 'cli') {

  CLI::danger(_('supported command line interface only'));
  exit;
}

// Stop CLI execution on cleaner process running
$semaphore = sem_get(crc32('crontab.cleaner'), 1);

if (false === sem_acquire($semaphore, true)) {

  CLI::danger(_('stop crontab.cleaner is running in another thread.'));
  exit;
}

// Stop CLI execution on crawler process running
$semaphore = sem_get(crc32('crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  CLI::danger(_('stop crontab.crawler is running in another thread.'));
  exit;
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('cli.yggo'), 1);

if (false === sem_acquire($semaphore, true)) {

  CLI::danger(_('process locked by another thread.'));
  exit;
}

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// CLI begin
if (!empty($argv[1])) {

  switch ($argv[1]) {

    case 'db':

      if (empty($argv[2])) {

        switch ($argv[2]) {

          case 'optimize':

            CLI::notice(_('optimize database tables...'));

            $db->optimize();

            CLI::success(_('tables successfully optimized!'));

          break;
        }
      }

    break;
    case 'crontab':

      if (empty($argv[2])) {

        switch ($argv[2]) {

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
        }
      }

    break;
    case 'hostSetting':

        if (!empty($argv[2])) {

          switch ($argv[2]) {

            case 'set':

              if (!empty($argv[3]) && !empty($argv[4]) && !empty($argv[5])) {

                switch ($argv[4]) {

                  case 'PAGES_LIMIT':

                    if ($hostSetting = $db->findHostSetting((int) $argv[3], 'PAGES_LIMIT')) {

                      if ($db->updateHostSetting($hostSetting->hostSettingId, (int) $argv[5], time())) {

                        CLI::warning(sprintf(_('%s for hostId %s updated:'), $argv[4], $argv[3]));
                        CLI::warning(
                          sprintf(
                            '%s > %s',
                            $hostSetting->value,
                            $argv[5],
                          )
                        );

                        exit;
                      }

                    } else {

                      if ($db->addHostSetting((int) $argv[3], 'PAGES_LIMIT', (int) $argv[5], time())) {

                        CLI::warning(sprintf(_('%s for hostId %s added:'), $argv[4], $argv[3]));
                        CLI::warning(
                          sprintf(
                            '%s > %s',
                            $argv[4],
                            $argv[5],
                          )
                        );

                        exit;
                      }
                    }

                  break;
                  default:

                    CLI::danger('unsupported host settings key');
                }
              }

            break;

            case 'get':

              if (!empty($argv[3]) && !empty($argv[4])) {

                if ($hostSetting = $db->findHostSetting((int) $argv[3], (string) $argv[4])) {

                  CLI::success(sprintf(_('%s for hostId %s is:'), $argv[4], $argv[3]));
                  CLI::success(
                    is_array($hostSetting->value) ? print_r($hostSetting->value, true) : $hostSetting->value
                  );

                  exit;

                } else {

                  CLI::warning(
                    sprintf(
                      'setting %s for hostId %s not found!',
                      $argv[4],
                      $argv[3]
                    )
                  );

                  exit;
                }
              }

            break;
          }
        }

      break;

    break;
    case 'hostPageSnap':

      if (empty($argv[2])) {

        switch ($argv[2]) {

          case 'repair':

            // @TODO
            CLI::danger(_('this function upgraded but not tested after snaps refactor.'));
            CLI::danger(_('make sure you have backups then remove this alert.'));
            exit;

            switch ($argv[3]) {

              case 'db':

                // Normalize & cleanup DB
                CLI::notice(_('scan database registry for missed snap files...'));

                foreach ($db->getHosts() as $host) {

                  foreach ($db->getHostPages($host->hostId) as $hostPage) {

                    foreach ($db->getHostPageSnaps($hostPage->hostPageId) as $hostPageSnap) {

                      // Prepare filenames
                      $hostPageSnapPath = 'hps/' . substr(trim(chunk_split($hostPageSnap->hostPageSnapId, 1, '/'), '/'), 0, -1);
                      $hostPageSnapFile = $hostPageSnapPath . substr($hostPageSnap->hostPageSnapId, -1) . '.zip';

                      // Define variables
                      $hostPageSnapStorageFilesExists = false;

                      // Check file exists
                      foreach (json_decode(SNAP_STORAGE) as $node => $storages) {

                        foreach ($storages as $location => $storage) {

                          // Generate storage id
                          $crc32name = crc32(sprintf('%s.%s', $node, $location));

                          switch ($node) {

                            case 'localhost':

                              // @TODO implemented, not tested
                              $hostPageSnapFile = $storage->directory . $hostPageSnapFile;

                              if (file_exists($hostPageSnapFile)) {

                                $hostPageSnapStorageFilesExists = true;

                                if (!$db->findHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

                                  if ($db->addHostPageSnapStorage($hostPageSnap->hostPageSnapId, $crc32name, $hostPageSnap->timeAdded)) {

                                    CLI::warning(sprintf(_('add index hostPageSnapId #%s file: %s node: %s location: %s;'), $hostPageSnap->hostPageSnapId, $hostPageSnapFile, $node, $location));
                                  }

                                } else {

                                  CLI::success(sprintf(_('skip related index hostPageSnapId #%s file: %s node: %s location: %s;'), $hostPageSnap->hostPageSnapId, $hostPageSnapFile, $node, $location));
                                }
                              }

                            break;

                            case 'ftp':

                              $ftp = new Ftp();

                              if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {

                                if ($ftp->size($hostPageSnapFile)) {

                                  $hostPageSnapStorageFilesExists = true;

                                  if (!$db->findHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

                                    if ($db->addHostPageSnapStorage($hostPageSnap->hostPageSnapId, $crc32name, $hostPageSnap->timeAdded)) {

                                      CLI::warning(sprintf(_('add index hostPageSnapId #%s file: %s node: %s location: %s;'), $hostPageSnap->hostPageSnapId, $hostPageSnapFile, $node, $location));
                                    }
                                  } else {

                                    CLI::success(sprintf(_('skip related index hostPageSnapId #%s file: %s node: %s location: %s;'), $hostPageSnap->hostPageSnapId, $hostPageSnapFile, $node, $location));
                                  }
                                }

                              // Prevent snap deletion from registry on FTP connection lost
                              } else {

                                CLI::danger(sprintf(_('could not connect to storage %s location %s. operation stopped to prevent the data lose.'), $hostPageSnapStorageName, $location));
                                exit;
                              }

                              $ftp->close();

                            break;
                          }
                        }
                      }

                      // Files not exists
                      if (!$hostPageSnapStorageFilesExists) {

                        // Delete snap from registry
                        try {

                          $db->beginTransaction();

                          foreach ($db->getHostPageSnapStorages($hostPageSnap->hostPageSnapId) as $hostPageSnapStorage) {

                            $db->deleteHostPageSnapDownloads($hostPageSnapStorage->hostPageSnapStorageId);
                          }

                          $db->deleteHostPageSnapStorages($hostPageSnap->hostPageSnapId);
                          $db->deleteHostPageSnap($hostPageSnap->hostPageSnapId);

                          CLI::warning(sprintf(_('delete hostPageSnapId: #%s timeAdded: %s as not found in file storages;'), $hostPageSnap->hostPageSnapId, $hostPageSnap->timeAdded));

                          $db->commit();

                        } catch(Exception $e) {

                          $db->rollBack();

                          var_dump($e);
                        }
                      }
                    }
                  }
                }

              break;
              case 'fs':

                // Cleanup FS
                CLI::notice(_('scan storage for snap files missed in the DB...'));

                // Copy files to each storage
                foreach (json_decode(SNAP_STORAGE) as $node => $storages) {

                  foreach ($storages as $location => $storage) {

                    // Generate storage id
                    $crc32name = crc32(sprintf('%s.%s', $node, $location));

                    switch ($node) {

                      case 'localhost':

                        // @TODO

                      break;

                      case 'ftp':

                        $ftp = new Ftp();

                        if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {

                          foreach ($ftp->nlistr($storage->directory) as $filename) {

                            if (false !== preg_match(sprintf('!/hps/([\d]+)\.zip$!ui', $storage->directory), $filename, $matches)) {

                              if (!empty($matches[1])) { // hostPageSnapId

                                if (!$db->getHostPageSnap($matches[1])) {

                                  if ($ftp->delete($filename)) {

                                    CLI::warning(sprintf(_('delete snap file: #%s from node %s location %s not found in registry;'), $filename, $node, $location));

                                  } else {

                                    CLI::danger(sprintf(_('delete snap file: #%s from node %s location %s not found in registry;'), $filename, $node, $location));
                                  }

                                } else {

                                  CLI::success(sprintf(_('skip snap file: #%s available in node %s location %s;'), $filename, $node, $location));
                                }
                              }
                            }
                          }
                        }

                        $ftp->close();

                      break;
                    }
                  }
                }

                CLI::success(_('missed snap files successfully deleted!'));
              break;
            }
          break;
          case 'reindex':

            //@TODO

          break;
        }
      }

    break;
    case 'hostPage':

      if (!empty($argv[2])) {

        switch ($argv[2]) {

          case 'rank':

            if (!empty($argv[3])) {

              switch ($argv[3]) {

                case 'reindex':

                  CLI::notice(_('hostPage rank fields reindex begin...'));

                  foreach ($db->getHosts() as $host) {

                    foreach ($db->getHostPages($host->hostId) as $hostPage) {

                      // @TODO add common method

                      $hostPageRank = 0;

                      // Get referrers
                      foreach ($db->getHostPagesToHostPageByHostPageIdTarget($hostPage->hostPageId) as $hostPageToHostPageByHostPageIdTarget) {

                        // Get source page details
                        if ($hostPageSource = $db->getHostPage($hostPageToHostPageByHostPageIdTarget->hostPageIdSource)) {

                          // Increase PR on external referrer only
                          if ($hostPageSource->hostId != $hostPage->hostId) {

                            $hostPageRank++;
                          }

                          // Delegate page rank value from redirected pages
                          if (false !== strpos($hostPageSource->httpCode, '30')) {

                            $hostPageRank += $hostPageSource->rank;
                          }
                        }
                      }

                      // Update registry
                      if ($db->updateHostPageRank($hostPage->hostPageId, $hostPageRank)) {

                        CLI::warning(sprintf(_('update hostPage #%s rank from %s to %s;'), $hostPage->hostPageId, $hostPage->rank, $hostPageRank));

                      } else {

                        # CLI::success(sprintf(_('keep hostPage #%s rank %s;'), $hostPage->hostPageId, $hostPageRank));
                      }
                    }
                  }

                  CLI::notice(_('hostPage rank fields successfully updated!'));
                  exit;

                break;
              }
            }

          break;
        }
      }

    break;
    case 'hostPageDom':

      if (empty($argv[2])) {

        CLI::danger(_('action required'));
        exit;
      }

      switch ($argv[2]) {

        case 'generate':

          // Validate hostId
          if (empty($argv[3])) {

            CLI::danger(_('hostId required'));
            exit;
          }

          if (!$db->getHost($argv[3])) {

            CLI::danger(_('hostId not found'));
            exit;
          }

          // Validate selector
          if (empty($argv[4])) {

            CLI::danger(_('CSS selector required'));
            exit;
          }

          // Init variables
          $hostPagesProcessedTotal = 0;
          $hostPagesSkippedTotal   = 0;
          $hostPageDomAddedTotal   = 0;

          // Begin selectors extraction
          foreach ($db->getHostPages($argv[3]) as $hostPage) {

            $hostPagesProcessedTotal++;

            if (false === stripos(Filter::mime($hostPage->mime), 'text/html')) {

              CLI::warning(sprintf(_('not supported MIME type for hostPageId "%s", skipped'), $hostPage->hostPageId));

              $hostPagesSkippedTotal++;

              continue;
            }

            if (!$hostPageDescription = $db->getLastPageDescription($hostPage->hostPageId)) {

              CLI::warning(sprintf(_('last hostPageId "%s" description empty, skipped'), $hostPage->hostPageId));

              $hostPagesSkippedTotal++;

              continue;
            }

            if (empty($hostPageDescription->data)) {

              CLI::warning(sprintf(_('empty hostPageDescription.data value for hostPageId "%s", skipped'), $hostPage->hostPageId));

              $hostPagesSkippedTotal++;

              continue;
            }

            if (!$html = base64_decode($hostPageDescription->data)) {

              CLI::danger(sprintf(_('could not decode base64 for hostPageDescription.data value for hostPageId "%s", skipped'), $hostPage->hostPageId));

              $hostPagesSkippedTotal++;

              continue;
            }

            if (empty($html)) {

              CLI::warning(sprintf(_('empty decoded hostPageDescription.data value for hostPageId "%s", skipped'), $hostPage->hostPageId));

              $hostPagesSkippedTotal++;

              continue;
            }

            // Init crawler
            $crawler = new Symfony\Component\DomCrawler\Crawler();
            $crawler->addHtmlContent($html);

            $selector = trim($argv[4]);

            if ($elements = $crawler->filter($selector)) {

              foreach ($elements as $element) {

                $value = trim($element->nodeValue);
                $value = strip_tags($value, empty($argv[5]) ? null : $argv[5]);

                if (empty($value)) {

                  continue;
                }

                // Save selector value
                $db->addHostPageDom(
                  $hostPage->hostPageId,
                  $selector,
                  $value,
                  time()
                );

                $hostPageDomAddedTotal++;
              }
            }
          }

          CLI::success(sprintf(_('Host pages processed: %s'), $hostPagesProcessedTotal));
          CLI::success(sprintf(_('Host pages skipped: %s'), $hostPagesSkippedTotal));
          CLI::success(sprintf(_('Host page DOM elements added: %s'), $hostPageDomAddedTotal));
          exit;

        break;
        case 'truncate':

          $db->truncateHostPageDom();

          CLI::success(_('hostPageDom table successfully truncated'));
          exit;

        break;
        default:

          CLI::danger(_('unknown action'));
          exit;
      }
    break;
  }
}

// Default message
CLI::default('__  ______________      __');
CLI::default('\ \/ / ____/ ____/___  / /');
CLI::default(' \  / / __/ / __/ __ \/ /' );
CLI::default(' / / /_/ / /_/ / /_/ /_/'  );
CLI::default('/_/\____/\____/\____(_)'   );

CLI::break();
CLI::default('available options:');
CLI::break();
CLI::default('  help                           - this message');
CLI::break();
CLI::default('  db                             ');
CLI::default('    optimize                     - optimize all tables');
CLI::break();
CLI::default('  crontab                        ');
CLI::default('    crawl                        - execute step in crawler queue');
CLI::default('    clean                        - execute step in cleaner queue');
CLI::break();
CLI::default('  hostPage                       ');
CLI::default('    rank                         ');
CLI::default('      reindex                    - reindex hostPage.rank fields');
CLI::break();
CLI::default('  hostSetting                                                         ');
CLI::default('    set [hostId] [key] [value]   - set formatted value by hostId and key');
CLI::default('    get [hostId] [key]           - get formatted value by hostId and key');
CLI::break();
CLI::default('  hostPageSnap                   ');
CLI::default('    repair                       ');
CLI::default('      db                         - scan database registry for new or deprecated snap files');
CLI::default('      fs                         - check all storages for snap files not registered in hostPageSnapStorage, cleanup filesystem');
CLI::default('    reindex                      - search for host pages without snap records, add found pages to the crawl queue');
CLI::break();
CLI::default('  hostPageDom                                     ');
CLI::default('    generate [hostId] [selector] [allowed tags] - generate hostPageDom values based on indexed hostPage.data field');
CLI::default('    truncate                                    - flush hostPageDom table');

CLI::break();

CLI::default('get support: https://github.com/YGGverse/YGGo/issues');

CLI::break();
CLI::break();
