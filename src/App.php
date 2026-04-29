<?php

namespace classicframework\core;

class App
{
  protected static $instance = null;

  protected $request = null;
  protected $services = array();

  public function __construct($request = null)
  {
    self::$instance = $this;

    if ($request instanceof Request) {
      $this->request = $request;
    }
  }

  public static function instance()
  {
    return self::$instance;
  }

  public function request()
  {
    if (!$this->request instanceof Request) {
      $this->request = new Request();
    }

    return $this->request;
  }

  public function set_service($name, $service)
  {
    $this->services[(string) $name] = $service;
  }

  public function get_service($name, $default = null)
  {
    $name = (string) $name;

    if (array_key_exists($name, $this->services)) {
      return $this->services[$name];
    }

    return $default;
  }

  public function has_service($name)
  {
    return array_key_exists((string) $name, $this->services);
  }

  public function bootstrap()
  {
    $this->load_config_files();
    $this->load_enabled_bridges();
    $this->apply_runtime_config();
    $this->load_route_files();
  }

  public function run()
  {
    $this->bootstrap();

    $request = $this->request();

    $route = Router::parse($request->path());

    if ($route === false) {
      $this->render_error(404, 'Page not found');
      return;
    }

    $controller_name = isset($route['controller']) ? (string) $route['controller'] : '';
    $action_name = isset($route['action']) ? (string) $route['action'] : '';
    $params = isset($route['params']) && is_array($route['params']) ? array_values($route['params']) : array();

    if ($controller_name === '' || $action_name === '') {
      $this->render_error(500, 'Invalid route target');
      return;
    }

    $controller_class = 'app\\controllers\\' . $controller_name . 'Controller';

    if (!class_exists($controller_class)) {
      $this->render_error(404, 'Controller not found');
      return;
    }

    $controller = new $controller_class($request);

    if (!method_exists($controller, $action_name)) {
      $this->render_error(404, 'Action not found');
      return;
    }

    if (method_exists($controller, 'set_action_name')) {
      $controller->set_action_name($action_name);
    }

    $filter_response = $controller->call_before_filter();

    if ($filter_response !== null) {
      echo $filter_response;
      return;
    }

    $response = call_user_func_array(array($controller, $action_name), $params);

    $controller->call_after_filter();

    if ($response !== null) {
      echo $response;
    }
  }

  protected function load_config_files()
  {
    if (!defined('APP_PATH')) {
      return;
    }

    $config_path = APP_PATH . DIRECTORY_SEPARATOR . 'config';

    $files = $this->glob_files($config_path . DIRECTORY_SEPARATOR . '*.php');

    foreach ($files as $file) {
      require_once $file;
    }

    $custom_files = $this->glob_files($config_path . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . '*.php');

    foreach ($custom_files as $file) {
      require_once $file;
    }
  }

  protected function load_enabled_bridges()
  {
    $config = Config::all();

    foreach ($config as $key => $value) {
      if (substr($key, -8) !== '_enabled') {
        continue;
      }

      if ($value !== true) {
        continue;
      }

      $package = substr($key, 0, -8);
      $class = 'classicframework\\' . $package . '\\Bridge';

      if (!class_exists($class)) {
        continue;
      }

      if (method_exists($class, 'register')) {
        call_user_func(array($class, 'register'), $this);
      }
    }
  }

  protected function apply_runtime_config()
  {
    $timezone = (string) Config::get('_app_timezone', '');

    if ($timezone !== '') {
      date_default_timezone_set($timezone);
    }

    $this->apply_error_handling();
  }

  protected function apply_error_handling()
  {
    $env = (string) Config::get('_app_env', 'prod');

    if ($env === 'dev') {
      error_reporting(E_ALL);
      ini_set('display_errors', '1');
      ini_set('display_startup_errors', '1');
    } else {
      error_reporting(0);
      ini_set('display_errors', '0');
      ini_set('display_startup_errors', '0');
    }

    $log_dir = APP_PATH . '/logs';

    if (!is_dir($log_dir)) {
      @mkdir($log_dir, 0777, true);
    }

    $log_file = $log_dir . '/error.log';

    ini_set('log_errors', '1');
    ini_set('error_log', $log_file);
  }

  protected function load_route_files()
  {
    if (!defined('APP_PATH')) {
      return;
    }

    $route_path = APP_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes';
    $files = $this->glob_files($route_path . DIRECTORY_SEPARATOR . '*.php');

    foreach ($files as $file) {
      require_once $file;
    }
  }

  protected function glob_files($pattern)
  {
    $files = glob($pattern);

    if (!is_array($files)) {
      return array();
    }

    usort($files, function ($a, $b) {
      $a_name = basename($a);
      $b_name = basename($b);

      $a_is_underscore = strpos($a_name, '_') === 0;
      $b_is_underscore = strpos($b_name, '_') === 0;

      if ($a_is_underscore && !$b_is_underscore) {
        return -1;
      }

      if (!$a_is_underscore && $b_is_underscore) {
        return 1;
      }

      return strcmp($a_name, $b_name);
    });

    return $files;
  }

  protected function render_error($error_code, $error_message)
  {
    $error_code = (int) $error_code;
    $error_message = (string) $error_message;

    if (!headers_sent()) {
      $this->send_status_code($error_code);
      header('X-Robots-Tag: noindex, nofollow', true);
    }

    $error_file = '';

    if (defined('APP_PATH')) {
      $error_file = APP_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error.php';
    }

    if ($error_file !== '' && is_file($error_file)) {
      $error_code_value = $error_code;
      $error_message_value = $error_message;

      $error_code = $error_code_value;
      $error_message = $error_message_value;

      include $error_file;
      return;
    }

    echo $error_code . ' ' . $error_message;
  }

  protected function send_status_code($status_code)
  {
    $status_code = (int) $status_code;

    if (function_exists('http_response_code')) {
      http_response_code($status_code);
      return;
    }

    $texts = array(
      404 => 'Not Found',
      500 => 'Internal Server Error',
    );

    $text = isset($texts[$status_code]) ? $texts[$status_code] : 'Error';
    $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

    header($protocol . ' ' . $status_code . ' ' . $text, true, $status_code);
  }
}