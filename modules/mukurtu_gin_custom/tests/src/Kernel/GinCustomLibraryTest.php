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

}
