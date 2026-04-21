<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\DashboardBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "node_statistics",
 *   label = @Translation("Show node types."),
 *   category = @Translation("Dashboards: Reports"),
 * )
 */
class NodeStatistics extends DashboardBase {
  use ChartTrait;

  /**
   * Entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryAggregateInterface
   */
  protected $entityQuery;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache, QueryAggregateInterface $query_factory, EntityTypeBundleInfoInterface $entity_type_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
    $this->entityQuery = $query_factory;
    $this->entityTypeInfo = $entity_type_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dashboards.cache'),
      $container->get('entity_type.manager')->getStorage('node')->getAggregateQuery('AND'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['chart_type'] = [
      '#type' => 'select',
      '#options' => $this->getAllowedStyles(),
      '#default_value' => (isset($configuration['chart_type'])) ? $configuration['chart_type'] : 'pie',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    if (isset($configuration['chart_type'])) {
      $this->setChartType($configuration['chart_type']);
    }

    $result = $this->entityQuery
      ->groupBy('type')
      ->aggregate('nid', 'COUNT')
      ->accessCheck(FALSE)
      ->execute();

    $types = $this->entityTypeInfo->getBundleInfo('node');
    $rows = [];

    foreach ($result as $r) {
      $rows[] = [
        $types[$r['type']]['label'],
        $r['nid_count'],
      ];
    }

    if (empty($result)) {
      $rows = [];
      $this->setEmpty(TRUE);
    }

    $this->setLabels([
      $this->t('Node Type'),
      $this->t('Count'),
    ]);

    $this->setRows($rows);

    return $this->renderChart($configuration);
  }

}
