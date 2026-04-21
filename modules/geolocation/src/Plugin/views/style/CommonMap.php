<?php

namespace Drupal\geolocation\Plugin\views\style;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow to display several field items on a common map.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "maps_common",
 *   title = @Translation("Geolocation CommonMap"),
 *   help = @Translation("Display geolocations on a common map."),
 *   theme = "views_view_list",
 *   display_types = {"normal"},
 * )
 */
class CommonMap extends GeolocationStyleBase {

  /**
   * Map ID.
   *
   * @var bool|string
   */
  protected $mapId = FALSE;

  /**
   * Map provider manager.
   *
   * @var \Drupal\geolocation\MapProviderManager
   */
  protected $mapProviderManager = NULL;

  /**
   * MapCenter options manager.
   *
   * @var \Drupal\geolocation\MapCenterManager
   */
  protected $mapCenterManager = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $map_provider_manager, $map_center_manager, $file_url_generator, $data_provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $data_provider_manager, $file_url_generator);

    $this->mapProviderManager = $map_provider_manager;
    $this->mapCenterManager = $map_center_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.mapprovider'),
      $container->get('plugin.manager.geolocation.mapcenter'),
      $container->get('file_url_generator'),
      $container->get('plugin.manager.geolocation.dataprovider')
    );
  }

  /**
   * Map update option handling.
   *
   * Dynamic map and client location and potentially others update the view by
   * information determined on the client site. They may want to update the
   * view result as well. So we need to provide the possible ways to do that.
   *
   * @return array
   *   The determined options.
   */
  protected function getMapUpdateOptions() {
    $options = [];

    foreach ($this->displayHandler->getOption('filters') as $filter_id => $filter) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter_handler */
      $filter_handler = $this->displayHandler->getHandler('filter', $filter_id);

      if (!$filter_handler->isExposed()) {
        continue;
      }

      if (!empty($filter_handler->isGeolocationCommonMapOption)) {
        $options['boundary_filter_' . $filter_id] = $this->t('Boundary Filter') . ' - ' . $filter_handler->adminLabel();
      }

    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return (bool) $this->options['even_empty'];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $render = parent::render();
    if ($render === FALSE) {
      return [];
    }

    if (!empty($this->options['dynamic_map']['enabled'])) {
      // @todo Not unique enough, but uniqueid() changes on every AJAX request.
      // For the geolocationCommonMapBehavior to work, this has to stay
      // identical.
      $this->mapId = $this->view->id() . '-' . $this->view->current_display;
      $this->mapId = str_replace('_', '-', $this->mapId);
    }
    else {
      $this->mapId = $this->view->dom_id;
    }

    $map_settings = [];
    if (!empty($this->options['map_provider_settings'])) {
      $map_settings = $this->options['map_provider_settings'];
    }

    $build = [
      '#type' => 'geolocation_map',
      '#maptype' => $this->options['map_provider_id'],
      '#id' => $this->mapId,
      '#settings' => $map_settings,
      '#layers' => [],
      '#attached' => [
        'library' => [
          'geolocation/geolocation.commonmap',
        ],
      ],
      '#context' => ['view' => $this->view],
    ];

    /*
     * Dynamic map handling.
     */
    if (!empty($this->options['dynamic_map']['enabled'])) {
      if (
        !empty($this->options['dynamic_map']['update_target'])
        && $this->view->displayHandlers->has($this->options['dynamic_map']['update_target'])
      ) {
        $update_view_display_id = $this->options['dynamic_map']['update_target'];
      }
      else {
        $update_view_display_id = $this->view->current_display;
      }

      $build['#attached']['drupalSettings']['geolocation']['commonMap'][$this->mapId]['dynamic_map'] = [
        'enable' => TRUE,
        'hide_form' => $this->options['dynamic_map']['hide_form'],
        'views_refresh_delay' => $this->options['dynamic_map']['views_refresh_delay'],
        'update_view_id' => $this->view->id(),
        'update_view_display_id' => $update_view_display_id,
      ];

      if (substr($this->options['dynamic_map']['update_handler'], 0, strlen('boundary_filter_')) === 'boundary_filter_') {
        $filter_id = substr($this->options['dynamic_map']['update_handler'], strlen('boundary_filter_'));
        $filters = $this->displayHandler->getOption('filters');
        $filter_options = $filters[$filter_id];
        $build['#attached']['drupalSettings']['geolocation']['commonMap'][$this->mapId]['dynamic_map'] += [
          'boundary_filter' => TRUE,
          'parameter_identifier' => $filter_options['expose']['identifier'],
        ];
      }
    }

    $this->renderFields($this->view->result);

    /*
     * Add locations to output.
     */
    foreach ($this->view->result as $row) {
      foreach ($this->getLocationsFromRow($row) as $location) {
        $build['locations'][] = $location;
      }
    }

    if (empty($build['locations']) && !$this->evenEmpty()) {
      return [];
    }

    $build = $this->mapCenterManager->alterMap($build, $this->options['centre'], $this);

    if ($this->view->getRequest()->get('geolocation_common_map_dynamic_view')) {
      if (empty($build['#attributes'])) {
        $build['#attributes'] = [];
      }
      $build['#attributes'] = array_replace_recursive($build['#attributes'], [
        'data-preserve-map-center' => TRUE,
      ]);
    }

    if ($this->mapProviderManager->hasDefinition($this->options['map_provider_id'])) {
      $build = $this->mapProviderManager
        ->createInstance($this->options['map_provider_id'], $this->options['map_provider_settings'])
        ->alterCommonMap($build, $this->options['map_provider_settings'], ['view' => $this]);
    }

    if (
      !empty($this->view->geolocationLayers)
      && !empty($this->view->geolocationLayers[$this->view->current_display])
    ) {
      if (empty($build['#layers'])) {
        $build['#layers'] = [];
      }
      $build['#layers'][] = $this->view->geolocationLayers[$this->view->current_display];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['even_empty'] = ['default' => '1'];

    $options['dynamic_map'] = [
      'contains' => [
        'enabled' => ['default' => 0],
        'update_handler' => ['default' => ''],
        'update_target' => ['default' => ''],
        'hide_form' => ['default' => 0],
        'views_refresh_delay' => ['default' => '1200'],
      ],
    ];
    $options['centre'] = ['default' => []];

    $options['map_provider_id'] = ['default' => ''];
    $options['map_provider_settings'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $map_provider_options = $this->mapProviderManager->getMapProviderOptions();

    if (empty($map_provider_options)) {
      $form = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t("No map provider found."),
      ];
      return;
    }

    parent::buildOptionsForm($form, $form_state);

    $map_update_target_options = $this->getMapUpdateOptions();

    /*
     * Dynamic map handling.
     */
    if (!empty($map_update_target_options)) {
      $form['dynamic_map'] = [
        '#title' => $this->t('Dynamic Map'),
        '#type' => 'fieldset',
      ];
      $form['dynamic_map']['enabled'] = [
        '#title' => $this->t('Update view on map boundary changes. Also known as "AirBnB" style.'),
        '#type' => 'checkbox',
        '#default_value' => $this->options['dynamic_map']['enabled'],
        '#description' => $this->t("If enabled, moving the map will filter results based on current map boundary. This functionality requires an exposed boundary filter. Enabling AJAX is highly recommend for best user experience. If additional views are to be updated with the map change as well, it is highly recommended to use the view containing the map as 'parent' and the additional views as attachments."),
      ];

      $form['dynamic_map']['update_handler'] = [
        '#title' => $this->t('Dynamic map update handler'),
        '#type' => 'select',
        '#default_value' => $this->options['dynamic_map']['update_handler'],
        '#description' => $this->t("The map has to know how to feed back the update boundary data to the view."),
        '#options' => $map_update_target_options,
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['dynamic_map']['hide_form'] = [
        '#title' => $this->t('Hide exposed filter form element if applicable.'),
        '#type' => 'checkbox',
        '#default_value' => $this->options['dynamic_map']['hide_form'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['dynamic_map']['views_refresh_delay'] = [
        '#title' => $this->t('Minimum idle time in milliseconds required to trigger views refresh'),
        '#description' => $this->t('Once the view refresh is triggered, any further change of the map bounds will have no effect until the map update is finished. User interactions like scrolling in and out or dragging the map might trigger the map idle event, before the user is finished interacting. This setting adds a delay before the view is refreshed to allow further map interactions.'),
        '#type' => 'number',
        '#min' => 0,
        '#default_value' => $this->options['dynamic_map']['views_refresh_delay'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      if ($this->displayHandler->getPluginId() !== 'page') {
        $update_targets = [
          $this->displayHandler->display['id'] => $this->t('- This display -'),
        ];
        foreach ($this->view->displayHandlers->getInstanceIds() as $instance_id) {
          $display_instance = $this->view->displayHandlers->get($instance_id);
          if (in_array($display_instance->getPluginId(), ['page', 'block'])) {
            $update_targets[$instance_id] = $display_instance->display['display_title'];
          }
        }
        if (!empty($update_targets)) {
          $form['dynamic_map']['update_target'] = [
            '#title' => $this->t('Dynamic map update target'),
            '#type' => 'select',
            '#default_value' => $this->options['dynamic_map']['update_target'],
            '#description' => $this->t("Targets other than page or block can only update themselves."),
            '#options' => $update_targets,
            '#states' => [
              'visible' => [
                ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }
      }
    }

    /*
     * Centre handling.
     */
    $form['centre'] = $this->mapCenterManager->getCenterOptionsForm((array) $this->options['centre'], $this);

    /*
     * Advanced settings
     */
    $form['advanced_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced settings'),
    ];

    $form['even_empty'] = [
      '#group' => 'style_options][advanced_settings',
      '#title' => $this->t('Display map when no locations are found'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['even_empty'],
    ];

    $form['map_provider_id'] = [
      '#type' => 'select',
      '#options' => $map_provider_options,
      '#title' => $this->t('Map Provider'),
      '#default_value' => $this->options['map_provider_id'],
      '#ajax' => [
        'callback' => [
          get_class($this->mapProviderManager),
          'addSettingsFormAjax',
        ],
        'wrapper' => 'map-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $form['map_provider_settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t("No settings available."),
    ];

    $map_provider_id = NestedArray::getValue(
      $form_state->getUserInput(),
      ['style_options', 'map_provider_id']
    );
    if (empty($map_provider_id)) {
      $map_provider_id = $this->options['map_provider_id'];
    }
    if (empty($map_provider_id)) {
      $map_provider_id = key($map_provider_options);
    }

    $map_provider_settings = $this->options['map_provider_settings'] ?? [];
    if (
      !empty($this->options['map_provider_id'])
      && $map_provider_id != $this->options['map_provider_id']
    ) {
      $map_provider_settings = [];
      if (!empty($form_state->getValue([
        'style_options',
        'map_provider_settings',
      ]))) {
        $form_state->setValue(['style_options', 'map_provider_settings'], []);
        $form_state->setUserInput($form_state->getValues());
      }
    }

    if (!empty($map_provider_id)) {
      $form['map_provider_settings'] = $this->mapProviderManager
        ->createInstance($map_provider_id, $map_provider_settings)
        ->getSettingsForm(
          $map_provider_settings,
          [
            'style_options',
            'map_provider_settings',
          ]
        );
    }

    $form['map_provider_settings'] = array_replace(
      $form['map_provider_settings'],
      [
        '#prefix' => '<div id="map-provider-settings">',
        '#suffix' => '</div>',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if (empty($this->options['map_provider_id'])) {
      return $dependencies;
    }

    $definition = $this->mapProviderManager->getDefinition($this->options['map_provider_id']);

    return array_merge_recursive($dependencies, ['module' => [$definition['provider']]]);
  }

}
