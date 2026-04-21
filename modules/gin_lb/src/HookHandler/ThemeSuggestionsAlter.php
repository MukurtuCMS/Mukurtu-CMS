<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementation.
 */
class ThemeSuggestionsAlter implements ContainerInjectionInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * The routes that will receive template suggestions.
   *
   * @var array|string[]
   */
  protected array $routesWithSuggestions = [
    'editor.image_dialog',
    'editor.link_dialog',
    'editor.media_dialog',
    'layout_builder.add_block',
    'layout_builder.choose_block',
    'layout_builder.choose_inline_block',
    'layout_builder.choose_section',
    'layout_builder.remove_block',
    'layout_builder.remove_section',
    'media_library.ui',
    'section_library.add_section_to_library',
    'section_library.add_template_to_library',
    'view.media_library.widget',
    'view.media_library.widget_table',
  ];

  /**
   * The hooks that will receive template suggestions.
   *
   * @var array|string[]
   */
  protected array $hooksWithSuggestions = [
    'media',
    'media_library_wrapper',
    'pager',
    'status_messages',
    'views_mini_pager',
    'views_view',
    'views_view_unformatted',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The current route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\gin_lb\Service\ContextValidatorInterface $contextValidator
   *   The context validator.
   */
  public function __construct(
    RouteMatchInterface $currentRouteMatch,
    RequestStack $requestStack,
    ContextValidatorInterface $contextValidator,
  ) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->requestStack = $requestStack;
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('gin_lb.context_validator')
    );
  }

  /**
   * Hook implementation.
   *
   * @param array $suggestions
   *   The list of suggestions.
   * @param array $variables
   *   The theme variables.
   * @param string $hook
   *   The base hook name.
   */
  public function alter(array &$suggestions, array $variables, string $hook): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    if ($hook == 'toolbar' && $this->contextValidator->isLayoutBuilderRoute()) {
      $suggestions[] = $hook . '__gin_lb';
    }

    if (isset($variables['element']['#gin_lb_form']) || $this->hasSuggestions($variables, $hook)) {
      // Fix form element suggestions when they are not implemented in the
      // theme.
      if (empty($suggestions) && !empty($variables['theme_hook_original'])) {
        $suggestions = [$variables['theme_hook_original']];
      }

      if ($hook === 'input' && isset($variables['element']['#type']) && $variables['element']['#type'] === 'checkbox') {
        $suggestions[] = 'input__checkbox__toggle';
      }

      $additional_suggestions = [];
      foreach ($suggestions as $suggestion) {
        $additional_suggestions[] = $suggestion . '__gin_lb';
      }
      $suggestions[] = $hook . '__gin_lb';
      $suggestions = \array_merge($suggestions, $additional_suggestions);

      if ($hook === 'views_view_unformatted' && isset($variables['view'])) {
        $suggestions[] = $hook . '__' . $variables['view']->id() . '__gin_lb';
      }
    }
  }

  /**
   * Check if suggestions should be added.
   *
   * @param array $variables
   *   The theme variables.
   * @param string $hook
   *   The base hook name.
   *
   * @return bool
   *   True if suggestions should be added.
   */
  protected function hasSuggestions(array $variables, string $hook): bool {
    $route_name = $this->currentRouteMatch->getRouteName();
    if (\in_array($route_name, $this->routesWithSuggestions, TRUE)) {
      return TRUE;
    }

    // For ajax the route is views.ajax
    // So a look to the suggestions help.
    if ($route_name === 'views.ajax') {
      $current_request = $this->requestStack->getCurrentRequest();
      if ($current_request && $current_request->query->get('media_library_opener_id')) {
        return TRUE;
      }

      if (isset($variables['view'])
        && $variables['view'] instanceof ViewExecutable
        && $variables['view']->id() === 'media_library'
      ) {
        return TRUE;
      }

      if ($hook === 'media' && $variables['elements']['#view_mode'] === 'media_library') {
        return TRUE;
      }
    }

    if ($this->contextValidator->isLayoutBuilderRoute() && \in_array($hook, $this->hooksWithSuggestions, TRUE)) {
      return TRUE;
    }

    return FALSE;
  }

}
