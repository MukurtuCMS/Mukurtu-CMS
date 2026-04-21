<?php

namespace Drupal\config_pages\Twig;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension with some useful functions and filters.
 *
 * The extension consumes quite a lot of dependencies. Most of them are not used
 * on each page request. For performance reasons services are wrapped in static
 * callbacks.
 */
class ConfigPagesExtension extends AbstractExtension {

  /**
   * The module handler to invoke alter hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager to invoke alter hooks.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs the ConfigPagesExtension object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager) {
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $functions = [
      new TwigFunction('config_pages_field', [self::class, 'configPagesField']),
    ];

    $this->moduleHandler->alter('config_pages_functions', $functions);
    $this->themeManager->alter('config_pages_functions', $functions);

    return $functions;
  }

  /**
   * Returns the render array for a single ConfigPage field.
   */
  public static function configPagesField(string $config_page_type, string $field_name, $view_mode = 'full'): array {
    $config_pages_loader = \Drupal::service('config_pages.loader');
    return $config_pages_loader->getFieldView($config_page_type, $field_name, $view_mode);
  }

}
