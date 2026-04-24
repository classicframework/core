<?php

namespace classicframework\core;

class Router
{
  protected static $routes = array();

  public static function connect($route, $target)
  {
    self::$routes[] = array(
      'route' => self::normalize_route($route),
      'target' => is_array($target) ? $target : array(),
    );
  }

  public static function routes()
  {
    return self::$routes;
  }

  public static function clear()
  {
    self::$routes = array();
  }

  public static function parse($path)
  {
    $path = self::normalize_route($path);

    foreach (self::$routes as $route) {
      $params = self::match_route($route['route'], $path);

      if ($params !== false) {
        $target = $route['target'];

        if (!isset($target['controller']) || !isset($target['action'])) {
          continue;
        }

        if (!isset($target['params']) || !is_array($target['params'])) {
          $target['params'] = array();
        }

        $target['params'] = array_merge($target['params'], $params);

        return $target;
      }
    }

    return false;
  }

  protected static function normalize_route($route)
  {
    $route = trim((string) $route);

    if ($route === '') {
      return '/';
    }

    if ($route[0] !== '/') {
      $route = '/' . $route;
    }

    if (strlen($route) > 1) {
      $route = rtrim($route, '/');
    }

    return $route;
  }

  protected static function match_route($route, $path)
  {
    if ($route === $path) {
      return array();
    }

    $route_segments = explode('/', trim($route, '/'));
    $path_segments = explode('/', trim($path, '/'));

    if ($route === '/') {
      return $path === '/' ? array() : false;
    }

    $params = array();
    $route_count = count($route_segments);
    $path_count = count($path_segments);

    $i = 0;

    for ($i = 0; $i < $route_count; $i++) {
      $route_segment = isset($route_segments[$i]) ? $route_segments[$i] : null;
      $path_segment = isset($path_segments[$i]) ? $path_segments[$i] : null;

      if ($route_segment === '*') {
        $params[] = implode('/', array_slice($path_segments, $i));
        return $params;
      }

      if ($path_segment === null) {
        return false;
      }

      if ($route_segment !== '' && $route_segment[0] === ':') {
        $params[substr($route_segment, 1)] = $path_segment;
        continue;
      }

      if ($route_segment !== $path_segment) {
        return false;
      }
    }

    if ($path_count !== $route_count) {
      return false;
    }

    return $params;
  }
}