<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines the Personal collection entity.
 *
 * @ingroup mukurtu_collection
 *
 * @ContentEntityType(
 *   id = "personal_collection",
 *   label = @Translation("Personal collection"),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_collection\PersonalCollectionStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_collection\PersonalCollectionListBuilder",
 *     "views_data" = "Drupal\mukurtu_collection\Entity\PersonalCollectionViewsData",
 *     "translation" = "Drupal\mukurtu_collection\PersonalCollectionTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_collection\Form\PersonalCollectionForm",
 *       "add" = "Drupal\mukurtu_collection\Form\PersonalCollectionForm",
 *       "edit" = "Drupal\mukurtu_collection\Form\PersonalCollectionForm",
 *       "delete" = "Drupal\mukurtu_collection\Form\PersonalCollectionDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_collection\PersonalCollectionHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_collection\PersonalCollectionAccessControlHandler",
 *   },
 *   base_table = "personal_collection",
 *   data_table = "personal_collection_field_data",
 *   revision_table = "personal_collection_revision",
 *   revision_data_table = "personal_collection_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer personal collection entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/personal-collection/{personal_collection}",
 *     "add-form" = "/personal-collection/add",
 *     "edit-form" = "/personal-collection/{personal_collection}/edit",
 *     "delete-form" = "/personal-collection/{personal_collection}/delete",
 *     "version-history" = "/personal-collection/{personal_collection}/revisions",
 *     "revision" = "/personal-collection/{personal_collection}/revisions/{personal_collection_revision}/view",
 *     "revision_revert" = "/personal-collection/{personal_collection}/revisions/{personal_collection_revision}/revert",
 *     "revision_delete" = "/personal-collection/{personal_collection}/revisions/{personal_collection_revision}/delete",
 *     "translation_revert" = "/personal-collection/{personal_collection}/revisions/{personal_collection_revision}/revert/{langcode}",
 *     "collection" = "/admin/structure/personal_collection",
 *   },
 *   field_ui_base_route = "personal_collection.settings"
 * )
 */
class PersonalCollection extends EditorialContentEntityBase implements PersonalCollectionInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the personal_collection owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivacy() {
    return $this->get('field_pc_privacy')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivacy($privacy) {
    $this->set('field_pc_privacy', $privacy);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPrivate(): bool {
    return $this->getPrivacy() != 'public';
  }

  /**
   * {@inheritdoc}
   */
  public function add(EntityInterface $entity): void {
    $items = $this->get('field_items_in_collection')->getValue();
    $items[] = ['target_id' => $entity->id()];
    $this->set('field_items_in_collection', $items);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(EntityInterface $entity): void {
    $needle = $entity->id();
    $items = $this->get('field_items_in_collection')->getValue();
    foreach ($items as $delta => $item) {
      if ($item['target_id'] == $needle) {
        unset($items[$delta]);
      }
    }
    $this->set('field_items_in_collection', $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(): int {
    $items = $this->get('field_items_in_collection')->getValue();
    if (is_countable($items)) {
      return count($items);
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Personal collection.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 10,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Personal collection name'))
      ->setDescription(t('The name of the Personal collection.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['field_summary'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Summary'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_pc_privacy'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Privacy Setting'))
      ->setDescription(t('Private personal collections are only visible to you. Public personal collections are accessible by anyone.'))
      ->setDefaultValue('private')
      ->setSettings([
        'allowed_values' => [
          'private' => t('Private'),
          'public' => t('Public'),
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => -9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['field_media_assets'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Personal Collection Image'))
      ->setDescription(t('An image to represent the personal collection.'))
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default:media')
      ->setSetting('handler_settings', [
        'target_bundles' => ['image' => 'image'],
        'auto_create' => FALSE,
      ])
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => 2,
        'settings' => [
          'media_types' => ['image'],
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_items_in_collection'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Items'))
      ->setDescription(t('The content contained in the personal collection.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
      ])
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => 4,
        'settings' => [
          'entity_browser' => 'mukurtu_content_browser',
          'field_widget_display' => 'label',
          'field_widget_display_settings' => [],
          'field_widget_edit' => FALSE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'selection_mode' => 'selection_append',
          'open' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']
      ->setDescription(t('A boolean indicating whether the Personal collection is published.'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
