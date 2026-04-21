<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a DataProvider annotation object.
 *
 * @see \Drupal\geolocation\DataProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class DataProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the DataProvider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the DataProvider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
