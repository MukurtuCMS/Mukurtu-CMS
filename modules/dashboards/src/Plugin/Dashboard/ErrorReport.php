<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Psr\Container\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "error_report",
 *   label = @Translation("Show error info"),
 *   category = @Translation("Dashboards: System")
 * )
 */
class ErrorReport extends DashboardBase {

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
    /**
     * @var \Drupal\Core\Database\Query\SelectInterface;
     */
    $query = $this->database->select('watchdog', 'w')->fields('w', ['severity']);
    $query->condition('timestamp', strtotime("this week"), '>');

    $query->addExpression('COUNT(wid)', 'severity_count');
    $query->groupBy('severity');
    $result = $query->execute()->fetchAll();

    $critical = array_reduce($result, function ($before, $v) {
      if ($v->severity < LOG_ERR) {
        return $before + $v->severity_count;
      }
      return $before;
    }, 0);

    $errors = array_reduce($result, function ($before, $v) {
      if ($v->severity == LOG_ERR) {
        return $v->severity_count;
      }
      return $before;
    }, 0);

    $warnings = array_reduce($result, function ($before, $v) {
      if ($v->severity == LOG_WARNING) {
        return $v->severity_count;
      }
      return $before;
    }, 0);

    $list = [
      'critical' => [
        'text' => 'Critical',
        'count' => $critical,
        'severity' => 'error',
        'query' => [0, 1, 2],
      ],
      'errors' => [
        'text' => 'Errors',
        'count' => $errors,
        'severity' => 'error',
        'query' => [3],
      ],
      'warnings' => [
        'text' => 'Warnings',
        'count' => $warnings,
        'severity' => 'warning',
        'query' => [4],
      ],
    ];

    return [
      '#theme' => 'dashboards_admin_list',
      '#list' => array_map(function ($state) use ($list) {
        $i = [
          'url' => Url::fromRoute('dblog.overview', [], [
            'query' => ['severity' => $list[$state]['query']],
          ]),
          'title' => $this->t('@count @text', [
            '@count' => $list[$state]['count'],
            '@text' => $list[$state]['text'],
          ]),
        ];
        return $i;
      },
      array_keys($list)),
    ];
  }

}
