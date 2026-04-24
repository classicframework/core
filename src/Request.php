<?php

namespace classicframework\core;

class Request
{
  protected $method = 'GET';
  protected $scheme = 'http';
  protected $host = '';
  protected $port = null;
  protected $uri = '/';
  protected $path = '/';
  protected $query_string = '';
  protected $get = array();
  protected $post = array();
  protected $files = array();
  protected $cookie = array();
  protected $server = array();

  public function __construct($server = null, $get = null, $post = null, $files = null, $cookie = null)
  {
    $this->server = is_array($server) ? $server : $_SERVER;
    $this->get = is_array($get) ? $get : $_GET;
    $this->post = is_array($post) ? $post : $_POST;
    $this->files = is_array($files) ? $files : $_FILES;
    $this->cookie = is_array($cookie) ? $cookie : $_COOKIE;

    $this->method = isset($this->server['REQUEST_METHOD']) ? strtoupper($this->server['REQUEST_METHOD']) : 'GET';
    $this->scheme = $this->detect_scheme();
    $this->host = isset($this->server['HTTP_HOST']) ? (string) $this->server['HTTP_HOST'] : '';
    $this->port = isset($this->server['SERVER_PORT']) ? (string) $this->server['SERVER_PORT'] : null;
    $this->uri = isset($this->server['REQUEST_URI']) ? (string) $this->server['REQUEST_URI'] : '/';
    $this->query_string = isset($this->server['QUERY_STRING']) ? (string) $this->server['QUERY_STRING'] : '';
    $this->path = $this->detect_path();
  }

  protected function detect_scheme()
  {
    if (!empty($this->server['HTTPS']) && strtolower((string) $this->server['HTTPS']) !== 'off') {
      return 'https';
    }

    if (isset($this->server['SERVER_PORT']) && (string) $this->server['SERVER_PORT'] === '443') {
      return 'https';
    }

    if (isset($this->server['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $this->server['HTTP_X_FORWARDED_PROTO']) === 'https') {
      return 'https';
    }

    return 'http';
  }

  protected function detect_path()
  {
    $path = parse_url($this->uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
      $path = '/';
    }

    $base_url = (string) Config::get('app_base_url', '/');
    $base_url = trim($base_url);

    if ($base_url !== '' && $base_url !== '/') {
      if (strpos($path, $base_url) === 0) {
        $path = substr($path, strlen($base_url));
      }
    }

    if ($path === '' || $path === false) {
      $path = '/';
    }

    if ($path[0] !== '/') {
      $path = '/' . $path;
    }

    return $path;
  }

  public function method()
  {
    return $this->method;
  }

  public function scheme()
  {
    return $this->scheme;
  }

  public function host()
  {
    return $this->host;
  }

  public function port()
  {
    return $this->port;
  }

  public function uri()
  {
    return $this->uri;
  }

  public function path()
  {
    return $this->path;
  }

  public function query_string()
  {
    return $this->query_string;
  }

  public function is($method)
  {
    return $this->method === strtoupper((string) $method);
  }

  public function get($key, $default = null)
  {
    if ($key === null) {
      return $this->get;
    }

    return array_key_exists($key, $this->get) ? $this->get[$key] : $default;
  }

  public function post($key, $default = null)
  {
    if ($key === null) {
      return $this->post;
    }

    return array_key_exists($key, $this->post) ? $this->post[$key] : $default;
  }

  public function file($key, $default = null)
  {
    if ($key === null) {
      return $this->files;
    }

    return array_key_exists($key, $this->files) ? $this->files[$key] : $default;
  }

  public function cookie($key, $default = null)
  {
    if ($key === null) {
      return $this->cookie;
    }

    return array_key_exists($key, $this->cookie) ? $this->cookie[$key] : $default;
  }

  public function server($key, $default = null)
  {
    if ($key === null) {
      return $this->server;
    }

    return array_key_exists($key, $this->server) ? $this->server[$key] : $default;
  }

  public function input($key, $default = null)
  {
    if (array_key_exists($key, $this->post)) {
      return $this->post[$key];
    }

    if (array_key_exists($key, $this->get)) {
      return $this->get[$key];
    }

    return $default;
  }

  public function full_url()
  {
    $url = $this->scheme . '://' . $this->host;

    if ($this->port !== null && $this->port !== '' && $this->port !== '80' && $this->port !== '443') {
      $url .= ':' . $this->port;
    }

    $url .= $this->uri;

    return $url;
  }
}