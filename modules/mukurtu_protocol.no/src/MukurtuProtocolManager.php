<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a service for managing and resolving Protocols.
 */
class MukurtuProtocolManager {
  use StringTranslationTrait;

  public $protocolFields;
  protected $protocolTable;
  protected $protocolFieldName;
  protected $logger;

  /**
   * Load the protocol lookup table.
   */
  public function __construct() {
    // TODO: Allow this to be configured.
    $this->protocolFieldName = MUKURTU_PROTOCOL_FIELD_NAME_READ;
    $this->protocolFields = [
      ['protocol' => MUKURTU_PROTOCOL_FIELD_NAME_READ, 'scope' => MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE],
      ['protocol' => MUKURTU_PROTOCOL_FIELD_NAME_WRITE, 'scope' => MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE],
    ];

    // Initialize the protocol set table.
    $this->protocolTable = \Drupal::state()->get('mukurtu_protocol_lookup_table');

    // Starting protocol set IDs from 1.
    if (!isset($this->protocolTable['new_id'])) {
      $this->protocolTable['new_id'] = 1;
    }

    // Setup named logger.
    $this->logger = \Drupal::logger('Mukurtu');
  }

  /**
   * Create a protocol for a given community.
   */
  public function createProtocol($community, $membership_handler = 'manual', $options = []) {
    $title = $community->get("title")->value . " " . $this->t("Only");
    $nid = $community->id();

    $node = Node::create([
      'type' => 'protocol',
      'title' => $title,
      'field_mukurtu_community' => [$nid],
      'field_membership_handler' => $membership_handler,
    ]);

    $node->save();
  }

  /**
   * Re-intialize the protocol table with default values.
   */
  public function clearProtocolTable() {
    $this->protocolTable = [];
    $this->protocolTable['new_id'] = 1;
    $this->saveProtocolTable();
  }

  /**
   * Given the protocol field name, return the corresponding scope field name.
   */
  public function getProtocolScopeFieldname($protocol_field_name) {
    foreach ($this->protocolFields as $protocol_field) {
      if ($protocol_field['protocol'] == $protocol_field_name) {
        return $protocol_field['scope'];
      }
    }

    return NULL;
  }

  /**
   * Given an operation, return the scope/protocol field names.
   */
  public function getProtocolFieldByOperation($operation) {
    switch ($operation) {
      case 'edit':
      case 'update':
      case 'delete':
        return [MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE, MUKURTU_PROTOCOL_FIELD_NAME_WRITE];

      case 'view':
      default:
        return [MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE, MUKURTU_PROTOCOL_FIELD_NAME_READ];
    }
  }

  /**
   * Return account access for a given operation.
   *
   * Checking in order of importance.
   * - Mukurtu membership rules.
   * - OG User Role Permissions - Protocol.
   * - OG User Role Permissions - Community.
   * - Drupal User Role Permissions.
   *
   * @param \Drupal\Core\Entity\EntityInterface|string $entity
   *   Either a node entity or the machine name of the content type on which to
   *   perform the access check.
   * @param string $operation
   *   The operation to be performed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user object to perform the access check operation on.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Get the correct fieldnames.
    list($scope_field_name, $protocol_field_name) = $this->getProtocolFieldByOperation($operation);

    // If the node has no scope/protocol field, we don't have an opinion.
    if (!($entity->hasField($protocol_field_name) && $entity->hasField($scope_field_name))) {
      return AccessResult::neutral();
    }

    // Get the content's protocol scope and protocols.
    $protocols = $this->getProtocols($entity, $protocol_field_name);
    $protocol_scope = $entity->get($scope_field_name)->value;

    // Default permissions to deny.
    $has_required_memberships = FALSE;
    $og_user_access_protocol = FALSE;

    // Handle unpublished content. Ideally we'd be returning
    // AccessResult::neutral and letting Drupal resolve this,
    // but OG messes this up. Worth taking another look
    // at this later as OG exits alpha.
    if ($entity->hasField('status') && $entity->get('status')->value == FALSE) {
      // If the user is not the author or does not have the correct
      // permisssion, deny.
      $author = $entity->getOwner();
      $permission = $entity->getEntityType() == 'media' ? 'view own unpublished media' : 'view own unpublished content';
      if ($author && !($author->id() == $account->id() && $account->hasPermission($permission))) {
        $unpublished_view_permission = FALSE;
      } else {
        $unpublished_view_permission = TRUE;
      }
    } else {
      $unpublished_view_permission = TRUE;
    }

    // Update protocol scope is default, which is classic Mukurtu v2 behavior.
    if ($protocol_scope == MUKURTU_PROTOCOL_DEFAULT) {
      // Replace the update protocol scope/protocols with the read values.
      $read_scope = $entity->get(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE)->value;

      $protocol_scope = $read_scope == MUKURTU_PROTOCOL_PERSONAL ? MUKURTU_PROTOCOL_PERSONAL : MUKURTU_PROTOCOL_ALL;
      //$protocol_scope = MUKURTU_PROTOCOL_ALL;//$entity->get(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE)->value;
      $protocols = $this->getProtocols($entity, MUKURTU_PROTOCOL_FIELD_NAME_READ);
    }

    // Item is set to personal, only the author should have access.
    if ($protocol_scope == MUKURTU_PROTOCOL_PERSONAL && ($entity->getOwnerId() == $account->id())) {
      // No OG checks or specific operation checks for personal items, we can return right away.
      return AccessResult::allowed();
    }

    // Item is public, everybody is a member.
    if ($protocol_scope == MUKURTU_PROTOCOL_PUBLIC) {
      // Item is public and operation is 'view', we don't need to check OG.
      if ($operation == 'view') {
        // If the item is unpublished, user either needs 'view own unpublished'
        // or update access.
        return $unpublished_view_permission ? AccessResult::allowed() : $this->checkAccess($entity, 'update', $account);
      }
    }

    // Is the user a member of *all* protocols?
    if ($protocol_scope == MUKURTU_PROTOCOL_ALL) {
      $grant = $this->getProtocolGrantId($protocols);
      $user_grants = $this->getUserGrantIds($account);
      if (in_array($grant, $user_grants)) {
        // User is a member of all required protocols.
        // For viewing we don't need to check OG and can return right away.
        if ($operation == 'view') {
          return $unpublished_view_permission ? AccessResult::allowed() : AccessResult::forbidden();
        }
        $has_required_memberships = TRUE;

        // Only nodes and media have permisssions as OG group content.
        if ($entity->getEntityType() === 'node' || $entity->getEntityType() === 'media') {
          // User meets the Mukurtu protocol requirements, now check
          // if they have the OG permissions. In this case, they need
          // OG permissions for ALL protocol groups.
          foreach ($protocols as $protocol) {
            if (is_null($protocol)) {
              continue;
            }

            // Load the protocol node.
            $group = \Drupal::entityTypeManager()->getStorage('node')->load($protocol);
            if ($group) {
              // Ask OG if they have the named permission.
              $og_access = \Drupal::service('og.access')->userAccessGroupContentEntityOperation($operation, $group, $entity, $account);

              // OG says they do not have access.
              if (!$og_access->isAllowed()) {
                return AccessResult::forbidden();
              }
            }
          }
        }

        // At this point the OG didn't invalidate the user.
        $og_user_access_protocol = TRUE;
      }
    }

    // Is the user a member of ANY protocols?
    if ($protocol_scope == MUKURTU_PROTOCOL_ANY) {
      foreach ($protocols as $protocol) {
        $grant = $this->getProtocolGrantId([$protocol]);
        $user_grants = $this->getUserGrantIds($account);

        if (in_array($grant, $user_grants)) {
          // The user is a member of at least one protocol.
          $has_required_memberships = TRUE;

          // Only nodes and media have permisssions as OG group content.
          if ($entity->getEntityType() != 'node' && $entity->getEntityType() != 'media') {
            $og_user_access_protocol = TRUE;
          }

          // We don't need to check OG for view, we can return right away.
          if ($operation == 'view') {
            return $unpublished_view_permission ? AccessResult::allowed() : AccessResult::forbidden();
          }

          // If we've already found one valid OG permission, stop checking.
          if ($og_user_access_protocol) {
            break;
          }

          // User is in one of the groups, ask OG if it has the required
          // permission to do the operation. In this case the user needs
          // OG permissions for only a single group.
          $group = \Drupal::entityTypeManager()->getStorage('node')->load($protocol);
          $og_access = \Drupal::service('og.access')->userAccessGroupContentEntityOperation($operation, $group, $entity, $account);
          if ($og_access->isAllowed()) {
            $og_user_access_protocol = TRUE;
          }
        }
      }
    }

    switch ($operation) {
      case 'view':
        $view_permission = TRUE;
        //dpm("view");
        // Handle unpublished content. Ideally we'd be returning AccessResult::neutral and
        // letting Drupal resolve this, but OG messes this up. Worth taking another look
        // at this later.
        if ($entity->hasField('status') && $entity->get('status')->value == FALSE) {
          // If the user is not the author or does not have the correct permisssion, deny.
          $author = $entity->getOwner();
          if ($author && !($author->id() == $account->id() && $account->hasPermission('view own unpublished content'))) {
            $view_permission = FALSE;
          }
        }

        return ($view_permission && $og_user_access_protocol && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'create':
        $create_permission = TRUE;
        return ($create_permission && $og_user_access_protocol && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'edit':
      case 'update':
        $update_permission = TRUE;
        return ($update_permission && $og_user_access_protocol && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'delete':
        $delete_permission = TRUE;
        return ($delete_permission && $og_user_access_protocol && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      default:
        //dpm("unhandled op $operation");
        return AccessResult::forbidden();
    }

    return AccessResult::forbidden();
  }

  /**
   * Check if the entity has protocol fields.
   */
  public function hasProtocolFields(EntityInterface $entity) {
    foreach ($this->protocolFields as $protocolField) {
      if (!method_exists($entity, 'hasField') || !$entity->hasField($protocolField['protocol'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Return the loaded protocol nodes the account has edit abilities in.
   */
  public function getValidProtocols($entity_type, $bundle, AccountInterface $account) {
    $memberships = Og::getMemberships($account);

    $entity_permission = $entity_type == 'media' ? 'media' : 'content';

    // Helper function to filter memberships to protocols only.
    $protocols_only = function ($e) {
      if ($e->get('entity_bundle')->value == 'protocol') {
        return TRUE;
      }
      return FALSE;
    };

    $memberships = array_filter($memberships, $protocols_only);

    // Helper function to take OG membership and return the protocol NID.
    $get_protocol_id = function ($e) {
      return $e->get('entity_id')->value;
    };

    // Get the protocol NID list and sort them.
    $protocols = array_map($get_protocol_id, $memberships);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($protocols);

    /** @var \Drupal\og\OgAccessInterface $og_access */
    $og_access = \Drupal::service('og.access');
    foreach ($nodes as $index => $protocol) {
      $access_result = AccessResult::Forbidden();
      if ($protocol && method_exists($protocol, 'id')) {
        $access_result = $og_access->userAccess($protocol, "edit any $bundle $entity_permission");
      }

      if (!$access_result->isAllowed()) {
        // Remove the protocol if the user doesn't have update abilities.
        unset($nodes[$index]);
      }
    }

    return $nodes;
  }

  /**
   * Return an array of effective protocols the user belongs to.
   *
   * @param Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function getUserGrantIds(AccountInterface $account) {
    $grants = [];
    $memberships = Og::getMemberships($account);

    // User is a member of their own personal protocol.
    $uid = $account->id();
    $personal = $this->getProtocolGrantId([$uid], 'user');
    $grants[$personal] = $personal;

    // All users have the public protocol.
    $public = $this->getProtocolGrantId([], 'public');
    $grants[$public] = $public;

    // Helper function to filter memberships to protocols only.
    $protocols_only = function ($e) {
      if ($e->get('entity_bundle')->value == 'protocol') {
        return TRUE;
      }
      return FALSE;
    };

    $memberships = array_filter($memberships, $protocols_only);

    // Helper function to take OG membership and return the protocol NID.
    $get_protocol_id = function ($e) {
      return $e->get('entity_id')->value;
    };

    // Get the protocol NID list and sort them.
    $protocols = array_map($get_protocol_id, $memberships);
    sort($protocols);

    // User has access to each single protocol they are a member of.
    foreach ($protocols as $protocol) {
      $p_gid = $this->getProtocolGrantId([$protocol]);
      $grants[$p_gid] = $p_gid;
    }

    // Search the entire protocol table for combinations of protocols
    // that the user is a member of. This is potentially slow, but it's faster
    // than computing the super set of user protocols.
    foreach ($this->protocolTable as $key => $superProtocol) {
      $superProtocolProtocols = explode(',', $key);
      $length = count($superProtocolProtocols);
      $i = 1;

      foreach ($superProtocolProtocols as $spp) {
        if (!in_array($spp, $protocols)) {
          break;
        }

        // The user is a member of all of the protocols in superProtocolProtocols.
        if ($i++ == $length) {
          $grants[$superProtocol] = $superProtocol;
        }
      }
    }

    return $grants;
  }

  /**
   * Save the protocol table.
   */
  protected function saveProtocolTable() {
    \Drupal::state()->set('mukurtu_protocol_lookup_table', $this->protocolTable);
  }

  /**
   * Create an array key for an effective protocol.
   *
   * @param array $protocols
   *   An array containing all the nids of the protocols.
   */
  protected function createProtocolKey(array $protocols) {
    // Filter out any non IDs (nulls, whitespace).
    $filtered_protocols = array_filter($protocols, 'is_numeric');

    // Remove duplicates.
    $filtered_protocols = array_unique($filtered_protocols);

    // Sort so we don't have to worry about different combinations.
    sort($filtered_protocols);

    return implode(',', $filtered_protocols);
  }

  /**
   * Return the Grant ID for an effective protocol.
   *
   * @param array $protocols
   *   An array containing all the nids of the protocols.
   */
  public function getProtocolGrantId(array $protocols, $type = NULL) {
    // Special handling for public access.
    if ($type == 'public') {
      if (isset($this->protocolTable['public'])) {
        return $this->protocolTable['public'];
      }
      $new_id = $this->protocolTable['new_id']++;
      $this->protocolTable['public'] = $new_id;
      $this->saveProtocolTable();
      return $new_id;
    }

    $key = $this->createProtocolKey($protocols);

    // No protocols given resolves to null.
    if (!$key) {
      return NULL;
    }

    // Special handling for user specific protocol.
    if ($type == 'user') {
      if (isset($this->protocolTable['user'][$key])) {
        return $this->protocolTable['user'][$key];
      }
      $new_id = $this->protocolTable['new_id']++;
      $this->protocolTable['user'][$key] = $new_id;
      $this->saveProtocolTable();
      return $new_id;
    }

    // Return the ID if it already exists.
    if (isset($this->protocolTable[$key])) {
      return $this->protocolTable[$key];
    }

    // Create it if it does not.
    $new_id = $this->protocolTable['new_id']++;
    $this->protocolTable[$key] = $new_id;
    $this->saveProtocolTable();
    return $new_id;
  }

  /**
   * Return the array of protocol NIDs an entity is using.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function getProtocols(EntityInterface $entity, $protocolFieldName = MUKURTU_PROTOCOL_FIELD_NAME_READ) {
    $protocols = [];

    if (method_exists($entity, 'hasField') && $entity->hasField($protocolFieldName)) {
      $protocols_og = $entity->get($protocolFieldName)->getValue();
      $flatten = function ($e) {
        return isset($e['target_id']) ? $e['target_id'] : NULL;
      };
      $protocols = array_map($flatten, $protocols_og);
    }

    return $protocols;
  }

  /**
   * Return the effective protocol ID the node is using.
   *
   * @param Drupal\node\Entity\Node $node
   *   The node.
   */
  public function getNodeProtocolId(Node $node) {
    $protocols = $this->getProtocols($node);
    return $this->getProtocolGrantId($protocols);
  }

  /**
   * Delete protocol table entries for a given entry.
   */
  public function clearProtocolAccess(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $langcode = $entity->get('langcode')->value;
    $entity_id = $entity->id();

    $connection = \Drupal::database();
    $query = $connection->delete('mukurtu_protocol_access')
      ->condition('id', $entity_id)
      ->condition('langcode', $langcode)
      ->condition('entity_type', $entity_type)
      ->execute();
  }

  /**
   * Handle any protocol set management on entity update.
   */
  public function handleProtocolUpdate(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $langcode = $entity->get('langcode')->value;

    // Clear out old protocol entries.
    $this->clearProtocolAccess($entity);

    $update = [];

    foreach ($this->protocolFields as $protocolField) {
      $scope = $entity->get($protocolField['scope'])->value;

      // Public.
      if ($scope == MUKURTU_PROTOCOL_PUBLIC) {
        $new_p = $this->getProtocolGrantId([], 'public');
        $update[$new_p] = ['protocol' => $new_p, 'view' => 1, 'update' => 0, 'delete' => 0];
      }

      // Personal, author only.
      if ($scope == MUKURTU_PROTOCOL_PERSONAL) {
        $uid = $entity->getOwnerId();
        $new_p = $this->getProtocolGrantId([$uid], 'user');
        $update[$new_p] = ['protocol' => $new_p, 'view' => 1, 'update' => 0, 'delete' => 0];
      }

      // All other scopes involve protocols, get the protocols.
      $protocols = $this->getProtocols($entity, $protocolField['protocol']);

      // Nothing to do, skip.
      if (empty($protocols)) {
        continue;
      }

      // "OR" operation, no protocol sets needed, each protocol is atomic.
      if ($entity->get($protocolField['scope'])->value == MUKURTU_PROTOCOL_ANY) {
        foreach ($protocols as $p) {
          $new_p = $this->getProtocolGrantId([$p]);
          $update[$new_p] = ['protocol' => $new_p, 'view' => 1, 'update' => 0, 'delete' => 0];
        }
      }

      // "AND" operation, protocol needs a protocol set.
      if ($entity->get($protocolField['scope'])->value == MUKURTU_PROTOCOL_ALL) {
        $new_p = $this->getProtocolGrantId($protocols);
        $update[$new_p] = ['protocol' => $new_p, 'view' => 1, 'update' => 0, 'delete' => 0];
      }
    }

    if (!empty($update)) {
      $connection = \Drupal::database();
      foreach ($update as $row) {
        $result = $connection->insert('mukurtu_protocol_access')
          ->fields([
            'id' => $entity_id,
            'langcode' => $langcode,
            'entity_type' => $entity_type,
            'protocol_set_id' => $row['protocol'],
            'grant_view' => $row['view'],
            'grant_update' => $row['update'],
            'grant_delete' => $row['delete'],
          ])
          ->execute();
      }
    }
  }

  /**
   * Push any protocol changes from entity to children.
   */
  public function updateProtocolInheritance(EntityInterface $entity, $operation = 'update') {
    // TODO: This shouldn't be hardcoded.
    $entity_types = ['node', 'media', 'message', 'comment'];
    $item_count = 0;

    // Protocol inheritance targets can only be nodes.
    if ($entity->getEntityTypeId() != 'node') {
      return;
    }

    // Query for any items targeting this entity for inheritance.
    $ids = [];
    foreach ($entity_types as $entity_type) {
      $query = \Drupal::entityQuery($entity_type)
        ->condition(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET, $entity->id());
      $ids[$entity_type] = $query->execute();
      $item_count += count($ids);
    }

    if ($item_count > 0) {
      // For fewer than 25 items that need updating,
      // do them without batch processing.
      if ($item_count < 25) {
        foreach ($entity_types as $entity_type) {
          $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
          $entities = $entity_storage->loadMultiple($ids[$entity_type]);
          foreach ($entities as $entity_to_be_updated) {
            if ($operation == 'delete') {
              $this->clearProtocolInheritance($entity_to_be_updated);
            } else {
              $this->copyProtocolFields($entity, $entity_to_be_updated);
            }
          }
        }
      } else {
        // More than 25 items and we run it in batch.
        $batch = [
          'title' => $this->t('Resolving Protocol Inheritance'),
          'operations' => [
            [
              'mukurtu_protocol_batch_update_protocol_inheritance',
              [
                [
                  'entity' => $entity,
                  'ids' => $ids,
                  'operation' => $operation,
                ],
              ],
            ],
          ],
          'file' => drupal_get_path('module', 'mukurtu_protocol') . '/mukurtu_protocol.protocolinheritance.inc',
        ];
        batch_set($batch);
      }
    }
  }

  /**
   * Clear the protocol inheritance target field.
   */
  public function clearProtocolInheritance(EntityInterface $entity) {
    if ($entity->hasField(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET)) {
      $target = $entity->get(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET);
      if (isset($target[0]) && isset($target[0]->target_id) && !is_null($target[0]->target_id)) {
        $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET, []);
        if (method_exists($entity, 'setRevisionLogMessage')) {
          $entity->setRevisionLogMessage('Item is no longer using protocol inheritance (target item deleted).');
        }
        $entity->save();
      }
    }
  }

  /**
   * Copy protocol fields from templateEntity to entity and save entity.
   */
  public function copyProtocolFields(EntityInterface $templateEntity, EntityInterface $entity) {
    $scope_fields = [MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE, MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE];
    $protocol_fields = [MUKURTU_PROTOCOL_FIELD_NAME_READ, MUKURTU_PROTOCOL_FIELD_NAME_WRITE];
    $dirty = FALSE;

    try {
      // Copy scope fields.
      foreach ($scope_fields as $field) {
        if ($templateEntity->hasField($field) && $entity->hasField($field)) {
          // Only copy if different.
          if ($templateEntity->get($field)->value != $entity->get($field)->value) {
            $entity->set($field, $templateEntity->get($field)->value);
            $dirty = TRUE;
          }
        }
      }

      // Copy protocol reference fields.
      foreach ($protocol_fields as $field) {
        // If there's a field mismatch, skip it.
        if (!$templateEntity->hasField($field) || !$entity->hasField($field)) {
          continue;
        }

        $templateProtocols = $this->getProtocols($templateEntity, $field);
        $entityProtocols = $this->getProtocols($entity, $field);

        // If they have a different number of protocols they are for sure
        // different.
        if (count($templateProtocols) != count($entityProtocols)) {
          $entity->set($field, $templateProtocols);
          $dirty = TRUE;
        } else {
          // We want to know if they are EXACTLY the same, including position.
          foreach ($templateProtocols as $delta => $tp) {
            if (!isset($entityProtocols[$delta]) || $entityProtocols[$delta] != $tp) {
              $entity->set($field, $templateProtocols);
              $dirty = TRUE;
              break;
            }
          }
        }
      }

      // Only save the entity if we made changes.
      if ($dirty) {
        if (method_exists($entity, 'setRevisionLogMessage')) {
          $entity->setRevisionLogMessage('Updated protocol fields via protocol inheritance.');
        }
        $entity->save();
      }
    } catch (Exception $e) {

    }
  }

  /**
   * Get all protocols associated with a community.
   */
  public function getCommunityProtocols(EntityInterface $community) {
    // TODO: This currently only takes into consideration the single level community.
    // TODO: Later once sub-communities are figured out, we need to change to handle them.

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'protocol')
      ->condition('field_mukurtu_community', $community->id());
    $nids = $query->execute();
    $nodes = $node_storage->loadMultiple($nids);

    return $nodes;
  }

  /**
   * Handle protocol membership changes needed when community user is added.
   */
  public function processCommunityMembershipInsert($gid, $uid) {
    $membership_manager = \Drupal::service('mukurtu_protocol.membership_manager');

    // Load the community entity.
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($gid);

    // Load the user.
    $account = User::load($uid);

    // Get all the community protocols.
    $protocols = $this->getCommunityProtocols($entity);

    // Check if any protocols are using the "community" mode.
    foreach ($protocols as $protocol) {
      if ($protocol->field_membership_handler->value == 'community') {
        // Add the user to the protocol.
        if ($membership_manager->addMember($protocol, $account)) {
          // Log user add.
          $this->logger->notice("User {$account->name->value} ($uid) added to protocol {$protocol->title->value} ({$protocol->id()}) as a result of being added to community {$entity->title->value} ($gid).");
        }
      }
    }
  }

  /**
   * Handle protocol membership changes needed when community user is removed.
   */
  public function processCommunityMembershipDelete($gid, $uid) {
    $membership_manager = \Drupal::service('mukurtu_protocol.membership_manager');

    // Load the community entity.
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($gid);

    // Load the user.
    $account = User::load($uid);

    // Get all the community protocols.
    $protocols = $this->getCommunityProtocols($entity);

    // Remove user from all community protocols.
    foreach ($protocols as $protocol) {
      if ($membership_manager->removeMember($protocol, $account)) {
        $this->logger->notice("User {$account->name->value} ($uid) removed from protocol {$protocol->title->value} ({$protocol->id()}) as a result of being removed from community {$entity->title->value} ($gid).");
      }
    }
  }

  /**
   * Handle community membership changes needed when protocol user is added.
   */
  public function processProtocolMembershipInsert($gid, $uid) {
    $membership_manager = \Drupal::service('mukurtu_protocol.membership_manager');

    // Load the protocol entity.
    $protocol = \Drupal::entityTypeManager()->getStorage('node')->load($gid);

    // Load the user.
    $account = User::load($uid);

    if ($protocol->hasField('field_mukurtu_community')) {
      $community_nid = $protocol->get('field_mukurtu_community')->target_id;
      if ($community_nid) {
        $community = \Drupal::entityTypeManager()->getStorage('node')->load($community_nid);
        if ($membership_manager->addMember($community, $account)) {
          $this->logger->notice("User {$account->name->value} ($uid) added to community {$community->title->value} ({$community->id()}) as a result of being added to protocol {$protocol->title->value} ($gid).");
        }
      }
    }
  }

  /**
   * Invalidate cache for content under a given protocol.
   */
  public function invalidateProtocolCache(EntityInterface $protocol) {
    // Nodes.
    foreach ($this->protocolFields as $protocolField) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $query = \Drupal::entityQuery('node')
        ->condition($protocolField['protocol'], $protocol->id());
      $ids = $query->execute();

      if (!empty($ids)) {
        Cache::invalidateTags(['library_info', 'node_list']);

        $entities = $storage->loadMultiple($ids);
        foreach ($entities as $entity) {
          Cache::invalidateTags($entity->getCacheTagsToInvalidate());
        }
      }
    }

    // Media. This errors out if we don't include the bundle,
    // otherwise we'd lump it together in a loop with nodes.
    $moduleHandler = \Drupal::service('module_handler');
    // We are checking for the existance of the mukurtu_digital_heritage module
    // purely because this crashes the unit/kernel tests if media isn't fully
    // bootstrapped. Longterm this should be set to something that actually makes
    // sense.
    if ($moduleHandler->moduleExists('mukurtu_digital_heritage')) {
      foreach ($this->protocolFields as $protocolField) {
        $storage = \Drupal::entityTypeManager()->getStorage('media');
        $query = \Drupal::entityQuery('media')
        ->exists('bundle')
        ->condition($protocolField['protocol'], $protocol->id());
        $ids = $query->execute();

        if (!empty($ids)) {
          Cache::invalidateTags(['library_info', 'media_list']);

          $entities = $storage->loadMultiple($ids);
          foreach ($entities as $entity) {
            Cache::invalidateTags($entity->getCacheTagsToInvalidate());
          }
        }
      }
    }
  }

  /**
   * Return the owning community for a protocol.
   */
  public function getCommunity($protocol) {
    if ($protocol->hasField('field_mukurtu_community')) {
      $field_value = $protocol->get('field_mukurtu_community')->getValue();

      if (isset($field_value[0]['target_id'])) {
        $community_id = $field_value[0]['target_id'];
        return \Drupal::entityTypeManager()->getStorage('node')->load($community_id);
      }
    }

    return NULL;
  }

}
