<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mukurtu_gin_custom/mukurtu-gin-custom library definition.
 *
 * hook_page_attachments() attaches this library unconditionally on every
 * page, including for anonymous users. A hard dependency on toolbar/toolbar
 * previously bypassed core's "access toolbar" permission gate and loaded
 * toolbar.anti-flicker.js for every visitor, which could read a stale
 * Drupal.toolbar.toolbarState left in sessionStorage from a prior logged-in
 * session and reserve toolbar-height blank space at the top of the page
 * after logout.
 */
#[Group('mukurtu_gin_custom')]
class GinCustomLibraryTest extends KernelTestBase {

  protected static $modules = [
    'mukurtu_gin_custom',
  ];

  /**
   * Tests the library does not pull in toolbar/toolbar for every visitor.
   */
  public function testLibraryDoesNotDependOnToolbar(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('mukurtu_gin_custom', 'mukurtu-gin-custom');

    $this->assertNotFalse($library);
    $this->assertArrayNotHasKey('toolbar/toolbar', array_flip($library['dependencies'] ?? []));
  }

  /**
   * Tests the library includes the secondary toolbar Escape handler (#2397).
   */
  public function testLibraryIncludesTrayEscape(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('mukurtu_gin_custom', 'mukurtu-gin-custom');

    $files = array_column($library['js'], 'data');
    $this->assertNotEmpty(array_filter($files, fn($file) => str_ends_with($file, 'js/gin-secondary-tray-escape.js')));
  }

  /**
   * Tests the library includes the media library dialog fixes (#2401).
   */
  public function testLibraryIncludesMediaLibraryDialog(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('mukurtu_gin_custom', 'mukurtu-gin-custom');

    $files = array_column($library['js'], 'data');
    $this->assertNotEmpty(array_filter($files, fn($file) => str_ends_with($file, 'js/media-library-dialog.js')));
  }

  /**
   * Tests select-all-name.js loads with core's tableselect library (#2397).
   */
  public function testTableselectLoadsSelectAllName(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('core', 'drupal.tableselect');

    $files = array_column($library['js'], 'data');
    $this->assertNotEmpty(array_filter($files, fn($file) => str_ends_with($file, 'mukurtu_gin_custom/js/select-all-name.js')));
  }

}
