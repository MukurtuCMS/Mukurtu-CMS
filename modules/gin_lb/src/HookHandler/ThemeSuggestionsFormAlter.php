<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class ThemeSuggestionsFormAlter implements ContainerInjectionInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The current route match.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\gin_lb\Service\ContextValidatorInterface $contextValidator
   *   The context validator.
   */
  public function __construct(
    RouteMatchInterface $currentRouteMatch,
    ModuleHandlerInterface $moduleHandler,
    ContextValidatorInterface $contextValidator,
  ) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->moduleHandler = $moduleHandler;
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('current_route_match'),
      $container->get('module_handler'),
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
   */
  public function alter(array &$suggestions, array $variables): void {
    if (!\str_contains($variables['element']['#form_id'], 'layout_builder_form')) {
      return;
    }

    $route_name = $this->currentRouteMatch->getRouteName();
    if (!$route_name) {
      return;
    }

    $gin_lb_show_toolbar = TRUE;
    $this->moduleHandler->alter('gin_lb_show_toolbar', $gin_lb_show_toolbar);
    // Disable toolbar for Layout Library.
    // Layout Builder is part of the layout library form.
    if ($gin_lb_show_toolbar === TRUE && !\str_starts_with($route_name, 'layout_builder.layout_library.')) {
      $suggestions[] = 'form__layout_builder_form__gin_lb';
    }
  }

}
