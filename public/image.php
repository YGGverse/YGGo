<?php

require_once('../config/app.php');
require_once('../library/icon.php');

if (isset($_GET['q'])) {

  $hash   = md5($_GET['q']);

  $width  = isset($_GET['width']) ? (int) $_GET['width'] : 16;
  $height = isset($_GET['height']) ? (int) $_GET['height'] : 16;

  $radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 0;

  header("Content-Type: image/webp");

  if (WEBSITE_IDENTICON_IMAGE_CACHE) {

    $filename = dirname(__FILE__) . '/../storage/cache/' . $hash . '.webp';

    if (!file_exists($filename)) {

      $icon = new Icon();

      file_put_contents($filename, $icon->generateImageResource($hash, $width, $height, false, $radius));
    }

    echo file_get_contents($filename);

  } else {

    $icon = new Icon();

    echo $icon->generateImageResource($hash, $width, $height, false, $radius);
  }
}
