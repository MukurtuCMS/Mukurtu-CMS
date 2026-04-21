<?php

namespace Drupal\message_digest;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for digest interval configs.
 */
class MessageDigestIntervalListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['interval'] = [
      'data' => $this->t('interval'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];
    $row['interval'] = Xss::filterAdmin($entity->getInterval());
    return $row + parent::buildRow($entity);
  }

}
