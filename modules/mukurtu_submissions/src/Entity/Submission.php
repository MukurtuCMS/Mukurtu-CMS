<?php

namespace Drupal\mukurtu_submissions\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the mukurtu_submission entity.
 *
 * Holds the contact/PII details captured on the public submission form,
 * kept separate from the submitted content entity itself so PII never rides
 * along in the content entity's own view displays, config export, or
 * translation set. Points at the submitted entity by type + id rather than
 * an entity_reference field so it can reference any future submittable
 * entity type, not just nodes.
 *
 * @ContentEntityType(
 *   id = "mukurtu_submission",
 *   label = @Translation("Submission"),
 *   label_collection = @Translation("Pending Submissions"),
 *   base_table = "mukurtu_submission",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "access" = "Drupal\mukurtu_submissions\SubmissionAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   admin_permission = "review mukurtu submissions",
 * )
 */
class Submission extends ContentEntityBase implements SubmissionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['target_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target entity type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['target_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Target entity ID'))
      ->setRequired(TRUE);

    $fields['submitter_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submitter name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['submitter_email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Submitter email'))
      ->setRequired(TRUE);

    $fields['submitter_phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submitter phone'))
      ->setSetting('max_length', 64);

    $fields['access_expectations'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Submitter access expectations'));

    $fields['submitter_ip'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submitter IP address'))
      ->setSetting('max_length', 128);

    $fields['review_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Review status'))
      ->setRequired(TRUE)
      ->setDefaultValue(SubmissionInterface::STATUS_PENDING)
      ->setSetting('allowed_values', [
        SubmissionInterface::STATUS_PENDING => t('Pending'),
        SubmissionInterface::STATUS_APPROVED => t('Approved'),
        SubmissionInterface::STATUS_REJECTED => t('Rejected'),
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Submitted'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return $this->get('target_entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetId() {
    return $this->get('target_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntity() {
    $entity_type_id = $this->getTargetEntityTypeId();
    $target_id = $this->getTargetId();
    if (!$entity_type_id || !$target_id) {
      return NULL;
    }
    return $this->entityTypeManager()->getStorage($entity_type_id)->load($target_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitterName(): ?string {
    return $this->get('submitter_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitterEmail(): ?string {
    return $this->get('submitter_email')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitterPhone(): ?string {
    return $this->get('submitter_phone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessExpectations(): ?string {
    return $this->get('access_expectations')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getReviewStatus(): string {
    return $this->get('review_status')->value ?? SubmissionInterface::STATUS_PENDING;
  }

  /**
   * {@inheritdoc}
   */
  public function setReviewStatus(string $status) {
    $this->set('review_status', $status);
    return $this;
  }

}
