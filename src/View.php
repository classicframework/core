<?php

namespace classicframework\core;

class View
{
  protected $view_base_path = '';
  protected $layout = 'default';
  protected $controller_name = '';
  protected $action_name = '';
  protected $blocks = array();
  protected $block_stack = array();
  protected $current_block = null;

  public function __construct($options = array())
  {
    $this->view_base_path = isset($options['view_base_path']) ? (string) $options['view_base_path'] : '';
    $this->layout = isset($options['layout']) ? (string) $options['layout'] : 'default';
    $this->controller_name = isset($options['controller_name']) ? (string) $options['controller_name'] : '';
    $this->action_name = isset($options['action_name']) ? (string) $options['action_name'] : '';
  }

  public function set_layout($layout)
  {
    $this->layout = (string) $layout;
  }

  public function get_layout()
  {
    return $this->layout;
  }

  public function render($template, $data, $layout)
  {
    $template_file = $this->resolve_template_file($template);

    if (!is_file($template_file)) {
      throw new \Exception('View template not found: ' . $template_file);
    }

    $this->layout = (string) $layout;

    $content = $this->render_file($template_file, $data);

    if ($this->layout === '' || $this->layout === false || $this->layout === null) {
      return $content;
    }

    $layout_file = $this->resolve_layout_file($this->layout);

    if (!is_file($layout_file)) {
      throw new \Exception('View layout not found: ' . $layout_file);
    }

    $layout_data = $data;
    $layout_data['content'] = $content;

    if (!isset($layout_data['body_class'])) {
      $layout_data['body_class'] = $this->build_body_class();
    }

    return $this->render_file($layout_file, $layout_data);
  }

  public function element($path, $data = null)
  {
    $file = $this->view_base_path . DIRECTORY_SEPARATOR . '_elements' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) . '.php';

    if (!is_file($file)) {
      throw new \Exception('View element not found: ' . $file);
    }

    return $this->render_file($file, is_array($data) ? $data : array());
  }

  public function start($name)
  {
    $this->current_block = (string) $name;
    $this->block_stack[] = $this->current_block;

    ob_start();
  }

  public function end()
  {
    if (empty($this->block_stack)) {
      return;
    }

    $name = array_pop($this->block_stack);
    $content = ob_get_clean();

    if (!isset($this->blocks[$name])) {
      $this->blocks[$name] = '';
    }

    $this->blocks[$name] .= $content;
    $this->current_block = empty($this->block_stack) ? null : end($this->block_stack);
  }

  public function assign($name, $content)
  {
    $this->blocks[(string) $name] = (string) $content;
  }

  public function append($name, $content)
  {
    $name = (string) $name;

    if (!isset($this->blocks[$name])) {
      $this->blocks[$name] = '';
    }

    $this->blocks[$name] .= (string) $content;
  }

  public function fetch($name, $clear = false)
  {
    $name = (string) $name;
    $clear = (bool) $clear;

    if (!isset($this->blocks[$name])) {
      return '';
    }

    $content = $this->blocks[$name];

    if ($clear) {
      $this->blocks[$name] = '';
    }

    return $content;
  }

  public function build_body_class()
  {
    $classes = array();

    if ($this->controller_name !== '') {
      $classes[] = $this->to_body_class($this->controller_name);
    }

    if ($this->action_name !== '') {
      $classes[] = $this->to_body_class($this->action_name);
    }

    return implode(' ', $classes);
  }

  protected function to_body_class($value)
  {
    $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', (string) $value);
    $value = str_replace('\\', '_', $value);
    $value = str_replace('/', '_', $value);
    $value = preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
    $value = strtolower(trim($value, '_'));

    return $value;
  }

  protected function resolve_template_file($template)
  {
    return $this->view_base_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $template) . '.php';
  }

  protected function resolve_layout_file($layout)
  {
    return $this->view_base_path . DIRECTORY_SEPARATOR . '_layouts' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $layout) . '.php';
  }

  protected function render_file($file, $data)
  {
    if (is_array($data) && !empty($data)) {
      extract($data, EXTR_SKIP);
    }

    ob_start();
    include $file;
    return ob_get_clean();
  }
}