<?php

namespace Drupal\mukurtu_protocol\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter for non-child communities (communities without a parent).
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
  public function adminLabel($prefix = TRUE) {
    return 'Non-child communities only';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    
    // Get the configuration that contains community organization data.
    $config = \Drupal::config('mukurtu_protocol.community_organization');
    $organization = $config->get('organization');
    
    // If no organization is configured, show all communities (no filter needed).
    if (empty($organization) || !is_array($organization)) {
      return;
    }
    
    // Find all community IDs that are non-child (parent: 0).
    // These are root-level communities that should be displayed.
    $parent_community_ids = [];
    foreach ($organization as $community_id => $data) {
      if (is_array($data) && isset($data['parent']) && (int)$data['parent'] === 0) {
        // This is a parent/non-child community.
        $parent_community_ids[] = (int) $community_id;
      }
    }
    
    // Filter to show only the parent communities.
    if (!empty($parent_community_ids)) {
      $this->query->addWhere(
        $this->options['group'],
        "{$this->tableAlias}.id",
        $parent_community_ids,
        'IN'
      );
    } else {
      // If there are no parent communities configured, show nothing.
      // Add a condition that will never be true.
      $this->query->addWhere(
        $this->options['group'],
        "{$this->tableAlias}.id",
        -1,
        '='
      );
    }
  }

}



