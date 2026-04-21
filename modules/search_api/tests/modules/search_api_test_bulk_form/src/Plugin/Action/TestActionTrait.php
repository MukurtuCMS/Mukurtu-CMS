<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Reusable code for test actions.
 */
trait TestActionTrait {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL) {
    $key_value = \Drupal::keyValue('search_api_test');
    $result = $key_value->get('search_api_test_bulk_form', []);
    $result[] = [
      $this->getPluginId(),
      $entity->getEntityTypeId(),
      $entity->id(),
    ];
    $key_value->set('search_api_test_bulk_form', $result);
  }

}
