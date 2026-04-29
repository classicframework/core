<?php

namespace classicframework\core;

class Config
{
  protected static $data = array();

  public static function set($key, $value)
  {
    self::$data[(string) $key] = $value;
  }

  public static function get($key, $default = null)
  {
    $key = (string) $key;

    if (array_key_exists($key, self::$data)) {
      return self::$data[$key];
    }

    return $default;
  }

  public static function has($key)
  {
    return array_key_exists((string) $key, self::$data);
  }

  public static function remove($key)
  {
    $key = (string) $key;

    if (array_key_exists($key, self::$data)) {
      unset(self::$data[$key]);
    }
  }

  public static function all()
  {
    return self::$data;
  }

  public static function clear()
  {
    self::$data = array();
  }

  public static function extract($prefix)
  {
    $prefix = (string) $prefix;

    $prefixes = array(
      $prefix . '_',
      '_' . $prefix . '_',
    );

    $result = array();

    foreach (self::$data as $key => $value) {
      foreach ($prefixes as $p) {
        if (strpos($key, $p) === 0) {
          $result[substr($key, strlen($p))] = $value;
          break;
        }
      }
    }

    return $result;
  }
}