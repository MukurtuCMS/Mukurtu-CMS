<?php

namespace Drupal\search_api_test;

use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\IndexInterface;

/**
 * Holds method overrides for test plugins.
 */
class MethodOverrides {

  /**
   * Saved arguments of saveMethodArguments().
   *
   * @see static::genericMethod()
   */
  public static array $methodArgs = [];

  /**
   * The return value of saveMethodArguments().
   *
   * @see static::genericMethod()
   */
  public static mixed $returnValue = NULL;

  /**
   * Provides a generic method override for the test backend.
   *
   * @param \Drupal\search_api\Backend\BackendInterface $backend
   *   The backend plugin on which the method was called.
   *
   * @return true
   *   Always returns TRUE, to cater to those methods that expect a return
   *   value.
   *
   * @throws \ErrorException
   */
  public static function overrideTestBackendMethod(BackendInterface $backend) {
    if ($backend->getConfiguration() !== ['test' => 'foobar']) {
      throw new \ErrorException('Critical server method called with incorrect backend configuration.');
    }
    return TRUE;
  }

  /**
   * Provides an override for the test backend's indexItems() method.
   *
   * @param \Drupal\search_api\Backend\BackendInterface $backend
   *   The backend plugin on which the method was called.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items should be indexed.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be indexed, keyed by their item IDs.
   *
   * @return string[]
   *   The array keys of $items.
   *
   * @throws \ErrorException
   */
  public static function overrideTestBackendIndexItems(BackendInterface $backend, IndexInterface $index, array $items) {
    if ($backend->getConfiguration() !== ['test' => 'foobar']) {
      throw new \ErrorException('Server method indexItems() called with incorrect backend configuration.');
    }
    return array_keys($items);
  }

  /**
   * Saves the arguments passed to this method.
   *
   * @return mixed
   *   Returns static::$returnValue.
   *
   * @see static::$methodArgs
   * @see static::$returnValue
   */
  public static function genericMethod(): mixed {
    static::$methodArgs = func_get_args();
    return static::$returnValue;
  }

  /**
   * Throws a type error when called.
   *
   * @throws \TypeError
   *   Always.
   */
  public static function throwTypeError(): never {
    throw new \TypeError('Foobar');
  }

}
