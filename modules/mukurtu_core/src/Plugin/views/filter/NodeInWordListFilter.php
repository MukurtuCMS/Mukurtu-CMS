<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters dictionary word nodes by word list membership.
 *
 * @ViewsFilter("mukurtu_node_in_word_list")
 */
class NodeInWordListFilter extends InOperator {

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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'word_list')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->sort('title');
    $ids = $query->execute();
    $nodes = $storage->loadMultiple($ids);
    $options = [];
    foreach ($nodes as $node) {
      $options[$node->id()] = $node->label();
    }
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }
    $this->ensureMyTable();
    $subquery = $this->database->select('node__field_words', 'fw')
      ->fields('fw', ['field_words_target_id'])
      ->condition('fw.entity_id', $this->value, 'IN')
      ->condition('fw.deleted', 0);
    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, 'IN');
  }

}
