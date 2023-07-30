<?php

require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/icon.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/ftp.php');

$type = !empty($_GET['type']) ? $_GET['type'] : false;

switch ($type) {

  case 'identicon':

    $query  = md5($_GET['query']);

    $width  = isset($_GET['width']) ? (int) $_GET['width'] : 16;
    $height = isset($_GET['height']) ? (int) $_GET['height'] : 16;

    $radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 0;

    header('Content-Type: image/webp');

    if (WEBSITE_IDENTICON_IMAGE_CACHE) {

      $filename = __DIR__ . '/../storage/cache/' . $query . '.webp';

      if (!file_exists($filename)) {

        $icon = new Icon();

        file_put_contents($filename, $icon->generateImageResource($query, $width, $height, false, $radius));
      }

      echo file_get_contents($filename);

    } else {

      $icon = new Icon();

      echo $icon->generateImageResource($query, $width, $height, false, $radius);
    }

  break;
  case 'snap':

    // Connect database
    $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

    // Init request
    $crc32ip = crc32(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    // Get snap details from DB
    if ($hostPageSnap = $db->getHostPageSnap(!empty($_GET['hps']) ? (int) $_GET['hps'] : 0)) {

      // Get file
      $snapFile = 'hp/' . chunk_split($hostPageSnap->hostPageId, 1, '/') . $hostPageSnap->timeAdded . '.zip';

      // Get snap file
      foreach (json_decode(SNAP_STORAGE) as $name => $storages) {

        foreach ($storages as $i => $storage) {

          // Generate storage id
          $crc32name = crc32(sprintf('%s.%s', $name, $i));

          if ($hostPageSnapStorage = $db->getHostPageSnapStorageByCRC32Name($hostPageSnap->hostPageSnapId, $crc32name)) {

            switch ($name) {

              case 'localhost':

                // Download local snap in higher priority if possible
                if (file_exists($storage->directory . $snapFile) &&
                    is_readable($storage->directory . $snapFile)) {

                    // Register snap download
                    $db->addHostPageSnapDownload($hostPageSnapStorage->hostPageSnapStorageId, $crc32ip, time());

                    // Return snap file
                    header('Content-Type: application/zip');
                    header(sprintf('Content-Length: %s', $snapSize));
                    header(sprintf('Content-Disposition: filename="snap.%s.%s.%s.zip"', $hostPageSnap->hostPageSnapId,
                                                                                        $hostPageSnap->hostPageId,
                                                                                        $hostPageSnap->timeAdded));
                    readfile($storage->directory . $snapFile);

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
                  header(sprintf('Content-Length: %s', $snapSize));
                  header(sprintf('Content-Disposition: filename="snap.%s.%s.%s.zip"', $hostPageSnap->hostPageSnapId,
                                                                                      $hostPageSnap->hostPageId,
                                                                                      $hostPageSnap->timeAdded));

                  $ftp->get($snapFile, 'php://output');

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
