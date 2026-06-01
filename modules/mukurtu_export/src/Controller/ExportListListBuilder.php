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
    $header['scope'] = $this->t('Visibility');
    $header['description'] = $this->t('Description');
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
    $row['label'] = $entity->label();
    $row['scope'] = $entity->isSiteWide() ? $this->t('All Export Users') : $this->t('Only You');
    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
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
    $build['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to export'),
      '#url' => Url::fromRoute('mukurtu_export.export_item_and_format_selection'),
    ];
    $build['table'] = parent::render();
    return $build;
  }

}
