<?php

namespace Drupal\mukurtu_protocol\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters users by community membership.
 *
 * @ViewsFilter("user_community_filter")
 */
class UserCommunityFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
      if ($community->access('view')) {
        $options[$community->id()] = $community->getName();
      }
    }
    asort($options);
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }
    $subquery = $this->database->select('og_membership', 'ogm')
      ->fields('ogm', ['uid'])
      ->condition('ogm.entity_type', 'community')
      ->condition('ogm.entity_id', $this->value, 'IN')
      ->condition('ogm.state', 'active');

    $this->query->addWhere($this->options['group'], 'users_field_data.uid', $subquery, 'IN');
  }

}
