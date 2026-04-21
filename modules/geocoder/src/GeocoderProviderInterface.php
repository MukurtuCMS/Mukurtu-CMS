<?php

declare(strict_types=1);

namespace Drupal\geocoder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining the Geocoder provider entity type.
 */
interface GeocoderProviderInterface extends ConfigEntityInterface {

  /**
   * Returns whether this provider is configurable.
   *
   * @return bool
   *   The bool result.
   */
  public function isConfigurable(): bool;

  /**
   * Returns the provider plugin.
   *
   * @return \Drupal\geocoder\ProviderInterface
   *   The Geocoder Provider plugin.
   */
  public function getPlugin(): ProviderInterface;

  /**
   * Sets the provider plugin.
   *
   * @param string $plugin
   *   The machine name of the Geocoder provider plugin.
   *
   * @return \Drupal\geocoder\GeocoderProviderInterface
   *   The entity, for chaining.
   */
  public function setPlugin(string $plugin): GeocoderProviderInterface;

  /**
   * Returns the definition of the provider plugin.
   *
   * @return array
   *   The plugin definition, as returned by the discovery object used by the
   *   plugin manager.
   */
  public function getPluginDefinition(): array;

}
