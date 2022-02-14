<?php

namespace Drupal\mukurtu_community\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Community entity.
 *
 * @ingroup mukurtu_community
 *
 * @ContentEntityType(
 *   id = "community",
 *   label = @Translation("Community"),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_community\CommunityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_community\CommunityListBuilder",
 *     "views_data" = "Drupal\mukurtu_community\Entity\CommunityViewsData",
 *     "translation" = "Drupal\mukurtu_community\CommunityTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_community\Form\CommunityForm",
 *       "add" = "Drupal\mukurtu_community\Form\CommunityForm",
 *       "edit" = "Drupal\mukurtu_community\Form\CommunityForm",
 *       "delete" = "Drupal\mukurtu_community\Form\CommunityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_community\CommunityHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_community\CommunityAccessControlHandler",
 *   },
 *   base_table = "community",
 *   data_table = "community_field_data",
 *   revision_table = "community_revision",
 *   revision_data_table = "community_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer community entities",
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
 *     "canonical" = "/communities/community/{community}",
 *     "add-form" = "/communities/community/add",
 *     "edit-form" = "/communities/community/{community}/edit",
 *     "delete-form" = "/communities/community/{community}/delete",
 *     "version-history" = "/communities/community/{community}/revisions",
 *     "revision" = "/communities/community/{community}/revisions/{community_revision}/view",
 *     "revision_revert" = "/communities/community/{community}/revisions/{community_revision}/revert",
 *     "revision_delete" = "/communities/community/{community}/revisions/{community_revision}/delete",
 *     "translation_revert" = "/communities/community/{community}/revisions/{community_revision}/revert/{langcode}",
 *     "collection" = "/communities/community",
 *   },
 *   field_ui_base_route = "community.settings"
 * )
 */
class Community extends EditorialContentEntityBase implements CommunityInterface {

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
    // make the community owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // We need to set the parent on all children.
    $children = $this->getChildCommunities();
    $childrenIds = [];
    foreach ($children as $child) {
      $childrenIds[$child->id()] = $child->id();

      // Get the child's current parent.
      $parent = $child->getParentCommunity();

      // If it's empty or pointing to a different entity
      // we need to set and save.
      if (!$parent || $this->id() != $parent->id()) {
        $child->setParentCommunity($this);
        $child->save();
      }
    }

    // Remove the parent relationship for any communities no longer
    // in the child list.
    $query = \Drupal::entityQuery('community')
      ->condition('field_parent_community', $this->id(), '=')
      ->accessCheck(FALSE);
    $results = $query->execute();
    $removeIds = array_diff($results, $childrenIds);
    $removeEntities = $this->entityTypeManager()->getStorage('community')->loadMultiple($removeIds);
    foreach ($removeEntities as $removeEntity) {
      /** @var \Drupal\mukurtu_community\Entity\CommunityInterface $removeEntity */
      $removeEntity->set('field_parent_community', NULL);
      $removeEntity->save();
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
   * {@inheritDoc}
   */
  public function getParentCommunity(): ?CommunityInterface {
   /*  $query = \Drupal::entityQuery('community')
      ->condition('field_child_collections', $this->id(), '=')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Not in use at all.
    if (count($results) == 0) {
      return NULL;
    }

    $id = reset($results);
    return $this->entityTypeManager()->getStorage('community')->load($id); */
    $entities = $this->get('field_parent_community')->referencedEntities();
    return $entities[0] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setParentCommunity(CommunityInterface $community): CommunityInterface {
    return $this->set('field_parent_community', $community->id());
  }

  /**
   * {@inheritDoc}
   */
  public function getChildCommunities() {
    return $this->get('field_child_communities')->referencedEntities() ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function setChildCommunities(array $communities): CommunityInterface {
    $ids = [];
    foreach ($communities as $community) {
      $ids[] = $community->id();
    }
    $this->set('field_child_communities', $ids);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function isParentCommunity(): bool {
    $value = $this->get('field_child_communities')->getValue();
    if (!empty($value)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function isChildCommunity(): bool {
    $query = \Drupal::entityQuery('community')
      ->condition('field_child_communities', $this->id(), '=')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Not in use at all.
    if (count($results) > 0) {
      return TRUE;
    }

    return FALSE;
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
      ->setDescription(t('The user ID of author of the Community entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
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
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Community entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['field_parent_community'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Community'))
      ->setSetting('target_type', 'community')
      ->setSetting('handler', 'default:community')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
      ])
      ->setRequired(FALSE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_child_communities'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sub-communities'))
      ->setSetting('target_type', 'community')
      ->setSetting('handler', 'default:community')
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
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Community is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
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
