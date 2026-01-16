<?php

declare(strict_types=1);

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
  public function getTitle(): string {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist): array {
    // This menu link is not overridable.
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink(): void {
    // This menu link cannot be deleted as it's derived from content.
  }

}
