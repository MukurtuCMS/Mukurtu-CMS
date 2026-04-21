<?php

namespace Drupal\better_exposed_filters\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\ViewsHandlerInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Base class for Better exposed filters widget plugins.
 */
abstract class BetterExposedFiltersWidgetBase extends PluginBase implements BetterExposedFiltersWidgetInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The views executable object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected ViewExecutable $view;

  /**
   * The views plugin this configuration will affect when exposed.
   *
   * @var \Drupal\views\Plugin\views\ViewsHandlerInterface
   */
  protected ViewsHandlerInterface $handler;

  /**
   * Constructs a BetterExposedFiltersWidgetBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected Request $request, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'plugin_id' => $this->pluginId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setView(ViewExecutable $view): void {
    $this->view = $view;
  }

  /**
   * {@inheritdoc}
   */
  public function setViewsHandler(ViewsHandlerInterface $handler): void {
    $this->handler = $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Apply submitted form state to configuration.
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (array_key_exists($key, $this->configuration)) {
        $this->configuration[$key] = $value;
      }
      else {
        // Remove from form state.
        unset($values[$key]);
      }
    }
  }

  /*
   * Helper functions.
   */

  /**
   * Sets metadata on the form elements for easier processing.
   *
   * @param array $element
   *   The form element to apply the metadata to.
   *
   * @see ://www.drupal.org/project/drupal/issues/2511548
   */
  protected function addContext(array &$element): void {
    $element['#context'] = [
      '#plugin_type' => 'bef',
      '#plugin_id' => $this->pluginId,
      '#view_id' => $this->view->id(),
      '#display_id' => $this->view->current_display,
    ];
  }

  /**
   * Moves an exposed form element into a field group.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Exposed views form state.
   * @param string $element
   *   The key of the form element.
   * @param string $group
   *   The name of the group element.
   */
  protected function addElementToGroup(array &$form, FormStateInterface $form_state, string $element, string $group): void {
    // Ensure group is enabled.
    $form[$group]['#access'] = TRUE;

    // Add element to group.
    $form[$element]['#group'] = $group;

    // Persist state of collapsible field-sets with active elements.
    if (empty($form[$group]['#open'])) {
      // Use raw user input to determine if field-set should be open or closed.
      $user_input = $form_state->getUserInput()[$element] ?? [0];
      // Take multiple values into account.
      if (!is_array($user_input)) {
        $user_input = [$user_input];
      }

      // Check if one or more values are set for our current element.
      $default_value = $form[$element]['#default_value'] ?? key($form[$element]['#options'] ?? []);
      $has_values_selected = array_reduce($user_input, function (bool $carry, mixed $value) use ($default_value, $form, $element) {
        return $carry ||
          ($form[$element]['#multiple'] ?? FALSE ? ($value === $default_value || isset($form[$element]['#options'][$value])) : !($value === $default_value) && ($value || $default_value === 0));
      }, FALSE);

      $collapsible_disable_automatic_open = FALSE;
      if (isset($form[$element]['#collapsible_disable_automatic_open'])) {
        $collapsible_disable_automatic_open = $form[$element]['#collapsible_disable_automatic_open'];
      }
      elseif (isset($form[$group]['#collapsible_disable_automatic_open'])) {
        $collapsible_disable_automatic_open = $form[$group]['#collapsible_disable_automatic_open'];
      }
      if ($has_values_selected && !$collapsible_disable_automatic_open) {
        $form[$group]['#open'] = TRUE;

        // Make sure that secondary group is opened if one of the collapsible
        // fields has user input.
        if (isset($form['secondary']) && isset($form[$group]['#group']) && 'secondary' === $form[$group]['#group']) {
          $form['secondary']['#open'] = TRUE;
        }
      }
    }
  }

  /**
   * Returns exposed form action URL object.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Exposed views form state.
   *
   * @return \Drupal\Core\Url
   *   Url object.
   */
  protected function getExposedFormActionUrl(FormStateInterface $form_state): Url {

    $request = $this->request;
    if ($this->view->hasUrl()) {
      $url = $this->view->getUrl();
    }
    else {
      try {
        $url = Url::createFromRequest(clone $request);
      }
      catch (ResourceNotFoundException) {
        // If the route is not found or a route parameter is not valid,
        // fallback to the 404-page URL.
        $uri = $this->configFactory->get('system.site')->get('page.404') ?: $request->getRequestUri();
        $url = Url::fromUserInput($uri);
      }
    }

    $url->setAbsolute();

    return $url;
  }

}
