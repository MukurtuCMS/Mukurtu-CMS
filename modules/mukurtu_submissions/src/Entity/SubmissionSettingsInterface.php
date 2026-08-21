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

  /**
   * Gets who may use the public submission form.
   *
   * @return string
   *   Either "anonymous" (visitors and authenticated users) or
   *   "authenticated" (authenticated users only).
   */
  public function getAccessLevel(): string;

  /**
   * Gets the introductory text shown above the Title field on the public
   * submission form.
   *
   * @return array
   *   A text_format-style array with "value" and "format" keys, or an empty
   *   array if none is configured.
   */
  public function getIntroText(): array;

  /**
   * Gets the collapsible section definitions for grouping fields on the
   * public submission form.
   *
   * @return array[]
   *   An array of ["id" => ..., "label" => ..., "collapsed" => bool], in
   *   display order.
   */
  public function getFieldGroups(): array;

  /**
   * Gets which group (if any) each included field belongs to.
   *
   * @return string[]
   *   Field name => group ID. A field absent from this map renders inline,
   *   ungrouped.
   */
  public function getFieldGroupAssignments(): array;

}
