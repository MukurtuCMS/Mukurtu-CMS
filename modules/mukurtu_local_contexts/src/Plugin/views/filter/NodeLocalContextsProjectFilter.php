<?php

namespace Drupal\mukurtu_local_contexts\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters content nodes by Local Contexts project.
 */
#[ViewsFilter('mukurtu_node_local_contexts_project_filter')]
class NodeLocalContextsProjectFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly LocalContextsSupportedProjectManager $localContextsProjectManager,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mukurtu_local_contexts.supported_project_manager'),
      $container->get('database'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $projects = $this->localContextsProjectManager->getAllProjects();
    $referencedIds = $this->localContextsProjectManager->getReferencedProjectIds();

    $options = [];
    foreach ($projects as $id => $project) {
      if (!in_array($id, $referencedIds, TRUE)) {
        continue;
      }
      $options[$id] = $project['title'] ?: $this->t('Unknown Project');
    }
    asort($options);
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    $this->ensureMyTable();
    $subquery = $this->database->select('node__field_local_contexts_projects', 'p')
      ->fields('p', ['entity_id'])
      ->condition('p.field_local_contexts_projects_value', $this->value, 'IN')
      ->condition('p.deleted', 0);

    // Selecting a Project should also match nodes that only carry one of
    // that project's individual labels/notices (compound key
    // "{project_id}:{id}:{label|notice}"), mirroring the reverse union in
    // NodeLocalContextsLabelFilter::query().
    $labelSubquery = $this->database->select('node__field_local_contexts_labels_and_notices', 'l')
      ->fields('l', ['entity_id'])
      ->condition('l.deleted', 0);
    $orGroup = $labelSubquery->orConditionGroup();
    foreach ((array) $this->value as $projectId) {
      $orGroup->condition('l.field_local_contexts_labels_and_notices_value', $this->database->escapeLike($projectId) . ':%', 'LIKE');
    }
    $labelSubquery->condition($orGroup);
    $subquery->union($labelSubquery, 'UNION');

    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, 'IN');
  }

}
