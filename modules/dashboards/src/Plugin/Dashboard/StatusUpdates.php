<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Drupal\update\UpdateManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "status_updates",
 *   label = @Translation("Module update status"),
 *   category = @Translation("Dashboards: System"),
 *   label_display = "hidden"
 * )
 */
class StatusUpdates extends DashboardBase {

  use StringTranslationTrait;

  /**
   * Update Manager Interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
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
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    if (!$this->moduleHandler->moduleExists('update')) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['status-report-logs', 'error']],
        'content' => [
          '#type' => 'container',
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('This feature requires the Core Update module to be enabled.'),
          ],
        ],
      ];
    }
    if ($available = update_get_available(FALSE)) {
      $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
      $project_data = update_calculate_project_data($available);

      $class = 'success';
      $counter = [
        UpdateManagerInterface::NOT_SECURE => 0,
        UpdateManagerInterface::NOT_CURRENT => 0,
        UpdateManagerInterface::CURRENT => count($this->moduleHandler->getModuleList()),
      ];

      $text = [
        UpdateManagerInterface::NOT_CURRENT => $this->t('Update available'),
        UpdateManagerInterface::NOT_SECURE => $this->t('Security update available'),
        UpdateManagerInterface::CURRENT => $this->t('Up to date'),
      ];

      $states = array_keys($counter);

      foreach ($project_data as $project) {
        if (in_array($project['status'], $states)) {
          $counter[$project['status']]++;
        }
      }

      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'status-report-card-updates',
            $class,
          ],
        ],
        'content' => [
          '#theme' => 'dashboards_admin_list',
          '#list' => array_map(
          function ($state) use ($counter, $text) {
            $i = [
              'url' => Url::fromRoute('update.module_update'),
              'title' => $this->t('@count @text', [
                '@count' => $counter[$state],
                '@text' => $text[$state],
              ]),
            ];
            return $i;
          },
          $states),
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['status-report-card']],
      'content' => [
        '#type' => 'container',
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Could not get updates'),
        ],
      ],
    ];
  }

}
