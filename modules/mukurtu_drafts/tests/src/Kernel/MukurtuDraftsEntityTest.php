<?php

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;

/**
 * Draft access is now managed via content_moderation workflow states.
 * These tests covered the removed boolean draft field and need to be rewritten
 * against the content_moderation moderation_state approach.
 */
#[Group('mukurtu_drafts')]
class MukurtuDraftsEntityTest extends KernelTestBase {

  protected static $modules = ['system', 'user'];

  public function testPlaceholder(): void {
    $this->assertTrue(TRUE, 'Placeholder test -- draft access tests need to be rewritten for content_moderation approach.');
  }

}
