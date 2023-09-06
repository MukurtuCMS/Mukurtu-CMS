<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\og\Entity\OgRole;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Exception;

/**
 * Defines the Protocol entity.
 *
 * @ingroup mukurtu_protocol
 *
 * @ContentEntityType(
 *   id = "protocol",
 *   label = @Translation("Cultural Protocol"),
 *   label_collection = @Translation("Cultural Protocols"),
 *   label_singular = @Translation("Cultural Protocol"),
 *   label_plural = @Translation("Cultural Protocols"),
 *   label_count = @PluralTranslation(
 *     singular = "@count cultural protocols",
 *     plural = "@count cultural protocols",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_protocol\ProtocolStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_protocol\ProtocolListBuilder",
 *     "views_data" = "Drupal\mukurtu_protocol\Entity\ProtocolViewsData",
 *     "translation" = "Drupal\mukurtu_protocol\ProtocolTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_protocol\Form\ProtocolForm",
 *       "add" = "Drupal\mukurtu_protocol\Form\ProtocolForm",
 *       "edit" = "Drupal\mukurtu_protocol\Form\ProtocolForm",
 *       "delete" = "Drupal\mukurtu_protocol\Form\ProtocolDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_protocol\ProtocolHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_protocol\ProtocolAccessControlHandler",
 *   },
 *   base_table = "protocol",
 *   data_table = "protocol_field_data",
 *   revision_table = "protocol_revision",
 *   revision_data_table = "protocol_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer protocol entities",
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
 *     "canonical" = "/protocols/protocol/{protocol}",
 *     "add-form" = "/protocols/protocol/add",
 *     "edit-form" = "/protocols/protocol/{protocol}/edit",
 *     "delete-form" = "/protocols/protocol/{protocol}/delete",
 *     "version-history" = "/protocols/protocol/{protocol}/revisions",
 *     "revision" = "/protocols/protocol/{protocol}/revisions/{protocol_revision}/view",
 *     "revision_revert" = "/protocols/protocol/{protocol}/revisions/{protocol_revision}/revert",
 *     "revision_delete" = "/protocols/protocol/{protocol}/revisions/{protocol_revision}/delete",
 *     "translation_revert" = "/protocols/protocol/{protocol}/revisions/{protocol_revision}/revert/{langcode}",
 *     "collection" = "/dashboard/protocols",
 *   },
 *   field_ui_base_route = "protocol.settings"
 * )
 */
class Protocol extends EditorialContentEntityBase implements ProtocolInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);

    // Don't add the current user as the community owner if we are currently
    // migrating. This would create the OG membership before we migrate
    // the memberships which will create a sync issue.
    $mukurtuMigrate = $values['mukurtu_migrate'] ?? FALSE;
    if (!$mukurtuMigrate) {
      $values += [
        'user_id' => \Drupal::currentUser()->id(),
      ];
    }
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
    // make the protocol owner the revision author.
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
  public function getDescription() {
    return $this->get('field_description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    return $this->set('field_description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getSharingSetting() {
    return $this->get('field_access_mode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSharingSettingLabel() {
    $setting = $this->getSharingSetting();
    $values = $this->getFieldDefinition('field_access_mode')->getSetting('allowed_values');
    return $values[$setting] ?? $setting;
  }

  /**
   * {@inheritdoc}
   */
  public function setSharingSetting($sharing) {
    $this->set('field_access_mode', $sharing);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isStrict(): bool {
    return $this->getSharingSetting() == 'strict';
  }

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    return $this->getSharingSetting() == 'open';
  }

  /**
   * {@inheritdoc}
   */
  public function inUse(): bool {
    $entityFieldManager = \Drupal::service('entity_field.manager');

    foreach (['node', 'media'] as $entity_type_id) {
      try {
        $storage = $this->entityTypeManager()->getStorage($entity_type_id);
      } catch (Exception $e) {
        continue;
      }

      // Check if the protocol field exists.
      $fields = $entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
      if (!isset($fields['field_cultural_protocols'])) {
        // If the protocol field doesn't exist, don't try to query it otherwise
        // it'll throw errors that mess up our kernel tests.
        continue;
      }

      // Check if any entity has a reference to this protocol.
      $query = $storage->getQuery();
      $query->condition('field_cultural_protocols.protocols', "|{$this->id()}|", 'CONTAINS')
        ->accessCheck(FALSE);
      $results = $query->execute();
      if (!empty($results)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommunities($communities) {
    return $this->set('field_communities', $communities);
  }

  /**
   * {@inheritDoc}
   */
  public function getCommunities() {
    return $this->get('field_communities')->referencedEntities() ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function getCommentStatus(): bool {
    return $this->get('field_comment_status')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setCommentStatus($status): ProtocolInterface {
    return $this->set('field_comment_status', $status);
  }

  /**
   * {@inheritDoc}
   */
  public function getCommentRequireApproval(): bool {
    return $this->get('field_comment_require_approval')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setCommentRequireApproval($require): ProtocolInterface {
    return $this->set('field_comment_require_approval', $require);
  }

  /**
   * {@inheritdoc}
   */
  public function addMember(AccountInterface $account, $roles = []): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if (!$membership) {
      // Load OgRoles from role ids.
      $ogRoles = [];
      foreach ($roles as $role) {
        $ogRole = OgRole::getRole('protocol', 'protocol', $role);
        if ($ogRole) {
          $ogRoles[] = $ogRole;
        }
      }

      // Create the membership and add the roles.
      $membership = Og::createMembership($this, $account);
      $membership->setRoles($ogRoles);
      $membership->save();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMember(AccountInterface $account): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if ($membership) {
      $membership->delete();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(AccountInterface $account, $roles = []): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if ($membership) {
      // Load OgRoles from role ids.
      $ogRoles = [];
      foreach ($roles as $role) {
        $ogRole = OgRole::getRole('protocol', 'protocol', $role);
        if ($ogRole) {
          $ogRoles[] = $ogRole;
        }
      }

      // Add the roles.
      $membership->setRoles($ogRoles);
      $membership->save();
    }

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getMembership(AccountInterface $account, array $states = [OgMembershipInterface::STATE_ACTIVE]): ?OgMembershipInterface {
    return Og::getMembership($this, $account, $states);
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Protocol.'))
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
      ->setDescription(t('The name of the Protocol.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
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

    $fields['field_description'] = BaseFieldDefinition::create('text_with_summary')
      ->setLabel(t('Description'))
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea_with_summary',
        'weight' => 0,
        'rows' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_comment_status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Comments Status'))
      ->setDescription(t('If comments are enabled for the protocol.'))
      ->setRevisionable(FALSE)
      ->setDefaultValue(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['field_comment_require_approval'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Comments Require Approval'))
      ->setDescription(t('If comments require approval for the protocol.'))
      ->setRevisionable(FALSE)
      ->setDefaultValue(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['status']->setDescription(t('A boolean indicating whether the Protocol is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 100,
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

    $fields['field_communities'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Communities'))
      ->setSetting('target_type', 'community')
      ->setSetting('handler', 'community_selection_for_protocols')
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
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_access_mode'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sharing Protocol'))
      ->setDescription('')
      ->setSettings([
        'allowed_values' => [
          'strict' => 'Strict',
          'open' => 'Open',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 10,
      ])
      ->setDefaultValue('strict')
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
