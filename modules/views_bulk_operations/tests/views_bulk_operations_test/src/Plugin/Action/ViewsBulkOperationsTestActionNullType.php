<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations_test\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Action for test purposes only, keep type empty to ensure NULL types display.
 */
#[Action(
  id: 'views_bulk_operations_test_null_type',
  label: new TranslatableMarkup('VBO Null type test action'),
)]
final class ViewsBulkOperationsTestActionNullType extends ViewsBulkOperationsActionBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): mixed {
    $this->messenger()->addMessage(\sprintf('Test action (label: %s)', $entity->label()));
    return $this->t('Test');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
