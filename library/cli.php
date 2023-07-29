<?php

class CLI {

  public static function brake() {

    echo PHP_EOL;
  }

  public static function default(string $string) {

    echo sprintf("%s", $string) . self::brake();
  }

  public static function notice(string $string) {

    echo sprintf("\033[36m%s\033[0m", $string) . self::brake();
  }

  public static function warning(string $string) {

    echo sprintf("\033[33m%s\033[0m", $string) . self::brake();
  }

  public static function danger(string $string) {

    echo sprintf("\033[31m%s\033[31m", $string) . self::brake();
  }

  public static function success(string $string) {

    echo sprintf("\033[32m%s\033[32m", $string) . self::brake();
  }
}