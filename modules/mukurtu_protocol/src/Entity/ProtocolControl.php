<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * Defines the Protocol control entity.
 *
 * @ingroup mukurtu_protocol
 *
 * @ContentEntityType(
 *   id = "protocol_control",
 *   label = @Translation("Protocol control"),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_protocol\ProtocolControlStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_protocol\ProtocolControlListBuilder",
 *     "views_data" = "Drupal\mukurtu_protocol\Entity\ProtocolControlViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_protocol\Form\ProtocolControlForm",
 *       "add" = "Drupal\mukurtu_protocol\Form\ProtocolControlForm",
 *       "edit" = "Drupal\mukurtu_protocol\Form\ProtocolControlForm",
 *       "delete" = "Drupal\mukurtu_protocol\Form\ProtocolControlDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_protocol\ProtocolControlHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_protocol\ProtocolControlAccessControlHandler",
 *   },
 *   base_table = "protocol_control",
 *   revision_table = "protocol_control_revision",
 *   revision_data_table = "protocol_control_field_revision",
 *   translatable = FALSE,
 *   admin_permission = "administer protocol control entities",
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
 *     "canonical" = "/protocols/protocol_control/{protocol_control}",
 *     "add-form" = "/protocols/protocol_control/add",
 *     "edit-form" = "/protocols/protocol_control/{protocol_control}/edit",
 *     "delete-form" = "/protocols/protocol_control/{protocol_control}/delete",
 *     "version-history" = "/protocols/protocol_control/{protocol_control}/revisions",
 *     "revision" = "/protocols/protocol_control/{protocol_control}/revisions/{protocol_control_revision}/view",
 *     "revision_revert" = "/protocols/protocol_control/{protocol_control}/revisions/{protocol_control_revision}/revert",
 *     "revision_delete" = "/protocols/protocol_control/{protocol_control}/revisions/{protocol_control_revision}/delete",
 *     "collection" = "/protocols/protocol_control",
 *   },
 *   field_ui_base_route = "protocol_control.settings"
 * )
 */
class ProtocolControl extends EditorialContentEntityBase implements ProtocolControlInterface {

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
  public static function getProtocolControlEntity(EntityInterface $entity) {
    if ($entity instanceof FieldableEntityInterface) {
      if ($entity->hasField('field_protocol_control')) {
        $pcs = $entity->get('field_protocol_control')->referencedEntities();
        if (is_array($pcs)) {
          return reset($pcs);
        }
      }
    }
    return NULL;
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

    // If no revision author has been set explicitly,
    // make the protocol_control owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }

    // Resolve the protocol set to a protocol ID and save.
    // dpm($this->getProtocolSetId());

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
   * {@inheritdoc}
   */
  public function getPrivacySettingOptions() {
    return $this->getFieldDefinition('field_sharing_setting')->getItemDefinition()->getSetting('allowed_values');
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivacySetting() {
    return $this->get('field_sharing_setting')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivacySetting($option) {
    return $this->set('field_sharing_setting', $option);
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    return $this->get('field_target_uuid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget($uuid) {
    return $this->set('field_target_uuid', $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function getProtocols() {
    return array_column($this->get('field_protocols')->getValue(), 'target_id');
  }

  /**
   * {@inheritdoc}
   */
  public function setProtocols($protocols) {
    return $this->set('field_protocols', $protocols);
  }

  /**
   * {@inheritDoc}
   */
  public function getCommunities() {
    $communities = [];
    $pids = $this->getProtocols();
    if (!empty($pids)) {
      $protocols = $this->entityTypeManager()->getStorage('protocol')->loadMultiple($pids);
      // Get the communities for each protocol.
      foreach ($protocols as $protocol) {
        /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
        $pCommunities = $protocol->getCommunities();
        // Build a community list without duplicates.
        foreach ($pCommunities as $pCommunity) {
          if (!isset($communities[$pCommunity->id()])) {
            $communities[$pCommunity->id()] = $pCommunity;
          }
        }
      }
    }

    return $communities;
  }

  /**
   * Create a hash key for a protocol set.
   */
  protected static function buildProtocolSetKey($protocols) {
    // Filter out any non IDs (nulls, whitespace).
    $filtered_protocols = array_filter($protocols, 'is_numeric');

    // Remove duplicates.
    $filtered_protocols = array_unique($filtered_protocols);

    // Sort so we don't have to worry about different combinations.
    sort($filtered_protocols);

    return implode(',', $filtered_protocols);
  }

  /**
   * Get the protocol set.
   *
   * @return string
   *   The protocol set.
   */
  protected function getProtocolSet() {
    $protocols = $this->getProtocols();
    return $this->buildProtocolSetKey($protocols);
  }

  /**
   * Handle protocol hash key to ID resolution.
   */
  protected static function protocolSetKeyToId($key) {
    if (empty($key)) {
      return NULL;
    }

    $database = \Drupal::database();

    // Check if this set already has an ID.
    $query = $database->select('mukurtu_protocol_map', 'mpm')
      ->fields('mpm', ['protocol_set_id'])
      ->condition('protocol_set', $key)
      ->range(0, 1);
    $result = $query->execute()->fetch();

    // Return if it does.
    if ($result) {
      return $result->protocol_set_id;
    }

    // ID doesn't exist, insert it here and return new ID.
    $result = $database->insert('mukurtu_protocol_map')
      ->fields([
        'protocol_set' => $key,
      ])->execute();

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getProtocolSetId() {
    $set = $this->getProtocolSet();
    return $this->protocolSetKeyToId($set);
  }

  /**
   * {@inheritDoc}
   */
  public function getNodeAccessGrants() {
    $grants = [];

    // Deny grant for missing/broken protocols.
    $grants[] = [
      'realm' => 'protocols',
      'gid' => 0,
      'grant_view' => 0,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 0,
    ];

    if ($this->getPrivacySetting() == 'all') {
      // User needs the grant that represents the set of all the
      // node's protocols.
      $gid = $this->getProtocolSetId();

      if ($gid) {
        $grants[] = [
          'realm' => 'protocols',
          'gid' => $gid,
          'grant_view' => 1,
          'grant_update' => 0,
          'grant_delete' => 0,
          'priority' => 0,
        ];
      }
    }
    else {
      // In this case, membership in any of involved protocols is
      // sufficient.
      $protocols = $this->getProtocols();
      foreach ($protocols as $protocol) {
        $gid = $this->protocolSetKeyToId($this->buildProtocolSetKey([$protocol]));
        if ($gid) {
          $grants[] = [
            'realm' => 'protocols',
            'gid' => $gid,
            'grant_view' => 1,
            'grant_update' => 0,
            'grant_delete' => 0,
            'priority' => 0,
          ];
        }
      }
    }

    return $grants;
  }

  /**
   * Get a list of all compound protocols in use on the site.
   */
  protected static function getCompoundProtocols() {
    $compoundProtocols = [];
    $database = \Drupal::database();

    $query = $database->select('mukurtu_protocol_map', 'mpm')
      ->fields('mpm', ['protocol_set_id', 'protocol_set']);
    $result = $query->execute()->fetchAll();
    foreach ($result as $ps) {
      if (str_contains($ps->protocol_set, ',')) {
        $compoundProtocols[$ps->protocol_set_id] = explode(',', $ps->protocol_set);
      }
    }

    return $compoundProtocols;
  }

  /**
   * Get the IDs of all published open protocols.
   */
  protected static function getAllOpenProtocols() {
    $query = \Drupal::entityQuery('protocol')
      ->condition('field_access_mode', 'open')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $results = $query->execute();

    return $results ? $results : [];
  }

  /**
   * {@inheritDoc}
   */
  public static function getAccountGrantIds(AccountInterface $account) {
    $grants = [];

    // Deny grant for missing protocols.
    $grants[0] = 0;

    /** @var \Drupal\og\OgMembershipInterface[] $memberships */
    $memberships = Og::getMemberships($account);
    $memberships = array_filter($memberships, fn ($e) => $e->getGroupEntityType() == 'protocol');

    // Get the protocol NID list and sort them.
    $protocols = array_map(fn ($e) => $e->getGroupId(), $memberships);
    sort($protocols);

    // User has access to all open protocols.
    foreach (self::getAllOpenProtocols() as $openProtocol) {
      $p_gid = self::protocolSetKeyToId(self::buildProtocolSetKey([$openProtocol]));
      $grants[$p_gid] = $p_gid;
    }

    // User has access to each single protocol they are a member of.
    foreach ($protocols as $protocol) {
      $p_gid = self::protocolSetKeyToId(self::buildProtocolSetKey([$protocol]));
      $grants[$p_gid] = $p_gid;
    }

    // Search the entire protocol table for combinations of protocols
    // that the user is a member of. This is potentially slow, but it's faster
    // than computing the super set of user protocols.
    foreach (self::getCompoundProtocols() as $id => $setProtocols) {
      $inAll = TRUE;
      foreach ($setProtocols as $setProtocol) {
        if (!in_array($setProtocol, $protocols)) {
          $inAll = FALSE;
          break;
        }
      }
      if ($inAll) {
        $grants[$id] = $id;
      }
    }

    return $grants;
  }

  /**
   * {@inheritDoc}
   */
  public function getMemberProtocols(?AccountInterface $user = NULL): array {
    $memberships = [];

    if (!$user) {
      $current_user = \Drupal::currentUser();
      $user = User::load($current_user->id());
    }

    $protocols = $this->getProtocols();
    if (!empty($protocols)) {
      $protocols = $this->entityTypeManager()->getStorage('protocol')->loadMultiple($protocols);
      foreach ($protocols as $protocol) {
        /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
        if ($protocol->isOpen()) {
          // Everybody is a "member" of an open protocol.
          $memberships[$protocol->id()] = $protocol;
        }
        else {
          // Strict protocol, need to lookup actual membership.
          $membership = Og::getMembership($protocol, $user);
          if ($membership) {
            $memberships[$protocol->id()] = $protocol;
          }
        }
      }
    }

    return $memberships;
  }

  /**
   * {@inheritdoc}
   */
  public function inGroup(AccountInterface $user): bool {
    $all = $this->getPrivacySetting() == 'all';

    $allProtocols = $this->getProtocols();
    $memberProtocols = $this->getMemberProtocols($user);

    if ($all) {
      return (count($allProtocols) == count($memberProtocols));
    }
    return !empty($memberProtocols);
  }

  /**
   * {@inheritdoc}
   */
  public function inAllGroups(AccountInterface $user): bool {
    $allProtocols = $this->getProtocols();
    $memberProtocols = $this->getMemberProtocols($user);
    return (count($allProtocols) == count($memberProtocols));
  }

  /**
   * {@inheritDoc}
   */
  public function getControlledEntity() {
    $entity_repository = \Drupal::service('entity.repository');
    // @todo we need to change this to store entity type id in the entity.
    list($id, $uuid) = explode(':', $this->getName());
    return $entity_repository->loadEntityByUuid($id, $uuid);
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
      ->setDescription(t('The user ID of author of the Protocol control entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
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
      ->setDescription(t('The name of the Protocol control entity.'))
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
      ->setRequired(FALSE);

    $fields['status']->setDescription(t('A boolean indicating whether the Protocol control is published.'))
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

    $fields['field_target_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target UUID'))
      ->setDescription(t('The UUID of the entity controlled by this protocol control entity.'))
      ->setRequired(FALSE)
      ->setCardinality(1)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['field_target_entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target Entity Type ID'))
      ->setDescription(t('The enitity type ID of the entity controlled by this protocol control entity.'))
      ->setRequired(FALSE)
      ->setCardinality(1)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['field_inheritance_target'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Protocol Inheritance Target'))
      ->setDescription(t('Inherit the protocol from the selected item.'))
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
          'entity_browser' => 'browse_content',
          'field_widget_display' => 'rendered_entity',
          'field_widget_display_settings' => [
            'view_mode' => 'content_browser',
          ],
          'field_widget_edit' => FALSE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'selection_mode' => 'selection_append',
          'open' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_protocols'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Protocols'))
      ->setDescription(t('The Protocols in the Protocol Control set.'))
      ->setSetting('target_type', 'protocol')
      ->setSetting('handler', 'default:protocol')
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

    $fields['field_sharing_setting'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sharing Protocol'))
      ->setDescription(t('TODO'))
      ->setSettings([
        'allowed_values' => [
          'all' => 'All',
          'any' => 'Any',
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
