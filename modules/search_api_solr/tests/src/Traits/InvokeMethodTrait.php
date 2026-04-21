<?php

namespace Drupal\Tests\search_api_solr\Traits;

/**
 * Provides a function to invoke protected/private methods of a class.
 */
trait InvokeMethodTrait {

  /**
   * Calls protected/private method of a class.
   *
   * @param object &$object
   *   Instantiated object that we will run method on.
   * @param string $methodName
   *   Method name to call.
   * @param array $parameters
   *   Array of parameters to pass into method.
   * @param array $protectedProperties
   *   Array of values that should be set on protected properties.
   *
   * @return mixed
   *   Method return.
   */
  protected function invokeMethod(&$object, $methodName, array $parameters = [], array $protectedProperties = []) {
    $reflection = new \ReflectionClass(get_class($object));

    foreach ($protectedProperties as $property => $value) {
      $property = $reflection->getProperty($property);
      $property->setAccessible(TRUE);
      $property->setValue($object, $value);
    }

    $method = $reflection->getMethod($methodName);
    $method->setAccessible(TRUE);

    return $method->invokeArgs($object, $parameters);
  }

}
