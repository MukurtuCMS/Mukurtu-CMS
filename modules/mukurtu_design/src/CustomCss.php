<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves the site's custom CSS from Design Settings.
 *
 * The CSS is written to its own file and linked like any other stylesheet,
 * never printed inline, so nothing in it can end up inside the page markup.
 * The file name carries a hash of the CSS so browsers fetch the new file as
 * soon as it changes instead of serving a cached copy.
 */
final class CustomCss implements ContainerInjectionInterface {

  /**
   * The config key holding the CSS.
   */
  public const KEY = 'custom_css';

  /**
   * The mukurtu_v4 library that carries the stylesheet.
   */
  public const LIBRARY = 'custom_css';

  /**
   * Where the stylesheet is written.
   *
   * Shared with the custom palette stylesheet and the background images, so
   * the file prefix below must not match anything else written there.
   */
  public const DIRECTORY = 'public://mukurtu-design';

  /**
   * The file name prefix, followed by a hash and ".css".
   */
  public const FILE_PREFIX = 'custom-css-';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ThemeManagerInterface $themeManager,
    protected FileSystemInterface $fileSystem,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('theme.manager'),
      $container->get('file_system'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * The stored CSS, or an empty string when there is none.
   */
  public function css(): string {
    return (string) $this->configFactory->get(DesignPalette::SETTINGS)->get(self::KEY);
  }

  /**
   * The URI of the stylesheet for the given CSS.
   */
  public static function uri(string $css): string {
    return self::DIRECTORY . '/' . self::FILE_PREFIX . substr(hash('sha256', $css), 0, 12) . '.css';
  }

  /**
   * Writes the stylesheet for the given CSS and removes any older ones.
   *
   * Empty CSS removes every stylesheet and writes none.
   */
  public function write(string $css): void {
    $current = trim($css) === '' ? NULL : self::uri($css);

    $directory = self::DIRECTORY;
    if ($current !== NULL) {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->saveData($css, $current, FileExists::Replace);
    }

    if (!is_dir($directory)) {
      return;
    }
    $pattern = '/^' . preg_quote(self::FILE_PREFIX, '/') . '[0-9a-f]+\.css$/';
    foreach ($this->fileSystem->scanDirectory($directory, $pattern, ['recurse' => FALSE]) as $file) {
      if ($file->uri !== $current) {
        $this->fileSystem->delete($file->uri);
      }
    }
  }

  /**
   * Adds the custom CSS library to the mukurtu_v4 theme.
   *
   * Always defined, empty when there is no CSS, so attaching it can never
   * name a library that does not exist. Weighted after the palette
   * stylesheets so the site's own rules win.
   *
   * @see \Drupal\mukurtu_design\DesignPalette::alterThemeLibraries()
   */
  public function alterThemeLibraries(array &$libraries, string $extension): void {
    if ($extension !== 'mukurtu_v4') {
      return;
    }

    $css = $this->css();
    $stylesheets = [];
    if (trim($css) !== '') {
      $uri = self::uri($css);
      // Files under public:// can go missing, for example on a new web
      // server or after the files directory is restored from a backup.
      if (!file_exists($uri)) {
        $this->write($css);
      }
      // Absolute, as for the custom palette: a root-relative path is read as
      // relative to the Drupal root and breaks on sites in a subdirectory.
      $stylesheets[$this->fileUrlGenerator->generateAbsoluteString($uri)] = [
        'weight' => 1000001,
        'preprocess' => FALSE,
      ];
    }

    $libraries[self::LIBRARY] = ['css' => ['theme' => $stylesheets]];
  }

  /**
   * Attaches the custom CSS to front-end pages.
   *
   * Only when the site's default theme is rendering, so the admin theme is
   * never affected and broken CSS cannot hide the form that fixes it.
   *
   * @see \Drupal\mukurtu_design\DesignPalette::enablePalette()
   */
  public function attach(array &$attachments): void {
    $config = $this->configFactory->get(DesignPalette::SETTINGS);

    // applyTo() replaces #cache, so this keeps what earlier hooks added.
    CacheableMetadata::createFromRenderArray($attachments)
      ->addCacheableDependency($config)
      ->applyTo($attachments);

    $default_theme = $this->configFactory->get('system.theme')->get('default');
    if ($this->themeManager->getActiveTheme()->getName() !== $default_theme) {
      return;
    }
    if (trim((string) $config->get(self::KEY)) === '') {
      return;
    }

    $attachments['#attached']['library'][] = 'mukurtu_v4/' . self::LIBRARY;
  }

}
