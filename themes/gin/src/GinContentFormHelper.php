<?php

namespace Drupal\gin;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

include_once __DIR__ . '/../gin.theme';
_gin_include_theme_includes();

/**
 * Service to handle content form overrides.
 */
class GinContentFormHelper implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use StringTranslationTrait;

  /**
   * GinContentFormHelper constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The HTTP request stack.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
    protected ThemeManagerInterface $themeManager,
    protected RequestStack $requestStack,
    protected ClassResolverInterface $classResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('current_route_match'),
      $container->get('theme.manager'),
      $container->get('request_stack'),
      $container->get('class_resolver'),
    );
  }

  /**
   * Add some major form overrides.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   *
   * @see hook_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    if ($this->isModalOrOffcanvas()) {
      $form['is_ajax_request'] = ['#weight' => -1];
      return FALSE;
    }

    // Save form types and behaviors.
    $use_sticky_action_buttons = $this->stickyActionButtons($form, $form_state, $form_id);
    $is_content_form = $this->isContentForm($form, $form_state, $form_id);

    // Sticky action buttons.
    if (($use_sticky_action_buttons || $is_content_form) && isset($form['actions'])) {
      // Add sticky class.
      $form['actions']['#attributes']['class'][] = 'gin-sticky-form-actions';

      // Add a class to identify modified forms.
      if (!isset($form['#attributes']['class'])) {
        $form['#attributes']['class'] = [];
      }
      elseif (is_string($form['#attributes']['class'])) {
        $form['#attributes']['class'] = [$form['#attributes']['class']];
      }
      $form['#attributes']['class'][] = 'gin--has-sticky-form-actions';

      // Sticky action container.
      $form['gin_sticky_actions'] = [
        '#type' => 'container',
        '#weight' => -1,
        '#multilingual' => TRUE,
        '#attributes' => [
          'class' => ['gin-sticky-form-actions'],
        ],
      ];

      // Create gin_more_actions group.
      $toggle_more_actions = $this->t('More actions');
      $form['gin_sticky_actions']['more_actions'] = [
        '#type' => 'container',
        '#multilingual' => TRUE,
        '#weight' => 998,
        '#attributes' => [
          'class' => ['gin-more-actions'],
        ],
        'more_actions_toggle' => [
          '#markup' => '<a href="#toggle-more-actions" class="gin-more-actions__trigger trigger" data-gin-tooltip role="button" title="' . $toggle_more_actions . '" aria-controls="gin_more_actions"><span class="visually-hidden">' . $toggle_more_actions . '</span></a>',
          '#weight' => 1,
        ],
        'more_actions_items' => [
          '#type' => 'container',
          '#multilingual' => TRUE,
        ],
      ];

      // Assign status to gin_actions.
      $form['gin_sticky_actions']['status'] = [
        '#type' => 'container',
        '#weight' => -1,
        '#multilingual' => TRUE,
      ];

      // Only alter the status field on content forms.
      if ($is_content_form) {
        // Set form id to status field.
        if (isset($form['status']['widget']) && isset($form['status']['widget']['value'])) {
          $form['status']['widget']['value']['#attributes']['form'] = $form['#id'];
          $widget_type = $form['status']['widget']['value']['#type'] ?? FALSE;
        }
        else {
          $widget_type = $form['status']['widget']['#type'] ?? FALSE;
        }
        // Only move status to status group if it is a checkbox.
        if ($widget_type === 'checkbox') {
          $form['status']['#group'] = 'status';
        }
      }

      // Helper item to move focus to sticky header.
      $form['gin_move_focus_to_sticky_bar'] = [
        '#markup' => '<a href="#" class="visually-hidden" role="button" gin-move-focus-to-sticky-bar>Moves focus to sticky header actions</a>',
        '#weight' => 999,
      ];

      // Attach library.
      $form['#attached']['library'][] = 'gin/more_actions';

      $form['#after_build'][] = [self::class, 'formAfterBuild'];
    }

    // Remaining changes only apply to content forms.
    if (!$is_content_form) {
      return;
    }

    // Provide a default meta form element if not already provided.
    // @see NodeForm::form()
    $form['advanced']['#attributes']['class'][] = 'entity-meta';
    if (!isset($form['meta'])) {
      $form['meta'] = [
        '#group' => 'advanced',
        '#weight' => -10,
        '#title' => $this->t('Status'),
        '#attributes' => ['class' => ['entity-meta__header']],
        '#tree' => TRUE,
      ];
    }

    // Ensure correct settings for advanced, meta and revision form elements.
    $form['advanced']['#type'] = 'container';
    $form['advanced']['#accordion'] = TRUE;
    $form['meta']['#type'] = 'container';
    $form['meta']['#access'] = TRUE;

    $form['revision_information']['#type'] = 'container';
    $form['revision_information']['#group'] = 'meta';
    $form['revision_information']['#attributes']['class'][] = 'entity-meta__revision';

    // Action buttons.
    if (isset($form['actions'])) {
      // Add sidebar toggle.
      $hide_panel = $this->t('Hide sidebar panel');
      $form['gin_sticky_actions']['gin_sidebar_toggle'] = [
        '#markup' => '<a href="#toggle-sidebar" class="meta-sidebar__trigger trigger" data-gin-tooltip role="button" title="' . $hide_panel . '" aria-controls="gin_sidebar"><span class="visually-hidden">' . $hide_panel . '</span></a>',
        '#weight' => 1000,
      ];
      $form['#attached']['library'][] = 'gin/sidebar';

      // Create gin_sidebar group.
      $form['gin_sidebar'] = [
        '#group' => 'meta',
        '#type' => 'container',
        '#weight' => 99,
        '#multilingual' => TRUE,
        '#attributes' => [
          'class' => [
            'gin-sidebar',
          ],
        ],
      ];
      // Copy footer over.
      $form['gin_sidebar']['footer'] = ($form['footer']) ?? [];

      // Sidebar close button.
      $close_sidebar_translation = $this->t('Close sidebar panel');
      $form['gin_sidebar']['gin_sidebar_close'] = [
        '#markup' => '<a href="#close-sidebar" class="meta-sidebar__close trigger" data-gin-tooltip role="button" title="' . $close_sidebar_translation . '"><span class="visually-hidden">' . $close_sidebar_translation . '</span></a>',
      ];

      $form['gin_sidebar_overlay'] = [
        '#markup' => '<div class="meta-sidebar__overlay trigger"></div>',
      ];
    }

    // Specify necessary node form theme and library.
    // @see claro_form_node_form_alter
    $form['#theme'] = ['node_edit_form'];
    // Attach libraries.
    $form['#attached']['library'][] = 'claro/node-form';
    $form['#attached']['library'][] = 'gin/edit_form';

    // Add a class that allows the logic in edit_form.js to identify the form.
    $form['#attributes']['class'][] = 'gin-node-edit-form';

    // If not logged in hide changed and author node info on add forms.
    $not_logged_in = $this->currentUser->isAnonymous();
    $route = $this->routeMatch->getRouteName();

    if ($not_logged_in && $route == 'node.add') {
      unset($form['meta']['changed']);
      unset($form['meta']['author']);
    }

  }

  /**
   * Helper function to remember the form actions after form has been built.
   */
  public static function formAfterBuild(array $form, FormStateInterface $form_state): array {
    // In some cases the form might be cached, including the `after_build`
    // callback, but maybe Gin is not the active theme anymore.
    // In that case `gin.theme` and the included files there won't be loaded, so
    // we better do an early return.
    if (!_gin_is_active()) {
      return $form;
    }

    // Allowlist for visible actions.
    $includes = ['save', 'submit', 'preview'];

    // Secondary action container options.
    $form['gin_sticky_actions']['more_actions']['more_actions_items']['#weight'] = 2;
    $form['gin_sticky_actions']['more_actions']['more_actions_items']['#attributes']['class'] = ['gin-more-actions__menu'];

    // Build actions.
    foreach (Element::children($form['actions']) as $key) {
      $button = ($form['actions'][$key]) ?? [];

      if (!($button['#access'] ?? TRUE)) {
        continue;
      }

      if (_gin_module_is_active('navigation')) {
        $form['gin_sticky_actions']['actions'][$key] = $button;
      }

      // The media_type_add_form form is a special case.
      // @see https://www.drupal.org/project/gin/issues/3534385
      // @see \Drupal\media\MediaTypeForm::actions
      if ($button['#type'] ?? '' === 'submit' || $form['#form_id'] === 'media_type_add_form') {
        // Update button.
        $button['#attributes']['id'] = 'gin-sticky-' . $button['#id'];
        $button['#attributes']['form'] = $form['#id'];
        $button['#attributes']['data-drupal-selector'] = 'gin-sticky-' . $button['#attributes']['data-drupal-selector'];
        $button['#attributes']['data-gin-sticky-form-selector'] = $button['#attributes']['data-drupal-selector'];

        // Add the button to the form actions array.
        if (_gin_module_is_active('navigation') || in_array($key, $includes, TRUE) || !empty($button['#gin_action_item'])) {
          $form['gin_sticky_actions']['actions'][$key] = $button;
        }
        // Add to more menu.
        else {
          $form['gin_sticky_actions']['more_actions']['more_actions_items'][$key] = $button;
        }
      }
      // Else add button to more menu.
      elseif (!in_array($key, $includes, TRUE)) {
        $form['gin_sticky_actions']['more_actions']['more_actions_items'][$key] = $button;
        $form['gin_sticky_actions']['more_actions']['more_actions_items'][$key]['#attributes']['form'] = $button['#id'];
      }
    }

    if (_gin_module_is_active('navigation')) {
      unset($form['gin_sticky_actions']['more_actions']);
    }

    _gin_form_actions($form['gin_sticky_actions'] ?? NULL);
    unset($form['gin_sticky_actions']);

    return $form;
  }

  /**
   * Sticky action buttons.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   */
  private function stickyActionButtons(?array $form = NULL, ?FormStateInterface $form_state = NULL, $form_id = NULL): bool {
    /** @var \Drupal\gin\GinSettings $settings */
    $settings = $this->classResolver->getInstanceFromDefinition(GinSettings::class);

    // Get route name.
    $route_name = $this->routeMatch->getRouteName();

    // Sets default to TRUE if setting is enabled.
    $sticky_action_buttons = $settings->get('sticky_action_buttons') ? TRUE : FALSE;

    // Always enable if navigation is active.
    if (_gin_module_is_active('navigation')) {
      $sticky_action_buttons = TRUE;
    }

    // API check.
    $form_ids = $this->moduleHandler->invokeAll('gin_ignore_sticky_form_actions');
    $this->moduleHandler->alter('gin_ignore_sticky_form_actions', $form_ids);
    $this->themeManager->alter('gin_ignore_sticky_form_actions', $form_ids);

    if (
      strpos($form_id, '_entity_add_form') !== FALSE ||
      strpos($form_id, '_entity_edit_form') !== FALSE ||
      strpos($form_id, '_exposed_form') !== FALSE ||
      strpos($form_id, '_preview_form') !== FALSE ||
      strpos($form_id, '_delete_form') !== FALSE ||
      strpos($form_id, '_confirm_form') !== FALSE ||
      strpos($form_id, 'views_ui_add_') !== FALSE ||
      strpos($form_id, 'views_ui_config_') !== FALSE ||
      strpos($form_id, 'views_ui_edit_') !== FALSE ||
      strpos($form_id, 'views_ui_rearrange_') !== FALSE ||
      strpos($form_id, 'layout_paragraphs_component_form') !== FALSE ||
      strpos($form_id, 'webform_submission_contact_edit_form') !== FALSE ||
      in_array($form_id, $form_ids, TRUE) ||
      in_array($route_name, $form_ids, TRUE)
    ) {
      $sticky_action_buttons = FALSE;
    }

    return $sticky_action_buttons;
  }

  /**
   * Check if we´re on a content edit form.
   *
   * _gin_is_content_form() is replaced by
   * \Drupal::classResolver(GinContentFormHelper::class)->isContentForm().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   */
  public function isContentForm(?array $form = NULL, ?FormStateInterface $form_state = NULL, $form_id = ''): bool {
    // Forms to exclude.
    // If media library widget, don't use new content edit form.
    // gin_preprocess_html is not triggered here, so checking
    // the form id is enough.
    $form_ids_to_ignore = [
      'media_library_add_form_',
      'views_form_media_library_widget_',
      'views_exposed_form',
      'date_recur_modular_sierra_occurrences_modal',
      'date_recur_modular_sierra_modal',
    ];

    foreach ($form_ids_to_ignore as $form_id_to_ignore) {
      if ($form_id && strpos($form_id, $form_id_to_ignore) !== FALSE) {
        return FALSE;
      }
    }

    $is_content_form = FALSE;

    // Get route name.
    $route_name = $this->routeMatch->getRouteName();

    // Routes to include.
    $route_names = [
      'node.add',
      'block_content.add_page',
      'block_content.add_form',
      'entity.block_content.canonical',
      'entity.media.add_form',
      'entity.media.canonical',
      'entity.media.edit_form',
      'entity.node.content_translation_add',
      'entity.node.content_translation_edit',
      'quick_node_clone.node.quick_clone',
      'entity.node.edit_form',
    ];

    // API check.
    $additional_routes = $this->moduleHandler->invokeAll('gin_content_form_routes');
    $route_names = array_merge($additional_routes, $route_names);
    $this->moduleHandler->alter('gin_content_form_routes', $route_names);
    $this->themeManager->alter('gin_content_form_routes', $route_names);

    if (
      in_array($route_name, $route_names, TRUE) ||
      ($form_state && ($form_state->getBuildInfo()['base_form_id'] ?? NULL) === 'node_form') ||
      ($route_name === 'entity.group_content.create_form' && substr($this->routeMatch->getParameter('plugin_id'), 0, 11) === "group_node:") ||
      ($route_name === 'entity.group_relationship.create_form' && substr($this->routeMatch->getParameter('plugin_id'), 0, 11) === "group_node:")
    ) {
      $is_content_form = TRUE;
    }

    return $is_content_form;
  }

  /**
   * Check the context we're in.
   *
   * Checks if the form is in either
   * a modal or an off-canvas dialog.
   */
  private function isModalOrOffcanvas() {
    $wrapper_format = $this->getRequestWrapperFormat() ?? '';
    return str_contains($wrapper_format, 'drupal_modal') ||
      str_contains($wrapper_format, 'drupal_dialog');
  }

}
