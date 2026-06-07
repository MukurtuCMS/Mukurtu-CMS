<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the communities associated with a node via its protocols.
 *
 * @ViewsField("mukurtu_node_community_field")
 */
class NodeCommunityField extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  public function query(): void {
    $this->ensureMyTable();
  }

  public function render(ResultRow $values): array|string {
    $nid = $this->getValue($values);
    if (empty($nid)) {
      return '';
    }

    $protocol_ids = $this->database->select('node_field_data', 'nfd')
      ->fields('nfd', ['field_cultural_protocols__protocols'])
      ->condition('nfd.nid', $nid)
      ->execute()
      ->fetchField();

    if (empty($protocol_ids)) {
      return '';
    }

    preg_match_all('/\|(\d+)\|/', $protocol_ids, $matches);
    $pids = array_unique($matches[1] ?? []);
    if (empty($pids)) {
      return '';
    }

    $community_ids = $this->database->select('protocol__field_communities', 'pfc')
      ->fields('pfc', ['field_communities_target_id'])
      ->condition('pfc.entity_id', $pids, 'IN')
      ->execute()
      ->fetchCol();

    if (empty($community_ids)) {
      return '';
    }

    $communities = $this->entityTypeManager
      ->getStorage('community')
      ->loadMultiple(array_unique($community_ids));

    $labels = array_map(fn($c) => $c->label(), $communities);
    return implode(', ', $labels);
  }

}
