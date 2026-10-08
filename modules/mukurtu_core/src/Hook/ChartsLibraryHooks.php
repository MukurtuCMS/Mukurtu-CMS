<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Serves the Chart.js files the profile ships instead of a CDN.
 *
 * The charts_chartjs libraries reference their files by root-relative
 * "/libraries/..." paths, which only resolve when the files sit in the web
 * root's libraries/ directory. The profile ships them in its own libraries/
 * directory instead (like Tagify, see #1897), because a profile installed as
 * a composer dependency can't set the root project's installer paths. This
 * rewrites each of those paths to wherever core's libraries file finder
 * actually locates the file, so the profile's copy is used.
 *
 * This only has an effect while charts.settings:advanced.requirements.cdn is
 * off. When it's on, the charts module rewrites the same paths to unpkg.com;
 * mukurtu_install() and mukurtu_core_update_40212() turn it off.
 *
 * @see \Drupal\charts\Hook\ChartsHooks::libraryInfoAlter()
 */
class ChartsLibraryHooks {

  /**
   * Constructs the hook implementations.
   */
  public function __construct(
    private readonly LibrariesDirectoryFileFinder $librariesDirectoryFileFinder,
  ) {}

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== 'charts_chartjs') {
      return;
    }

    foreach ($libraries as &$library) {
      if (empty($library['js']) || !is_array($library['js'])) {
        continue;
      }
      $js = [];
      foreach ($library['js'] as $file => $options) {
        if (is_string($file) && str_starts_with($file, '/libraries/')) {
          $path = $this->librariesDirectoryFileFinder->find(substr($file, strlen('/libraries/')));
          if ($path) {
            $file = '/' . $path;
          }
        }
        $js[$file] = $options;
      }
      $library['js'] = $js;
    }
  }

}
