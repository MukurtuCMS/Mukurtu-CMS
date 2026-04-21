<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\Element\StatusReport;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Drupal\system\SystemManager;
use Psr\Container\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "system_info",
 *   label = @Translation("Show system info"),
 *   category = @Translation("Dashboards: System")
 * )
 */
class SystemInfo extends DashboardBase {

  use StringTranslationTrait;

  /**
   * Update Manager Interface.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache, SystemManager $system_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
    $this->systemManager = $system_manager;
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
      $container->get('system.manager'),
    );
  }

  /**
   * Get all elements.
   */
  public static function getCounter($requirements) {
    // Count number of items with different severity for summary.
    $element = [];
    $counters = [
      'error' => [
        'amount' => 0,
        'text' => t('Error'),
        'text_plural' => t('Errors'),
      ],
      'warning' => [
        'amount' => 0,
        'text' => t('Warning'),
        'text_plural' => t('Warnings'),
      ],
      'checked' => [
        'amount' => 0,
        'text' => t('Checked', [], ['context' => 'Examined']),
        'text_plural' => t('Checked', [], ['context' => 'Examined']),
      ],
    ];

    $severities = StatusReport::getSeverities();
    foreach ($requirements as $key => &$requirement) {
      $severity = $severities[REQUIREMENT_INFO];
      if (isset($requirement['severity'])) {
        $severity = $severities[(int) $requirement['severity']];
      }
      elseif (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE == 'install') {
        $severity = $severities[REQUIREMENT_OK];
      }

      if (isset($counters[$severity['status']])) {
        $counters[$severity['status']]['amount']++;
      }
    }

    foreach ($counters as $key => $counter) {
      if ($counter['amount'] === 0) {
        continue;
      }

      $text = new PluralTranslatableMarkup($counter['amount'], $counter['text'], $counter['text_plural']);

      $element[$key] = [
        'amount' => $counter['amount'],
        'text' => $text,
        'severity' => $key,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    $counter = static::getCounter($this->systemManager->listRequirements());
    $severity = 'checked';

    if (isset($counter['warning'])
      && isset($counter['warning']['amount'])
      && $counter['warning']['amount'] > 0) {
      $severity = 'warning';
    }
    if (isset($counter['error'])
      && isset($counter['error']['amount'])
      && $counter['error']['amount'] > 0) {
      $severity = 'error';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'status-report-errors',
          $severity,
        ],
      ],
      'child' => [
        '#theme' => 'dashboards_admin_list',
        '#list' => array_map(
          function ($severity) use ($counter) {
            $i = [
              'url' => Url::fromRoute('system.status'),
              'title' => $this->t('@count @text', [
                '@count' => $counter[$severity]['amount'],
                '@text' => $counter[$severity]['text'],
              ]),
            ];
            return $i;
          },
          array_keys($counter)
        ),
      ],
    ];
  }

}
