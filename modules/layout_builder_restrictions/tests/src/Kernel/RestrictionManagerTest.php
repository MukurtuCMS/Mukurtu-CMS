<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Layout builder restrictions manager kernel tests.
 *
 * @group layout_builder_restrictions
 */
class RestrictionManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder_restrictions',
  ];

  /**
   * Test that we can invoke getSortedPlugins without creating a notice.
   */
  public function testNoPhpNotice() {
    /** @var \Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager $manager */
    $manager = \Drupal::service('plugin.manager.layout_builder_restriction');
    $plugins = $manager->getSortedPlugins();
    // This should be at least one item long, since we ship a plugin in the
    // module itself.
    $this->assertNotCount(0, $plugins);
  }

}
