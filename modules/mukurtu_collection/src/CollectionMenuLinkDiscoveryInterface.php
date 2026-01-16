<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\mukurtu_collection\Entity\Collection;

/**
 * Interface for collection menu link discovery.
 */
interface CollectionMenuLinkDiscoveryInterface {

  /**
   * Get menu link definitions for all collection hierarchies.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection|null $collection
   *   Optional specific collection to get menu links for. If NULL, gets all.
   *
   * @return array
   *   Array of menu link plugin definitions keyed by collection UUID.
   */
  public function getMenuLinkDefinitions(?Collection $collection = NULL): array;

}
