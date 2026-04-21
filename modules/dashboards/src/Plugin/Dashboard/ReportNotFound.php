<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Psr\Container\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "report_not_found",
 *   label = @Translation("Top 404 pages"),
 *   category = @Translation("Dashboards: Reports")
 * )
 */
class ReportNotFound extends DashboardBase {

  use StringTranslationTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CacheBackendInterface $cache,
    Connection $connection,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
    $this->database = $connection;
    $this->moduleHandler = $module_handler;
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
      $container->get('database'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    if (!$this->moduleHandler->moduleExists('dblog')) {
      return [
        '#theme' => 'dashboards_admin_list',
        '#list' => [
            [
              'title' => $this->t('DBLog module is not enabled.'),
              'description' => $this->t('DBLog module must enabled to show this report.'),
              'url' => Url::fromRoute('system.modules_list'),
            ],
        ],
      ];
    }
    $type = 'page not found';
    $count_query = $this->database->select('watchdog');
    $count_query->addExpression('COUNT(DISTINCT([message]))');
    $count_query->condition('type', $type);

    $query = $this->database->select('watchdog', 'w');
    $query->addExpression('COUNT([wid])', 'count');
    $query->fields('w', ['message', 'variables'])
      ->condition('w.type', $type)
      ->groupBy('message')
      ->groupBy('variables')
      ->orderBy('count', 'DESC');
    $query = $query->extend(PagerSelectExtender::class);
    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $query = $query->limit(5);
    $query->setCountQuery($count_query);
    $result = $query->execute()->fetchAll();
    return [
      '#theme' => 'dashboards_admin_list',
      '#list' => array_map(function ($r) {
        return [
          'title' => $r->count,
          'description' => [
            '#markup' => $this->t('@uri', unserialize($r->variables, [
              'allowed_classes' => FALSE,
            ])),
          ],
          'url' => Url::fromRoute('dblog.overview'),
        ];
      }, $result),
    ];
  }

}
