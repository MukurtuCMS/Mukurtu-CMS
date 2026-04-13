<?php

namespace Drupal\mukurtu_protocol\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters out child communities, showing only root-level communities.
 *
 * @ViewsFilter("community_is_root")
 */
class CommunityIsRoot extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('is root community');
  }

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
    $config = \Drupal::config('mukurtu_protocol.community_organization');
    $org = $config->get('organization') ?? [];

    $child_ids = [];
    foreach ($org as $id => $settings) {
      if (!empty($settings['parent']) && intval($settings['parent']) !== 0) {
        $child_ids[] = $id;
      }
    }

    if (empty($child_ids)) {
      return;
    }

    $this->ensureMyTable();
    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.$this->realField",
      $child_ids,
      'NOT IN'
    );
  }

}
