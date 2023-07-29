<?php

class CLI {

  public static function default(string $string) {

    echo sprintf("%s", $string) . PHP_EOL;
  }

  public static function notice(string $string) {

    echo sprintf("\033[36ms%s\033[0m", $string) . PHP_EOL;
  }

  public static function warning(string $string) {

    echo sprintf("\033[33m%s\033[0m", $string) . PHP_EOL;
  }

  public static function danger(string $string) {

    echo sprintf("\033[31m%s\033[31m", $string) . PHP_EOL;
  }

  public static function success(string $string) {

    echo sprintf("\033[32m%s\033[32m", $string) . PHP_EOL;
  }
}