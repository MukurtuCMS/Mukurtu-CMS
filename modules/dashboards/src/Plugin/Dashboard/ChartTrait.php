<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Trait for build a chart.
 */
trait ChartTrait {
  use StringTranslationTrait;

  /**
   * Labels.
   *
   * @var array
   */
  protected $labels = [];

  /**
   * Rows.
   *
   * @var array
   */
  protected $rows = [];

  /**
   * Chart type.
   *
   * @var string
   */
  protected $type = 'pie';

  /**
   * Empty flag.
   *
   * @var bool
   */
  protected $empty = FALSE;

  /**
   * Add a label.
   *
   * @param string $chart
   *   Label to add.
   */
  public function setChartType($chart): void {
    if (!in_array($chart, array_keys($this->getAllowedStyles()))) {
      throw new \InvalidArgumentException('Chart type @chart not allowed');
    }
    $this->type = $chart;
  }

  /**
   * Add a label.
   *
   * @param string $label
   *   Label to add.
   */
  public function addLabel($label): void {
    $this->labels[] = $label;
  }

  /**
   * Get allowed styles.
   *
   * @return array
   *   Return array keyed by type.
   */
  public function getAllowedStyles(): array {
    return [
      'line' => $this->t('Lines'),
      'pie' => $this->t('Pies'),
      'bar' => $this->t('Bars'),
      'radar' => $this->t('Radar'),
      'polarArea' => $this->t('Polar area'),
      'doughnut' => $this->t('Doughnut'),
      'bubble' => $this->t('Bubbles'),
    ];
  }

  /**
   * Set all labels.
   *
   * @param array $labels
   *   Labels to set.
   */
  public function setLabels(array $labels): void {
    $this->labels = $labels;
  }

  /**
   * Add a row.
   *
   * @param array $row
   *   Row to add.
   */
  public function addRow(array $row): void {
    $this->rows[] = $row;
  }

  /**
   * Set all rows.
   *
   * @param array $rows
   *   Rows to set.
   */
  public function setRows(array $rows): void {
    $this->rows = $rows;
  }

  /**
   * Set this chart is empty.
   *
   * @param bool $empty
   *   Empty flag to set.
   */
  public function setEmpty(bool $empty): void {
    $this->empty = $empty;
  }

  /**
   * Set all rows.
   *
   * @param array $conf
   *   Plugin configuration.
   * @param bool $plain
   *   Show only table.
   *
   * @return array
   *   Return renderable array.
   */
  public function renderChart(array $conf = [], bool $plain = FALSE): array {
    if (count($this->rows) == 0) {
      return [
        '#markup' => $this->t('No data found'),
      ];
    }
    $table = [
      '#type' => 'table',
      '#header' => $this->labels,
      '#rows' => $this->rows,
      '#attributes' => [
        'class' => ['dashboard-table', 'table'],
      ],
      '#attached' => [
        'library' => ['dashboards/chart'],
      ],
    ];
    $attributes = [
      'data-app' => 'chart',
      'data-chart-type' => $this->type,
    ];

    if (isset($conf['legend']) && $conf['legend']) {
      $attributes['data-chart-display-legend'] = '1';
    }
    $build = [
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      'chart' => [
        '#type' => 'container',
        '#attributes' => $attributes,
        [
          '#type' => 'container',
          [
            '#type' => 'details',
            '#title' => $this->t('Show data'),
            '#open' => FALSE,
            'content' => $table,
          ],
        ],
      ],
    ];
    if ($plain == TRUE) {
      unset($build['chart']['#attributes']);
    }
    return $build;
  }

}
