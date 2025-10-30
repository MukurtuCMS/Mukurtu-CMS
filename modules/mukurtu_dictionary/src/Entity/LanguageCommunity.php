<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the Language community entity.
 *
 * @ingroup mukurtu_dictionary
 *
 * @ContentEntityType(
 *   id = "language_community",
 *   label = @Translation("Language community"),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_dictionary\LanguageCommunityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_dictionary\LanguageCommunityListBuilder",
 *     "views_data" = "Drupal\mukurtu_dictionary\Entity\LanguageCommunityViewsData",
 *     "translation" = "Drupal\mukurtu_dictionary\LanguageCommunityTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_dictionary\Form\LanguageCommunityForm",
 *       "add" = "Drupal\mukurtu_dictionary\Form\LanguageCommunityForm",
 *       "edit" = "Drupal\mukurtu_dictionary\Form\LanguageCommunityForm",
 *       "delete" = "Drupal\mukurtu_dictionary\Form\LanguageCommunityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_dictionary\LanguageCommunityHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_dictionary\LanguageCommunityAccessControlHandler",
 *   },
 *   base_table = "language_community",
 *   data_table = "language_community_field_data",
 *   revision_table = "language_community_revision",
 *   revision_data_table = "language_community_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer language community entities",
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
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/language_community/{language_community}",
 *     "add-form" = "/admin/structure/language_community/add",
 *     "edit-form" = "/admin/structure/language_community/{language_community}/edit",
 *     "delete-form" = "/admin/structure/language_community/{language_community}/delete",
 *     "version-history" = "/admin/structure/language_community/{language_community}/revisions",
 *     "revision" = "/admin/structure/language_community/{language_community}/revisions/{language_community_revision}/view",
 *     "revision_revert" = "/admin/structure/language_community/{language_community}/revisions/{language_community_revision}/revert",
 *     "revision_delete" = "/admin/structure/language_community/{language_community}/revisions/{language_community_revision}/delete",
 *     "translation_revert" = "/admin/structure/language_community/{language_community}/revisions/{language_community_revision}/revert/{langcode}",
 *     "collection" = "/admin/structure/language_community",
 *   },
 *   field_ui_base_route = "language_community.settings"
 * )
 */
class LanguageCommunity extends EditorialContentEntityBase implements LanguageCommunityInterface {

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
    // make the language_community owner the revision author.
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
   * Default language community community language field value.
   */
  public static function getDefaultCommunityLanguage() {
    return NULL;
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
      ->setDescription(t('The user ID of author of the Language community.'))
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
      ->setDescription(t('The name of the Language community.'))
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

    $fields['status']->setDescription(t('A boolean indicating whether the Language community is published.'))
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

    $fields['community_language'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Community Language'))
      ->setDescription(t('The language the language community manages.'))
      ->setRevisionable(TRUE)
      ->setCardinality(1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['language' => 'language']])
      ->setDefaultValueCallback('Drupal\mukurtu_dictionary\Entity\LanguageCommunity::getDefaultCommunityLanguage')
      ->setDisplayOptions('form', [
        'region' => 'content',
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
