<?php

namespace Drupal\dashboards_matomo\Plugin\Dashboard;

use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\DashboardBase;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "matomo_visit_statistic",
 *   label = @Translation("Visit report."),
 *   category = @Translation("Matomo"),
 * )
 */
class VisitStatistic extends MatomoBase {

  /**
   * Get columns for query.
   *
   * @return array
   *   Return columns.
   */
  protected function getChartColumns() {
    return [
      'nb_visits' => $this->t('Visits'),
      'avg_time_on_site' => $this->t('Average time on site'),
      'nb_uniq_visitors' => $this->t('Unique visitors'),
      'nb_actions' => $this->t('Actions'),
      'sum_visit_length' => $this->t('Visit length summary.'),
      'max_actions' => $this->t('Max actions.'),
      'nb_users' => $this->t('Users'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $form['fields'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getChartColumns(),
      '#multiple' => TRUE,
      '#default_value' => $configuration['fields'] ?? [],
      '#title' => $this->t('Stats to show'),
    ];
    return $form;
  }

  /**
   * Lazy build callback.
   *
   * @param \Drupal\dashboards\Plugin\DashboardBase $plugin
   *   Matomo base plugin.
   * @param array $configuration
   *   Configuration.
   */
  public static function lazyBuild(DashboardBase $plugin, array $configuration): array {
    if (!($plugin instanceof VisitStatistic)) {
      return [];
    }
    try {
      $fields = array_values($configuration['fields']);
      $fields = array_filter($fields);

      $response = $plugin->query('VisitsSummary.get', [
        'filter_limit' => 20,
        'period' => $configuration['period'],
        'date' => $plugin->getDateTranslated($configuration['date']),
        'flat' => 1,
        'columns' => $fields,
      ]);

      $rows = [];
      foreach ($response as $date => $row) {
        if (!isset($row[0])) {
          $r = [$date];
          foreach ($fields as $field) {
            $r[] = 0;
          }
          continue;
        }
        $r = [$date];
        foreach ($fields as $field) {
          $r[] = $row[0][$field];
        }
        $rows[] = $r;
      }

      $plugin->setRows($rows);
      $plugin->setChartType('line');

      $labelsFields = $plugin->getChartColumns();

      $labels = [$plugin->t('Period')];
      foreach ($fields as $field) {
        $labels[] = $labelsFields[$field];
      }

      $plugin->setLabels($labels);

      $plugin->setChartType($configuration['chart_type']);
      return $plugin->renderChart($configuration);
    }
    catch (\Exception $ex) {
      return [
        '#markup' => $plugin->t('Error occurred: @error', ['@error' => $ex->getMessage()]),
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

}
