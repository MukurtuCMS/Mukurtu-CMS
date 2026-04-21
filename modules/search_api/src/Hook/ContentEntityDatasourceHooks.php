<?php

namespace Drupal\search_api\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides hook implementations on behalf of the Content Entity datasource.
 *
 * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntity
 * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager
 */
class ContentEntityDatasourceHooks {

  public function __construct(
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    protected ContentEntityTrackingManager $trackingManager,
  ) {}

  /**
   * Implements hook_entity_insert().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityInsert()
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->trackingManager->entityInsert($entity);
  }

  /**
   * Implements hook_entity_update().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityUpdate()
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->trackingManager->entityUpdate($entity);
  }

  /**
   * Implements hook_entity_delete().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityDelete()
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->trackingManager->entityDelete($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for type "search_api_index".
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::indexUpdate()
   */
  #[Hook('search_api_index_update')]
  public function searchApiIndexUpdate(IndexInterface $index): void {
    $this->trackingManager->indexUpdate($index);
  }

}
