<?php

namespace Drupal\mukurtu_export\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Export List entity.
 */
#[ContentEntityType(
  id: 'export_list',
  label: new TranslatableMarkup('Export List'),
  label_collection: new TranslatableMarkup('Export Lists'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'label',
    'uid' => 'uid',
  ],
  handlers: [
    'access' => 'Drupal\mukurtu_export\ExportListAccessController',
    'list_builder' => 'Drupal\mukurtu_export\Controller\ExportListListBuilder',
    'form' => [
      'add' => 'Drupal\mukurtu_export\Form\ExportListAddForm',
      'edit' => 'Drupal\mukurtu_export\Form\ExportListEditForm',
      'delete' => 'Drupal\mukurtu_export\Form\ExportListDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider',
    ],
  ],
  links: [
    'add-form' => '/admin/export/lists/add',
    'edit-form' => '/admin/export/lists/manage/{export_list}',
    'delete-form' => '/admin/export/lists/manage/{export_list}/delete',
    'collection' => '/admin/export/lists',
  ],
  admin_permission: 'access mukurtu export',
  base_table: 'export_list',
)]
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

    $fields['item_languages'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Item languages'))
      ->setDescription(t('Serialized map of "entity_type:id" => langcode pairs, for items whose export should use a specific translation instead of their original language. Additive to items - an item with no entry here exports in its original language, unchanged from before this field existed.'));

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

  /**
   * Adds entity IDs of a given type to this list (read-modify-write).
   *
   * Does not save the entity; callers should call save() once after adding
   * everything they need to.
   *
   * @param string $entity_type_id
   *   The entity type ID the given IDs belong to.
   * @param array $ids
   *   The entity IDs to add.
   *
   * @return $this
   */
  public function addItems(string $entity_type_id, array $ids) {
    $items = $this->getItems();
    $items[$entity_type_id] = $items[$entity_type_id] ?? [];
    foreach ($ids as $id) {
      $items[$entity_type_id][$id] = $id;
    }
    $this->setItems($items);
    return $this;
  }

  /**
   * Gets the requested-translation langcode for a list item, if one is set.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $id
   *   The entity ID.
   *
   * @return string|null
   *   The langcode, or NULL if the item should export in its original
   *   language (the default, and the only option before this field
   *   existed).
   */
  public function getItemLanguage(string $entity_type_id, int|string $id): ?string {
    $first = $this->get('item_languages')->first();
    $languages = $first ? ($first->value ?? []) : [];
    return $languages["{$entity_type_id}:{$id}"] ?? NULL;
  }

  /**
   * Sets (or clears) the requested-translation langcode for a list item.
   *
   * Does not save the entity; callers should call save() once after
   * making all the changes they need.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $id
   *   The entity ID.
   * @param string|null $langcode
   *   The langcode to export this item in, or NULL to clear any previously
   *   set langcode and export it in its original language.
   *
   * @return $this
   */
  public function setItemLanguage(string $entity_type_id, int|string $id, ?string $langcode) {
    $first = $this->get('item_languages')->first();
    $languages = $first ? ($first->value ?? []) : [];
    $key = "{$entity_type_id}:{$id}";
    if ($langcode) {
      $languages[$key] = $langcode;
    }
    else {
      unset($languages[$key]);
    }
    $this->set('item_languages', ['value' => $languages]);
    return $this;
  }

}
