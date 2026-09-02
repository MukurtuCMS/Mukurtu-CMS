<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40109(), which adds an "All content" link to the
 * end of the dashboard's "Recent content" block.
 *
 * @see mukurtu_core_update_40109()
 */
#[Group('mukurtu_core')]
class RecentContentMoreLinkUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The hook writes a partial views config fixture, not full schema data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * Sets up a views.view.content_recent config fixture.
   */
  protected function saveContentRecentFixture(): void {
    \Drupal::configFactory()->getEditable('views.view.content_recent')
      ->setData([
        'id' => 'content_recent',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'title' => 'Recent content',
            ],
          ],
          'block_1' => [
            'id' => 'block_1',
            'display_options' => [
              'display_extenders' => [],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The update hook enables the more link on the block display.
   */
  public function testUpdateAddsMoreLinkToBlockDisplay(): void {
    $this->saveContentRecentFixture();

    mukurtu_core_update_40109();

    $config = \Drupal::config('views.view.content_recent');
    $this->assertTrue($config->get('display.block_1.display_options.use_more'));
    $this->assertTrue($config->get('display.block_1.display_options.use_more_always'));
    $this->assertEquals('All content', $config->get('display.block_1.display_options.use_more_text'));
    $this->assertEquals('custom_url', $config->get('display.block_1.display_options.link_display'));
    $this->assertEquals('/admin/content', $config->get('display.block_1.display_options.link_url'));
  }

  /**
   * The update hook doesn't touch the default display.
   */
  public function testUpdateLeavesDefaultDisplayUntouched(): void {
    $this->saveContentRecentFixture();

    mukurtu_core_update_40109();

    $config = \Drupal::config('views.view.content_recent');
    $this->assertNull($config->get('display.default.display_options.use_more'));
    $this->assertEquals('Recent content', $config->get('display.default.display_options.title'));
  }

  /**
   * The update hook is a no-op when the view doesn't exist on the site.
   */
  public function testUpdateIsNoOpWithoutView(): void {
    $this->assertTrue(\Drupal::config('views.view.content_recent')->isNew());
    mukurtu_core_update_40109();
    $this->assertTrue(\Drupal::config('views.view.content_recent')->isNew());
  }

  /**
   * The update hook is a no-op when the block display has been removed.
   */
  public function testUpdateIsNoOpWithoutBlockDisplay(): void {
    \Drupal::configFactory()->getEditable('views.view.content_recent')
      ->setData([
        'id' => 'content_recent',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'title' => 'Recent content',
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40109();

    $config = \Drupal::config('views.view.content_recent');
    $this->assertNull($config->get('display.block_1'));
  }

}
