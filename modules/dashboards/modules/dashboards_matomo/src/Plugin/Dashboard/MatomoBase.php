<?php

namespace Drupal\dashboards_matomo\Plugin\Dashboard;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\Dashboard\ChartTrait;
use Drupal\dashboards\Plugin\DashboardLazyBuildBase;
use Drupal\matomo_reporting_api\MatomoQueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for matomo plugins.
 */
abstract class MatomoBase extends DashboardLazyBuildBase {
  use ChartTrait;

  /**
   * Entity query.
   *
   * @var \Drupal\matomo_reporting_api\MatomoQueryFactory
   */
  protected $matomoQuery;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CacheBackendInterface $cache,
    MatomoQueryFactory $matomo,
    DateFormatterInterface $date_formatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
    $this->matomoQuery = $matomo;
    $this->dateFormatter = $date_formatter;
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
      $container->get('matomo.query_factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * Get matomo query.
   *
   * @return \Drupal\matomo_reporting_api\MatomoQueryFactory
   *   Matomo query factory.
   */
  public function getQuery() {
    return $this->matomoQuery;
  }

  /**
   * Translate date matomo string.
   *
   * @param string $period
   *   Period to translated.
   *
   * @return string
   *   Translated date.
   */
  protected function getDateTranslated(string $period): string {
    $format = 'Y-m-d';
    $start = time();
    $time = time();
    switch ($period) {
      case 'last_seven_days':
        $start = strtotime('-7 days');
        break;

      case 'this_week':
        $start = strtotime('monday this week');
        $time = strtotime('sunday this week');
        break;

      case 'this_month':
        $start = strtotime('first day of this month');
        $time = strtotime('last day of this month');
        break;

      case 'last_three_months':
        $start = strtotime('first day of this month -2 months');
        $time = strtotime('last day of this month');
        break;

      case 'last_six_months':
        $start = strtotime('first day of this month -5 months');
        $time = strtotime('last day of this month');
        break;

      case 'year':
        $start = strtotime('first day of this year');
        $time = strtotime('last day of this year');
        break;

      default:
        return $period;
    }
    $date = new \DateTime();
    $date->setTimestamp($time);

    $startDateTime = new \DateTime();
    $startDateTime->setTimestamp($start);

    return implode(',', [
      $startDateTime->format($format),
      $date->format($format),
    ]);
  }

  /**
   * Helper function for build rows from matomo.
   *
   * @param mixed $response
   *   Data from matomo.
   * @param string $label
   *   Label for display.
   * @param array $column
   *   Columns to show.
   */
  protected function buildDateRows($response, $label, array $column) {
    $labels = [$label];
    foreach ($response as $date => &$row) {
      foreach ($row as $key => $r) {
        $labels[$r['label']] = $r['label'];
        unset($row[$key]);
        $row[$r['label']] = $r;
        uksort($row, function ($a, $b) {
          return strcmp($a, $b);
        });
      }
    }
    $items = [];
    foreach ($response as $date => &$row) {
      $item = [$date];
      if (empty($row)) {
        if (is_array($column)) {
          foreach ($column as $c) {
            $item[] = 0;
          }
          continue;
        }
        $item[] = 0;
      }
      foreach ($row as $r) {
        if (is_array($column)) {
          foreach ($column as $c) {
            $item[] = $r[$c];
          }
          continue;
        }
        $item[] = $r[$column];
      }
      $items[] = $item;
    }
    $this->setRows($items);
    $this->setLabels($labels);
  }

  /**
   * Helper function for query matomo.
   *
   * @param string $action
   *   Matomo action to call.
   * @param array $params
   *   Parameters.
   *
   * @return array
   *   Response array
   */
  protected function query($action, array $params): array {
    $cid = md5(serialize([$action, $params]));
    if ($data = $this->getCache($cid)) {
      return $data->data;
    }
    $query = $this->matomoQuery->getQuery($action);
    $query->setParameters($params);

    $response = $query->execute()->getRawResponse();
    $response = Json::decode($response->getBody()->getContents());
    if (isset($response['result']) && $response['result'] == 'error') {
      throw new \Exception($response['message']);
    }
    $items = [];
    foreach ($response as $date => $values) {
      $nDates = explode(',', $date);
      array_walk($nDates, function (&$i, $key, $formatter) {
        $date = strtotime($i);
        if ($date !== FALSE) {
          $i = $formatter->format($date, 'custom', 'd.m.Y');
        }
      }, $this->dateFormatter);
      $date = implode(',', $nDates);
      if (count($nDates) > 1) {
        $date = static::formatDateRange($nDates[0], $nDates[1]);
      }
      $items[$date] = $values;
    }
    $this->setCache($cid, $items, time() + 600);
    return $items;
  }

  /**
   * Helper function for short date ranges.
   *
   * @param int $d1
   *   Date start.
   * @param int $d2
   *   Date end.
   *
   * @return string
   *   Formatted date.
   */
  public static function formatDateRange($d1, $d2) {
    $d1 = new \DateTime($d1);
    $d2 = new \DateTime($d2);
    if ($d1->format('Y-m-d') === $d2->format('Y-m-d')) {
      return $d1->format('d.m');
    }
    elseif ($d1->format('Y-m') === $d2->format('Y-m')) {
      return $d1->format('d') . $d2->format(' – d.m');
    }
    elseif ($d1->format('Y') === $d2->format('Y')) {
      return $d1->format('d.m') . $d2->format(' – d.m');
    }
    else {
      return $d1->format('d.m.Y') . $d2->format(' – d.m.Y');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['period'] = [
      '#type' => 'select',
      '#options' => [
        'day' => $this->t('Day'),
        'week' => $this->t('Week'),
        'month' => $this->t('Month'),
        'year' => $this->t('Year'),
      ],
      '#default_value' => (isset($configuration['period'])) ? $configuration['period'] : 'day',
    ];
    $form['date'] = [
      '#type' => 'select',
      '#options' => [
        'last_seven_days' => $this->t('Last seven days'),
        'this_week' => $this->t('This week'),
        'this_month' => $this->t('This month'),
        'last_three_months' => $this->t('Last 3 months'),
        'last_six_months' => $this->t('Last 6 months'),
        'year' => $this->t('This year'),
      ],
      '#default_value' => (isset($configuration['date'])) ? $configuration['date'] : 'today',
    ];
    $form['chart_type'] = [
      '#type' => 'select',
      '#options' => $this->getAllowedStyles(),
      '#default_value' => (isset($configuration['chart_type'])) ? $configuration['chart_type'] : 'bar',
    ];
    $form['legend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show legend'),
      '#default_value' => (isset($configuration['legend'])) ? $configuration['legend'] : 0,
    ];
    return $form;
  }

}
