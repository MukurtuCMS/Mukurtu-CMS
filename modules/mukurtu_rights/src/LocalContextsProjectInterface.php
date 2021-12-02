<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a Local Contexts Project entity.
 */
interface LocalContextsProjectInterface extends ContentEntityInterface {

  /**
   * Fetch the project from the Local Contexts Hub.
   *
   * @return bool
   *   The result of the fetch.
   */
  public function fetch(): bool;

  /**
   * Get the project title.
   *
   * @return string
   *   The title of the project.
   */
  public function getTitle(): string;

}
