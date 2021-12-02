<?php

namespace Drupal\mukurtu_rights\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mukurtu_rights\LocalContextsProjectInterface;

/**
 * Defines the LocalContextsProject entity.
 *
 * @ingroup local_contexts_hub
 *
 * @ContentEntityType(
 *   id = "lcproject",
 *   label = @Translation("Local Contexts Project"),
 *   base_table = "lcproject",
 *   handlers = {
 *     "access" = "Drupal\mukurtu_rights\LocalContextsProjectAccessControlHandler",
 *   },
 *   entity_keys = {
 *     "id" = "pid",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class LocalContextsProject extends ContentEntityBase implements LocalContextsProjectInterface {

  /**
   * {@inheritdoc}
   */
  public function fetch(): bool {

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['pid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t("The projects's unique ID."))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The project UUID.'))
      ->setReadOnly(FALSE);

    $fields['title'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Project Title'))
      ->setDescription(t('The title of the project.'));

    $fields['labels'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Labels'))
      ->setDescription(t('The labels in the project.'))
      ->setSetting('target_type', 'lclabel');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The timestamp when the label was created locally.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The timestamp when the label was last changed locally.'));

    return $fields;
  }

}
