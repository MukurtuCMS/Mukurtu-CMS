<?php

namespace Drupal\mukurtu_protocol\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts communities by their weight in mukurtu_protocol.community_organization.
 *
 * @ViewsSort("community_organization_weight")
 */
class CommunityOrganizationWeight extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $config = \Drupal::config('mukurtu_protocol.community_organization');
    $org = $config->get('organization') ?? [];

    if (empty($org)) {
      return;
    }

    $this->ensureMyTable();

    // Build a CASE WHEN expression mapping each community ID to its weight.
    // Communities absent from the config sort last.
    $cases = [];
    $args = [];
    foreach ($org as $id => $settings) {
      $cases[] = "WHEN $this->tableAlias.$this->realField = :id_$id THEN :weight_$id";
      $args[":id_$id"] = $id;
      $args[":weight_$id"] = $settings['weight'] ?? 0;
    }

    $expression = 'CASE ' . implode(' ', $cases) . ' ELSE 9999 END';

    $this->query->addOrderBy(NULL, $expression, $this->options['order'], 'community_organization_weight', $args);
  }

}
