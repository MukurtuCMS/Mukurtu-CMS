<?php

namespace Drupal\dashboards_comments\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\Dashboard\ChartTrait;
use Drupal\dashboards\Plugin\DashboardBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for comment reports.
 *
 * @Dashboard(
 *   id = "comments_statistic",
 *   label = @Translation("Comment per node type."),
 *   category = @Translation("Statistics"),
 * )
 */
class Comments extends DashboardBase {
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
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

    $field = 'comment_count';

    $cache = $this->getCache($field);

    if (!$cache) {
      $query = $this->database->select('node_field_data', 'nfd');
      $query->join('comment_entity_statistics', 'ces', 'ces.entity_id = nfd.nid');
      $query->fields('nfd', ['type']);
      $query->addExpression('SUM(ces.' . $field . ')', 'count');
      $query->groupBy('type');
      $rows = [];

      $stats['types'] = $query->execute()->fetchAllAssoc('type');

      foreach ($stats['types'] as $type => $count) {
        $rows[] = [
          $type,
          $count->count,
        ];
      }
      $this->setCache($field, $rows, CacheBackendInterface::CACHE_PERMANENT, ['comment_list']);
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
