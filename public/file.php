<?php

require_once('../config/app.php');
require_once('../library/icon.php');
require_once('../library/mysql.php');
require_once('../library/ftp.php');

$type = !empty($_GET['type']) ? $_GET['type'] : false;

switch ($type) {

  case 'identicon':

    $query  = md5($_GET['query']);

    $width  = isset($_GET['width']) ? (int) $_GET['width'] : 16;
    $height = isset($_GET['height']) ? (int) $_GET['height'] : 16;

    $radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 0;

    header('Content-Type: image/webp');

    if (WEBSITE_IDENTICON_IMAGE_CACHE) {

      $filename = dirname(__FILE__) . '/../storage/cache/' . $query . '.webp';

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

    // Get snap details from DB
    if ($hostPageSnap = $db->getHostPageSnap(!empty($_GET['hps']) ? (int) $_GET['hps'] : 0)) {

      // Init variables
      $crc32ip = crc32(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
      $time    = time();

      $hostPageDownloadsTotalSize = $db->findHostPageSnapDownloadsTotalSize($crc32ip, $time - WEBSITE_QUOTA_IP_SNAP_DOWNLOAD_TOTAL_SIZE_TIME_OFFSET);

      // Check for downloading quotas
      if ($hostPageDownloadsTotalSize >= WEBSITE_QUOTA_IP_SNAP_DOWNLOAD_TOTAL_SIZE) {

        header('HTTP/1.0 403 Forbidden');

        echo _('403 Access forbidden by requests quota');

        exit;
      }

      // Register snap download
      $hostPageSnapDownloadId = $db->addHostPageSnapDownload($hostPageSnap->hostPageSnapId, $crc32ip, $time);

      // Init variables
      $snapSize = 0;
      $snapFile = 'hp/' . chunk_split($hostPageSnap->hostPageId, 1, '/') . $hostPageSnap->timeAdded . '.zip';

      // Download local snap in higher priority if possible
      if ($hostPageSnap->storageLocal && file_exists('../storage/snap/' . $snapFile) &&
                                        is_readable('../storage/snap/' . $snapFile)) {

        $snapSize = (int) @filesize('../storage/snap/' . $snapFile);

        $db->updateHostPageSnapDownload($hostPageSnapDownloadId, 'local', $snapSize, 200);

        header('Content-Type: application/zip');
        header(sprintf('Content-Length: %s', $snapSize));
        header(sprintf('Content-Disposition: filename="snap.%s.%s.%s.zip"', $hostPageSnap->hostPageSnapId,
                                                                            $hostPageSnap->hostPageId,
                                                                            $hostPageSnap->timeAdded));
        readfile('../storage/snap/' . $snapFile);

      // Then try to download from MEGA storage if exists
      } else if ($hostPageSnap->storageMega) {

        $ftp = new Ftp();

        if ($ftp->connect(MEGA_FTP_HOST, MEGA_FTP_PORT, null, null, MEGA_FTP_DIRECTORY)) {

          if ($snapSize = $ftp->size($snapFile)) {

            $db->updateHostPageSnapDownload($hostPageSnapDownloadId, 'mega', $snapSize, 200);

            header('Content-Type: application/zip');
            header(sprintf('Content-Length: %s', $snapSize));
            header(sprintf('Content-Disposition: filename="snap.%s.%s.%s.zip"', $hostPageSnap->hostPageSnapId,
                                                                                $hostPageSnap->hostPageId,
                                                                                $hostPageSnap->timeAdded));

            $ftp->get($snapFile, 'php://output');

          } else {

            $db->updateHostPageSnapDownload($hostPageSnapDownloadId, 'mega', $snapSize, 404);

            header('HTTP/1.0 404 Not Found');

            echo _('404 File not found');
          }

        } else {

          $db->updateHostPageSnapDownload($hostPageSnapDownloadId, 'mega', $snapSize, 404);

          header('HTTP/1.0 404 Not Found');

          echo _('404 File not found');
        }

      // Return 404 when file not found
      } else {

        $db->updateHostPageSnapDownload($hostPageSnapDownloadId, 'other', $snapSize, 404);

        header('HTTP/1.0 404 Not Found');

        echo _('404 File not found');
      }

    } else {

      header('HTTP/1.0 404 Not Found');

      echo _('404 Snap not found');
    }

  break;
  default:

    header('HTTP/1.0 404 Not Found');

    echo _('404');
}
