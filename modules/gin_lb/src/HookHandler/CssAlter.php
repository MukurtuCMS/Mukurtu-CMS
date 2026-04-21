<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class CssAlter implements ContainerInjectionInterface {

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The theme extension list.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected ThemeExtensionList $themeExtensionList;

  /**
   * The stable themes list.
   *
   * @var array|string[]
   */
  protected array $stableThemes = [
    'stable',
    'stable9',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    ThemeHandlerInterface $themeHandler,
    ThemeExtensionList $themeExtensionList,
  ) {
    $this->themeHandler = $themeHandler;
    $this->themeExtensionList = $themeExtensionList;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('theme_handler'),
      $container->get('extension.list.theme')
    );
  }

  /**
   * Hook implementation.
   *
   * @param array $css
   *   An array of all CSS items (files and inline CSS) being requested on the
   *   page.
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *   The assets attached to the current response.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language of the request that the assets will be rendered for.
   */
  public function alter(array &$css, AttachedAssetsInterface $assets, LanguageInterface $language): void {
    // Only target layout builder specific pages by checking if the layout
    // builder library is attached.
    if (!\in_array('layout_builder/drupal.layout_builder', $assets->getLibraries(), TRUE)) {
      return;
    }

    foreach ($this->stableThemes as $stable_theme) {
      if ($this->themeHandler->themeExists($stable_theme)) {
        $stable_theme_css = $this->themeExtensionList->getPath($stable_theme) . '/css';
        // Remove very specific CSS files that this module is overriding.
        unset($css[$stable_theme_css . '/layout_builder/layout-builder.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.theme.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.details.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.reset.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.base.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.table.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.tabledrag.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.form.css']);
        unset($css[$stable_theme_css . '/core/dialog/off-canvas.button.css']);
        // unset($css[$stable_theme_css . '/core/vertical-tabs.css']);.
      }
    }
    unset($css['core/modules/layout_builder/css/layout-builder.css']);
    unset($css['core/modules/system/css/components/tabledrag.module.css']);
    unset($css['core/assets/vendor/jquery.ui/themes/base/dialog.css']);
    unset($css['core/misc/dialog/off-canvas.theme.css']);
    unset($css['core/misc/dialog/off-canvas.base.css']);
    unset($css['core/misc/dialog/off-canvas.reset.css']);
    unset($css['core/misc/dialog/off-canvas.table.css']);
    unset($css['core/misc/dialog/off-canvas.tabledrag.css']);
    unset($css['core/misc/dialog/off-canvas.details.css']);
    unset($css['core/misc/dialog/off-canvas.form.css']);
    unset($css['core/misc/dialog/off-canvas.button.css']);
    unset($css['core/assets/vendor/jquery.ui/themes/base/theme.css']);

    // Drupal 10.
    unset($css['core/misc/dialog/off-canvas/css/reset.css']);
    unset($css['core/misc/dialog/off-canvas/css/wrapper.css']);
    unset($css['core/misc/dialog/off-canvas/css/titlebar.css']);
    unset($css['core/misc/dialog/off-canvas/css/dropbutton.css']);
    unset($css['core/misc/dialog/off-canvas/css/messages.css']);
    unset($css['core/misc/dialog/off-canvas/css/details.css']);
    unset($css['core/misc/dialog/off-canvas/css/form.css']);
    unset($css['core/misc/dialog/off-canvas/css/button.css']);
    unset($css['core/misc/dialog/off-canvas/css/base.css']);
    unset($css['core/misc/dialog/off-canvas/css/table.css']);

    $whitelist = [
      'core/themes/claro/css/components/fieldset.css',
      'core/themes/claro/css/components/entity-meta.css',
      'core/themes/claro/css/components/jquery.ui/theme.css',
    ];
    // Gin 4.
    foreach ($css as $name => $config) {
      if (str_contains($name, 'core/themes/claro/css/components') &&
      !in_array($name, $whitelist)
      ) {
        unset($css[$name]);
      }
    }
    unset($css['core/themes/claro/css/base/elements.css']);
    unset($css['core/themes/claro/css/base/typography.css']);
  }

}
