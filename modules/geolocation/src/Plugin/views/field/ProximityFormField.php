<?php

namespace Drupal\geolocation\Plugin\views\field;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\geolocation\LocationInputManager;
use Drupal\geolocation\LocationManager;
use Drupal\geolocation\ProximityTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler for geolocation field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geolocation_field_proximity_form")
 */
class ProximityFormField extends ProximityField implements ContainerFactoryPluginInterface {

  use ProximityTrait;

  /**
   * Center value.
   *
   * @var array
   */
  protected $centerValue = [];

  /**
   * Location input manager.
   *
   * @var \Drupal\geolocation\LocationInputManager
   */
  protected $locationInputManager;

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\LocationManager $location_manager
   *   Location manager.
   * @param \Drupal\geolocation\LocationInputManager $location_input_manager
   *   Location input manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocationManager $location_manager, LocationInputManager $location_input_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $location_manager);

    $this->locationInputManager = $location_input_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.location'),
      $container->get('plugin.manager.geolocation.locationinput')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $proximity_center_options = NestedArray::getValue(
      $form_state->getUserInput(),
      ['options', 'center'],
    );
    if (empty($proximity_center_options)) {
      $proximity_center_options = $this->options['center'];
    }
    if (empty($proximity_center_options)) {
      $proximity_center_options = [];
    }
    $form['center'] = $this->locationInputManager->getOptionsForm($proximity_center_options, $this);
  }

  /**
   * {@inheritdoc}
   *
   * Provide a more useful title to improve the accessibility.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['center'] = $this->locationInputManager->getForm($this->options['center'], $this, $this->getCenter());

    $form['actions']['submit']['#value'] = $this->t('Calculate proximity');

    // #weight will be stripped from 'output' in preRender callback.
    // Offset negatively to compensate.
    foreach (Element::children($form) as $key) {
      if (isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = $form[$key]['#weight'] - 2;
      }
      else {
        $form[$key]['#weight'] = -2;
      }
    }
    $form['actions']['#weight'] = -1;
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user tried to access an action without access to it.
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('step') == 'views_form_views_form') {
      $form_state->disableRedirect(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCenter() {
    if (empty($this->centerValue)) {
      $this->centerValue = $this->locationInputManager->getCoordinates((array) $this->view->getRequest()->get('center', []), $this->options['center'], $this);
    }
    return $this->centerValue;
  }

}
