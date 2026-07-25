<?php

namespace Drupal\mukurtu_submissions\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Submission settings entities.
 */
interface SubmissionSettingsInterface extends ConfigEntityInterface {

  /**
   * Gets the target entity type ID this settings entity applies to.
   *
   * @return string
   *   The target entity type ID, e.g. "node".
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Gets the target bundle this settings entity applies to.
   *
   * @return string
   *   The target bundle.
   */
  public function getTargetBundle(): string;

  /**
   * Gets the media type bundles allowed on the public submission form.
   *
   * @return string[]
   *   An array of media type IDs.
   */
  public function getAllowedMediaTypes(): array;

  /**
   * Whether the "access expectations" hint field should be shown.
   *
   * @return bool
   *   TRUE if the field should be shown.
   */
  public function accessExpectationsEnabled(): bool;

}
