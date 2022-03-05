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
        ['provideApplyProtocolPermission'],
      ],
    ];
  }

  /**
   * Provides Mukurtu specific OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideApplyProtocolPermission(PermissionEventInterface $event) {
    if ($event->getGroupEntityTypeId() === 'protocol') {
      $event->setPermissions([
        new GroupPermission([
          'name' => 'apply protocol',
          'title' => $this->t('Apply Protocol'),
          'description' => $this->t('Apply the protocol to content.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
      ]);
    }
  }

}
