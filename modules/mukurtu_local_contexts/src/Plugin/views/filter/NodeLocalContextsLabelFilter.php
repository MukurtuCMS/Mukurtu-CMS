<?php

namespace Drupal\mukurtu_local_contexts\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters content nodes by Local Contexts label or notice.
 *
 * @ViewsFilter("mukurtu_node_local_contexts_label_filter")
 */
class NodeLocalContextsLabelFilter extends InOperator {

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

    $labels = $this->localContextsProjectManager->getAllLabels();
    $notices = $this->localContextsProjectManager->getAllNotices();
    $referencedProjectIds = $this->localContextsProjectManager->getReferencedProjectIds();
    $referencedKeys = $this->localContextsProjectManager->getReferencedLabelAndNoticeKeys();

    // Multiple Local Contexts projects can add the same standardized label
    // or notice (e.g. "TK Attribution"), each as its own project-scoped row.
    // Group them by display name so they appear as a single option; the
    // option's key carries every underlying compound key so selecting it
    // still matches content tagged under any of the originating projects.
    //
    // An entry is only offered if it (or its owning project) is actually
    // referenced by content: either the compound key itself was directly
    // applied, or the whole project was applied directly (which matches
    // this label/notice too, per NodeLocalContextsLabelFilter::query()).
    $groups = [];
    foreach ($labels as $label) {
      $key = $label['project_id'] . ':' . $label['id'] . ':' . $label['display'];
      if (!in_array($key, $referencedKeys, TRUE) && !in_array($label['project_id'], $referencedProjectIds, TRUE)) {
        continue;
      }
      $name = $label['name'] ?: (string) $this->t('Unknown Label');
      $groups[$name][] = $key;
    }
    foreach ($notices as $notice) {
      $key = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      if (!in_array($key, $referencedKeys, TRUE) && !in_array($notice['project_id'], $referencedProjectIds, TRUE)) {
        continue;
      }
      $name = $notice['name'] ?: (string) $this->t('Unknown Notice');
      $groups[$name][] = $key;
    }

    $options = [];
    foreach ($groups as $name => $keys) {
      $options[implode(',', $keys)] = $name;
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

    // Each selected value is a comma-joined group of one or more compound
    // "{project_id}:{id}:{label|notice}" keys (see getValueOptions()), since
    // the same label/notice can be provided by more than one project.
    //
    // Selecting an entire Local Contexts Project applies all of that
    // project's labels/notices, so a node should also match if it has the
    // owning project applied directly, even if the label/notice itself was
    // never individually selected. The project ID is always the first
    // segment of each compound value.
    $rawValues = [];
    $projectIds = [];
    foreach ((array) $this->value as $groupKey) {
      foreach (explode(',', $groupKey) as $value) {
        $rawValues[$value] = $value;
        [$projectId] = explode(':', $value, 2);
        $projectIds[$projectId] = $projectId;
      }
    }

    $subquery = $this->database->select('node__field_local_contexts_labels_and_notices', 'l')
      ->fields('l', ['entity_id'])
      ->condition('l.field_local_contexts_labels_and_notices_value', array_values($rawValues), 'IN')
      ->condition('l.deleted', 0);

    $projectSubquery = $this->database->select('node__field_local_contexts_projects', 'p')
      ->fields('p', ['entity_id'])
      ->condition('p.field_local_contexts_projects_value', array_values($projectIds), 'IN')
      ->condition('p.deleted', 0);
    $subquery->union($projectSubquery, 'UNION');

    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, 'IN');
  }

}
