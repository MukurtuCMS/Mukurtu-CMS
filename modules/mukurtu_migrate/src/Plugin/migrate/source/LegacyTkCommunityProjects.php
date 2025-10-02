<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for retrieving community IDs from the variable table for
 * communities that have customized TK Labels in Mukurtu CMS v3.
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_legacy_tk_community_projects"
 * )
 */
class LegacyTkCommunityProjects extends SqlBase
{
  protected $communityIds;
  protected $communityNames;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator()
  {
    $this->communityIds = [];
    $this->communityNames = [];

    // Query to get all the community customized labels.
    $query = $this->select('variable', 'v')
      ->fields('v', ['value'])
      ->condition('name', 'mukurtu_comm_custom_TK_%', 'LIKE');
    $result = $query->execute()->fetchAll();

    // Check all the community label values. If the 'custom' property
    // is set, the community has customized that label. If there are any
    // custom labels for a given community, we need to create a legacy
    // project in v4 for that community to contain it.
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

    // Get all the community titles.
    $query = $this->select('node', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('type', 'community');
    if ($result = $query->execute()->fetchAllAssoc('nid')) {
      $this->communityNames = array_column($result, 'title', 'nid');
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
  public function __toString()
  {
    return 'mukurtu_v3_legacy_tk_community_projects';
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
  public function prepareRow(Row $row)
  {
    if ($community_id = $row->getSource()['id']) {
      $name = $this->communityNames[$community_id] ?? NULL;
      if ($name) {
        $row->setSourceProperty('title', 'TK Legacy Labels - ' . $name);
        $row->setSourceProperty('updated', time());
        $row->setSourceProperty('project_id', 'comm_' . $community_id . '_tk');
      }
    }
    return parent::prepareRow($row);
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

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Intentionally empty. We're abusing SqlBase, mostly for the DB setup.
    // We need to implement query to fit the abstract class, but we've
    // removed all calls to it.
  }

  protected function doCount()
  {
    $iterator = $this->getIterator();
    return $iterator instanceof \Countable ? $iterator->count() : iterator_count($this->initializeIterator());
  }
}
