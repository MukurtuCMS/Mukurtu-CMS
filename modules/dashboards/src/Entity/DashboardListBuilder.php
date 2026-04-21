<?php

namespace Drupal\dashboards\Entity;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Custom list builder for dashboards.
 *
 * @package Drupal\dashboards\Entity
 */
class DashboardListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dashboards';
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = [
      'view' => [
        'title' => new TranslatableMarkup('View'),
        'weight' => 1,
        'url' => Url::fromRoute(
          'entity.dashboard.canonical', [
            'dashboard' => $entity->id(),
          ]
        ),
      ],
      'layout' => [
        'title' => new TranslatableMarkup('Manage Layout'),
        'weight' => 1,
        'url' => Url::fromRoute(
          'layout_builder.dashboards.view', [
            'dashboard' => $entity->id(),
          ]
        ),
      ],
    ] + parent::getOperations($entity);

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      $this->t('Admin Label'),
      $this->t('Category'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /**
     * @var \Drupal\dashboards\Entity\Dashboard $entity
     */
    $row = [];
    $row['label'] = $entity->label();
    $row['category']['#markup'] = $entity->category;
    return $row + parent::buildRow($entity);
  }

}
