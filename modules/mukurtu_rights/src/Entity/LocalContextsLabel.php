<?php

namespace Drupal\mukurtu_rights\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mukurtu_rights\LocalContextsLabelInterface;

/**
 * Defines the LocalContextLabel entity.
 *
 * @ingroup local_contexts_hub
 *
 * @ContentEntityType(
 *   id = "lclabel",
 *   label = @Translation("Local Contexts Label"),
 *   label_collection = @Translation("Local Contexts Labels"),
 *   label_singular = @Translation("Local Contexts Label"),
 *   label_plural = @Translation("Local Contexts Labels"),
 *   label_count = @PluralTranslation(
 *     singular = "@count label",
 *     plural = "@count labels",
 *   ),
 *   base_table = "lclabel",
 *   entity_keys = {
 *     "id" = "lid",
 *     "uuid" = "uuid",
 *   },
 * handlers = {
 *    "access" = "Drupal\mukurtu_rights\LocalContextsLabelAccessControlHandler",
 *    "views_data" = "Drupal\views\EntityViewsData",
 *  },
 * )
 */
class LocalContextsLabel extends ContentEntityBase implements LocalContextsLabelInterface {

  /**
   * {@inheritdoc}
   */
  public function getTitle() : string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectTitle() : string {
    return $this->get('project_title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHubCommunity() : string {
    return $this->get('community')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommunity(int $community): void {
    $this->set('field_mukurtu_community', ['target_id' => $community]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunity(): ?EntityInterface {
    $ref = $this->get('field_mukurtu_community')->referencedEntities();
    if ($ref && isset($ref[0])) {
      return $ref[0];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getText() : string {
    return $this->get('text')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageUrl() : string {
    return $this->get('image_url')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['lid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('LID'))
      ->setDescription(t("The labels's unique ID."))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The label UUID.'))
      ->setReadOnly(TRUE);

    $fields['project_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Project UUID'))
      ->setDescription(t('The unique ID of the owning project.'));

    $fields['project_title'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Project Title'))
      ->setDescription(t('The title of the owning project.'));

    $fields['name'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Label Title'))
      ->setDescription(t('The label title.'));

    $fields['label_class'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label Class'))
      ->setDescription(t('The label class (e.g., TK or BC).'));

    $fields['label_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label Type'))
      ->setDescription(t('The label type.'));

    $fields['text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Label Text'))
      ->setDescription(t('The label text.'));

    $fields['image_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Label Image URL'))
      ->setDescription(t('The label image URL.'));

    $fields['community'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Community'))
      ->setDescription(t('The label community from the Local Contexts Hub.'));

    $fields['hub_created'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Hub Creation Time'))
      ->setDescription(t('When the label was created on the Local Contexts Hub.'));

    $fields['hub_updated'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Hub Updated Time'))
      ->setDescription(t('When the label was last updated on the Local Contexts Hub.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The timestamp when the label was created locally.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The timestamp when the label was last changed locally.'));

    $fields['field_mukurtu_community'] = BaseFieldDefinition::create('og_standard_reference')
      ->setLabel(t('Mukurtu Community'))
      ->setDescription(t('The Mukurtu community the label belongs to.'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'node');

    return $fields;
  }

}
