<?php

namespace Drupal\dashboards_matomo\Plugin\Dashboard;

use Drupal\dashboards\Plugin\DashboardBase;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "matomo_browser",
 *   label = @Translation("Browser."),
 *   category = @Translation("Matomo"),
 * )
 */
class Browser extends MatomoBase {

  /**
   * Lazy build callback.
   *
   * @param \Drupal\dashboards\Plugin\DashboardBase $plugin
   *   Matomo base plugin.
   * @param array $configuration
   *   Configuration.
   */
  public static function lazyBuild(DashboardBase $plugin, array $configuration): array {
    if (!($plugin instanceof MatomoBase)) {
      return [];
    }
    try {
      $response = $plugin->query('DevicesDetection.getBrowsers', [
        'filter_limit' => 20,
        'period' => $configuration['period'],
        'date' => $plugin->getDateTranslated($configuration['date']),
        'flat' => 1,
      ]);

      if (empty($response)) {
        $plugin->setEmpty(TRUE);
        return $plugin->renderChart($configuration);
      }

      $plugin->buildDateRows($response, $plugin->t('Date'), ['nb_visits']);
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
