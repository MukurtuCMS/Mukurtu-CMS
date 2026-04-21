<?php

declare(strict_types=1);

namespace Drupal\gin_lb\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Validate Layout Builder and Gin context.
 */
class ContextValidator implements ContextValidatorInterface {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * If the current theme should be changed by Gin Layout Builder.
   *
   * @var bool
   */
  protected bool $isValidTheme;

  /**
   * If the current route is Layout Builder related.
   *
   * @var bool
   */
  protected bool $isLayoutBuilderRoute;

  /**
   * The list of form IDs to style.
   *
   * @var array|string[]
   */
  protected array $formIds = [
    'editor_image_dialog',
    'form-autocomplete',
    'layout_builder_add_block',
    'layout_builder_block_move',
    'layout_builder_configure_section',
    'layout_builder_remove_block',
    'layout_builder_update_block',
    'section_library_add_section_to_library',
    'section_library_add_template_to_library',
  ];

  /**
   * If the form ID matches one regex it will be styled.
   *
   * @var array|string[]
   */
  protected array $formIdRegexes = [
    '/^media_library_add_form_.*/',
    '/^media_.*_edit_form$/',
    '/layout_builder_translate_form.*/',
    '/layout_builder_block_translation.*/',
    '/^views_form_media_library_widget_.*/',
    '/ai_ckeditor_dialog_form.*/',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    ThemeManagerInterface $themeManager,
    RouteMatchInterface $routeMatch,
    ModuleHandlerInterface $moduleHandler,
  ) {
    $this->themeManager = $themeManager;
    $this->routeMatch = $routeMatch;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidTheme(): bool {
    if (isset($this->isValidTheme)) {
      return $this->isValidTheme;
    }

    $active_theme = $this->themeManager->getActiveTheme();
    if ($active_theme->getName() === 'gin' || \array_key_exists('gin', $active_theme->getBaseThemeExtensions())) {
      $this->isValidTheme = FALSE;
    }
    else {
      $this->isValidTheme = TRUE;
    }

    return $this->isValidTheme;
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutBuilderFormId(string $form_id, array $form): bool {
    if (!$this->isValidTheme()) {
      return FALSE;
    }

    if (\in_array($form_id, $this->formIds, TRUE)) {
      return TRUE;
    }

    foreach ($this->formIdRegexes as $formIdRegex) {
      if (\preg_match($formIdRegex, $form_id)) {
        return TRUE;
      }
    }

    if ($form_id === 'views_exposed_form' && isset($form['#id']) && $form['#id'] === 'views-exposed-form-media-library-widget') {
      return TRUE;
    }

    if (\str_contains($form_id, 'layout_builder_form')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutBuilderRoute(): bool {
    if (isset($this->isLayoutBuilderRoute)) {
      return $this->isLayoutBuilderRoute;
    }

    $routeName = $this->routeMatch->getRouteName();
    $isLayoutBuilderRoute = FALSE;
    if ($routeName && \preg_match('/^(layout_builder\.([^.]+\.)?)/', $routeName)) {
      $isLayoutBuilderRoute = TRUE;
    }
    $this->moduleHandler->alter('gin_lb_is_layout_builder_route', $isLayoutBuilderRoute);
    $this->isLayoutBuilderRoute = $isLayoutBuilderRoute;

    return $this->isLayoutBuilderRoute;
  }

}
