<?php

// Load system dependencies
require_once('../config/app.php');
require_once('../library/filter.php');
require_once('../library/sqlite.php');

// Connect database
$db = new SQLite(DB_NAME, DB_USERNAME, DB_PASSWORD);

$totalPages = $db->getTotalPages();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            ]);
?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US') ?>">
  <head>
    <title><?php echo _('YGGo! Web Search Engine') ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="Description" content="<?php echo _('Javascript-less Open Source Web Search Engine') ?>" />
    <meta name="Keywords" content="<?php echo _('web, search, engine, crawler, php, pdo, sqlite, fts5, yggdrasil, js-less, open source') ?>" />
    <style>

      * {
        border: 0;
        margin: 0;
        padding: 0;
        font-family: Sans-serif;
      }

      body {
        background-color: #2e3436
      }

      h1 {
        color: #fff;
        font-weight: normal;
        font-size: 48px;
        margin: 16px 0
      }

      form {
        display: block;
        max-width: 640px;
        margin: 280px auto;
        text-align: center;
      }

      input {
        width: 100%;
        margin: 16px 0;
        padding: 18px 0;
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
        color: #090808;
      }

      button {
        margin: 22px 0;
        padding: 12px 16px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      footer {
        position: fixed;
        bottom: 0;
        left:0;
        right: 0;
        text-align: center;
        padding: 24px;
      }

      a, a:visited, a:active {
        color: #ccc;
      }

      a:hover {
        color: #fff;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><?php echo _('YGGo!') ?></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit"><?php echo _('Search') ?></button>
      </form>
    </header>
    <footer>
      <a href="https://github.com/d47081/YGGo/issues"><?php echo _('meow') ?></a>
    </footer>
  </body>
</html>