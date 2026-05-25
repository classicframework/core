<?php

namespace classicframework\core;

class Controller
{
  protected $request = null;
  protected $view = null;
  protected $controller_name = '';
  protected $action_name = '';
  protected $layout = 'default';
  protected $view_path = '';
  protected $view_data = array();

  public function __construct($request = null)
  {
    $app = App::instance();

    $this->request = $request instanceof Request ? $request : ($app instanceof App ? $app->request() : new Request());

    $this->controller_name = $this->detect_controller_name();
    $this->view_path = $this->detect_view_path();
    $this->view = new View(array(
      'view_base_path' => $this->view_path,
      'layout' => $this->layout,
      'controller_name' => $this->controller_name,
      'action_name' => $this->action_name,
    ));

    $this->initialize();
  }

  protected function initialize()
  {
  }

  public function app()
  {
    return App::instance();
  }

  public function service($name, $default = null)
  {
    $app = $this->app();

    if (!$app instanceof App) {
      return $default;
    }

    return $app->get_service($name, $default);
  }

  public function request()
  {
    return $this->request;
  }

  protected function detect_controller_name()
  {
    $class_name = get_class($this);
    $parts = explode('\\', $class_name);
    $short_name = end($parts);

    if (substr($short_name, -10) === 'Controller') {
      $short_name = substr($short_name, 0, -10);
    }

    return $short_name;
  }

  protected function detect_view_path()
  {
    if (defined('APP_PATH')) {
      return APP_PATH . DIRECTORY_SEPARATOR . 'views';
    }

    return getcwd();
  }

  public function set_action_name($action_name)
  {
    $this->action_name = (string) $action_name;

    if ($this->view instanceof View) {
      $this->view = new View(array(
        'view_base_path' => $this->view_path,
        'layout' => $this->layout,
        'controller_name' => $this->controller_name,
        'action_name' => $this->action_name,
      ));
    }
  }

  public function set($key, $value)
  {
    $this->view_data[(string) $key] = $value;
  }

  public function set_many($data)
  {
    if (!is_array($data)) {
      return;
    }

    foreach ($data as $key => $value) {
      $this->view_data[(string) $key] = $value;
    }
  }

  public function render($template = null, $data = null, $layout = null)
  {
    $this->_before_render();

    $template = $template !== null ? (string) $template : $this->default_template();
    $data = is_array($data) ? $data : array();
    $layout = $layout !== null ? (string) $layout : $this->layout;

    $view_data = array_merge($this->view_data, $data);

    if (!isset($view_data['body_class'])) {
      $view_data['body_class'] = $this->build_body_class();
    }

    $html = $this->view->render($template, $view_data, $layout);

    return $this->_after_render($html);
  }

  public function element($path, $data = null)
  {
    $data = is_array($data) ? $data : array();
    return $this->view->element($path, $data);
  }

  public function redirect($url, $status_code = null)
  {
    if ($status_code === null) {
      header('Location: ' . (string) $url);
      exit;
    }

    header('Location: ' . (string) $url, true, (int) $status_code);
    exit;
  }

  protected function default_template()
  {
    return $this->underscore($this->controller_name) . '/' . $this->underscore($this->action_name);
  }

  protected function build_body_class()
  {
    $classes = array();

    if ($this->layout !== '') {
      $classes[] = $this->underscore($this->layout) . '-layout';
    }

    if ($this->controller_name !== '') {
      $classes[] = $this->underscore($this->controller_name) . '-controller';
    }

    if ($this->action_name !== '') {
      $classes[] = $this->underscore($this->action_name) . '-action';
    }

    return implode(' ', $classes);
  }

  protected function underscore($value)
  {
    $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', (string) $value);
    $value = preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $value);
    $value = strtolower(trim($value, '_'));

    return $value;
  }

  public function __get($name)
  {
    $app = App::instance();

    if (!$app instanceof App) {
      return null;
    }

    if ($app->has_service($name)) {
      return $app->get_service($name);
    }

    return $app->resolve($name, null);
  }

  public function call_before_filter()
  {
    $response = $this->_before_filter();

    if ($response !== null) {
      return $response;
    }

    return $this->call_controller_filters('before');
  }

  public function call_controller_filters($type)
  {
    $app = $this->app();

    if (!$app instanceof App || !method_exists($app, 'controller_filters')) {
      return null;
    }

    $filters = $app->controller_filters($type);

    foreach ($filters as $filter) {
      if (!is_callable($filter)) {
        continue;
      }

      $response = call_user_func($filter, $this);

      if ($response !== null) {
        return $response;
      }
    }

    return null;
  }

  public function action_name()
  {
    return $this->action_name;
  }

  public function call_after_filter()
  {
    return $this->_after_filter();
  }

  protected function _before_filter()
  {
    return null;
  }

  protected function _after_filter()
  {
    return null;
  }

  protected function _before_render()
  {
    return null;
  }

  protected function _after_render($html)
  {
    return $html;
  }

  protected function config($key, $default = null)
  {
    return Config::get($key, $default);
  }

  protected function has_config($key)
  {
    return Config::has($key);
  }

  protected function set_config($key, $value)
  {
    Config::set($key, $value);
  }


}