<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\Widget;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\WidgetBase;
use Drupal\views\Entity\View as ViewEntity;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "view",
 *   label = @Translation("View"),
 *   provider = "views",
 *   description = @Translation("Uses a view to provide entity listing in a browser's widget."),
 *   auto_select = TRUE
 * )
 */
class View extends WidgetBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The paramconverter manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface
   */
  protected $paramConverterManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'view' => NULL,
      'view_display' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->renderer = $container->get('renderer');
    $instance->requestStack = $container->get('request_stack');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->routeProvider = $container->get('router.route_provider');
    $instance->paramConverterManager = $container->get('paramconverter_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    // @todo do we need better error handling for view and view_display (in
    // case either of those is nonexistent or display not of correct type)?
    $form['#attached']['library'] = ['entity_browser/view'];

    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($this->configuration['view'])
      ->getExecutable();

    if (!empty($this->configuration['arguments'])) {
      if (!empty($additional_widget_parameters['path_parts'])) {
        $arguments = [];
        // Map configuration arguments with original path parts.
        foreach ($this->configuration['arguments'] as $argument) {
          $arguments[] = $additional_widget_parameters['path_parts'][$argument] ?? '';
        }
        $view->setArguments(array_values($arguments));
      }
    }

    $context = $this->getContext($form_state);
    $this->moduleHandler->alter('entity_browser_view_executable', $view, $this->configuration, $context);

    $form['view'] = $view->executeDisplay($this->configuration['view_display']);

    if (empty($view->field['entity_browser_select'])) {
      $url = Url::fromRoute('entity.view.edit_form', ['view' => $this->configuration['view']])->toString();
      if ($this->currentUser->hasPermission('administer views')) {
        return [
          '#markup' => $this->t('Entity browser select form field not found on a view. <a href=":link">Go fix it</a>!', [':link' => $url]),
        ];
      }
      else {
        return [
          '#markup' => $this->t('Entity browser select form field not found on a view. Go fix it!'),
        ];
      }
    }

    // When rebuilding makes no sense to keep checkboxes that were previously
    // selected.
    if (!empty($form['view']['entity_browser_select'])) {
      foreach (Element::children($form['view']['entity_browser_select']) as $child) {
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\entity_browser\Plugin\EntityBrowser\Widget\View', 'processCheckbox'];
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\Core\Render\Element\Checkbox', 'processAjaxForm'];
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\Core\Render\Element\Checkbox', 'processGroup'];
      }
    }

    $form['view']['view'] = [
      '#markup' => $this->renderer->render($form['view']['view']),
    ];

    return $form;
  }

  /**
   * Sets the #checked property when rebuilding form.
   *
   * Every time when we rebuild we want all checkboxes to be unchecked.
   *
   * @see \Drupal\Core\Render\Element\Checkbox::processCheckbox()
   */
  public static function processCheckbox(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($form_state->isRebuilding()) {
      $element['#checked'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    if (isset($user_input['entity_browser_select'])) {
      if (is_array($user_input['entity_browser_select'])) {
        $selected_rows = array_values(array_filter($user_input['entity_browser_select']));
      }
      else {
        $selected_rows = [$user_input['entity_browser_select']];
      }

      $use_field_cardinality = !empty($user_input['entity_browser_select_form_metadata']['use_field_cardinality']);
      if ($use_field_cardinality) {
        $cardinality = !empty($user_input['entity_browser_select_form_metadata']['cardinality']) ? $user_input['entity_browser_select_form_metadata']['cardinality'] : 0;
        if ($cardinality > 0 && count($selected_rows) > $cardinality) {
          $message = $this->formatPlural($cardinality, 'You can only select one item.', 'You can only select up to @number items.', ['@number' => $cardinality]);
          $form_state->setError($form['widget']['view']['entity_browser_select'], $message);
        }
      }

      foreach ($selected_rows as $row) {
        // Verify that the user input is a string and split it.
        // Each $row is in the format entity_type:id.
        if (is_string($row) && $parts = explode(':', $row, 2)) {
          // Make sure we have a type and id present.
          if (count($parts) == 2) {
            try {
              $storage = $this->entityTypeManager->getStorage($parts[0]);
              if (!$storage->load($parts[1])) {
                $message = $this->t('The @type Entity @id does not exist.', [
                  '@type' => $parts[0],
                  '@id' => $parts[1],
                ]);
                $form_state->setError($form['widget']['view']['entity_browser_select'], $message);
              }
            }
            catch (PluginNotFoundException $e) {
              $message = $this->t('The Entity Type @type does not exist.', [
                '@type' => $parts[0],
              ]);
              $form_state->setError($form['widget']['view']['entity_browser_select'], $message);
            }
          }
        }
      }

      // If there weren't any errors set, run the normal validators.
      if (empty($form_state->getErrors())) {
        parent::validate($form, $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    if (is_array($form_state->getUserInput()['entity_browser_select'])) {
      $selected_rows = array_values(array_filter($form_state->getUserInput()['entity_browser_select']));
    }
    else {
      $selected_rows = [$form_state->getUserInput()['entity_browser_select']];
    }

    $entities = [];
    foreach ($selected_rows as $row) {
      $item = explode(':', $row);
      if (count($item) == 2) {
        [$type, $id] = $item;
        $storage = $this->entityTypeManager->getStorage($type);
        if ($entity = $storage->load($id)) {
          $entities[] = $entity;
        }
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $entities = $this->prepareEntities($form, $form_state);
    $this->selectEntities($entities, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $options = [];
    // Get only those enabled Views that have entity_browser displays.
    $displays = Views::getApplicableViews('entity_browser_display');
    foreach ($displays as $display) {
      [$view_id, $display_id] = $display;
      $view = $this->entityTypeManager->getStorage('view')->load($view_id);
      $options[$view_id . '.' . $display_id] = $this->t('@view : @display', ['@view' => $view->label(), '@display' => $view->get('display')[$display_id]['display_title']]);
    }

    $form['view'] = [
      '#type' => 'select',
      '#title' => $this->t('View : View display'),
      '#default_value' => $this->configuration['view'] . '.' . $this->configuration['view_display'],
      '#options' => $options,
      '#empty_option' => $this->t('- Select a view -'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues()['table'][$this->uuid()]['form'];
    $this->configuration['submit_text'] = $values['submit_text'];
    $this->configuration['auto_select'] = $values['auto_select'];
    if (!empty($values['view'])) {
      [$view_id, $display_id] = explode('.', $values['view']);
      $this->configuration['view'] = $view_id;
      $this->configuration['view_display'] = $display_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    if ($this->configuration['view']) {
      $view = ViewEntity::load($this->configuration['view']);
      $dependencies[$view->getConfigDependencyKey()] = [$view->getConfigDependencyName()];
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    // Mark the widget as not visible if the user has no access to the view.
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($this->configuration['view'])
      ->getExecutable();

    // Check if the current user has access to this view.
    return AccessResult::allowedIf($view->access($this->configuration['view_display']));
  }

  /**
   * Create context data for hook_entity_browser_view_widget_view_alter().
   *
   * @param $form_state
   *   The current form state.
   *
   * @return array
   *   An array of helpful contextual data.
   */
  protected function getContext($form_state) {
    $context = [];
    $storage = $form_state->getStorage();
    if (!empty($storage['entity_browser']['widget_context'])) {
      $context = $storage['entity_browser']['widget_context'];
    }
    $current_request = $this->requestStack->getCurrentRequest();
    $original_path = $current_request->query->get('original_path');

    // If within the context of an entity embed dialog, we must get the original form
    // from the REFERER.
    if (!empty($original_path) && strpos($original_path, '/entity-embed/dialog') === 0) {
      $headers = $current_request->server->getHeaders();
      $referer = isset($headers['REFERER']) ? $headers['REFERER'] : '';
      if (!empty($referer)) {
        $request = Request::create($referer);
        $original_path = $request->getPathInfo();
      }
    }

    if (empty($original_path)) {
      return $context;
    }

    $url = Url::fromUserInput($original_path);
    $route = $route_match = NULL;
    if ($url->isRouted()) {
      $route_name = $url->getRouteName();
      $route = $this->routeProvider->getRouteByName($route_name);
      $parameters = $url->getRouteParameters();
      $parameters[RouteObjectInterface::ROUTE_NAME] = $route_name;
      $parameters[RouteObjectInterface::ROUTE_OBJECT] = $route;
      $upcasted_parameters = $this->paramConverterManager->convert($parameters + $route->getDefaults());
      $route_match = new RouteMatch($route_name, $route, $upcasted_parameters, $parameters);
    }
    $context += [
      'original_route' => $route,
      'original_route_match' => $route_match,
      'original_path' => $original_path,
      'original_path_url' => $url,
      'current_user' => $this->currentUser,
    ];
    return $context;
  }

}
