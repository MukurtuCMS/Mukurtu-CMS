<?php

namespace Drupal\mukurtu_protocol\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter for parent communities (communities without a parent).
 *
 * @ViewsFilter("mukurtu_protocol_parent_community")
 */
class ParentCommunity extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    
    // Get the configuration that contains community organization data.
    $config = \Drupal::config('mukurtu_protocol.community_organization');
    $organization = $config->get('organization') ?? [];
    
    // Find all community IDs that have a parent (child communities).
    $child_community_ids = [];
    foreach ($organization as $community_id => $data) {
      if (!empty($data['parent'])) {
        $child_community_ids[] = $community_id;
      }
    }
    
    // Filter to show only communities that don't have a parent.
    if (!empty($child_community_ids)) {
      $this->query->addWhere(
        $this->options['group'],
        "{$this->tableAlias}.id",
        $child_community_ids,
        'NOT IN'
      );
    }
  }

}
