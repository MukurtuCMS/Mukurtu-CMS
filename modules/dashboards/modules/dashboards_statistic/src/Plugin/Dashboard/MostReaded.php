<?php

namespace Drupal\dashboards_statistic\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\Dashboard\ChartTrait;
use Drupal\dashboards\Plugin\DashboardBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

// cspell:ignore readed
// Ignore keyword 'readed' used in the plugin ID and class name.
/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "node_most_readed",
 *   label = @Translation("Show most visited."),
 *   category = @Translation("Statistics"),
 * )
 */
class MostReaded extends DashboardBase {
  use ChartTrait;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeInfo;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache_backend, EntityTypeBundleInfoInterface $entity_type_info, ModuleHandlerInterface $module_handler, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache_backend);
    $this->entityTypeInfo = $entity_type_info;
    $this->moduleHandler = $module_handler;
    $this->database = $database;
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
      $container->get('entity_type.bundle.info'),
      $container->get('module_handler'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $options = [
      'totalcount' => $this->t('Total count'),
      'daycount' => $this->t('Daily count'),
    ];
    $form['count'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => (isset($configuration['count'])) ? $configuration['count'] : FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    $stats = [];

    $field = 'totalcount';
    if ($configuration['count'] == 'daycount') {
      $field = 'daycount';
    }

    $cache = $this->getCache($field);

    if (!$cache) {
      $query = $this->database->select('node_field_data', 'nfd');
      $query->join('node_counter', 'nc', 'nc.nid = nfd.nid');
      $query->fields('nfd', ['type']);
      $query->addExpression('SUM(nc.' . $field . ')', 'count');
      $query->groupBy('type');
      $rows = [];

      $stats['types'] = $query->execute()->fetchAllAssoc('type');

      foreach ($stats['types'] as $type => $count) {
        $rows[] = [
          $type,
          $count->count,
        ];
      }
      $this->setCache($field, $rows, CacheBackendInterface::CACHE_PERMANENT, ['node_list']);
    }
    else {
      $rows = $cache->data;
    }

    $this->setLabels([
      $this->t('Node Type'),
      $this->t('Count'),
    ]);

    $this->setRows($rows);

    $build = $this->renderChart($configuration);
    $build['#cache'] = [
      'tags' => ['node_list'],
    ];
    return $build;
  }

}
