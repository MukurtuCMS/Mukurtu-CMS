<?php

namespace Drupal\search_api\DataType;

use Drupal\search_api\Plugin\HideablePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other data type classes may extend.
 *
 *  Plugins extending this class need to provide the plugin definition using the
 *  \Drupal\search_api\Attribute\SearchApiBackend attribute. These definitions
 *  may be altered using the "search_api.gathering_data_types" event.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * #[SearchApiDataType(
 *   id: 'my_data_type',
 *   label: new TranslatableMarkup('My data type'),
 *   description: new TranslatableMarkup('Some information about my data type'),
 *   fallback_type: 'string'
 * )]
 * @endcode
 *
 * Search API comes with a couple of default data types. These have an extra
 * "default" property in the annotation. It is not allowed for custom data type
 * plugins to set this property.
 *
 * @see \Drupal\search_api\Attribute\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see \Drupal\search_api\Event\SearchApiEvents::GATHERING_DATA_TYPES
 * @see plugin_api
 */
abstract class DataTypePluginBase extends HideablePluginBase implements DataTypeInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackType() {
    return $this->pluginDefinition['fallback_type'] ?? 'string';
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return !empty($this->pluginDefinition['default']);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'];
  }

}
