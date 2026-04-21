<?php

declare(strict_types=1);

namespace Drupal\search_api\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\MissingThemeDependencyException;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Provides simple theme switching for use during indexing.
 */
class ThemeSwitcher implements ThemeSwitcherInterface {

  public function __construct(
    protected ThemeManagerInterface $themeManager,
    protected ThemeInitializationInterface $themeInitializer,
    protected ConfigFactoryInterface $configFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public function switchToDefault(): ?ActiveTheme {
    // Switch to the default theme in case the admin theme (or any other theme)
    // is enabled.
    $activeTheme = $this->themeManager->getActiveTheme();
    $defaultTheme = $this->configFactory
      ->get('system.theme')
      ->get('default');
    try {
      $defaultTheme = $this->themeInitializer
        ->getActiveThemeByName($defaultTheme);
    }
    catch (MissingThemeDependencyException) {
      // It is highly unlikely that the default theme cannot be initialized, but
      // in this case the site will have far larger problems than incorrect
      // indexing. Just act like all is fine.
      return NULL;
    }
    if ($defaultTheme->getName() === $activeTheme->getName()) {
      return NULL;
    }

    $this->themeManager->setActiveTheme($defaultTheme);
    // Ensure that statically cached default variables are reset correctly,
    // especially the directory variable.
    drupal_static_reset('template_preprocess');
    // Return the previously active theme, for switching back.
    return $activeTheme;
  }

  /**
   * {@inheritdoc}
   */
  public function switchBack(?ActiveTheme $previousTheme): void {
    if ($previousTheme === NULL
        || $previousTheme === $this->themeManager->getActiveTheme()) {
      return;
    }
    $this->themeManager->setActiveTheme($previousTheme);
    drupal_static_reset('template_preprocess');
  }

}
