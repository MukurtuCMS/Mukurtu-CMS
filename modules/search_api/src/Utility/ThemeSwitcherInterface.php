<?php

declare(strict_types=1);

namespace Drupal\search_api\Utility;

use Drupal\Core\Theme\ActiveTheme;

/**
 * Provides an interface for the theme switcher service.
 */
interface ThemeSwitcherInterface {

  /**
   * Switches to the default theme, in case another theme is active.
   *
   * @return \Drupal\Core\Theme\ActiveTheme|null
   *   The previously active theme, or NULL in case the default theme was
   *   already active.
   */
  public function switchToDefault(): ?ActiveTheme;

  /**
   * Switches back to the specified theme.
   *
   * @param \Drupal\Core\Theme\ActiveTheme|null $previousTheme
   *   The theme to switch to, as returned by switchToDefault(). For the sake of
   *   simplicity, NULL can be passed which will do nothing.
   */
  public function switchBack(?ActiveTheme $previousTheme): void;

}
