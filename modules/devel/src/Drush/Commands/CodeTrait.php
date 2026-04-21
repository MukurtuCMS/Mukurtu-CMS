<?php

namespace Drupal\devel\Drush\Commands;

trait CodeTrait {

  /**
   * Get source code line for specified function or method.
   */
  public function codeLocate($function_name): array {
    // Get implementations in the .install files as well.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    drupal_load_updates();

    if (!str_contains($function_name, '::')) {
      if (!function_exists($function_name)) {
        throw new \Exception(dt('Function not found'));
      }

      $reflect = new \ReflectionFunction($function_name);
    }
    else {
      [$class, $method] = explode('::', $function_name);
      if (!method_exists($class, $method)) {
        throw new \Exception(dt('Method not found'));
      }

      $reflect = new \ReflectionMethod($class, $method);
    }

    return [
      'file' => $reflect->getFileName(),
      'startline' => $reflect->getStartLine(),
      'endline' => $reflect->getEndLine(),
    ];
  }

}
