<?php

namespace Drupal\mukurtu_community_records\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\GroupContentEntityOperationAccessEventInterface;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupPermission;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgRoleInterface;
use Drupal\og\PermissionManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for community records.
 */
class MukurtuCommunityRecordEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs an MukurtuCommunityRecordEventSubscriber object.
   *
   * @param \Drupal\og\PermissionManagerInterface $permissionManager
   *   The OG permission manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The service providing information about bundles.
   * @param \Drupal\og\OgAccessInterface $ogAccess
   *   The OG access service.
   */
  public function __construct(protected PermissionManagerInterface $permissionManager, protected EntityTypeManagerInterface $entityTypeManager, protected EntityTypeBundleInfoInterface $entityTypeBundleInfo, protected OgAccessInterface $ogAccess) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PermissionEventInterface::EVENT_NAME => [
        ['provideDefaultMukurtuOgPermissions'],
      ],
      GroupContentEntityOperationAccessEventInterface::EVENT_NAME => [
        ['checkGroupCommunityRecordsAccess'],
      ],
    ];
  }

  /**
   * Provides default OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideDefaultMukurtuOgPermissions(PermissionEventInterface $event) {
    if ($event->getGroupEntityTypeId() == 'protocol') {
      $event->setPermissions([
        new GroupPermission([
          'name' => 'administer community records',
          'title' => t('Administer Community Records'),
          'description' => t('Users may manage community records for content in this group.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
      ]);
    }
  }

  /**
   * Checks if a user has access to administer community records.
   *
   * @param \Drupal\og\Event\GroupContentEntityOperationAccessEventInterface $event
   *   The event fired when a group content entity operation is performed.
   */
  public function checkGroupCommunityRecordsAccess(GroupContentEntityOperationAccessEventInterface $event): void {
    $group_content_entity = $event->getGroupContent();
    $group_entity = $event->getGroup();
    $user = $event->getUser();

    if (!mukurtu_community_records_is_community_record($group_content_entity)) {
      $event->mergeAccessResult(AccessResult::neutral()->addCacheableDependency($group_content_entity));
    }
    else {
      $event->mergeAccessResult($this->ogAccess->userAccess($group_entity, 'administer community records', $user));
    }
  }

}
