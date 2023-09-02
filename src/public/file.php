<?php

require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/ftp.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

$type = !empty($_GET['type']) ? $_GET['type'] : false;

switch ($type) {

  case 'identicon':

    if (!empty($_GET['query']))
    {
      $icon = new Jdenticon\Identicon();

      $icon->setValue(urldecode($_GET['query']));
      $icon->setSize(16);
      $icon->setStyle(
        [
          'backgroundColor' => 'rgba(255, 255, 255, 0)',
        ]
      );

      $icon->displayImage('webp');
    }

  break;
  case 'snap':

    // Connect database
    try {

      $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

    } catch(Exception $e) {

      var_dump($e);

      exit;
    }
    // Init request
    $crc32ip = crc32(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    // Get snap details from DB
    if ($hostPageSnap = $db->getHostPageSnap(!empty($_GET['hps']) ? (int) $_GET['hps'] : 0)) {

      // Prepare filenames
      $hostPageSnapPath = 'hps/' . substr(trim(chunk_split($hostPageSnap->hostPageSnapId, 1, '/'), '/'), 0, -1);
      $hostPageSnapFile = $hostPageSnapPath . substr($hostPageSnap->hostPageSnapId, -1) . '.zip';

      // Get snap file
      foreach (json_decode(SNAP_STORAGE) as $node => $storages) {

        foreach ($storages as $location => $storage) {

          // Generate storage id
          $crc32name = crc32(sprintf('%s.%s', $node, $location));

          if ($hostPageSnapStorage = $db->findHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

            switch ($node) {

              case 'localhost':

                // Download local snap in higher priority if possible
                if (file_exists($storage->directory . $hostPageSnapFile) &&
                    is_readable($storage->directory . $hostPageSnapFile)) {

                    // Register snap download
                    $db->addHostPageSnapDownload($hostPageSnapStorage->hostPageSnapStorageId, $crc32ip, time());

                    // Return snap file
                    header('Content-Type: application/zip');
                    header(sprintf('Content-Length: %s', filesize($storage->directory . $hostPageSnapFile)));
                    header(sprintf('Content-Disposition: filename="snap.%s.zip"', $hostPageSnap->hostPageSnapId));
                    readfile($storage->directory . $hostPageSnapFile);

                    exit;
                }

              break;
              case 'ftp':

                $ftp = new Ftp();

                if ($ftp->connect($storage->host, $storage->port, $storage->username, $storage->password, $storage->directory, $storage->timeout, $storage->passive)) {

                  // Register snap download
                  $db->addHostPageSnapDownload($hostPageSnapStorage->hostPageSnapStorageId, $crc32ip, time());

                  // Return snap file
                  header('Content-Type: application/zip');
                  header(sprintf('Content-Length: %s', $ftp->size($hostPageSnapFile)));
                  header(sprintf('Content-Disposition: filename="snap.%s.zip"', $hostPageSnap->hostPageSnapId));

                  $ftp->get($hostPageSnapFile, 'php://output');

                  $ftp->close();

                  exit;
                }

              break;
            }
          }
        }
      }
    }

    header('HTTP/1.0 404 Not Found');

    echo _('404 Snap not found');

  break;

  default:

    header('HTTP/1.0 404 Not Found');

    echo _('404');
}
