<?php

class Sitemap {

  private $_files = [];
  private $_links = [];

  public function __construct(string $filename) {

    $this->_scanFiles($filename);
    $this->_scanLinks();
  }

  private function _scanFiles(string $filename) {

    if ($data = @simplexml_load_file($filename)) {

      if (!empty($data->sitemap)) { // sitemaps index

        foreach ($data->sitemap as $value) {

          if (!empty($value->loc)) {

            $this->_scanFiles(trim(urldecode($value->loc)));
          }
        }

      } else if (!empty($data->url)) { // target file

        $this->_files[trim(urldecode($filename))] = []; // @TODO attributes
      }
    }
  }

  private function _scanLinks() {

    foreach ($this->_files as $filename => $attributes) {

      if ($data = @simplexml_load_file($filename)) {

        if (!empty($data->url)) {

          foreach ($data->url as $value) {

            if (!empty($value->loc)) {

              $this->_links[trim(urldecode($value->loc))] = []; // @TODO attributes
            }
          }
        }
      }
    }
  }

  public function getLinks() {

    return $this->_links;
  }
}