<?php

namespace Drupal\geofield\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Geofield Proximity Source annotation object.
 *
 * @see \Drupal\geofield\Plugin\GeofieldProximitySourceManager
 * @see plugin_api
 *
 * @Annotation
 */
class GeofieldProximitySource extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * A description to show in the exposed form.
   *
   * @var \Drupal\Core\Annotation\Translation
   *   (optional)
   *
   * @ingroup plugin_translatable
   */
  public $exposedDescription;

  /**
   * An array of the view handler plugins type (contexts) it would work for.
   *
   * Possible values:
   *   - filter
   *   - sort
   *   - field
   *   - NULL (all)
   *
   * @var array
   *   (optional)
   *
   * @ingroup plugin_translatable
   */
  public $context;

  /**
   * A flag that specify if the plugin should work only if exposed filter.
   *
   * @var bool
   *   (optional)
   *
   * @ingroup plugin_translatable
   */
  public $exposedOnly;

}
