<?php

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

/**
 * Access controller for the Multipage Item entity.
 *
 * @see \Drupal\mukurtu_multipage_items\Entity\MultipageItem.
 */
class MultipageItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|NULL
   */
  protected ?EntityTypeManagerInterface $entityTypeManager = NULL;

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Sets the entity type manager for this handler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof MultipageItemInterface) {
      return AccessResult::neutral();
    }

    // First page controls access to the multipage item.
    $first_page = $entity->getFirstPage();
    if (!$first_page) {
      return parent::checkAccess($entity, $operation, $account);
    }
    return $first_page->access($operation, $account, TRUE)
      ->orIf(parent::checkAccess($entity, $operation, $account));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $protocols = $this->entityTypeManager()->getStorage('protocol')->loadMultiple();
    $protocol_defn = $this->entityTypeManager()->getDefinition('protocol');

    $cacheability_metadata = CacheableMetadata::createFromRenderArray([
      '#cache' => [
        'tags' => $protocol_defn->getListCacheTags(),
      ],
    ]);
    foreach ($protocols as $protocol) {
      assert($protocol instanceof ProtocolInterface);
      $cacheability_metadata->addCacheableDependency($protocol);
      $membership = $protocol->getMembership($account);
      if (!$membership) {
        continue;
      }
      $cacheability_metadata->addCacheableDependency($membership);

      foreach ($membership->getRoles() as $role) {
        $cacheability_metadata->addCacheableDependency($role);
        foreach ($role->getPermissions() as $permission) {
          $matches = [];
          if (preg_match('/^create (.+) content$/', $permission, $matches)) {
            $result = $this->multipageItemManager()
              ->isEnabledBundleType($matches[1]);
            $cacheability_metadata->addCacheableDependency($result);
            if ($result->isEnabled()) {
              return AccessResult::allowed()
                ->addCacheableDependency($cacheability_metadata);
            }
          }
        }
      }
    }
    return AccessResult::forbidden()->addCacheableDependency($cacheability_metadata);
  }

  protected function multipageItemManager(): MultipageItemManager {
    $multipage_item_manager = \Drupal::service(MultipageItemManager::class);
    assert($multipage_item_manager instanceof MultipageItemManager);
    return $multipage_item_manager;
  }

}
