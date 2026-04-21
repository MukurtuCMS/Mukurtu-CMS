<?php

namespace Drupal\dashboards_webform\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\Dashboard\ChartTrait;
use Drupal\dashboards\Plugin\DashboardBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "webform_submissions",
 *   label = @Translation("Submission statistic."),
 *   category = @Translation("Webform"),
 * )
 */
class Submissions extends DashboardBase {
  use ChartTrait;

  /**
   * Database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache_backend, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache_backend);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $webform = FALSE;
    if (!empty($configuration['webform'])) {
      $webform = $this->entityTypeManager->getStorage('webform')->load($configuration['webform']);
    }
    $form['webform'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'webform',
      '#selection_handler' => 'default',
      '#default_value' => $webform,
    ];
    $form['period'] = [
      '#type' => 'select',
      '#options' => [
        'hour' => $this->t('Hour'),
        'day' => $this->t('Day'),
        'week' => $this->t('Week'),
        'month' => $this->t('Month'),
      ],
      '#default_value' => $configuration['period'] ?? 'day',
    ];
    $form['date'] = [
      '#type' => 'select',
      '#options' => [
        'today' => $this->t('Today'),
        'yesterday' => $this->t('Yesterday'),
        'this_week' => $this->t('This week'),
        'this_month' => $this->t('This month'),
        'last_three_months' => $this->t('Last 3 months'),
        'last_six_months' => $this->t('Last 6 months'),
        'year' => $this->t('This year'),
      ],
      '#default_value' => $configuration['date'] ?? 'today',
    ];
    $form['chart_type'] = [
      '#type' => 'select',
      '#options' => $this->getAllowedStyles(),
      '#default_value' => $configuration['chart_type'] ?? 'bar',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    $field = $configuration['period'];
    $cid = md5(serialize(
      $configuration,
    ));
    $dateLabel = $this->t('Date');

    $cache = $this->getCache($cid);

    if (!$cache) {
      $query = $this->database->select('webform_submission', 'ws');
      if (isset($configuration['webform'])) {
        $query->condition('webform_id', $configuration['webform']);
      }
      switch ($configuration['date']) {
        case 'yesterday':
          $query->condition('ws.created', [
            strtotime('yesterday'), strtotime('today'),
          ], 'BETWEEN');
          break;

        case 'this_week':
          $query->condition('ws.created', strtotime('this week'), '>=');
          break;

        case 'this_month':
          $query->condition('ws.created', strtotime('first day of this month'), '>=');
          break;

        case 'last_three_months':
          $query->condition('ws.created', strtotime('first day of this month -3 months'), '>=');
          break;

        case 'last_six_months':
          $query->condition('ws.created', strtotime('first day of this month -6 months'), '>=');
          break;

        case 'year':
          $query->condition('ws.created', strtotime('first day of january this year'), '>=');
          break;

        default:
          $query->condition('ws.created', strtotime('yesterday'), '>=');
          break;
      }
      switch ($field) {
        case 'week':
          $query->addExpression('CONCAT(YEAR(FROM_UNIXTIME(ws.created)), \'-\', WEEK(FROM_UNIXTIME(ws.created)))', 'date');
          break;

        case 'month':
          $query->addExpression('CONCAT(YEAR(FROM_UNIXTIME(ws.created)), \'-\', MONTH(FROM_UNIXTIME(ws.created)))', 'date');
          break;

        case 'hour':
          $query->addExpression('CONCAT(YEAR(FROM_UNIXTIME(ws.created)), \'-\', MONTH(FROM_UNIXTIME(ws.created)),\'-\', DAY(FROM_UNIXTIME(ws.created)), \' \', HOUR(FROM_UNIXTIME(ws.created)),\':00\')', 'date');
          break;

        default:
          $query->addExpression('CONCAT(YEAR(FROM_UNIXTIME(ws.created)), \'-\', MONTH(FROM_UNIXTIME(ws.created)), \'-\', DAY(FROM_UNIXTIME(ws.created)))', 'date');
          break;
      }

      $query->addExpression('COUNT(*)', 'count');
      $query->groupBy('date');
      $query->groupBy('webform_id');
      $query->orderBy('webform_id');
      $query->fields('ws', ['webform_id']);
      $result = $query->execute()->fetchAll();

      $rows = [];
      $labels = [];

      foreach ($result as $r) {
        $labels[$r->webform_id] = $r->webform_id;
      }

      foreach ($labels as $key => $label) {
        foreach ($result as $r) {
          if ($r->webform_id != $label) {
            continue;
          }
          $rows[$r->date][$key] = $r->count;
        }
      }

      foreach ($rows as $key => $row) {
        foreach ($labels as $label) {
          if (!isset($row[$label])) {
            $rows[$key][$label] = 0;
          }
        }
      }

      foreach ($rows as $key => $row) {
        array_unshift($rows[$key], $key);
      }

      usort($rows, function ($a, $b) {
        return strcmp($a[0], $b[0]);
      });
      $labels = array_merge([$dateLabel], $labels);

      $this->setCache($cid, ['labels' => $labels, 'rows' => $rows], time() + 1800, ['node_list']);
    }
    else {
      $rows = $cache->data['rows'];
      $labels = $cache->data['labels'];
    }
    $this->setLabels($labels);
    $this->setRows($rows);

    $this->setChartType($configuration['chart_type']);
    $build = $this->renderChart($configuration);
    $build['#cache'] = [
      'tags' => ['node_list'],
    ];
    return $build;
  }

}
