<?php

namespace Drupal\dashboards_matomo\Plugin\Dashboard;

use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\DashboardBase;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "matomo_top_urls",
 *   label = @Translation("Top urls."),
 *   category = @Translation("Matomo"),
 * )
 */
class TopUrls extends MatomoBase {

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $form['chart_type']['#access'] = FALSE;
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
    if (!($plugin instanceof MatomoBase)) {
      return [];
    }
    try {
      $response = $plugin->query('Actions.getPageUrls', [
        'filter_limit' => 20,
        'period' => $configuration['period'],
        'date' => $plugin->getDateTranslated($configuration['date']),
        'flat' => 1,
        'expanded' => TRUE,
      ]);

      $lists = [];
      foreach ($response as $date => $row) {
        $items = [];
        foreach ($row as $r) {
          if (empty($r)) {
            continue;
          }
          $items[] = [
            '#type' => 'inline_template',
            '#template' => '<a href="{{ url }}">{{ url }}</a>',
            '#context' => [
              'url' => (isset($r['url'])) ? $r['url'] : $plugin->t('Unknown'),
            ],
          ];
        }
        if (!empty($items)) {
          $lists[] = [
            '#theme' => 'item_list',
            '#title' => $date,
            '#items' => $items,
          ];
        }
      }

      return $lists;
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
