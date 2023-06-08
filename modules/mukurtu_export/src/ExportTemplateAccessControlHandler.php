<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the Export Template entity.
 *
 * @see \Drupal\mukurtu_export\Entity\ExportTemplate.
 */
class ExportTemplateAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $export, $operation, AccountInterface $account) {
    // Allowed when the operation is not view or the status is true.
    /** @var \Drupal\mukurtu_export\Entity\ExportTemplate $export */
    $access_result = AccessResult::allowed();
    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
      return AccessResult::allowed();
  }

}
