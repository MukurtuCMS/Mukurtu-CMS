<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "add_content_menu",
 *   label = @Translation("Add content"),
 *   category = @Translation("Dashboards: Navigation")
 * )
 */
class AddContentMenu extends DashboardBase {

  /**
   * AccountInterface definition.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Redirect service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CacheBackendInterface $cache,
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $bundle_info,
    RedirectDestinationInterface $destination,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache);
    $this->account = $account;
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->destination = $destination;
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
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    $bundleInfo = $this->bundleInfo->getBundleInfo('node');
    /**
     * @var \Drupal\node\Entity\NodeType[]
     */
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $items = [];
    foreach ($configuration['items'] as $bundle => $info) {
      if (isset($types[$bundle]) && $this->entityTypeManager->getAccessControlHandler('node')->createAccess($bundle, NULL, [])) {
        $url_params = ['node_type' => $bundle];
        if (!empty($configuration['include_destination'])) {
          $url_params['destination'] = $this->destination->get();
        }
        $url = Url::fromRoute('node.add', $url_params);
        $items[] = [
          'url' => $url,
          'title' => $bundleInfo[$bundle]['label'],
          'description' => ['#markup' => $types[$bundle]->getDescription()],
        ];
      }
    }
    return [
      '#theme' => 'dashboards_admin_list',
      '#list' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $group_class = 'group-order-weight';
    $info = $this->bundleInfo->getBundleInfo('node');

    $form['include_destination'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include destination'),
      '#default_value' => $configuration['include_destination'] ?? 0,
    ];

    $form['items'] = [
      '#type' => 'table',
      '#caption' => $this->t('Content types'),
      '#header' => [
        $this->t('Label'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No content types.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
    ];

    foreach ($info as $key => $value) {
      $form['items'][$key]['#attributes']['class'][] = 'draggable';
      $form['items'][$key]['#weight'] = (!isset($value['weight'])) ? 0 : $value['weight'];

      // Label col.
      $form['items'][$key]['label'] = [
        '#plain_text' => $value['label'],
      ];

      // Weight col.
      $form['items'][$key]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $value['label']]),
        '#title_display' => 'invisible',
        '#default_value' => (!isset($value['weight'])) ? 0 : $value['weight'],
        '#attributes' => ['class' => [$group_class]],
      ];
    }

    $form['#attached']['library'][] = 'core/drupal.tabledrag';

    return $form;
  }

}
