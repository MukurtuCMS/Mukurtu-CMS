<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Gets source info for migrating group-supported community TK projects.
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_legacy_tk_community_projects_group_supported"
 * )
 */
class LegacyTkCommunityProjectsGroupSupported extends SqlBase
{

  protected $communityIds;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator()
  {
    $this->communityIds = [];

    // Query to get all the community customized labels.
    $query = $this->select('variable', 'v')
      ->fields('v', ['name', 'value'])
      ->condition('name', 'mukurtu_comm_custom_TK_%', 'LIKE');
    $result = $query->execute()->fetchAll();

    // Check all the community label values. If the 'custom' property
    // is set, the community has customized that label. Add that community's id
    // to the iterator.
    foreach ($result as $row) {
      $value = unserialize($row['value']);
      foreach ($value as $community_id => $community_label_info) {
        if (is_numeric($community_id)) {
          $custom = $community_label_info['custom'] ?? FALSE;
          if ($custom) {
            $this->communityIds[$community_id] = ['id' => $community_id];
          }
        }
      }
    }

    return new \ArrayIterator($this->communityIds);
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return ['id' => $this->t('Community ID')];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return ['id' => ['type' => 'integer']];
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Empty on purpose.
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    $community_id = $row->getSourceProperty('id');
    if ($community_id) {
      $row->setSourceProperty('group_id', $community_id);
      $row->setSourceProperty('project_id', 'comm_' . $community_id . '_tk');
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString()
  {
    return 'mukurtu_v3_legacy_tk_community_projects_group_supported';
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE): int
  {
    $count = $this->doCount();
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void
  {
    SourcePluginBase::next();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void
  {
    $this->getIterator()->rewind();
    $this->next();
  }

  protected function doCount()
  {
    $iterator = $this->getIterator();
    return $iterator instanceof \Countable ? $iterator->count() : iterator_count($this->initializeIterator());
  }
}
