<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_protocol\EventSubscriber;

use Drupal\og\EventSubscriber\OgEventSubscriber;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\PermissionManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\OgRoleInterface;
use Drupal\og\GroupPermission;
use Drupal\og\GroupContentOperationPermission;

/**
 * Event subscribers for Organic Groups.
 */
class MukurtuProtocolOgEventSubscriber extends OgEventSubscriber {

  use StringTranslationTrait;

  /**
   * The OG permission manager.
   *
   * @var \Drupal\og\PermissionManagerInterface
   */
  protected $permissionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The service providing information about bundles.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * Constructs an MukurtuProtocolOgEventSubscriber object.
   *
   * @param \Drupal\og\PermissionManagerInterface $permission_manager
   *   The OG permission manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The service providing information about bundles.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(PermissionManagerInterface $permission_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, OgAccessInterface $og_access) {
    $this->permissionManager = $permission_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PermissionEventInterface::EVENT_NAME => [
        ['provideDefaultProtocolsPermission'],
        ['provideDefaultCommunityPermissions'],
      ],
    ];
  }

  /**
   * Provides Mukurtu specific OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideDefaultMediaPermissions(PermissionEventInterface $event) {
  }

  /**
   * Provides Mukurtu specific OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideDefaultCommunityPermissions(PermissionEventInterface $event) {
    if ($event->getGroupEntityTypeId() === 'community') {
      $event->setPermissions([
        new GroupContentOperationPermission([
          'name' => 'create protocol protocol',
          'title' => $this->t('Create new protocols'),
          'entity type' => 'protocol',
          'bundle' => 'protocol',
          'operation' => 'create',
          'default roles' => [],
          'restrict access' => FALSE,
        ]),
        new GroupContentOperationPermission([
          'name' => 'update own protocol protocol',
          'title' => $this->t('Edit own protocols'),
          'entity type' => 'protocol',
          'bundle' => 'protocol',
          'operation' => 'update',
          'owner' => TRUE,
          'default roles' => [],
          'restrict access' => FALSE,
        ]),
        new GroupContentOperationPermission([
          'name' => 'update any protocol protocol',
          'title' => $this->t('Edit any protocols'),
          'entity type' => 'protocol',
          'bundle' => 'protocol',
          'operation' => 'update',
          'owner' => FALSE,
          'default roles' => [],
          'restrict access' => FALSE,
        ]),
        new GroupContentOperationPermission([
          'name' => 'delete own protocol protocol',
          'title' => $this->t('Delete own protocols'),
          'entity type' => 'protocol',
          'bundle' => 'protocol',
          'operation' => 'delete',
          'owner' => TRUE,
          'default roles' => [],
          'restrict access' => FALSE,
        ]),
        new GroupContentOperationPermission([
          'name' => 'delete any protocol protocol',
          'title' => $this->t('Delete any protocols'),
          'entity type' => 'protocol',
          'bundle' => 'protocol',
          'operation' => 'delete',
          'owner' => FALSE,
          'default roles' => [],
          'restrict access' => FALSE,
        ]),
      ]);
    }
  }

  /**
   * Provides Mukurtu specific OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideDefaultProtocolsPermission(PermissionEventInterface $event) {
    if ($event->getGroupEntityTypeId() === 'protocol') {
      $event->setPermissions([
        new GroupPermission([
          'name' => 'apply protocol',
          'title' => $this->t('Apply Protocol'),
          'description' => $this->t('Apply the protocol to content.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
        new GroupPermission([
          'name' => 'administer comments',
          'title' => $this->t('Administer comments and comment settings'),
          'description' => $this->t('Approve/unpublish commments and change comment settings at the protocol level.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
        new GroupPermission([
          'name' => 'skip comment approval',
          'title' => $this->t('Skip comment approval'),
          'description' => $this->t('Comments can be immediately be published unless overridden by site or other protocol configuration.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
      ]);

      // Add a list of generic CRUD permissions for all group content.
      $group_content_permissions = $this->getDefaultEntityOperationPermissions($event->getGroupContentBundleIds());
      $event->setPermissions($group_content_permissions);
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function getDefaultEntityOperationPermissions(array $group_content_bundle_ids) {
    $permissions = [];

    foreach ($group_content_bundle_ids as $group_content_entity_type_id => $bundle_ids) {
      foreach ($bundle_ids as $bundle_id) {
        $bundle_permissions = $this->generateEntityOperationPermissionList($group_content_entity_type_id, $bundle_id);
        $permissions = array_merge($permissions, $bundle_permissions);
      }
    }

    return $permissions;
  }

}
