<?php

namespace Drupal\config_pages;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Context plugins.
 *
 * @package Drupal\config_pages
 */
class ConfigPagesContextBase extends PluginBase implements ConfigPagesContextInterface, ContainerFactoryPluginInterface {

  /**
   * Get the label of the context.
   *
   * @return string
   *   Return the label of the context.
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * Get the value of the context. Needs to be overridden for concrete context.
   *
   * @return mixed
   *   Return the value of the context.
   */
  public function getValue() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Return an array of available links to switch on the given context.
   *
   * @return array
   *   Return links.
   */
  public function getLinks() {
    return [];
  }

}
