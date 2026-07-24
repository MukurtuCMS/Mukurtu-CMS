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
    $referencedLegacyIds = $this->localContextsProjectManager->getReferencedLegacyProjectIds();

    $options = [];
    foreach ($labels as $label) {
      if ($this->localContextsProjectManager->isLegacyProjectId((string) $label['project_id']) && !in_array($label['project_id'], $referencedLegacyIds, TRUE)) {
        continue;
      }
      $key = $label['project_id'] . ':' . $label['id'] . ':' . $label['display'];
      $options[$key] = $label['name'] ?: $this->t('Unknown Label');
    }
    foreach ($notices as $notice) {
      if ($this->localContextsProjectManager->isLegacyProjectId((string) $notice['project_id']) && !in_array($notice['project_id'], $referencedLegacyIds, TRUE)) {
        continue;
      }
      $key = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      $options[$key] = $notice['name'] ?: $this->t('Unknown Notice');
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
    $subquery = $this->database->select('node__field_local_contexts_labels_and_notices', 'l')
      ->fields('l', ['entity_id'])
      ->condition('l.field_local_contexts_labels_and_notices_value', $this->value, 'IN')
      ->condition('l.deleted', 0);
    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, 'IN');
  }

}
