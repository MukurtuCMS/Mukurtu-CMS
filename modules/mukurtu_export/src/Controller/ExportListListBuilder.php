<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * List builder for Export List entities.
 */
class ExportListListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = 25;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Name');
    $header['description'] = $this->t('Description');
    $header['item_count'] = $this->t('Items');
    $header['scope'] = $this->t('Visibility');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    if (!$entity->access('view')) {
      return [];
    }
    $items = $entity->getItems();
    $count = array_sum(array_map('count', $items));
    $row['label'] = ['data' => ['#type' => 'link', '#title' => $entity->label(), '#url' => $entity->toUrl('edit-form')]];
    $row['description'] = $entity->getDescription();
    $row['item_count'] = $count;
    $row['scope'] = $entity->isSiteWide() ? $this->t('All Export Users') : $this->t('Only You');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['export'] = [
      'title' => $this->t('Export'),
      'weight' => 0,
      'url' => Url::fromRoute('mukurtu_export.start_list_export', ['export_list' => $entity->id()]),
    ];
    return $operations;
  }

  protected function getEntityIds() {
    $uid = \Drupal::currentUser()->id();
    $query = $this->getStorage()->getQuery()->accessCheck(TRUE);
    $orGroup = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    return $query->condition($orGroup)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Create new export list'),
      '#url' => Url::fromRoute('entity.export_list.add_form'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];
    return $build;
  }

}
