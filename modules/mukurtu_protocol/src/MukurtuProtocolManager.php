<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * Provides a service for managing and resolving Protocols.
 */
class MukurtuProtocolManager {

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
    $title = $community->get("title")->value . " " . t("Only");
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
   * Return account access for a given operation.
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
    // If the node has no protocol field, we don't have an opinion.
    if (!$entity->hasField(MUKURTU_PROTOCOL_FIELD_NAME_READ)) {
      return AccessResult::neutral();
    }

    $protocols = $this->getProtocols($entity);

    // TODO: Get from node.
    $protocol_mode = 'all';

    // If the protocol field exists but is empty, only the owner has access.
    if (empty($protocols)) {
      return ($entity->getOwnerId() == $account->id()) ? AccessResult::allowed() : AccessResult::forbidden();
    }

    $has_required_memberships = FALSE;

    // Is the user a member of all protocols?
    if ($protocol_mode == 'all') {
      $grant = $this->getProtocolGrantId($protocols);
      $user_grants = $this->getUserGrantIds($account);
      if (in_array($grant, $user_grants)) {
        $has_required_memberships = TRUE;
      }
    }

    // Is the user a member of any protocols?
    if ($protocol_mode == 'any') {
      foreach ($protocols as $protocol) {
        $grant = $this->getProtocolGrantId([$protocol]);
        $user_grants = $this->getUserGrantIds($account);
        if (in_array($grant, $user_grants)) {
          $has_required_memberships = TRUE;
          break;
        }
      }
    }

    switch ($operation) {
      case 'view':
        $view_permission = TRUE;
        return ($view_permission && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'create':
        $create_permission = TRUE;
        return ($create_permission && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'update':
        $update_permission = TRUE;
        return ($update_permission && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      case 'delete':
        $delete_permission = TRUE;
        return ($delete_permission && $has_required_memberships) ? AccessResult::allowed() : AccessResult::forbidden();

      default:
        return AccessResult::forbidden();
    }

    return AccessResult::forbidden();
  }

  /**
   * Check if the entity has protocol fields.
   */
  public function hasProtocolFields(EntityInterface $entity) {
    foreach ($this->protocolFields as $protocolField) {
      if (method_exists($entity, 'hasField') && $entity->hasField($protocolField['protocol'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function getUserProtocolMemberships(AccountInterface $account) {
    $memberships = Og::getMemberships($account);

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
    //sort($protocols);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($protocols);

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
    $filtered_protocols = array_filter($protocols, is_numeric);

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
  public function getProtocolGrantId(array $protocols) {
    $key = $this->createProtocolKey($protocols);

    // No protocols given resolves to null.
    if (!$key) {
      return NULL;
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

    if ($entity->hasField($protocolFieldName)) {
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
      // Get the protocols.
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
        $membership_manager->addMember($protocol, $account);

        // Log user add.
        // TODO: This should be handling success/failure.
        $this->logger->notice("User {$account->name->value} ($uid) added to protocol {$protocol->title->value} ({$protocol->id()}) as a result of being added to community {$entity->title->value} ($gid).");
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
      $membership_manager->removeMember($protocol, $account);
      $this->logger->notice("User {$account->name->value} ($uid) removed from protocol {$protocol->title->value} ({$protocol->id()}) as a result of being removed from community {$entity->title->value} ($gid).");
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

  /**
   * Return the owning community for a protocol.
   */
  public function getCommunity($protocol) {
    if ($protocol->hasField('field_mukurtu_community')) {
      $field_value = $protocol->get('field_mukurtu_community')->getValue();

      if(isset($field_value[0]['target_id'])) {
        $community_id = $field_value[0]['target_id'];
        return \Drupal::entityTypeManager()->getStorage('node')->load($community_id);
      }
    }

    return NULL;
  }
}
