<?php

namespace Drupal\mukurtu_multipage_items\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mukurtu_multipage_items\MultipageItemInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the multipage item entity class.
 *
 * @ContentEntityType(
 *   id = "multipage_item",
 *   label = @Translation("Multipage Item"),
 *   label_collection = @Translation("Multipage Items"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_multipage_items\MultipageItemListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_multipage_items\Form\MultipageItemForm",
 *       "edit" = "Drupal\mukurtu_multipage_items\Form\MultipageItemForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_multipage_items\MultipageItemAccessControlHandler",
 *   },
 *   base_table = "multipage_item",
 *   data_table = "multipage_item_field_data",
 *   revision_table = "multipage_item_revision",
 *   revision_data_table = "multipage_item_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer multipage item",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/multipage-item/add",
 *     "canonical" = "/multipage-item/{multipage_item}",
 *     "edit-form" = "/admin/content/multipage-item/{multipage_item}/edit",
 *     "delete-form" = "/admin/content/multipage-item/{multipage_item}/delete",
 *     "collection" = "/admin/content/multipage-item"
 *   },
 *   field_ui_base_route = "entity.multipage_item.settings"
 * )
 */
class MultipageItem extends RevisionableContentEntityBase implements MultipageItemInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new multipage item entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
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
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setDescription(t('A short, descriptive name for the multipage item. The title should give users useful information about the item when browsing or searching. Maximum 255 characters.	</br>The title will default to the title or name of the content used as the first page, but can be changed.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_pages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Pages'))
      ->setDescription(t('All pages included in the multipage item. Examples include yearbooks, scrapbooks, or photo albums.	</br>Select "Select Content" to choose from existing site content. Pages will be displayed in the order they are added, and can be manually arranged by dragging them into the desired order. </br>The content types that can be included in multipage items must be configured by a Mukurtu Manager.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
      ])
      ->setRequired(TRUE)
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
          'field_widget_edit' => TRUE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'selection_mode' => 'selection_append',
          'open' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->addConstraint('MultipageValidNode');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the multipage item is published.'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Published')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the multipage item author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(FALSE)
      ->setDescription(t('The time that the multipage item was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(FALSE)
      ->setDescription(t('The time that the multipage item was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstPage() {
    $page_ids = array_column($this->get('field_pages')->getValue(), 'target_id');
    if (!empty($page_ids)) {
      $first = reset($page_ids);
      return $this->entityTypeManager()->getStorage('node')->load($first);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstPage(NodeInterface $node): MultipageItemInterface {
    $page_ids = array_column($this->get('field_pages')->getValue(), 'target_id');

    // If page is already included, remove it.
    if (($key = array_search($node->id(), $page_ids)) !== FALSE) {
      unset($page_ids[$key]);
    }
    // Stick the requested node on the front of the list.
    $new_refs = array_merge([$node->id()], $page_ids);
    return $this->set('field_pages', $new_refs);
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(NodeInterface $node): MultipageItemInterface {
    $page_ids = array_column($this->get('field_pages')->getValue(), 'target_id');
    $page_ids[] = $node->id();
    return $this->set('field_pages', $page_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getPages($accessCheck = FALSE) {
    $page_ids = array_column($this->get('field_pages')->getValue(), 'target_id');
    // Using entity query to do quick access checking.
    if ($accessCheck) {
      $query = $this->entityTypeManager()->getStorage('node')->getQuery();
      $query
        ->condition('nid', $page_ids, 'IN')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      $allowed_page_ids = $query->execute();
      $denied = array_diff($page_ids, $allowed_page_ids);
      foreach($denied as $k => $d) {
        unset($page_ids[$k]);
      }
    }
    if (!empty($page_ids)) {
      return $this->entityTypeManager()->getStorage('node')->loadMultiple($page_ids);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasPage(NodeInterface $node): bool {
    $page_ids = array_column($this->get('field_pages')->getValue(), 'target_id');
    return in_array($node->id(), $page_ids);
  }

}
