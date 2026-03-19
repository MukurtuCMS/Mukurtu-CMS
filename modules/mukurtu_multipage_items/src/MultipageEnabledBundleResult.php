<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * Value object for a multipage-enabled bundle check result with cacheability.
 */
final class MultipageEnabledBundleResult implements RefinableCacheableDependencyInterface {
  use RefinableCacheableDependencyTrait;

  /**
   * Whether the bundle is enabled for multipage items.
   *
   * @var bool
   */
  protected bool $enabled;

  /**
   * Returns whether the bundle is enabled for multipage items.
   *
   * @return bool
   *   TRUE if the bundle is enabled, FALSE otherwise.
   */
  public function isEnabled(): bool {
    return $this->enabled;
  }

  /**
   * Sets whether the bundle is enabled for multipage items.
   *
   * @param bool $enabled
   *   TRUE if the bundle is enabled, FALSE otherwise.
   *
   * @return $this
   */
  public function setEnabled(bool $enabled): self {
    $this->enabled = $enabled;
    return $this;
  }

}
