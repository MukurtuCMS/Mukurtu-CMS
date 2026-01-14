<?php

namespace Drupal\mukurtu_collection\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a menu link plugin for collection hierarchies.
 *
 * This menu link is read-only and derived from the collection hierarchy.
 */
class CollectionMenuItem extends MenuLinkBase implements ContainerFactoryPluginInterface {

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
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    // This menu link is not overridable.
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {
    // This menu link cannot be deleted as it's derived from content.
  }

}
