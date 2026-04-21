<?php

namespace Drupal\geofield\Plugin\GeofieldProximitySource;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\Plugin\GeofieldProximitySourceBase;
use Drupal\geofield\Plugin\GeofieldProximitySourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'Geofield Custom Origin' plugin.
 *
 * @package Drupal\geofield\Plugin
 *
 * @GeofieldProximitySource(
 *   id = "geofield_origin_from_proximity_filter",
 *   label = @Translation("Origin from Proximity Filter"),
 *   description = @Translation("A sort and field plugin that points the Origin from an existing Geofield Proximity Filter."),
 *   exposedDescription = @Translation("The origin is fixed from an existing Geofield Proximity Filter."),
 *   context = {
 *     "sort",
 *     "field",
 *   }
 * )
 */
class OriginFromProximityFilter extends GeofieldProximitySourceBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The geofield proximity manager.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceManager
   */
  protected $proximitySourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geofield_proximity_source')
    );
  }

  /**
   * Constructs a GeocodeOrigin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\Plugin\GeofieldProximitySourceManager $proximitySourceManager
   *   The Geofield Proximity Source manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeofieldProximitySourceManager $proximitySourceManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->proximitySourceManager = $proximitySourceManager;
  }

  /**
   * Returns the list of available proximity filters.
   *
   * @return array
   *   The list of available proximity filters
   */
  protected function getAvailableProximityFilters() {
    $proximity_filters = [];

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    foreach ($this->viewHandler->displayHandler->getHandlers('filter') as $delta => $filter) {
      if ($filter->pluginId === 'geofield_proximity_filter') {
        $proximity_filters[$delta] = $filter->adminLabel();
      }
    }

    return $proximity_filters;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE) {

    $user_input = $form_state->getUserInput();
    $proximity_filters_sources = $this->getAvailableProximityFilters();
    $user_input_proximity_filter = $user_input['options']['source_configuration']['source_proximity_filter'] ?? current(array_keys($proximity_filters_sources));
    $source_proximity_filter = $this->configuration['source_proximity_filter'] ?? $user_input_proximity_filter;

    if (!empty($proximity_filters_sources)) {
      $form['source_proximity_filter'] = [
        '#type' => 'select',
        '#title' => $this->t('Source Proximity Filter'),
        '#description' => $this->t('Select the Geofield Proximity filter to use as the starting point for calculating proximity.'),
        '#options' => $this->getAvailableProximityFilters(),
        '#default_value' => $source_proximity_filter,
        '#ajax' => [
          'callback' => [static::class, 'sourceProximityFilterUpdate'],
          'effect' => 'fade',
        ],
      ];
    }
    else {
      $form['source_proximity_filter_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No Geofield Proximity Filter found. At least one should be set for this Proximity Field be able to work.'),
        "#attributes" => [
          'class' => ['geofield-warning', 'red'],
        ],
      ];
      $form_state->setError($form['source_proximity_filter_warning'], $this->t('This Proximity Field cannot work. Dismiss this and add & setup a Geofield Proximity Filter before.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents) {
    $values = $form_state->getValues();
    if (!isset($values['options']['source_configuration']['source_proximity_filter'])) {
      $form_state->setError($form['source_proximity_filter_warning'], $this->t('This Proximity Field cannot work. Dismiss this and add and setup a Proximity Filter before.'));
    }
  }

  /**
   * Ajax callback triggered on Proximity Filter Selection.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response with updated form element.
   */
  public static function sourceProximityFilterUpdate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#proximity-source-configuration',
      $form['options']['source_configuration']
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrigin() {
    $origin = [];

    if (isset($this->viewHandler)
      && isset($this->viewHandler->view->filter[$this->viewHandler->options['source_configuration']['source_proximity_filter']])
      && is_a($this->viewHandler->view->filter[$this->viewHandler->options['source_configuration']['source_proximity_filter']], '\Drupal\geofield\Plugin\views\filter\GeofieldProximityFilter')
      && $source_proximity_filter = $this->viewHandler->options['source_configuration']['source_proximity_filter']
    ) {
      /** @var \Drupal\geofield\Plugin\views\filter\GeofieldProximityFilter $geofield_proximity_filter */
      $geofield_proximity_filter = $this->viewHandler->view->filter[$source_proximity_filter];

      $source_plugin_id = $geofield_proximity_filter->options['source'];
      $source_plugin_configuration = $geofield_proximity_filter->options['source_configuration'];

      try {

        /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
        $source_plugin = $this->proximitySourceManager->createInstance($source_plugin_id, $source_plugin_configuration);
        $source_plugin->setViewHandler($geofield_proximity_filter);

        $origin = $source_plugin->getOrigin();
      }
      catch (\Exception $e) {
        $this->getLogger('geofield')->error($e->getMessage());
      }
    }
    return $origin;
  }

}
