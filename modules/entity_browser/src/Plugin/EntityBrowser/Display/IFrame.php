<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\Display;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\DisplayBase;
use Drupal\entity_browser\DisplayRouterInterface;
use Drupal\entity_browser\Events\AlterEntityBrowserDisplayData;
use Drupal\entity_browser\Events\Events;
use Drupal\entity_browser\Events\RegisterJSCallbacks;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Presents entity browser in an iFrame.
 *
 * @EntityBrowserDisplay(
 *   id = "iframe",
 *   label = @Translation("iFrame"),
 *   description = @Translation("Displays the entity browser in an iFrame container embedded into the main page."),
 *   uses_route = TRUE
 * )
 */
class IFrame extends DisplayBase implements DisplayRouterInterface {

  /**
   * Current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The bare HTML page renderer.
   *
   * @var \Drupal\Core\Render\BareHtmlPageRendererInterface
   */
  protected $bareHtmlPageRenderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentRouteMatch = $container->get('current_route_match');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->currentPath = $container->get('path.current');
    $instance->renderer = $container->get('renderer');
    $instance->bareHtmlPageRenderer = $container->get('bare_html_page_renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'width' => '650',
      'height' => '500',
      'link_text' => $this->t('Select entities'),
      'auto_open' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function displayEntityBrowser(array $element, FormStateInterface $form_state, array &$complete_form, array $persistent_data = []) {
    parent::displayEntityBrowser($element, $form_state, $complete_form, $persistent_data);
    /** @var \Drupal\entity_browser\Events\RegisterJSCallbacks $event */
    $js_event_object = new RegisterJSCallbacks($this->configuration['entity_browser_id'], $this->getUuid());
    $js_event_object->registerCallback('Drupal.entityBrowser.selectionCompleted');
    $callback_event = $this->eventDispatcher->dispatch($js_event_object, Events::REGISTER_JS_CALLBACKS);
    $original_path = $this->currentPath->getPath();

    if (!empty($original_path) && strpos($original_path, '/entity-embed/dialog') === 0) {
      $referer = $_SERVER['HTTP_REFERER'];
      if (!empty($referer)) {
        $request = Request::create($referer);
        $original_path = $request->getPathInfo();
      }
    }

    $data = [
      'query_parameters' => [
        'query' => [
          'uuid' => $this->getUuid(),
          'original_path' => $original_path,
        ],
      ],
      'attributes' => [
        'href' => '#browser',
        'class' => ['entity-browser-handle', 'entity-browser-iframe'],
        'data-uuid' => $this->getUuid(),
        'data-original-path' => $original_path,
      ],
    ];
    $event_object = new AlterEntityBrowserDisplayData($this->configuration['entity_browser_id'], $this->getUuid(), $this->getPluginDefinition(), $form_state, $data);
    $event = $this->eventDispatcher->dispatch($event_object, Events::ALTER_BROWSER_DISPLAY_DATA);
    $data = $event->getData();
    return [
      '#theme_wrappers' => ['container'],
      '#attributes' => [
        'class' => [
          'entity-browser-iframe-container',
        ],
      ],
      'link' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this->configuration['link_text'],
        '#attributes' => $data['attributes'],
        '#attached' => [
          'library' => ['entity_browser/iframe'],
          'drupalSettings' => [
            'entity_browser' => [
              $this->getUuid() => [
                'auto_open' => $this->configuration['auto_open'],
              ],
              'iframe' => [
                $this->getUuid() => [
                  'src' => Url::fromRoute('entity_browser.' . $this->configuration['entity_browser_id'], [], $data['query_parameters'])
                    ->toString(),
                  'width' => $this->configuration['width'],
                  'height' => $this->configuration['height'],
                  'js_callbacks' => $callback_event->getCallbacks(),
                  'entity_browser_id' => $this->configuration['entity_browser_id'],
                  'auto_open' => $this->configuration['auto_open'],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * KernelEvents::RESPONSE listener.
   *
   * Intercepts default response and injects response that will trigger JS to
   * propagate selected entities upstream.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function propagateSelection(ResponseEvent $event) {
    $render = [
      '#attached' => [
        'library' => ['entity_browser/' . $this->pluginDefinition['id'] . '_selection'],
        'drupalSettings' => [
          'entity_browser' => [
            $this->pluginDefinition['id'] => [
              'entities' => array_map(function (EntityInterface $item) {
                return [$item->id(), $item->uuid(), $item->getEntityTypeId()];
              }, $this->entities),
              'uuid' => $this->request->query->get('uuid'),
            ],
          ],
        ],
      ],
    ];

    $event->setResponse($this->bareHtmlPageRenderer->renderBarePage($render, $this->t('Entity browser'), 'page'));
  }

  /**
   * {@inheritdoc}
   */
  public function path() {
    return '/entity-browser/' . $this->pluginDefinition['id'] . '/' . $this->configuration['entity_browser_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width of the iFrame'),
      '#default_value' => $configuration['width'],
      '#description' => $this->t('Positive integer for absolute size or a relative size in percentages.'),
    ];

    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height of the iFrame'),
      '#min' => 1,
      '#default_value' => $configuration['height'],
    ];

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $configuration['link_text'],
    ];

    $form['auto_open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto open entity browser'),
      '#default_value' => $configuration['auto_open'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // We want all positive integers, or percentages between 1% and 100%.
    $pattern = '/^([1-9][0-9]*|([2-9][0-9]{0,1}%)|(1[0-9]{0,2}%))$/';
    if (preg_match($pattern, $form_state->getValue('width')) == 0) {
      $form_state->setError($form['width'], $this->t('Width must be a number greater than 0, or a percentage between 1% and 100%.'));
    }

    if ($form_state->getValue('height') <= 0) {
      $form_state->setError($form['height'], $this->t('Height must be greater than 0.'));
    }
  }

}
