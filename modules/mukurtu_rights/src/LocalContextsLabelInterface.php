<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a Local Contexts Label entity.
 */
interface LocalContextsLabelInterface extends ContentEntityInterface {

  /**
   * Get the label title.
   *
   * @return string
   *   The title of the label.
   */
  public function getTitle(): string;

  /**
   * Get the label text.
   *
   * @return string
   *   The text of the label.
   */
  public function getText(): string;

  /**
   * Get the label image URL.
   *
   * @return string
   *   The image URL of the label.
   */
  public function getImageUrl(): string;

}
