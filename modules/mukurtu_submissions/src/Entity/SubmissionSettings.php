<?php

namespace Drupal\mukurtu_submissions\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the mukurtu_submission_settings entity type.
 *
 * @ConfigEntityType(
 *   id = "mukurtu_submission_settings",
 *   label = @Translation("Submission settings"),
 *   label_collection = @Translation("Submission Forms"),
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
 *     "intro_text",
 *     "thank_you_text",
 *     "field_groups",
 *     "field_group_assignments",
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
  protected $access_level = 'authenticated';

  /**
   * Introductory text shown above the Title field on the public submission
   * form, as a text_format-style ["value" => ..., "format" => ...] array.
   *
   * @var array
   */
  protected $intro_text = [];

  /**
   * Text shown on the confirmation page after a successful submission, as
   * a text_format-style ["value" => ..., "format" => ...] array. Empty
   * means the controller falls back to a generic message.
   *
   * @var array
   */
  protected $thank_you_text = [];

  /**
   * Collapsible section definitions for grouping fields on the public
   * submission form, in display order.
   *
   * @var array[]
   *   Each element is ["id" => ..., "label" => ..., "collapsed" => bool].
   */
  protected $field_groups = [];

  /**
   * Which group (if any) each included field belongs to.
   *
   * @var string[]
   *   Field name => group ID. A field absent from this map renders inline,
   *   ungrouped.
   */
  protected $field_group_assignments = [];

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
    return $this->access_level ?? 'authenticated';
  }

  /**
   * {@inheritdoc}
   */
  public function getIntroText(): array {
    return $this->intro_text ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getThankYouText(): array {
    return $this->thank_you_text ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldGroups(): array {
    return $this->field_groups ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldGroupAssignments(): array {
    return $this->field_group_assignments ?? [];
  }

}
