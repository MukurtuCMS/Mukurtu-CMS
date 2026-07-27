<?php

namespace Drupal\mukurtu_submissions\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the mukurtu_submission_settings entity type.
 *
 * @ConfigEntityType(
 *   id = "mukurtu_submission_settings",
 *   label = @Translation("Submission settings"),
 *   label_collection = @Translation("Public Submissions"),
 *   label_singular = @Translation("submission settings"),
 *   label_plural = @Translation("submission settings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count submission setting",
 *     plural = "@count submission settings",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mukurtu_submissions\SubmissionSettingsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_submissions\Form\SubmissionSettingsForm",
 *       "edit" = "Drupal\mukurtu_submissions\Form\SubmissionSettingsForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "mukurtu_submission_settings",
 *   admin_permission = "administer mukurtu submissions",
 *   links = {
 *     "collection" = "/admin/config/mukurtu/submissions",
 *     "add-form" = "/admin/config/mukurtu/submissions/add",
 *     "edit-form" = "/admin/config/mukurtu/submissions/{mukurtu_submission_settings}",
 *     "delete-form" = "/admin/config/mukurtu/submissions/{mukurtu_submission_settings}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "target_entity_type_id",
 *     "target_bundle",
 *     "allowed_media_types",
 *     "access_expectations_enabled",
 *     "access_level",
 *   }
 * )
 */
class SubmissionSettings extends ConfigEntityBase implements SubmissionSettingsInterface {

  /**
   * The settings ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The settings label.
   *
   * @var string
   */
  protected $label;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $target_entity_type_id = 'node';

  /**
   * The target bundle.
   *
   * @var string
   */
  protected $target_bundle;

  /**
   * The media type bundles allowed on the public submission form.
   *
   * @var string[]
   */
  protected $allowed_media_types = [];

  /**
   * Whether the "access expectations" hint field should be shown.
   *
   * @var bool
   */
  protected $access_expectations_enabled = FALSE;

  /**
   * Who may use the public submission form: "anonymous" (visitors and
   * authenticated users) or "authenticated" (authenticated users only).
   *
   * @var string
   */
  protected $access_level = 'anonymous';

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return $this->target_entity_type_id ?? 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetBundle(): string {
    return $this->target_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedMediaTypes(): array {
    return $this->allowed_media_types ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function accessExpectationsEnabled(): bool {
    return (bool) $this->access_expectations_enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessLevel(): string {
    return $this->access_level ?? 'anonymous';
  }

}
