<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters content nodes by community (via cultural protocol association).
 *
 * @ViewsFilter("mukurtu_node_community_filter")
 */
class NodeCommunityFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $communities = $this->entityTypeManager->getStorage('community')->loadMultiple();
    $options = [];
    foreach ($communities as $community) {
      $options[$community->id()] = $community->label();
    }
    asort($options);
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    // Find all protocol IDs that belong to the selected communities.
    $protocol_ids = $this->database->select('protocol__field_communities', 'pfc')
      ->fields('pfc', ['entity_id'])
      ->condition('pfc.field_communities_target_id', $this->value, 'IN')
      ->execute()
      ->fetchCol();

    if (empty($protocol_ids)) {
      // Selected communities have no protocols; nothing can match.
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    // Match nodes whose protocols column contains any of those protocol IDs.
    // The column stores pipe-delimited IDs like |1| |3|.
    $this->ensureMyTable();
    $or = $this->query->getQuery()->orConditionGroup();
    foreach ($protocol_ids as $pid) {
      $or->condition("$this->tableAlias.field_cultural_protocols__protocols", '%|' . $pid . '|%', 'LIKE');
    }
    $this->query->addWhere($this->options['group'], $or);
  }

}
