<?php

namespace Drupal\mukurtu_export\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Export List entity.
 *
 * @ContentEntityType(
 *   id = "export_list",
 *   label = @Translation("Export List"),
 *   label_collection = @Translation("Export Lists"),
 *   base_table = "export_list",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "uid" = "uid",
 *   },
 *   handlers = {
 *     "access" = "Drupal\mukurtu_export\ExportListAccessController",
 *     "list_builder" = "Drupal\mukurtu_export\Controller\ExportListListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_export\Form\ExportListAddForm",
 *       "edit" = "Drupal\mukurtu_export\Form\ExportListEditForm",
 *       "delete" = "Drupal\mukurtu_export\Form\ExportListDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer site configuration",
 *   links = {
 *     "add-form" = "/admin/export/lists/add",
 *     "edit-form" = "/admin/export/lists/manage/{export_list}",
 *     "delete-form" = "/admin/export/lists/manage/{export_list}/delete",
 *     "collection" = "/admin/export/lists",
 *   }
 * )
 */
class ExportList extends ContentEntityBase implements EntityOwnerInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultOwner');

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ]);

    $fields['site_wide'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Share with all export users'))
      ->setDefaultValue(FALSE);

    $fields['items'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Items'))
      ->setDescription(t('Serialized map of entity_type_id => [id => id] pairs.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

  /**
   * Default value callback for the uid field.
   */
  public static function getDefaultOwner() {
    return \Drupal::currentUser()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->entityTypeManager()->getStorage('user')->load($this->uid->target_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->uid->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  public function getDescription() {
    return $this->get('description')->value;
  }

  public function isSiteWide() {
    return $this->get('site_wide')->value === TRUE || $this->get('site_wide')->value == 1;
  }

  public function getItems(): array {
    $first = $this->get('items')->first();
    return $first ? ($first->value ?? []) : [];
  }

  public function setItems(array $items) {
    // MapItem expects ['value' => $array]; wrapping ensures FieldItemList
    // treats this as a single item rather than iterating over the array keys.
    $this->set('items', ['value' => $items]);
    return $this;
  }

}
