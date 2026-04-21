<?php

namespace Drupal\search_api\Utility;

use Drupal\Core\Entity\DependencyTrait;

/**
 * Provides an easy mechanism for building a dependency array.
 */
class Dependencies {

  use DependencyTrait {
    addDependency as public;
    addDependencies as public;
  }

  /**
   * Gets the dependencies as an array as expected by configuration.
   *
   * @return array
   *   The dependencies.
   */
  public function toArray(): array {
    return $this->dependencies;
  }

}
