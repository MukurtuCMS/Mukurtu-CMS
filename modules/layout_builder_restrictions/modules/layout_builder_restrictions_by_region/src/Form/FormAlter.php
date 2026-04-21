<?php

namespace Drupal\layout_builder_restrictions_by_region\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_region\Traits\LayoutBuilderRestrictionsByRegionHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class FormAlter implements ContainerInjectionInterface {

  use PluginHelperTrait;
  use LayoutBuilderRestrictionsByRegionHelperTrait;
  use DependencySerializationTrait;

  /**
   * The available restriction types.
   *
   * @var array
   */
  protected $restrictionTypes = [
    'allowlisted',
    'denylisted',
  ];

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;


  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * A service for generating UUIDs.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Creates a private temporary storage for a collection.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * FormAlter constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Block\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   A service for generating UUIDs.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   Creates a private temporary storage for a collection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    SectionStorageManagerInterface $section_storage_manager,
    BlockManagerInterface $block_manager,
    LayoutPluginManagerInterface $layout_manager,
    ContextHandlerInterface $context_handler,
    UuidInterface $uuid,
    PrivateTempStoreFactory $private_temp_store_factory,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->blockManager = $block_manager;
    $this->layoutManager = $layout_manager;
    $this->contextHandler = $context_handler;
    $this->uuid = $uuid;
    $this->privateTempStoreFactory = $private_temp_store_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block'),
      $container->get('plugin.manager.core.layout'),
      $container->get('context.handler'),
      $container->get('uuid'),
      $container->get('tempstore.private'),
      $container->get('config.factory')
    );
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface &$form_state, $form_id) {
    // Create a unique ID for this form build and store it in a hidden
    // element on the rendered form. This will be used to retrieve data
    // from tempStore.
    $user_input = $form_state->getUserInput();
    if (!isset($user_input['static_id'])) {
      $static_id = $this->uuid->generate();

      $form['static_id'] = [
        '#type' => 'hidden',
        '#value' => $static_id,
      ];
    }
    else {
      $static_id = $user_input['static_id'];
    }

    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['layout']['layout_builder_restrictions']['messages'] = [
        '#markup' => '<div id="layout-builder-restrictions-messages" class="hidden"></div>',
      ];

      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      // Layout settings.
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
      $allowed_layouts = (isset($third_party_settings['allowed_layouts'])) ? $third_party_settings['allowed_layouts'] : [];
      $layout_form = [
        '#type' => 'details',
        '#title' => $this->t('Layouts available for sections'),
        '#parents' => ['layout_builder_restrictions', 'allowed_layouts'],
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $layout_form['layout_restriction'] = [
        '#type' => 'radios',
        '#options' => [
          "all" => $this->t('Allow all existing & new layouts.'),
          "restricted" => $this->t('Allow only specific layouts:'),
        ],
        '#default_value' => !empty($allowed_layouts) ? "restricted" : "all",
      ];

      $entity_view_display_id = $display->get('id');
      $definitions = $this->getLayoutDefinitions();
      foreach ($definitions as $section => $definition) {
        $enabled = FALSE;
        if (!empty($allowed_layouts) && in_array($section, $allowed_layouts)) {
          $enabled = TRUE;
        }
        $layout_form['layouts'][$section] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#description' => [
            $definition->getIcon(60, 80, 1, 3),
            [
              '#type' => 'container',
              '#children' => $definition->getLabel() . ' (' . $section . ')',
            ],
          ],
          '#attributes' => [
            'data-layout-plugin' => [
              $section,
            ],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="layout_builder_restrictions[allowed_layouts][layout_restriction]"]' => ['value' => "all"],
            ],
          ],
        ];
      }
      $form['layout']['layout_builder_restrictions']['allowed_layouts'] = $layout_form;

      // Block settings.
      $layout_definitions = $definitions;

      foreach ($layout_definitions as $section => $definition) {
        $regions = $definition->getRegions();
        $regions['all_regions'] = [
          'label' => $this->t('All regions'),
        ];

        $form['layout'][$section] = [
          '#type' => 'details',
          '#title' => $this->t('Blocks available for the <em>@layout_label</em> layout', ['@layout_label' => $definition->getLabel()]),
          '#parents' => [
            'layout_builder_restrictions',
            'allowed_blocks_by_layout',
            $section,
          ],
          '#attributes' => [
            'data-layout-plugin' => $section,
          ],
          '#states' => [
            'disabled' => [
              [':input[name="layout[enabled]"]' => ['checked' => FALSE]],
              'or',
              ['#edit-layout-builder-restrictions-allowed-layouts :input[data-layout-plugin="' . $section . '"]' => ['checked' => FALSE]],
            ],
            'invisible' => [
              [':input[name="layout[enabled]"]' => ['checked' => FALSE]],
              'or',
              ['#edit-layout-builder-restrictions-allowed-layouts :input[data-layout-plugin="' . $section . '"]' => ['checked' => FALSE]],
            ],
          ],
        ];
        $default_restriction_behavior = 'all';
        if (isset($third_party_settings['allowlisted_blocks'][$section]) && !isset($third_party_settings['allowlisted_blocks'][$section]['all_regions'])) {
          $default_restriction_behavior = 'per-region';
        }
        if (isset($third_party_settings['denylisted_blocks'][$section]) && !isset($third_party_settings['denylisted_blocks'][$section]['all_regions'])) {
          $default_restriction_behavior = 'per-region';
        }
        if (isset($third_party_settings['restricted_categories'][$section]) && !isset($third_party_settings['restricted_categories'][$section]['all_regions'])) {
          $default_restriction_behavior = 'per-region';
        }
        $form['layout'][$section]['restriction_behavior'] = [
          '#type' => 'radios',
          '#options' => [
            "all" => $this->t('Apply block restrictions to all regions in layout'),
            "per-region" => $this->t('Apply block restrictions on a region-by-region basis'),
          ],
          '#attributes' => [
            'class' => [
              'restriction-type',
            ],
            'data-layout-plugin' => $section,
          ],
          '#default_value' => $default_restriction_behavior,
        ];

        $form['layout'][$section]['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Region'),
            $this->t('Status'),
            $this->t('Operations'),
          ],
          '#attributes' => [
            'data-layout' => $section,
          ],
        ];

        foreach ($regions as $region_id => $region) {
          $form['layout'][$section]['table']['#rows'][$region_id] = [
            'data-region' => $region_id,
            'data' => [
              'region_label' => [
                'class' => [
                  'region-label',
                ],
                'data' => [
                  '#markup' => is_object($region['label']) ? $region['label']->render() : $region['label'],
                ],
              ],
              'status' => [
                'class' => [
                  'restriction-status',
                ],
                'id' => 'restriction-status--' . $section . '--' . $region_id,
                'data' => [
                  '#markup' => '<span class="data">' . $this->RegionRestrictionStatusString($section, $region_id, $static_id, $entity_view_display_id) . '</span>',
                ],
              ],
              'operations' => [
                'class' => [
                  'operations',
                ],
                'data' => [
                  '#type' => 'dropbutton',
                  '#links' => [
                    'manage' => [
                      'title' => $this->t('Manage allowed blocks'),
                      'url' => Url::fromRoute("layout_builder_restrictions_by_region.{$form['#entity_type']}_allowed_blocks", [
                        'static_id' => $static_id,
                        'entity_view_display_id' => $entity_view_display_id,
                        'layout_plugin' => $section,
                        'region_id' => $region_id,
                      ]),
                      'attributes' => [
                        'class' => [
                          'use-ajax',
                        ],
                        'data-dialog-type' => 'modal',
                        'data-dialog-options' => Json::encode(['width' => 800]),
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ];
        }
      }

      // Add certain variables as form state temp value for later use.
      $form_state->setTemporaryValue('static_id', $static_id);

      $form['#attached']['library'][] = 'layout_builder_restrictions_by_region/display_mode_form';
    }
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    $settings = $this->configFactory->get('layout_builder_restrictions_by_region.settings');
    $retain_restrictions_after_layout_removal = $settings->get('retain_restrictions_after_layout_removal') ?? '0';

    // Get any existing third party settings.
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region');
    // Get form submission data.
    $layout_restriction = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_layouts',
      'layout_restriction',
    ]);
    $allowed_layouts = [];
    if ($layout_restriction == 'restricted') {
      $allowed_layouts = array_keys(array_filter($form_state->getValue([
        'layout_builder_restrictions',
        'allowed_layouts',
        'layouts',
      ])));
    }
    // Get tempstore data (where per-layout data is stored).
    $tempstore = $this->privateTempStoreFactory;
    $static_id = $form_state->getTemporaryValue('static_id');
    $store = $tempstore->get('layout_builder_restrictions_by_region');
    // Get extant list of site's available blocks.
    $layout_definitions = $this->getLayoutDefinitions();

    if ($retain_restrictions_after_layout_removal) {
      // Do not clean up restrictions on layouts that have been removed.
      // See #3305449.
      $third_party_settings['allowed_layouts'] = $allowed_layouts;
    }
    else {
      // Clean up any restrictions on layouts which have been removed.
      $third_party_settings = $this->setAllowedLayouts($allowed_layouts, $third_party_settings);
    }

    // Prepare third party settings data for each section.
    foreach ($allowed_layouts as $section) {
      // Set allowed layouts.
      $scope = $form_state->getValue([
        'layout_builder_restrictions',
        'allowed_blocks_by_layout',
        $section,
      ]);

      $third_party_settings = $this->prepareRegionRestrictions($section, $scope['restriction_behavior'], $layout_definitions, $store, $static_id, $third_party_settings);
    }
    // Ensure data is saved in consistent alpha order by region.
    foreach ($this->restrictionTypes as $logic_type) {
      if (isset($third_party_settings[$logic_type . '_blocks'])) {
        foreach ($third_party_settings[$logic_type . '_blocks'] as $section => $regions) {
          ksort($regions);
          $third_party_settings[$logic_type . '_blocks'][$section] = $regions;
        }
      }
      if (isset($third_party_settings[$logic_type . '_blocks'])) {
        // Ensure data is saved in alpha order by layout.
        ksort($third_party_settings[$logic_type . '_blocks']);
      }
    }
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', $third_party_settings);
  }

  /**
   * Update which layouts are eligible for restrictions.
   *
   * @param array $allowed_layouts
   *   Layouts allowed by the current form state.
   * @param mixed $third_party_settings
   *   The entity view mode's Layout Builder Restrictions 3rd party settings.
   *
   * @return array
   *   The updated 3rd party settings.
   */
  public function setAllowedLayouts(array $allowed_layouts, $third_party_settings = []) {
    // First, set the allowed layouts from the form state.
    $third_party_settings['allowed_layouts'] = $allowed_layouts;

    // The rest of this method removes data from layouts that were previously
    // allowed but are no longer allowed by removing their key.
    if (isset($third_party_settings['allowlisted_blocks'])) {
      foreach (array_keys($third_party_settings['allowlisted_blocks']) as $layout) {
        if (!in_array($layout, $allowed_layouts)) {
          unset($third_party_settings['allowlisted_blocks'][$layout]);
        }
      }
    }
    if (isset($third_party_settings['denylisted_blocks'])) {
      foreach (array_keys($third_party_settings['denylisted_blocks']) as $layout) {
        if (!in_array($layout, $allowed_layouts)) {
          unset($third_party_settings['denylisted_blocks'][$layout]);
        }
      }
    }
    if (isset($third_party_settings['restricted_categories'])) {
      foreach (array_keys($third_party_settings['restricted_categories']) as $layout) {
        if (!in_array($layout, $allowed_layouts)) {
          unset($third_party_settings['restricted_categories'][$layout]);
        }
      }
    }
    return $third_party_settings;
  }

  /**
   * Set region-specific restrictions.
   *
   * @param string $region
   *   The region for the current section.
   * @param string $section
   *   The section (i.e., layout).
   * @param mixed $data
   *   Form state state as provided by the tempstore.
   * @param mixed $third_party_settings
   *   The entity view mode's Layout Builder Restrictions 3rd party settings.
   *
   * @return array
   *   The updated 3rd party settings.
   */
  public function setRegionSettings($region, $section, $data = [], $third_party_settings = []) {
    unset($third_party_settings['restricted_categories'][$section][$region]);
    unset($third_party_settings['allowlisted_blocks'][$section][$region]);
    unset($third_party_settings['denylisted_blocks'][$section][$region]);
    if (isset($data)) {
      foreach ($data as $category => $settings) {
        $restriction_type = $settings['restriction_type'];
        if ($restriction_type == 'restrict_all') {
          $third_party_settings['restricted_categories'][$section][$region][] = $category;
        }
        elseif (in_array($restriction_type, $this->restrictionTypes)) {
          if (empty($settings['restrictions'])) {
            $third_party_settings[$restriction_type . '_blocks'][$section][$region][$category] = [];
          }
          else {
            foreach ($settings['restrictions'] as $block_id => $block_setting) {
              $third_party_settings[$restriction_type . '_blocks'][$section][$region][$category][] = $block_id;
            }
          }
        }
      }
    }
    if (empty($third_party_settings['restricted_categories'][$section])) {
      unset($third_party_settings['restricted_categories'][$section]);
    }
    if (empty($third_party_settings['allowlisted_blocks'][$section])) {
      unset($third_party_settings['allowlisted_blocks'][$section]);
    }
    if (empty($third_party_settings['denylisted_blocks'][$section])) {
      unset($third_party_settings['denylisted_blocks'][$section]);
    }
    return $third_party_settings;
  }

  /**
   * Wrapper method to region-specific restrictions.
   *
   * @param string $section
   *   The section (i.e., layout).
   * @param string $scope
   *   Whether restrictions are per layout or per region..
   * @param array $layout_definitions
   *   The site configuration's extant layouts.
   * @param \Drupal\Core\TempStore\PrivateTempStore $store
   *   The entity view mode's Layout Builder Restrictions 3rd party settings.
   * @param string $static_id
   *   An ID to identify the tempstore.
   * @param array $third_party_settings
   *   The entity view mode's Layout Builder Restrictions 3rd party settings.
   *
   * @return array
   *   The updated 3rd party settings.
   */
  public function prepareRegionRestrictions($section, $scope, array $layout_definitions, PrivateTempStore $store, $static_id, array $third_party_settings = []) {
    $types = [
      'restricted_categories',
      'allowlisted_blocks',
      'denylisted_blocks',
    ];
    $layout_definition = $layout_definitions[$section];
    $regions = $layout_definition->getRegions();
    // First check if the layout's restriction is set to apply to all regions.
    // If so, we should delete all other regions.
    if ($scope === 'all') {
      // Delete any previously stored restrictions for specific regions.
      foreach (array_keys($regions) as $region) {
        foreach ($types as $type) {
          unset($third_party_settings[$type][$section][$region]);
        }
      }
      $all_regions_temp = $store->get($static_id . ':' . $section . ':all_regions');
      if (!empty($all_regions_temp)) {
        // Now set the all_regions value.
        $third_party_settings = $this->setRegionSettings('all_regions', $section, $all_regions_temp, $third_party_settings);
      }
      foreach ($types as $type) {
        if (empty($third_party_settings[$type][$section])) {
          unset($third_party_settings[$type][$section]);
        }
      }
    }
    elseif ($scope === 'per-region') {
      unset($third_party_settings['restricted_categories'][$section]['all_regions']);
      unset($third_party_settings['allowlisted_blocks'][$section]['all_regions']);
      unset($third_party_settings['denylisted_blocks'][$section]['all_regions']);
      // Second, check each region for temp data.
      // If there is temp data, that means changes have been made prior to save.
      // Otherwise, preserve whatever what is present before.
      foreach (array_keys($regions) as $region) {
        $region_temp = $store->get($static_id . ':' . $section . ':' . $region);
        if (!empty($region_temp)) {
          $third_party_settings = $this->setRegionSettings($region, $section, $region_temp, $third_party_settings);
        }
      }
    }
    return $third_party_settings;
  }

}
